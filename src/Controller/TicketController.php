<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Exception\ConflictException;
use App\Exception\ValidationException;
use App\Kernel;
use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\State\InvalidTransitionException;
use App\Service\AttachmentService;
use App\Service\TicketService;

/**
 * Talep ekranları: Taleplerim (arama/filtre/sayfa), yeni talep formu, talep detayı ve durum eylemleri.
 * Yalnız isteği okur, servisi çağırır ve görünümü seçer; iş kuralı TicketService'te.
 */
final class TicketController
{
    public function __construct(private readonly Kernel $kernel, private readonly TicketService $tickets)
    {
    }

    /** Müşterinin talepleri (wireframe: Destek Taleplerim). */
    public function index(Request $request): Response
    {
        $customer = $this->kernel->requireRole(Role::Customer);
        $status = TicketStatus::tryFrom($request->string('durum'));
        $query = $request->string('q');
        $page = max(1, (int) $request->string('sayfa'));

        return $this->kernel->render('tickets/index', [
            'title' => 'Taleplerim',
            'result' => $this->tickets->searchForCustomer($customer, $status, $query, $page),
            'status' => $status,
            'query' => $query,
        ]);
    }

    public function createForm(): Response
    {
        $this->kernel->requireRole(Role::Customer, Role::Admin);

        return $this->kernel->render('tickets/create', $this->formData([], []));
    }

    public function store(Request $request): Response
    {
        $user = $this->kernel->requireRole(Role::Customer, Role::Admin);
        // Yönetici kendi adına ya da seçtiği müşteri adına talep açabilir (FR-16, US-39).
        $customer = $user;
        if ($user->role === Role::Admin && (int) $request->string('customer_id') > 0) {
            $chosen = $this->kernel->userRepository()->findById((int) $request->string('customer_id'));
            if ($chosen === null || $chosen->role !== Role::Customer) {
                throw new \App\Exception\NotFoundException('Müşteri bulunamadı.');
            }
            $customer = $chosen;
        }
        $old = [
            'category_id' => $request->string('category_id'),
            'priority' => $request->string('priority'),
            'title' => $request->string('title'),
            'description' => $request->string('description'),
        ];
        $extra = $request->input('extra', []);
        $extra = is_array($extra) ? $extra : [];

        $attachments = $this->kernel->attachmentService();
        try {
            // Önce dosyalar doğrulanır; geçersiz dosya yarım kalmış talep bırakmasın.
            $files = $attachments->validateMany(AttachmentService::normalizeFiles($request->files['ekler'] ?? null));
            $ticket = $this->tickets->createTicket($customer, $old + ['extra' => $extra]);
        } catch (ValidationException $e) {
            return $this->kernel->render('tickets/create', $this->formData($old + ['customer_id' => $request->string('customer_id')], $e->errors(), $extra), 422);
        }

        foreach ($files as $file) {
            try {
                $attachments->store((int) $ticket->id, $customer, $file);
            } catch (ValidationException $e) {
                // Talep kaydedildi; formu yeniden göstermek tekrar gönderimde çift talep açardı.
                $this->kernel->session()->flash('error', 'Talep açıldı ancak bir ek kaydedilemedi: ' . $e->getMessage());
            }
        }

        if ($user->role === Role::Admin) {
            $this->kernel->session()->flash('success', 'Talep açıldı: ' . $ticket->displayCode());

            return Response::redirect('/talepler/' . $ticket->id);
        }

        return Response::redirect('/talepler/' . $ticket->id . '/olusturuldu');
    }

    /** Talep oluşturuldu ekranı: yalnız talebin sahibi görür; 5 saniye sonra Taleplerim'e döner. */
    public function created(int $id): Response
    {
        $customer = $this->kernel->requireRole(Role::Customer);
        $ticket = $this->tickets->findVisible($id, $customer);

        return $this->kernel->render('tickets/created', [
            'title' => 'Talebiniz alındı',
            'ticket' => $ticket,
            'seconds' => 5,
            'refresh' => '5;url=/talepler',
        ]);
    }

