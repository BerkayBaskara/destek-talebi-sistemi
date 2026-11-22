<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\Clock\FixedClock;
use App\Exception\ValidationException;
use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\Json\JsonAuditLogRepository;
use App\Repository\Json\JsonSettingsRepository;
use App\Repository\Json\JsonUserRepository;
use App\Service\AuditLogger;
use App\Service\AuthService;
use App\Service\SettingsService;
use App\Service\StaffService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

/** Personel hesapları: oluşturma, aktif/pasif, kapasite. */
final class StaffServiceTest extends TestCase
{
    use TempStoreTrait;

    private JsonUserRepository $users;
    private JsonAuditLogRepository $audit;
    private StaffService $staff;
    private User $admin;

    protected function setUp(): void
    {
        $store = $this->makeStore();
        $this->users = new JsonUserRepository($store);
        $this->audit = new JsonAuditLogRepository($store);
        $logger = new AuditLogger($this->audit, new FixedClock(new DateTimeImmutable('2026-11-24 10:00')));
        $auth = new AuthService($this->users);
        $this->staff = new StaffService($this->users, $auth, new SettingsService(new JsonSettingsRepository($store), $logger), $logger);
        $this->admin = $auth->createStaff(Role::Admin, 'Selin', 'Arslan', 'Destek2026!');
    }

    public function test_create_staff_gets_P_number_and_policy(): void
    {
        $agent = $this->staff->create($this->admin, 'Zeynep', 'Kaya', 'Destek2026!');

        self::assertMatchesRegularExpression('/^P\d{6}$/', $agent->customerNo);
        self::assertSame(Role::Agent, $agent->role);
        self::assertTrue($agent->active);
        self::assertSame('create', $this->audit->latest(1)[0]->action);

        try {
            $this->staff->create($this->admin, 'Murat', 'Demir', 'zayifsifre');
            self::fail('Kurala uymayan şifre kabul edilmemeli.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('password', $e->errors());
        }
    }

    public function test_capacity_validation(): void
    {
        $agent = $this->staff->create($this->admin, 'Zeynep', 'Kaya', 'Destek2026!');

        self::assertSame(5, $this->staff->capacityOf($agent)); // boş = varsayılan
        self::assertSame(2, $this->staff->capacityOf($this->staff->setCapacity($this->admin, (int) $agent->id, 2)));
        self::assertSame(5, $this->staff->capacityOf($this->staff->setCapacity($this->admin, (int) $agent->id, null)));

        foreach ([0, -1, 101] as $bad) {
            try {
                $this->staff->setCapacity($this->admin, (int) $agent->id, $bad);
                self::fail($bad . ' kabul edilmemeliydi.');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('capacity', $e->errors());
            }
        }
    }

    public function test_parse_capacity_from_form(): void
    {
        self::assertNull(StaffService::parseCapacity(''));
        self::assertSame(3, StaffService::parseCapacity(' 3 '));
        foreach (['abc', '2.5', '-1'] as $bad) {
            try {
                StaffService::parseCapacity($bad);
                self::fail($bad . ' kabul edilmemeliydi.');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('capacity', $e->errors());
            }
        }
    }

    public function test_deactivate_and_activate_agent(): void
    {
        $agent = $this->staff->create($this->admin, 'Zeynep', 'Kaya', 'Destek2026!');

        self::assertFalse($this->staff->setActive($this->admin, (int) $agent->id, false)->active);
        self::assertFalse($this->users->findById((int) $agent->id)?->active);
        self::assertTrue($this->staff->setActive($this->admin, (int) $agent->id, true)->active);
        self::assertSame('activate', $this->audit->latest(1)[0]->action);
    }

    public function test_cannot_deactivate_admin_or_self(): void
    {
        $this->expectException(ValidationException::class);
        $this->staff->setActive($this->admin, (int) $this->admin->id, false);
    }
}
