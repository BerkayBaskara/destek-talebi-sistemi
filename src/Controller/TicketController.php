<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Exception\ConflictException;
use App\Exception\ValidationException;
use App\Kernel;
use App\Model\Enum\Priority;
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
        $this->kernel->requireRole(Role::Customer);

        return $this->kernel->render('tickets/create', $this->formData([], []));
    }

    public function store(Request $request): Response
    {
        $customer = $this->kernel->requireRole(Role::Customer);
        $old = [
            'category_id' => $request->string('category_id'),
            'priority' => $request->string('priority'),
            'title' => $request->string('title'),
            'description' => $request->string('description'),
        ];

        $attachments = $this->kernel->attachmentService();
        try {
            // Önce dosyalar doğrulanır; geçersiz dosya yarım kalmış talep bırakmasın.
            $files = $attachments->validateMany(AttachmentService::normalizeFiles($request->files['ekler'] ?? null));
            $ticket = $this->tickets->createTicket($customer, $old);
            foreach ($files as $file) {
                $attachments->store((int) $ticket->id, $customer, $file);
            }
        } catch (ValidationException $e) {
            return $this->kernel->render('tickets/create', $this->formData($old, $e->errors()), 422);
        }

        $this->kernel->session()->flash('success', 'Talebiniz alındı. Talep no: #' . $ticket->id);

        return Response::redirect('/talepler/' . $ticket->id);
    }

    /** Talep detayı: müşteri kendi talebini, personel kendisine atananı/havuzdakini, yönetici hepsini görür. */
    public function show(int $id): Response
    {
        $viewer = $this->kernel->requireRole();
        $ticket = $this->tickets->findVisible($id, $viewer);
        $replies = $this->kernel->replyRepository()->findByTicket($id);
        $logs = $this->kernel->ticketLogRepository()->findByTicket($id);

        // Ekranda isim göstermek için ilgili kullanıcıları bir kez yükle (0 = Sistem).
        $ids = array_merge([$ticket->creatorId, (int) $ticket->agentId], array_map(static fn ($r) => $r->userId, $replies), array_map(static fn ($l) => $l->userId, $logs));
        $names = [0 => 'Sistem'];
        foreach (array_unique($ids) as $userId) {
            if ($userId > 0) {
                $names[$userId] = $this->kernel->userRepository()->findById($userId)?->fullName() ?? 'Silinmiş kullanıcı';
            }
        }

        return $this->kernel->render('tickets/show', [
            'title' => 'Talep #' . $ticket->id,
            'ticket' => $ticket,
            'viewer' => $viewer,
            'names' => $names,
            'category' => $this->kernel->categoryRepository()->findById($ticket->categoryId),
            'sla' => $this->kernel->slaStatusService()->forTicket($ticket),
            'replies' => $replies,
            'logs' => $logs,
            'attachments' => $this->kernel->attachmentRepository()->findByTicket($id),
            'actions' => $this->tickets->availableActions($ticket, $viewer),
            'canReply' => $this->tickets->canReply($ticket, $viewer),
        ]);
    }

    /** Talebe mesaj yazma (US-04, US-10). */
    public function reply(Request $request, int $id): Response
    {
        $author = $this->kernel->requireRole();
        $this->tickets->findVisible($id, $author);

        try {
            $this->tickets->addReply($id, $author, $request->string('mesaj'));
            $this->kernel->session()->flash('success', 'Mesajınız gönderildi.');
        } catch (ValidationException $e) {
            $this->kernel->session()->flash('error', $e->getMessage());
        }

        return Response::redirect('/talepler/' . $id . '#mesajlar');
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

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     * @return array<string, mixed>
     */
    private function formData(array $old, array $errors): array
    {
        return [
            'title' => 'Yeni destek talebi',
            'categories' => $this->kernel->categoryRepository()->findAll(),
            'priorities' => Priority::cases(),
            'old' => $old,
            'errors' => $errors,
            'maxFiles' => $this->kernel->attachmentService()->maxFiles(),
        ];
    }
}