    /** Talep detayı: müşteri kendi talebini, personel kendisine atananı/havuzdakini, yönetici hepsini görür. */
    public function show(int $id): Response
    {
        $viewer = $this->kernel->requireRole();
        $ticket = $this->tickets->findVisible($id, $viewer);
        $replies = $this->kernel->replyRepository()->findByTicket($id);
        $logs = $this->kernel->ticketLogRepository()->findByTicket($id);
        $activities = $this->kernel->ticketActivityRepository()->findByTicket($id);

        // Ekranda isim göstermek için ilgili kullanıcıları bir kez yükle (0 = Sistem).
        $ids = array_merge(
            [$ticket->creatorId, (int) $ticket->agentId, (int) $ticket->previousAgentId],
            array_map(static fn ($r) => $r->userId, $replies),
            array_map(static fn ($l) => $l->userId, $logs),
            array_map(static fn ($a) => $a->userId, $activities),
            array_map(static fn ($a) => (int) $a->targetUserId, $activities),
        );
        $names = [0 => 'Sistem'];
        foreach (array_unique($ids) as $userId) {
            if ($userId > 0) {
                $names[$userId] = $this->kernel->userRepository()->findById($userId)?->fullName() ?? 'Silinmiş kullanıcı';
            }
        }

        return $this->kernel->render('tickets/show', [
            'title' => 'Talep ' . $ticket->displayCode(),
            'ticket' => $ticket,
            'viewer' => $viewer,
            'names' => $names,
            'category' => $this->kernel->categoryRepository()->findById($ticket->categoryId),
            'categoryLabel' => $this->kernel->catalogService()->labels()[$ticket->categoryId] ?? '-',
            'sla' => $this->kernel->slaStatusService()->forTicket($ticket),
            'replies' => $replies,
            'logs' => $logs,
            'activities' => $activities,
            'attachments' => $this->kernel->attachmentRepository()->findByTicket($id),
            'actions' => $this->tickets->availableActions($ticket, $viewer),
            'canReply' => $this->tickets->canReply($ticket, $viewer),
            'canAsk' => $viewer->role !== Role::Customer && in_array($ticket->status, [TicketStatus::Taken, TicketStatus::InReview], true) && $ticket->pausedAt === null,
            'questionsLeft' => max(0, $this->kernel->settingsService()->int('tickets.max_questions') - $ticket->agentQuestionCount),
            'canEscalate' => in_array($ticket->status, [TicketStatus::Taken, TicketStatus::InReview], true)
                && ($viewer->role === Role::Admin || ($viewer->role === Role::Agent && $ticket->agentId === $viewer->id)),
            'assignableAgents' => $viewer->role === Role::Admin && $ticket->status === TicketStatus::Pending && $ticket->agentId === null
                ? $this->assignableAgents() : [],
            'replyBlockedMessage' => $this->tickets->replyBlockedReason($ticket, $viewer),
        ]);
    }

    /** Talebe mesaj yazma (US-04, US-10). */
    public function reply(Request $request, int $id): Response
    {
        $author = $this->kernel->requireRole();
        $this->tickets->findVisible($id, $author);

        try {
            $this->tickets->addReply($id, $author, $request->string('mesaj'), $request->string('soru') === '1');
            $this->kernel->session()->flash('success', 'Mesajınız gönderildi.');
        } catch (ValidationException $e) {
            $this->kernel->session()->flash('error', $e->getMessage());
        }

        return Response::redirect('/talepler/' . $id . '#mesajlar');
    }

    /** SLA aşımı sonrası müşterinin kararı: aynı personelle devam ya da sorun çözüldü. */
    public function decide(Request $request, int $id): Response
    {
        $customer = $this->kernel->requireRole(Role::Customer);
        $this->tickets->findVisible($id, $customer);

        try {
            $result = $this->kernel->escalationService()->decide($id, $customer, $request->string('karar'));
            $this->kernel->session()->flash('success', $result['message']);
        } catch (ValidationException $e) {
            $this->kernel->session()->flash('error', $e->getMessage());
        }

        return Response::redirect('/talepler/' . $id);
    }

