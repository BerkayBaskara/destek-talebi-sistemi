<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\User;

/**
 * FR-13.1 takvim dosyası yükleme, FR-13.2 özel gün / yarım gün ve aylık görünüm,
 * FR-13.4 takvim değişikliğinin açık taleplere uygulanması (yönetici ayarı).
 * Takvim: Pzt–Cum 09:00–18:00, öğle 12:00–13:00. Talep Pzt 2026-11-09 10:00, 16 iş saati.
 */
final class CalendarAdvancedTest extends FeatureTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->admin = $this->kernel->authService()->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
    }

    private function openTicket(): int
    {
        $this->post('/cikis');
        $this->loginAs($this->registerCustomer(), self::PASSWORD);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Takvim talebi', 'description' => 'Takvim denemesi için talep.']);
        $this->post('/cikis');
        $this->loginAs($this->admin->customerNo, self::PASSWORD);

        return 1;
    }

    /** @param array<string, mixed> $data */
    private function upload(array $data, string $name = 'takvim.json'): \App\Core\Http\Response
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'cal');
        file_put_contents($path, (string) json_encode($data));

        return $this->post('/yonetici/takvim/yukle', [], ['takvim' => ['name' => $name, 'type' => 'application/json', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)]]);
    }

    public function test_half_day_shortens_sla_window(): void
    {
        $response = $this->post('/yonetici/takvim/ozel-gun', ['date' => '2026-11-10', 'start' => '09:00', 'end' => '13:00', 'description' => 'Arife (yarım gün)']);
        self::assertSame(302, $response->status);

        $this->openTicket();

        // Pzt 7 sa + Sal (yarım gün) 4 sa + Çar 09–12 (3) + 13–15 (2) = 16 sa
        self::assertSame('2026-11-11 15:00', $this->kernel->ticketRepository()->findById(1)?->deadline->format('Y-m-d H:i'));
        self::assertStringContainsString('Arife (yarım gün)', $this->get('/yonetici/takvim')->body);
    }

    public function test_special_day_validation(): void
    {
        $bad = $this->post('/yonetici/takvim/ozel-gun', ['date' => '2026-11-10', 'start' => '13:00', 'end' => '09:00', 'description' => '']);
        self::assertSame(422, $bad->status);
        self::assertStringContainsString('Bitiş saati başlangıçtan sonra olmalı.', $bad->body);

        $this->post('/yonetici/takvim/ozel-gun', ['date' => '2026-11-10', 'start' => '09:00', 'end' => '13:00', 'description' => '']);
        $dup = $this->post('/yonetici/takvim/ozel-gun', ['date' => '2026-11-10', 'start' => '10:00', 'end' => '12:00', 'description' => '']);
        self::assertSame(422, $dup->status);
        self::assertStringContainsString('Bu tarih için zaten özel gün var.', $dup->body);

        $this->post('/yonetici/takvim/ozel-gun/sil', ['date' => '2026-11-10']);
        self::assertSame([], $this->kernel->workCalendarRepository()->specialDays());
    }

    public function test_saturday_special_working_day_counts(): void
    {
        $this->post('/yonetici/takvim/ozel-gun', ['date' => '2026-11-14', 'start' => '09:00', 'end' => '17:00', 'description' => 'Telafi günü']);

        self::assertSame([[540, 1020]], $this->kernel->workCalendarRepository()->getConfig()->intervalsFor(new \DateTimeImmutable('2026-11-14', new \DateTimeZone('Europe/Istanbul'))));
    }

    public function test_calendar_file_import_replaces_calendar_and_rejects_invalid_file(): void
    {
        $bad = $this->upload(['work_days' => ['1' => ['start' => '18:00', 'end' => '09:00']]]);
        self::assertSame(422, $bad->status);
        self::assertStringContainsString('Takvim dosyası geçersiz', $bad->body);
        self::assertTrue($this->kernel->workCalendarRepository()->workHours()[1]['works'], 'Hatalı dosyada eski takvim korunur.');

        $ok = $this->upload([
            'work_days' => [
                '1' => ['start' => '08:00', 'end' => '17:00', 'breaks' => [['start' => '12:00', 'end' => '13:00']]],
                '2' => ['start' => '08:00', 'end' => '17:00'],
            ],
            'holidays' => ['2026-12-31'],
            'special_days' => ['2026-12-30' => ['start' => '08:00', 'end' => '12:00', 'description' => 'Yıl sonu']],
        ]);
        self::assertSame(302, $ok->status);
        $repo = $this->kernel->workCalendarRepository();
        self::assertSame('08:00', $repo->workHours()[1]['start']);
        self::assertFalse($repo->workHours()[3]['works']);
        self::assertSame(['2026-12-31'], array_column($repo->holidays(), 'date'));
        self::assertSame('2026-12-30', $repo->specialDays()[0]['date']);
    }

    public function test_calendar_change_applies_to_open_tickets_only_when_enabled(): void
    {
        $this->openTicket();
        self::assertSame('2026-11-11 10:00', $this->kernel->ticketRepository()->findById(1)?->deadline->format('Y-m-d H:i'));

        $this->post('/yonetici/takvim/tatil', ['date' => '2026-11-10', 'description' => 'Ek tatil']);
        self::assertSame('2026-11-11 10:00', $this->kernel->ticketRepository()->findById(1)?->deadline->format('Y-m-d H:i'), 'Varsayılan: açık talepler değişmez.');
        $this->post('/yonetici/takvim/tatil/sil', ['date' => '2026-11-10']);

        self::assertSame(302, $this->post('/yonetici/takvim/kural', ['recalculate_open' => '1'])->status);
        $this->post('/yonetici/takvim/tatil', ['date' => '2026-11-10', 'description' => 'Ek tatil']);

        self::assertSame('2026-11-12 10:00', $this->kernel->ticketRepository()->findById(1)?->deadline->format('Y-m-d H:i'), 'Açık talebin kalan süresi yeni takvime göre.');
        self::assertSame('2026-11-11 10:00', $this->kernel->ticketRepository()->findById(1)?->firstDeadline()->format('Y-m-d H:i'), 'İlk SLA bitişi değişmez.');
    }

    public function test_month_view_shows_day_types(): void
    {
        $this->post('/yonetici/takvim/tatil', ['date' => '2026-11-20', 'description' => 'Kurum tatili']);
        $this->post('/yonetici/takvim/ozel-gun', ['date' => '2026-11-19', 'start' => '09:00', 'end' => '13:00', 'description' => 'Yarım gün']);

        $body = $this->get('/yonetici/takvim?ay=2026-11')->body;

        self::assertStringContainsString('Kasım 2026', $body);
        self::assertMatchesRegularExpression('/day-holiday[^>]*>\s*<span class="day-num">20</', $body);
        self::assertMatchesRegularExpression('/day-special[^>]*>\s*<span class="day-num">19</', $body);
        self::assertMatchesRegularExpression('/day-off[^>]*>\s*<span class="day-num">15</', $body, 'Pazar çalışılmaz.');
    }
}
