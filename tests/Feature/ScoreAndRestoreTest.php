<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Model\User;

/** FR-15 personel performans puanı; FR-88 yedekten geri yükleme. */
final class ScoreAndRestoreTest extends FeatureTestCase
{
    private User $admin;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->admin = $this->kernel->authService()->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
        $this->agent = $this->kernel->authService()->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
    }

    private function closedTicket(int $attempts): int
    {
        $now = $this->clock->now();
        $ticket = new Ticket(null, 99, (int) $this->agent->id, 1, 'Çözülen', TicketStatus::Closed, 2, 2, $attempts, $now, $now->modify('+1 day'), $now->modify('+1 hour'));

        return (int) $this->kernel->ticketRepository()->save($ticket)->id;
    }

    public function test_score_depends_on_attempts_and_escalation_bonus(): void
    {
        $this->closedTicket(0); // 1. denemede: 100
        $this->closedTicket(1); // 2. denemede: 70
        $this->closedTicket(2); // 3. denemede: 40
        $escalated = $this->closedTicket(0);
        $this->kernel->escalationRepository()->add($escalated, 999, (int) $this->agent->id, 'manual', 'test', $this->clock->now()); // devralan: 120

        $report = $this->kernel->detailedReportService();
        $row = array_values(array_filter($report->perAgent($report->filter([])), fn (array $r): bool => $r['agent']->id === $this->agent->id))[0];
        self::assertSame(330, $row['score']);

        $this->kernel->settingsService()->update($this->admin, ['score.base' => '10']);
        $row = array_values(array_filter($report->perAgent($report->filter([])), fn (array $r): bool => $r['agent']->id === $this->agent->id))[0];
        self::assertSame(33, $row['score'], 'Taban puan yönetici ayarı.');

        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        self::assertStringContainsString('Performans puanı', $this->get('/yonetici/rapor')->body);
    }

    private function backupZip(): string
    {
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        $zip = $this->post('/yonetici/yedek/indir')->body;
        $path = (string) tempnam(sys_get_temp_dir(), 'bak');
        file_put_contents($path, $zip);

        return $path;
    }

    /** @return array<string, mixed> */
    private static function upload(string $path): array
    {
        return ['yedek' => ['name' => 'yedek.zip', 'type' => 'application/zip', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)]];
    }

    public function test_restore_brings_back_data_and_keeps_automatic_backup(): void
    {
        $this->closedTicket(0);
        $path = $this->backupZip();
        $this->closedTicket(0);
        self::assertCount(2, $this->kernel->ticketRepository()->findAll());

        $response = $this->post('/yonetici/yedek/geri-yukle', [], self::upload($path));

        self::assertSame(302, $response->status);
        self::assertCount(1, $this->kernel->ticketRepository()->findAll(), 'Yedekteki duruma dönüldü.');
        self::assertNotSame([], glob($this->root . '/storage/backups/otomatik-*.zip') ?: [], 'Geri yüklemeden önce otomatik yedek alınır.');
    }

    public function test_invalid_backup_is_rejected_and_data_untouched(): void
    {
        $this->closedTicket(0);
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        $path = (string) tempnam(sys_get_temp_dir(), 'bad');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('../kotu.php', '<?php echo 1;');
        $zip->addFromString('tickets.json', '{bozuk');
        $zip->close();

        $response = $this->post('/yonetici/yedek/geri-yukle', [], self::upload($path));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Yedek dosyası geçersiz', $response->body);
        self::assertCount(1, $this->kernel->ticketRepository()->findAll());
        self::assertFileDoesNotExist($this->root . '/storage/kotu.php');
    }

    public function test_agent_cannot_restore(): void
    {
        $this->loginAs($this->agent->customerNo, self::PASSWORD);

        self::assertSame(403, $this->post('/yonetici/yedek/geri-yukle')->status);
    }
}
