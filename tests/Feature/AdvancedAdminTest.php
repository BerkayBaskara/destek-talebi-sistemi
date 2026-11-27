<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\User;

/** Ayrıntılı rapor ve CSV (FR-12), kategoriye özel ek alanlar (FR-01), veri yedeği indirme. */
final class AdvancedAdminTest extends FeatureTestCase
{
    private User $admin;
    private User $agent;
    private string $customerNo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $auth = $this->kernel->authService();
        $this->admin = $auth->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
        $this->agent = $auth->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $this->customerNo = $this->registerCustomer();
    }

    private function openTickets(): void
    {
        $this->loginAs($this->customerNo, self::PASSWORD);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.']);
        $this->post('/talepler', ['category_id' => '2', 'priority' => '2', 'title' => 'Excel açılmıyor', 'description' => 'Excel her açılışta kapanıyor.']);
        $this->post('/cikis');
    }

    public function test_report_with_filters_and_per_agent_stats(): void
    {
        $this->openTickets();
        $this->loginAs($this->agent->customerNo, self::PASSWORD);
        $this->post('/talepler/1/islem', ['islem' => 'start_review']);
        $this->post('/talepler/1/islem', ['islem' => 'resolve']);
        $this->post('/cikis');
        $this->loginAs($this->admin->customerNo, self::PASSWORD);

        $page = $this->get('/yonetici/rapor')->body;
        self::assertStringContainsString('Personel Performansı', $page);
        self::assertStringContainsString('Zeynep Kaya', $page);
        self::assertStringContainsString('%100', $page, 'Süresinde çözülen tek talep → SLA uyumu %100.');

        $filtered = $this->get('/yonetici/rapor?kategori=2')->body;
        self::assertStringContainsString('Excel açılmıyor', $filtered);
        self::assertStringNotContainsString('Yazıcı arızası', $filtered);
    }

    public function test_csv_export_uses_same_filters(): void
    {
        $this->openTickets();
        $this->loginAs($this->admin->customerNo, self::PASSWORD);

        $csv = $this->get('/yonetici/rapor.csv?kategori=1');

        self::assertSame(200, $csv->status);
        self::assertStringStartsWith('text/csv', $csv->headers['Content-Type'] ?? '');
        self::assertStringContainsString('attachment; filename="talep-raporu-', $csv->headers['Content-Disposition'] ?? '');
        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', ltrim($csv->body, "\xEF\xBB\xBF")))));
        self::assertCount(2, $lines, 'Başlık + 1 talep.');
        self::assertStringStartsWith('Talep Kodu;Konu;Kategori;Öncelik;Durum', $lines[0]);
        self::assertStringContainsString('Yazıcı arızası', $lines[1]);
        self::assertStringContainsString((string) $this->kernel->ticketRepository()->findById(1)?->code, $lines[1]);
    }

    public function test_csv_cells_are_protected_against_formula_injection(): void
    {
        $this->loginAs($this->customerNo, self::PASSWORD);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => '=HYPERLINK("x")', 'description' => 'Formül denemesi açıklaması.']);
        $this->post('/cikis');
        $this->loginAs($this->admin->customerNo, self::PASSWORD);

        $body = $this->get('/yonetici/rapor.csv')->body;

        self::assertStringContainsString("'=HYPERLINK", $body);
    }

    public function test_category_extra_field_is_required_shown_and_stored(): void
    {
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        self::assertSame(302, $this->post('/yonetici/kategoriler/1/alanlar', ['label' => 'Cihaz seri no', 'type' => 'text', 'required' => '1'])->status);
        $bad = $this->post('/yonetici/kategoriler/1/alanlar', ['label' => 'x', 'type' => 'renk', 'required' => '']);
        self::assertSame(422, $bad->status);
        $this->post('/cikis');

        $this->loginAs($this->customerNo, self::PASSWORD);
        $form = $this->get('/talepler/yeni')->body;
        self::assertStringContainsString('Cihaz seri no', $form);

        $missing = $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.']);
        self::assertSame(422, $missing->status);
        self::assertStringContainsString('Cihaz seri no alanı zorunlu.', $missing->body);

        $other = $this->post('/talepler', ['category_id' => '2', 'priority' => '2', 'title' => 'Excel açılmıyor', 'description' => 'Excel her açılışta kapanıyor.']);
        self::assertSame(302, $other->status, 'Başka kategorinin alanı zorunlu değil.');

        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.', 'extra' => ['1' => 'SN-4471']]);
        $ticket = $this->kernel->ticketRepository()->findById(2);
        self::assertSame(['Cihaz seri no' => 'SN-4471'], $ticket?->extraFields);
        self::assertStringContainsString('SN-4471', $this->get('/talepler/2')->body);
    }

    public function test_backup_download_is_zip_and_admin_only(): void
    {
        $this->openTickets();
        $this->loginAs($this->agent->customerNo, self::PASSWORD);
        self::assertSame(403, $this->post('/yonetici/yedek/indir')->status);
        $this->post('/cikis');

        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        self::assertSame(200, $this->get('/yonetici/yedek')->status);
        $zip = $this->post('/yonetici/yedek/indir');

        self::assertSame(200, $zip->status);
        self::assertSame('application/zip', $zip->headers['Content-Type'] ?? null);
        self::assertStringStartsWith('PK', $zip->body);
        $path = tempnam(sys_get_temp_dir(), 'yedek');
        file_put_contents((string) $path, $zip->body);
        $archive = new \ZipArchive();
        self::assertTrue($archive->open((string) $path));
        self::assertNotFalse($archive->locateName('tickets.json'));
        self::assertFalse($archive->locateName('tickets.lock'), 'Kilit dosyaları yedeğe girmez.');
        $archive->close();
        unlink((string) $path);
    }
}