    /** Personelin (ya da yöneticinin) gerekçeyle üst seviyeye aktarması. */
    public function escalate(Request $request, int $id): Response
    {
        $actor = $this->kernel->requireRole(Role::Agent, Role::Admin);
        $this->tickets->findVisible($id, $actor);

        try {
            $target = $this->kernel->escalationService()->escalateManually($id, $actor, $request->string('gerekce'));
            $this->kernel->session()->flash('success', $target !== null
                ? 'Talep üst seviye personele aktarıldı.'
                : 'Uygun üst seviye personel yok; talep yönetici müdahalesi için Görev Havuzu\'na alındı.');
        } catch (ValidationException $e) {
            $this->kernel->session()->flash('error', $e->getMessage());
        }

        return Response::redirect($actor->role === Role::Agent ? '/kanban' : '/talepler/' . $id);
    }

    /** Yöneticinin havuzdaki talebi elle ataması. */
    public function assign(Request $request, int $id): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $agentId = (int) $request->string('personel');

        try {
            $this->kernel->escalationService()->assignManually($id, $admin, $agentId, $request->string('gerekce'), $this->kernel->userRepository()->findById($agentId));
            $this->kernel->session()->flash('success', 'Talep personele atandı.');
        } catch (ValidationException $e) {
            $this->kernel->session()->flash('error', $e->getMessage());
        }

        return Response::redirect('/talepler/' . $id);
    }

    /** Yöneticinin talebi "çözülemedi" olarak kapatması. */
    public function closeUnresolvable(Request $request, int $id): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);

        try {
            $this->kernel->escalationService()->closeUnresolvable($id, $admin, $request->string('gerekce'));
            $this->kernel->session()->flash('success', 'Talep "çözülemedi" olarak kapatıldı.');
        } catch (ValidationException $e) {
            $this->kernel->session()->flash('error', $e->getMessage());
        }

        return Response::redirect('/talepler/' . $id);
    }

    /** Durum eylemi (iptal, incelemeye al, çözüldü, onayla, reddet). İzinsiz geçişin nedeni kullanıcıya gösterilir. */
    public function action(Request $request, int $id): Response
    {
        $actor = $this->kernel->requireRole();
        $this->tickets->findVisible($id, $actor);

        try {
            $this->tickets->perform($id, $actor, $request->string('islem'));
            $this->kernel->session()->flash('success', 'Talep durumu güncellendi.');
        } catch (InvalidTransitionException | ValidationException | ConflictException $e) {
            $this->kernel->session()->flash('error', $e->getMessage());
        }

        return Response::redirect('/talepler/' . $id);
    }

    /** @return list<array{agent: \App\Model\User, load: int, capacity: int}> aktif personel ve doluluğu */
    private function assignableAgents(): array
    {
        $load = $this->kernel->ticketRepository()->countActiveByAgent();
        $rows = [];
        foreach ($this->kernel->userRepository()->findByRole(Role::Agent) as $agent) {
            if ($agent->active) {
                $rows[] = ['agent' => $agent, 'load' => $load[(int) $agent->id] ?? 0, 'capacity' => $this->kernel->staffService()->capacityOf($agent)];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     * @param array<mixed> $oldExtra
     * @return array<string, mixed>
     */
    private function formData(array $old, array $errors, array $oldExtra = []): array
    {
        $fields = [];
        foreach ($this->kernel->categoryFieldRepository()->all() as $field) {
            $fields[] = $field;
        }

        $customers = $this->kernel->currentUser()?->role === Role::Admin ? $this->kernel->userRepository()->findByRole(Role::Customer) : [];

        return [
            'customers' => $customers,
            'oldCustomer' => $old['customer_id'] ?? '',
            'extraFields' => $fields,
            'categoryLabels' => $this->kernel->catalogService()->labels(),
            'oldExtra' => $oldExtra,
            'title' => 'Yeni destek talebi',
            'categories' => $this->kernel->categoryRepository()->findActive(),
            'priorities' => $this->kernel->priorityRepository()->findActive(),
            'old' => $old,
            'errors' => $errors,
            'maxFiles' => $this->kernel->attachmentService()->maxFiles(),
            'maxImageMb' => $this->kernel->settingsService()->int('files.max_image_mb'),
            'maxVideoMb' => $this->kernel->settingsService()->int('files.max_video_mb'),
        ];
    }
}
