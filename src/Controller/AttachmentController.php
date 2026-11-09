<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Response;
use App\Exception\NotFoundException;
use App\Kernel;
use App\Service\AttachmentService;

/** NFR-03: ek dosyaları web kökü dışından, yalnız talebi görebilen kullanıcıya verir. */
final class AttachmentController
{
    public function __construct(private readonly Kernel $kernel, private readonly AttachmentService $attachments)
    {
    }

    public function download(int $id): Response
    {
        $user = $this->kernel->requireRole();
        $attachment = $this->kernel->attachmentRepository()->findById($id) ?? throw new NotFoundException('Dosya bulunamadı.');
        $this->kernel->ticketService()->findVisible($attachment->ticketId, $user); // yetki yoksa 403

        $path = $this->attachments->absolutePath($attachment);
        if (!is_file($path)) {
            throw new NotFoundException('Dosya bulunamadı.');
        }

        $extension = AttachmentService::ALLOWED[$attachment->fileType] ?? 'bin';

        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => $attachment->fileType,
            'Content-Disposition' => 'inline; filename="ek-' . $attachment->id . '.' . $extension . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
