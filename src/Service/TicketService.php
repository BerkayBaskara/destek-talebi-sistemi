<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Core\Text;
use App\Exception\AuthorizationException;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Model\Enum\Priority;
use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\Reply;
use App\Model\State\InvalidTransitionException;
use App\Model\Ticket;
use App\Model\TicketLog;
use App\Model\User;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\ReplyRepositoryInterface;
use App\Repository\SlaPolicyRepositoryInterface;
use App\Repository\TicketRepositoryInterface;
use App\Service\Observer\TicketObserver;

/**
 * Talep iş kuralları: açma (FR-01), akıllı öncelik (FR-03), otomatik atama (FR-04), SLA bitişi (FR-05),
 * durum geçişleri (FR-06, State deseni), yanıtlaşma ve görünürlük/yetki kuralları.
 * Olayları Observer deseniyle yayınlar. Controller'lar iş kuralı yazmaz, buraya çağırır.
 */
final class TicketService
{
    public const EVENT_CREATED = 'ticket_created';
    public const EVENT_ASSIGNED = 'ticket_assigned';
    public const EVENT_STATUS_CHANGED = 'status_changed';
    public const EVENT_CUSTOMER_REPLIED = 'customer_replied';
    public const EVENT_AGENT_REPLIED = 'agent_replied';

