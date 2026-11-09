<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Clock\FixedClock;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\ArraySession;
use App\Kernel;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Uygulamayı tarayıcı olmadan, Kernel'e doğrudan istek göndererek test eder.
 * Her test geçici bir proje kökü (views + storage/config kopyası) ve boş veriyle başlar.
 */
abstract class FeatureTestCase extends TestCase
{
    protected string $root;
    protected ArraySession $session;
    protected FixedClock $clock;
    protected Kernel $kernel;

    protected function setUp(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $this->root = sys_get_temp_dir() . '/ft_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/storage/data', 0775, true);
        mkdir($this->root . '/storage/uploads', 0775, true);
        self::copyDir($projectRoot . '/views', $this->root . '/views');
        if (is_dir($projectRoot . '/storage/config')) {
            self::copyDir($projectRoot . '/storage/config', $this->root . '/storage/config');
        }

        $this->clock = new FixedClock(new DateTimeImmutable('2026-11-09 10:00', new DateTimeZone('Europe/Istanbul')));
        $this->session = new ArraySession();
        $this->bootKernel();
    }

    protected function tearDown(): void
    {
        self::removeDir($this->root);
    }

    /** Aynı kök, saat ve oturumla Kernel'i yeniden kurar (yeni bir HTTP isteği gibi). */
    protected function bootKernel(): void
    {
        $this->kernel = new Kernel($this->root, $this->clock, $this->session);
    }

    protected function get(string $uri): Response
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

        return $this->kernel->handle(new Request('GET', $path, $query));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     */
    protected function post(string $path, array $data = [], array $files = []): Response
    {
        $data['_csrf'] = $this->kernel->csrf()->token();

        return $this->kernel->handle(new Request('POST', $path, [], $data, $files));
    }

    protected const PASSWORD = 'gizliSifre1';

    /** Yeni müşteri kaydeder, müşteri numarasını döner (şifre: self::PASSWORD). */
    protected function registerCustomer(string $name = 'Ahmet'): string
    {
        return $this->kernel->authService()
            ->register($name, 'Yılmaz', self::PASSWORD, \App\Service\AuthService::SECRET_QUESTIONS[0], 'Pamuk')
            ->customerNo;
    }

    /** Örnek kategorileri ve SLA kurallarını yükler (1 Donanım, 2 Yazılım, 3 Ağ / Erişim, 4 Hesap). */
    protected function seedCatalog(): void
    {
        (new \App\Seed\DemoSeeder($this->kernel, $this->clock))->seedCatalog();
    }

    protected function loginAs(string $customerNo, string $password): void
    {
        $response = $this->post('/giris', ['customer_no' => $customerNo, 'password' => $password]);
        self::assertSame(302, $response->status, 'Giriş başarısız: ' . $customerNo);
    }

    /** @param array<string, mixed> $data */
    protected function postWithoutCsrf(string $path, array $data = []): Response
    {
        return $this->kernel->handle(new Request('POST', $path, [], $data));
    }

    /** @param array<string, mixed> $json */
    protected function postJson(string $path, array $json, bool $withCsrf = true): Response
    {
        $headers = $withCsrf ? ['X-CSRF-Token' => $this->kernel->csrf()->token()] : [];

        return $this->kernel->handle(new Request('POST', $path, [], [], [], $headers, (string) json_encode($json)));
    }

    private static function copyDir(string $from, string $to): void
    {
        mkdir($to, 0775, true);
        foreach (scandir($from) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $src = $from . '/' . $item;
            is_dir($src) ? self::copyDir($src, $to . '/' . $item) : copy($src, $to . '/' . $item);
        }
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? self::removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
