<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Attachment;

/** NFR-03: ekler yalnız talebi görebilen kullanıcıya verilir; başkasına 403, olmayana 404. */
final class AttachmentAccessTest extends FeatureTestCase
{
    private int $attachmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->loginAs($this->registerCustomer(), self::PASSWORD);
        $this->post('/talepler', [
            'category_id' => '1', 'priority' => '2',
            'title' => 'Ekran görüntüsü ekli', 'description' => 'Hata ekranı ektedir, inceleyin lütfen.',
        ]);

        // Yükleme akışı birim testlerinde; burada doğrudan kayıt + dosya oluşturuyoruz.
        file_put_contents($this->root . '/storage/uploads/' . str_repeat('a', 32) . '.png', 'PNGDATA');
        $this->attachmentId = (int) $this->kernel->attachmentRepository()->save(
            new Attachment(null, 1, 1, str_repeat('a', 32) . '.png', 'image/png', $this->clock->now()),
        )->id;
    }

    public function test_owner_downloads_with_safe_headers(): void
    {
        $response = $this->get('/ekler/' . $this->attachmentId);

        self::assertSame(200, $response->status);
        self::assertSame('PNGDATA', $response->body);
        self::assertSame('image/png', $response->headers['Content-Type']);
        self::assertStringContainsString('ek-' . $this->attachmentId . '.png', $response->headers['Content-Disposition']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
    }

    public function test_detail_page_links_attachment(): void
    {
        self::assertStringContainsString('/ekler/' . $this->attachmentId, $this->get('/talepler/1')->body);
    }

    public function test_other_customers_attachment_403(): void
    {
        $this->post('/cikis');
        $this->loginAs($this->registerCustomer('Ayşe'), self::PASSWORD);

        $response = $this->get('/ekler/' . $this->attachmentId);

        self::assertSame(403, $response->status);
        self::assertStringNotContainsString('PNGDATA', $response->body);
    }

    public function test_unknown_attachment_404_and_guest_redirect(): void
    {
        self::assertSame(404, $this->get('/ekler/999')->status);

        $this->post('/cikis');
        self::assertSame(302, $this->get('/ekler/' . $this->attachmentId)->status);
    }

    public function test_broken_upload_rejected_before_ticket_is_created(): void
    {
        $empty = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];
        $bad = ['name' => 'x.png', 'type' => 'image/png', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0];
        $files = ['ekler' => [
            'name' => [$bad['name'], $empty['name']],
            'type' => [$bad['type'], $empty['type']],
            'tmp_name' => [$bad['tmp_name'], $empty['tmp_name']],
            'error' => [$bad['error'], $empty['error']],
            'size' => [0, 0],
        ]];

        $response = $this->post('/talepler', [
            'category_id' => '1', 'priority' => '2',
            'title' => 'Bozuk ekli talep', 'description' => 'Bu talebin eki yarım yüklendi.',
        ], $files);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Dosya yüklenemedi', $response->body);
        self::assertCount(1, $this->kernel->ticketRepository()->findAll(), 'Geçersiz ekli talep kaydedilmemeli.');
    }
}
