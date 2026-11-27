<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\Notification;
use App\Model\Ticket;

/** NFR-33: büyük listelerde bir sayfada en fazla 50 kayıt; kalan kayıtlara sayfa bağlantısıyla ulaşılır. */
final class PaginationTest extends FeatureTestCase
{
    public function test_report_ticket_table_shows_fifty_per_page(): void
    {
        $this->seedCatalog();
        $admin = $this->kernel->authService()->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
        $now = $this->clock->now();
        $rows = [];
        for ($i = 1; $i <= 55; $i++) {
            $rows[] = (new Ticket($i, 99, null, 1, 'Rapor talebi ' . $i, TicketStatus::Pending, 2, 2, 0, $now, $now->modify('+1 day'), null))->toArray();
        }
        file_put_contents($this->root . '/storage/data/tickets.json', (string) json_encode($rows, JSON_UNESCAPED_UNICODE));
        $this->loginAs($admin->customerNo, self::PASSWORD);

        $first = $this->get('/yonetici/rapor')->body;
        $second = $this->get('/yonetici/rapor?sayfa=2')->body;

        self::assertSame(50, substr_count($first, 'class="row-link"'));
        self::assertSame(5, substr_count($second, 'class="row-link"'));
        self::assertStringContainsString('55 talep', $first);
    }

    public function test_notifications_show_fifty_per_page_and_older_ones_are_reachable(): void
    {
        $customerNo = $this->registerCustomer();
        $user = $this->kernel->userRepository()->findByCustomerNo($customerNo);
        self::assertNotNull($user);
        $repo = $this->kernel->notificationRepository();
        for ($i = 1; $i <= 55; $i++) {
            $repo->add(new Notification(null, (int) $user->id, 'reply', null, 'Bildirim numarası ' . $i . '.', $this->clock->now()->modify('+' . $i . ' minutes')));
        }
        $this->loginAs($customerNo, self::PASSWORD);

        $first = $this->get('/bildirimler')->body;
        $second = $this->get('/bildirimler?sayfa=2')->body;

        self::assertSame(50, substr_count($first, 'Bildirim numarası'));
        self::assertStringContainsString('Bildirim numarası 55.', $first, 'En yeni önce.');
        self::assertSame(5, substr_count($second, 'Bildirim numarası'));
        self::assertStringContainsString('Bildirim numarası 1.', $second);
    }
}
