<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\Clock\FixedClock;
use App\Exception\ValidationException;
use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\Json\JsonAttachmentRepository;
use App\Service\AttachmentService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

/** FR-02, NFR-03, NFR-08: güvenli ek dosya yükleme. */
final class AttachmentServiceTest extends TestCase
{
    use TempStoreTrait;

    private string $work;
    private string $uploads;
    private JsonAttachmentRepository $repo;
    private User $user;

    protected function setUp(): void
    {
        $this->work = sys_get_temp_dir() . '/att_' . bin2hex(random_bytes(4));
        $this->uploads = $this->work . '/uploads';
        mkdir($this->uploads, 0775, true);
        $this->repo = new JsonAttachmentRepository($this->makeStore());
        $this->user = new User(5, 'M100001', 'x', Role::Customer, 'Ahmet', 'Yılmaz', 'S', 'C');
    }

    protected function tearDown(): void
    {
        foreach ([$this->uploads, $this->work] as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                is_file($file) && unlink($file);
            }
        }
        rmdir($this->uploads);
        rmdir($this->work);
        // TempStoreTrait temizliği
        if (isset($this->tempDir) && is_dir($this->tempDir)) {
            foreach (glob($this->tempDir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->tempDir);
        }
    }

    /** @param array<string, int> $settings */
    private function service(array $settings = []): AttachmentService
    {
        return new AttachmentService(
            $this->repo,
            $this->uploads,
            $settings + ['max_upload_bytes' => 5 * 1024 * 1024, 'max_video_bytes' => 50 * 1024 * 1024, 'max_files_per_ticket' => 3],
            new FixedClock(new DateTimeImmutable('2026-11-09 10:00')),
            static fn (string $from, string $to): bool => rename($from, $to), // testte move_uploaded_file yerine
        );
    }

    /** @return array{name: string, type: string, tmp_name: string, error: int, size: int} */
    private function file(string $name, string $content): array
    {
        $tmp = $this->work . '/' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, $content);

        return ['name' => $name, 'type' => 'application/octet-stream', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
    }

    private static function image(string $type, int $w = 40, int $h = 30): string
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        $type === 'png' ? imagepng($img) : imagejpeg($img);