    /** @var list<TicketObserver> */
    private array $observers = [];

    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly ReplyRepositoryInterface $replies,
        private readonly CategoryRepositoryInterface $categories,
        private readonly SlaPolicyRepositoryInterface $policies,
        private readonly SlaCalculatorService $sla,
        private readonly PriorityDetector $priorityDetector,
        private readonly AssignmentService $assignment,
        private readonly ClockInterface $clock,
    ) {
    }

    public function attachObserver(TicketObserver $observer): void
    {
        $this->observers[] = $observer;
    }

    /** @param array<string, mixed> $context */
    public function notifyObservers(Ticket $ticket, string $eventType, array $context = []): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($ticket, $eventType, $context);
        }
    }

    /**
     * Yeni talep: doğrula → etkin öncelik → SLA bitişi → kaydet (Bekleniyor) → açıklamayı ilk mesaj yap
     * → en az iş yüklü personele ata (varsa).
     *
     * @param array<string, mixed> $data category_id, priority, title, description
     */
    public function createTicket(User $creator, array $data): Ticket
    {
        if ($creator->role !== Role::Customer) {
            throw new AuthorizationException('Talep yalnız müşteri hesabıyla açılabilir.');
        }

        $title = trim(is_string($data['title'] ?? null) ? $data['title'] : '');
        $description = trim(is_string($data['description'] ?? null) ? $data['description'] : '');
        $categoryId = is_numeric($data['category_id'] ?? null) ? (int) $data['category_id'] : 0;
        $chosen = is_numeric($data['priority'] ?? null) ? Priority::tryFrom((int) $data['priority']) : null;

        $errors = [];
        if ($this->categories->findById($categoryId) === null) {
            $errors['category_id'] = 'Geçerli bir kategori seçin.';
        }
        if ($chosen === null) {
            $errors['priority'] = 'Geçerli bir öncelik seçin.';
        }
        if (Text::length($title) < 3 || Text::length($title) > 150) {
            $errors['title'] = 'Konu başlığı 3-150 karakter olmalı.';
        }
        if (Text::length($description) < 10 || Text::length($description) > 5000) {
            $errors['description'] = 'Açıklama 10-5000 karakter olmalı.';
        }
        if ($errors !== [] || $chosen === null) {
            throw new ValidationException($errors);
        }

        $priority = $this->priorityDetector->effective($chosen, $title, $description);
        $hours = $this->policies->findFor($categoryId, $priority)->durationHours ?? $priority->defaultSlaHours();
        $now = $this->clock->now();

        $ticket = $this->tickets->save(new Ticket(
            null,
            (int) $creator->id,
            null,
            $categoryId,
            $title,
            TicketStatus::Pending,
            $priority,
            $chosen,
            0,
            $now,
            $this->sla->calculateWithJson($now, $hours * 60),
            null,
        ));
        $this->replies->save(new Reply(null, (int) $ticket->id, (int) $creator->id, $description, $now));
        $this->notifyObservers($ticket, self::EVENT_CREATED, ['actor_id' => (int) $creator->id]);

        $agent = $this->assignment->pickAgent();
        if ($agent !== null && $this->tickets->assignIfUnassigned((int) $ticket->id, (int) $agent->id)) {
            $ticket = $this->mustFind((int) $ticket->id);
            $this->notifyObservers($ticket, self::EVENT_ASSIGNED, ['agent_id' => (int) $agent->id]);
            $this->notifyObservers($ticket, self::EVENT_STATUS_CHANGED, [
                'old' => TicketStatus::Pending,
                'new' => TicketStatus::Taken,
                'actor_id' => TicketLog::SYSTEM_USER,
            ]);
        }

        return $ticket;
    }

    /** Personel havuzdaki talebi üstlenir; aynı anda iki kişi alamaz (NFR-04). */
    public function take(int $ticketId, User $agent): Ticket
    {
        if (!in_array($agent->role, [Role::Agent, Role::Admin], true)) {
            throw new AuthorizationException('Talebi yalnız personel üstlenebilir.');
        }
        $ticket = $this->mustFind($ticketId);
        if ($ticket->agentId === null) {
            $ticket->state()->take($ticket); // iptal edilmiş vb. ise nedenini söyler
        }

        // Kontrol ile atama arasında başka biri alabilir; asıl karar kilit içindeki koşullu atamada.
        if ($ticket->agentId !== null || !$this->tickets->assignIfUnassigned($ticketId, (int) $agent->id)) {
            throw new ConflictException('Bu talep az önce başka bir personel tarafından alındı.');
        }

        $ticket = $this->mustFind($ticketId);
        $this->notifyObservers($ticket, self::EVENT_ASSIGNED, ['agent_id' => (int) $agent->id]);
        $this->notifyObservers($ticket, self::EVENT_STATUS_CHANGED, [
            'old' => TicketStatus::Pending,
            'new' => TicketStatus::Taken,
            'actor_id' => (int) $agent->id,
        ]);

        return $ticket;
    }

    /**
     * Durum eylemi. cancel/approve/reject: talebin sahibi müşteri; start_review/resolve: atanan personel
     * veya yönetici. İzin verilmeyen geçişte InvalidTransitionException (State deseni) atılır.
     */
    public function perform(int $ticketId, User $actor, string $action): Ticket
    {
        $ticket = $this->mustFind($ticketId);
        $isOwner = $ticket->creatorId === $actor->id;
        $isHandler = $actor->role === Role::Admin || ($actor->role === Role::Agent && $ticket->agentId === $actor->id);
        $state = $ticket->state();
        $old = $ticket->status;

        switch ($action) {
            case 'cancel':
                $this->authorize($isOwner);
                $ticket->setStatus($state->cancel($ticket));
                break;
            case 'approve':
                $this->authorize($isOwner);
                $ticket->setStatus($state->close($ticket));
                break;
            case 'reject':
                $this->authorize($isOwner);
                $ticket->setStatus($state->reject($ticket));
                $ticket->incrementAttempt();
                $ticket->resolvedAt = null;
                break;
            case 'start_review':
                $this->authorize($isHandler);
                $ticket->setStatus($state->startReview($ticket));
                break;
            case 'resolve':
                $this->authorize($isHandler);
                $ticket->setStatus($state->resolve($ticket));
                $ticket->resolvedAt = $this->clock->now();
                break;
            default:
                throw new ValidationException(['action' => 'Bilinmeyen işlem.']);
        }

        // Okuma ile yazma arasında başka bir istek talebi değiştirdiyse üzerine yazma (NFR-04 ruhu).
        if (!$this->tickets->saveIfStatus($ticket, $old)) {
            throw new ConflictException('Talep bu arada değişti. Sayfayı yenileyip tekrar deneyin.');
        }
        $this->notifyObservers($ticket, self::EVENT_STATUS_CHANGED, ['old' => $old, 'new' => $ticket->status, 'actor_id' => (int) $actor->id]);

        return $ticket;
    }

    /**
     * Ekranda gösterilecek eylem düğmeleri: kullanıcının yetkisi olan ve mevcut durumda State'in izin verdiği eylemler.
     * Sunucu her eylemde perform() ile yine denetler; bu liste yalnız arayüz içindir.
     *
     * @return list<string> cancel | start_review | resolve | approve | reject
     */
    public function availableActions(Ticket $ticket, User $viewer): array
    {
        $isOwner = $ticket->creatorId === $viewer->id;
        $isHandler = $viewer->role === Role::Admin || ($viewer->role === Role::Agent && $ticket->agentId === $viewer->id);
        $state = $ticket->state();
        $checks = [
            'cancel' => [$isOwner, static fn () => $state->cancel($ticket)],
            'start_review' => [$isHandler, static fn () => $state->startReview($ticket)],
            'resolve' => [$isHandler, static fn () => $state->resolve($ticket)],
            'approve' => [$isOwner, static fn () => $state->close($ticket)],
            'reject' => [$isOwner, static fn () => $state->reject($ticket)],
        ];

        $actions = [];
        foreach ($checks as $action => [$allowed, $try]) {
            if (!$allowed) {
                continue;
            }
            try {
                $try();
                $actions[] = $action;
            } catch (InvalidTransitionException) {
                // bu durumda izin yok
            }
        }

        return $actions;
    }

    /** Yanıt yazabilir mi (arayüzde form gösterilsin mi). */
    public function canReply(Ticket $ticket, User $viewer): bool
    {
        return !$ticket->status->isFinal()
            && ($ticket->creatorId === $viewer->id
                || $viewer->role === Role::Admin
                || ($viewer->role === Role::Agent && $ticket->agentId === $viewer->id));
    }

    /** Kanban'da kartı sütuna taşıma: yalnız ileri geçişler (Alındı → İnceleniyor → Çözüldü). */
    public function moveOnKanban(int $ticketId, User $agent, TicketStatus $target): Ticket
    {
        return match ($target) {
            TicketStatus::InReview => $this->perform($ticketId, $agent, 'start_review'),
            TicketStatus::Resolved => $this->perform($ticketId, $agent, 'resolve'),
            default => throw new InvalidTransitionException("Kanban'da bu sütuna taşınamaz."),
        };
    }

    /** Talebe mesaj yazar (US-04, US-10): talep sahibi, atanan personel veya yönetici. */
    public function addReply(int $ticketId, User $author, string $message): Reply
    {
        $ticket = $this->mustFind($ticketId);
        $this->authorize(
            $ticket->creatorId === $author->id
            || $author->role === Role::Admin
            || ($author->role === Role::Agent && $ticket->agentId === $author->id),
        );
        if ($ticket->status->isFinal()) {
            throw new ValidationException(['message' => 'Kapalı veya iptal edilmiş talebe yanıt yazılamaz.']);
        }
        $message = trim($message);
        if (Text::length($message) < 1 || Text::length($message) > 5000) {
            throw new ValidationException(['message' => 'Mesaj 1-5000 karakter olmalı.']);
        }

        $reply = $this->replies->save(new Reply(null, $ticketId, (int) $author->id, $message, $this->clock->now()));
        $event = $author->role === Role::Customer ? self::EVENT_CUSTOMER_REPLIED : self::EVENT_AGENT_REPLIED;
        $this->notifyObservers($ticket, $event, ['actor_id' => (int) $author->id, 'reply_id' => $reply->id]);

        return $reply;
    }

    /**
     * Görüntüleme yetkisi: müşteri yalnız kendi talebini, personel kendisine atananı ve havuzdakini,
     * yönetici hepsini görür. Talep yoksa 404, yetki yoksa 403.
     */
    public function findVisible(int $ticketId, User $viewer): Ticket
    {
        $ticket = $this->mustFind($ticketId);
        $visible = match ($viewer->role) {
            Role::Admin => true,
            Role::Customer => $ticket->creatorId === $viewer->id,
            Role::Agent => $ticket->agentId === $viewer->id
                || ($ticket->agentId === null && $ticket->status === TicketStatus::Pending),
        };
        $this->authorize($visible, 'Bu talebi görüntüleme yetkiniz yok.');

        return $ticket;
    }

    /**
     * Müşterinin talepleri: durum filtresi, başlıkta veya talep numarasında arama, sayfalama (yeniden eskiye).
     *
     * @return array{items: list<Ticket>, total: int, page: int, pages: int}
     */
    public function searchForCustomer(User $customer, ?TicketStatus $status, string $query, int $page, int $perPage = 10): array
    {
        $items = array_values(array_filter(
            $this->tickets->findByCreator((int) $customer->id),
            fn (Ticket $t): bool => ($status === null || $t->status === $status) && $this->matches($t, $query),
        ));
        usort($items, static fn (Ticket $a, Ticket $b): int => [$b->createdAt, $b->id] <=> [$a->createdAt, $a->id]);

        return self::paginate($items, $page, $perPage);
    }

    /**
     * Ortak sayfalama yardımcısı.
     *
     * @param list<Ticket> $items
     * @return array{items: list<Ticket>, total: int, page: int, pages: int}
     */
    public static function paginate(array $items, int $page, int $perPage): array
    {
        $total = count($items);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /** Arama: "#7" yalnız talep numarası; düz metin başlıkta (Türkçe büyük/küçük harf duyarsız) veya numarada. */
    public function matches(Ticket $ticket, string $query): bool
    {
        $query = trim($query);
        if ($query === '') {
            return true;
        }
        if (preg_match('/^#(\d+)$/', $query, $m) === 1) {
            return $ticket->id === (int) $m[1];
        }
        if (ctype_digit($query) && $ticket->id === (int) $query) {
            return true;
        }

        return str_contains(Text::fold($ticket->title), Text::fold($query));
    }

    public function mustFind(int $ticketId): Ticket
    {
        return $this->tickets->findById($ticketId) ?? throw new NotFoundException('Talep bulunamadı.');
    }

    private function authorize(bool $allowed, string $message = 'Bu işlem için yetkiniz yok.'): void
    {
        if (!$allowed) {
            throw new AuthorizationException($message);
        }
    }
}
