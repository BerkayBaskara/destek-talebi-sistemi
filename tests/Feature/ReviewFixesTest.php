<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Model\User;
use App\Service\DetailedReportService;
use DateTimeImmutable;
use DateTimeZone;

/** Son incelemede bulunan durumlar: soru duraklamasının kapanması, uyum hesabı, yeniden aktif personel, puan, geri yükleme. */
final class ReviewFixesTest extends FeatureTestCase
{
    private User $admin;
    private User $agent;
    private User $senior;
    private string $customerNo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $auth = $this->kernel->authService();
        $this->admin = $auth->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
        $this->agent = $auth->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $this->senior = $auth->createStaff(Role::Agent, 'Murat', 'Demir', self::PASSWORD);
        $this->kernel->hierarchyRepository()->setLevel(1, (int) $this->agent->id, 1);
        $this->kernel->hierarchyRepository()->setLevel(1, (int) $this->senior->id, 2);
        $this->customerNo = $this->registerCustomer();
        $this->as($this->customerNo);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.']);
    }

    private function as(string $number): void
    {
        $this->post('/cikis');
        $this->loginAs($number, self::PASSWORD);
    }

    private function at(string $moment): void
    {
        $this->clock->set(new DateTimeImmutable($moment, new DateTimeZone('Europe/Istanbul')));
    }

    private function ticket(): Ticket
    {
        $ticket = $this->kernel->ticketRepository()->findById(1);
        self::assertNotNull($ticket);

        return $ticket;
    }

    private function ask(): void
    {
        $this->as($this->agent->customerNo);
        $this->post('/talepler/1/yanit', ['mesaj' => 'Yazıcının modeli nedir?', 'soru' => '1']);
        self::assertNotNull($this->ticket()->pausedAt, 'Soru SLA\'yı durdurdu.');
    }

    /** Bulgu 1: talep başka personele aktarılınca soru duraklaması kapanır; yeni personel soru sorabilir. */
    public function test_escalation_ends_question_pause(): void
    {
        $this->ask();
        $this->post('/talepler/1/eskale', ['gerekce' => 'Sunucu yetkisi gerekiyor']);

        $ticket = $this->ticket();
        self::assertSame($this->senior->id, $ticket->agentId);
        self::assertNull($ticket->pausedAt);
        self::assertNull($ticket->questionDueAt);
    }

    /** Bulgu 1: soru beklerken çözülüp kapanan talebin bitişine izleme dokunmaz, personele "yanıtlamadı" bildirimi gitmez. */
    public function test_closed_ticket_is_not_touched_when_question_time_ends(): void
    {
        $this->ask();
        $this->post('/talepler/1/islem', ['islem' => 'start_review']);
        $this->post('/talepler/1/islem', ['islem' => 'resolve']);
        $this->as($this->customerNo);
        $this->post('/talepler/1/islem', ['islem' => 'approve']);
        $closed = $this->ticket();
        self::assertSame(TicketStatus::Closed, $closed->status);
        self::assertNull($closed->pausedAt);

        $this->clock->advance('+2 days');
        $this->kernel->slaMonitor()->run();

        self::assertEquals($closed->deadline, $this->ticket()->deadline);
        $messages = array_map(static fn ($n): string => $n->message, $this->kernel->notificationRepository()->forUser((int) $this->agent->id));
        self::assertSame([], array_values(array_filter($messages, static fn (string $m): bool => str_contains($m, 'yanıtlamadı'))));
    }

    /** Bulgu 6: yanıtlanmayan soruda SLA, izlemenin çalıştığı andan değil yanıt süresinin bittiği andan devam eder. */
    public function test_unanswered_question_resumes_from_reply_due_time(): void
    {
        $this->kernel->settingsService()->update($this->admin, ['tickets.question_reply_hours' => '2']);
        $this->ask(); // Pzt 10:00, yanıt süresi 12:00'de biter
        $before = $this->ticket();
        $remaining = $this->kernel->slaCalculator()->businessMinutesBetween($before->pausedAt ?? $before->createdAt, $before->deadline);
        $dueAt = $before->questionDueAt;
        self::assertNotNull($dueAt);

        $this->at('2026-11-09 15:00');
        $this->kernel->slaMonitor()->run();

        $expected = $this->kernel->slaCalculator()->calculateWithJson($dueAt, $remaining);
        self::assertSame($expected->format('Y-m-d H:i'), $this->ticket()->deadline->format('Y-m-d H:i'));
    }

    /** Bulgu 5: SLA uyumunda soru nedeniyle duran süre personelin aleyhine sayılmaz. */
    public function test_paused_time_does_not_count_against_sla_compliance(): void
    {
        $this->ask(); // Pzt 10:00; ilk SLA bitişi Çar 10:00
        self::assertSame('2026-11-11 10:00', $this->ticket()->firstDeadline()->format('Y-m-d H:i'));
        $this->at('2026-11-11 10:00');
        $this->as($this->customerNo);
        $this->post('/talepler/1/yanit', ['mesaj' => 'HP LaserJet 400.']); // 16 iş saati durdu
        $this->at('2026-11-11 11:00');
        $this->as($this->agent->customerNo);
        $this->post('/talepler/1/islem', ['islem' => 'start_review']);
        $this->post('/talepler/1/islem', ['islem' => 'resolve']);

        self::assertTrue(DetailedReportService::metFirstDeadline($this->ticket()), 'İlk bitişten 1 saat sonra ama duraklama düşülünce süresinde.');
    }

    /** Bulgu 2: yönetici yeniden aktif yaptığı personel bir sonraki denetimde tekrar pasif yapılmaz. */
    public function test_reactivated_staff_is_not_deactivated_again(): void
    {
        $this->kernel->settingsService()->update($this->admin, ['staff.inactivity_days' => '30']);
        $this->kernel->slaMonitor()->run(); // hiç giriş yapmamış personelde sayım ilk denetimde başlar
        $this->clock->advance('+60 days');
        $this->kernel->slaMonitor()->run();
        self::assertFalse($this->kernel->userRepository()->findById((int) $this->senior->id)?->active);

        $this->as($this->admin->customerNo);
        $this->post('/yonetici/personel/' . $this->senior->id, ['capacity' => '', 'active' => '1']);
        $this->clock->advance('+5 minutes');
        $this->kernel->slaMonitor()->run();

        self::assertTrue($this->kernel->userRepository()->findById((int) $this->senior->id)?->active);
    }

    /** Bulgu 4: eskalasyonla devralan personelin puanı yalnız kendi denemelerine göre hesaplanır. */
    public function test_score_of_receiving_agent_ignores_previous_rejections(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $agent = $this->ticket()->agentId === $this->senior->id ? $this->senior : $this->agent;
            $this->as($agent->customerNo);
            if ($this->ticket()->status === TicketStatus::Taken) {
                $this->post('/talepler/1/islem', ['islem' => 'start_review']);
            }
            $this->post('/talepler/1/islem', ['islem' => 'resolve']);
            $this->as($this->customerNo);
            $this->post('/talepler/1/islem', ['islem' => 'reject']);
        }
        self::assertSame($this->senior->id, $this->ticket()->agentId);
        $this->as($this->senior->customerNo);
        $this->post('/talepler/1/islem', ['islem' => 'start_review']);
        $this->post('/talepler/1/islem', ['islem' => 'resolve']);
        $this->as($this->customerNo);
        $this->post('/talepler/1/islem', ['islem' => 'approve']);

        $report = $this->kernel->detailedReportService();
        $scores = [];
        foreach ($report->perAgent($report->filter([])) as $row) {
            $scores[(int) $row['agent']->id] = $row['score'];
        }
        self::assertSame(120, $scores[(int) $this->senior->id], 'İlk denemede çözdü: 100 × 1,0 × 1,2.');
        self::assertSame(0, $scores[(int) $this->agent->id]);
    }

    /** Bulgu 3: yöneticisi olmayan ya da kimliksiz satır içeren kullanıcı tablosu geri yüklenmez. */
    public function test_restore_rejects_users_table_without_active_admin(): void
    {
        $this->as($this->admin->customerNo);
        foreach (['[1,2]', '[{"id": 1, "customer_no": "M100001", "role": "customer", "active": true}]'] as $users) {
            $path = (string) tempnam(sys_get_temp_dir(), 'bad');
            $zip = new \ZipArchive();
            $zip->open($path, \ZipArchive::OVERWRITE);
            $zip->addFromString('users.json', $users);
            $zip->close();

            $response = $this->post('/yonetici/yedek/geri-yukle', [], ['yedek' => ['name' => 'y.zip', 'type' => 'application/zip', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)]]);

            self::assertSame(422, $response->status, $users);
            self::assertNotNull($this->kernel->userRepository()->findById((int) $this->admin->id), 'Veri değişmedi.');
        }
    }

    /** Bulgu 3: açıldığında çok büyüyen dosya (zip bombası) reddedilir. */
    public function test_restore_rejects_oversized_uncompressed_content(): void
    {
        $this->as($this->admin->customerNo);
        $raw = (string) tempnam(sys_get_temp_dir(), 'raw');
        $h = fopen($raw, 'wb');
        self::assertNotFalse($h);
        fwrite($h, '[');
        for ($i = 0; $i < 60; $i++) {
            fwrite($h, str_repeat(' ', 1024 * 1024)); // 60 MB boşluk, bellekte tek parça kurulmaz
        }
        fwrite($h, ']');
        fclose($h);
        $path = (string) tempnam(sys_get_temp_dir(), 'big');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFile($raw, 'users.json');
        $zip->close();
        unlink($raw);
        self::assertLessThan(1024 * 1024, (int) filesize($path));

        $response = $this->post('/yonetici/yedek/geri-yukle', [], ['yedek' => ['name' => 'y.zip', 'type' => 'application/zip', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)]]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('açılmış hâli', $response->body);
    }
}