        return (string) ob_get_clean();
    }

    public function test_accepts_jpg_png_pdf(): void
    {
        $service = $this->service();

        self::assertSame('image/jpeg', $service->store(1, $this->user, $this->file('foto.jpg', self::image('jpg')))->fileType);
        self::assertSame('image/png', $service->store(1, $this->user, $this->file('ekran.png', self::image('png')))->fileType);
        self::assertSame('application/pdf', $service->store(1, $this->user, $this->file('fatura.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n"))->fileType);
        self::assertCount(3, $this->repo->findByTicket(1));
    }

    public function test_rejects_php_disguised_as_jpg(): void
    {
        try {
            $this->service()->store(1, $this->user, $this->file('sahte.jpg', '<?php echo "hack"; ?>'));
            self::fail('Sahte dosya reddedilmeliydi.');
        } catch (ValidationException $e) {
            self::assertSame('Yalnız JPG, PNG, PDF, MP4 ve WebM dosyaları yüklenebilir.', $e->errors()['ekler']);
        }
        self::assertSame([], glob($this->uploads . '/*'));
    }

    public function test_rejects_oversized_image(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Dosya en fazla');
        $this->service(['max_upload_bytes' => 10])->store(1, $this->user, $this->file('foto.png', self::image('png')));
    }

    public function test_stored_name_is_random_and_has_correct_extension(): void
    {
        $attachment = $this->service()->store(1, $this->user, $this->file('../../etc/kotu ad.PNG', self::image('png')));

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.png$/', $attachment->filePath);
        self::assertFileExists($this->uploads . '/' . $attachment->filePath);
        self::assertSame($this->uploads . DIRECTORY_SEPARATOR . $attachment->filePath, $this->service()->absolutePath($attachment));
    }

    public function test_large_png_is_downscaled_to_1920x1080(): void
    {
        $attachment = $this->service(['max_upload_bytes' => 50 * 1024 * 1024])->store(1, $this->user, $this->file('buyuk.png', self::image('png', 3000, 2000)));

        [$w, $h] = (array) getimagesize($this->uploads . '/' . $attachment->filePath);
        self::assertLessThanOrEqual(1920, $w);
        self::assertLessThanOrEqual(1080, $h);
        self::assertSame(1620, $w, 'Oran korunmalı: 3000x2000 → 1620x1080');
        self::assertSame(1080, $h);
    }

    public function test_rejects_image_with_too_many_pixels_before_decoding(): void
    {
        // Yalnız JPEG başlığı (SOF0): 6000x4500 = 27 milyon piksel diyor. Gerçekten açılsa GD belleği tüketirdi
        // (bu testin ilk hali tam görseli üretmeye çalışınca PHPUnit'in kendisi bellekten düştü).
        $jpeg = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            . "\xFF\xC0\x00\x11\x08" . pack('nn', 4500, 6000) . "\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01"
            . "\xFF\xD9";
        try {
            $this->service(['max_upload_bytes' => 50 * 1024 * 1024])->validate($this->file('dev.jpg', $jpeg));
            self::fail('Çok yüksek çözünürlüklü görsel reddedilmeliydi.');
        } catch (ValidationException $e) {
            self::assertSame('Görselin çözünürlüğü çok yüksek (en fazla 24 milyon piksel).', $e->errors()['ekler']);
        }
    }

    private static function pngHeader(int $width, int $height): string
    {
        $ihdr = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));
    }

    public function test_png_has_lower_pixel_limit_than_jpeg(): void
    {
        // PNG açmak piksel başına ~7 bayt ister (JPEG ~5): 5500x4000 = 22M px PNG 128 MB'ı aşıyordu.
        $service = $this->service(['max_upload_bytes' => 50 * 1024 * 1024]);
        try {
            $service->validate($this->file('buyuk.png', self::pngHeader(5500, 4000)));
            self::fail('22 milyon piksellik PNG reddedilmeliydi.');
        } catch (ValidationException $e) {
            self::assertSame('Görselin çözünürlüğü çok yüksek (en fazla 13 milyon piksel).', $e->errors()['ekler']);
        }

        self::assertSame('png', $service->validate($this->file('telefon.png', self::pngHeader(4032, 3024))), '12 MP telefon görüntüsü kabul edilmeli.');
    }

    public function test_accepts_mp4_and_webm_video(): void
    {
        $mp4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom" . str_repeat("\x00", 64);
        $webm = "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF2\x81\x04\x42\xF3\x81\x08\x42\x82\x84webm\x42\x87\x81\x04\x42\x85\x81\x02" . str_repeat("\x00", 64);

        self::assertSame('mp4', $this->service()->validate($this->file('kayit.mp4', $mp4)));
        self::assertSame('webm', $this->service()->validate($this->file('kayit.webm', $webm)));
    }

    public function test_video_over_video_limit_rejected(): void
    {
        $mp4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom" . str_repeat("\x00", 200);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Video en fazla');
        $this->service(['max_video_bytes' => 100])->validate($this->file('kayit.mp4', $mp4));
    }

    public function test_php_ini_size_error_says_file_is_too_big(): void
    {
        $file = $this->file('video.mp4', 'x');
        $file['error'] = UPLOAD_ERR_INI_SIZE;

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Dosya, sunucunun izin verdiği boyutu aşıyor');
        $this->service()->validate($file);
    }

    public function test_upload_error_code_message(): void
    {
        $file = $this->file('foto.png', self::image('png'));
        $file['error'] = UPLOAD_ERR_PARTIAL;

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Dosya yüklenemedi');
        $this->service()->store(1, $this->user, $file);
    }

    public function test_validate_many_checks_count_and_skips_empty_slots(): void
    {
        $service = $this->service(['max_files_per_ticket' => 2]);
        $empty = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];

        self::assertCount(1, $service->validateMany([$this->file('a.png', self::image('png')), $empty]));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('En fazla 2 dosya');
        $service->validateMany([$this->file('a.png', self::image('png')), $this->file('b.png', self::image('png')), $this->file('c.png', self::image('png'))]);
    }

    public function test_normalize_files_array(): void
    {
        $files = AttachmentService::normalizeFiles([
            'name' => ['a.png', 'b.pdf'],
            'type' => ['image/png', 'application/pdf'],
            'tmp_name' => ['/tmp/a', '/tmp/b'],
            'error' => [0, 0],
            'size' => [10, 20],
        ]);

        self::assertSame('b.pdf', $files[1]['name']);
        self::assertSame(20, $files[1]['size']);
        self::assertSame([], AttachmentService::normalizeFiles(null));
    }
}
