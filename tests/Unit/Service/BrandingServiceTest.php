<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\Clock\FixedClock;
use App\Exception\ValidationException;
use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\Json\JsonAuditLogRepository;
use App\Repository\Json\JsonSettingsRepository;
use App\Service\AuditLogger;
use App\Service\BrandingService;
use App\Service\SettingsService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

/** Site logosu yükleme: türü içeriğe göre denetlenir, boyut sınırı uygulanır, eski logo ancak başarıdan sonra silinir. */
final class BrandingServiceTest extends TestCase
{
    use TempStoreTrait {
        tearDown as private removeStore;
    }

    /** 1×1 piksel geçerli PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private string $brandingDir;
    private string $uploadDir;
    private SettingsService $settings;
    private BrandingService $branding;
    private User $admin;

    protected function setUp(): void
    {
        $store = $this->makeStore();
        $this->brandingDir = $this->tempDir . '_branding';
        $this->uploadDir = $this->tempDir . '_upload';
        mkdir($this->uploadDir, 0775, true);
        $audit = new AuditLogger(new JsonAuditLogRepository($store), new FixedClock(new DateTimeImmutable('2026-11-24 10:00')));
        $this->settings = new SettingsService(new JsonSettingsRepository($store), $audit);
        // Testte dosyalar PHP yüklemesi değil; taşıma işlemi rename ile yapılır.
        $this->branding = new BrandingService($this->brandingDir, $this->settings, $audit, static fn (string $from, string $to): bool => rename($from, $to));
        $this->admin = new User(1, 'Y000001', 'x', Role::Admin, 'Selin', 'Arslan', 'S?', 'x');
    }

    protected function tearDown(): void
    {
        foreach ([$this->brandingDir, $this->uploadDir] as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
        $this->removeStore();
    }

    /** @return array{name: string, type: string, tmp_name: string, error: int, size: int} */
    private function upload(string $name, string $content): array
    {
        $path = $this->uploadDir . '/' . bin2hex(random_bytes(4));
        file_put_contents($path, $content);

        return ['name' => $name, 'type' => 'image/png', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
    }

    private function logoFile(): string
    {
        return $this->brandingDir . '/' . basename($this->settings->string('site.logo_path'));
    }

    public function test_rejects_fake_png_and_large_file_and_keeps_old_logo(): void
    {
        $this->branding->storeLogo($this->admin, $this->upload('logo.png', (string) base64_decode(self::PNG)));
        $oldPath = $this->settings->string('site.logo_path');

        foreach ([
            ['logo.png', '<?php echo "zararlı"; ?>', 'Logo yalnız PNG ya da JPG olabilir.'],
            ['buyuk.png', (string) base64_decode(self::PNG) . str_repeat("\0", 1_048_576), 'Logo en fazla 1 MB olabilir.'],
        ] as [$name, $content, $message]) {
            try {
                $this->branding->storeLogo($this->admin, $this->upload($name, $content));
                self::fail($name . ' kabul edilmemeliydi.');
            } catch (ValidationException $e) {
                self::assertSame(['logo' => $message], $e->errors());
            }
        }

        self::assertSame($oldPath, $this->settings->string('site.logo_path'));
        self::assertFileExists($this->logoFile());
        self::assertCount(1, glob($this->brandingDir . '/*') ?: []);
    }

    public function test_missing_file_is_reported(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Bir logo dosyası seçin.');
        $this->branding->storeLogo($this->admin, ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]);
    }

    public function test_new_logo_replaces_old_file(): void
    {
        $first = $this->branding->storeLogo($this->admin, $this->upload('a.png', (string) base64_decode(self::PNG)));
        $firstFile = $this->logoFile();

        $second = $this->branding->storeLogo($this->admin, $this->upload('b.png', (string) base64_decode(self::PNG)));

        self::assertNotSame($first, $second);
        self::assertMatchesRegularExpression('#^/branding/logo-[0-9a-f]{16}\.png$#', $second);
        self::assertSame($second, $this->settings->string('site.logo_path'));
        self::assertFileDoesNotExist($firstFile);
        self::assertFileExists($this->logoFile());
    }
}
