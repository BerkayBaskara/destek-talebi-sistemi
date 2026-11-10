<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Exception\ValidationException;
use App\Model\Attachment;
use App\Model\User;
use App\Repository\AttachmentRepositoryInterface;
use GdImage;

/**
 * FR-02: güvenli ek dosya yükleme.
 * - Tür, dosya adına/uzantısına göre değil İÇERİĞE göre (finfo) belirlenir: .jpg uzantılı PHP kodu reddedilir.
 * - Dosya rastgele adla, tarayıcının erişemediği storage/uploads klasörüne kaydedilir (NFR-03).
 * - Büyük JPG/PNG görseller oranı korunarak en fazla 1920x1080'e küçültülür (NFR-08).
 */
final class AttachmentService
{
    /** İzinli MIME türleri => kaydedilecek uzantı. */
    public const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
    ];

    private const MAX_WIDTH = 1920;
    private const MAX_HEIGHT = 1080;

    /**
     * GD görseli açarken JPEG için piksel başına ~5, PNG için ~7 bayt kullanır; bu sınırların üstü
     * 128 MB bellek sınırını aşabilir. (13 milyon piksel 12 MP'lik 4032x3024 telefon fotoğrafını kapsar.)
     */
    private const MAX_PIXELS = ['jpg' => 24_000_000, 'png' => 13_000_000];

    /** @var callable(string, string): bool */
    private $mover;

    /**
     * @param array<string, int> $settings max_upload_bytes, max_video_bytes, max_files_per_ticket
     * @param (callable(string, string): bool)|null $mover dosyayı taşıyan fonksiyon (varsayılan move_uploaded_file)
     */
    public function __construct(
        private readonly AttachmentRepositoryInterface $repo,
        private readonly string $uploadDir,
        private readonly array $settings,
        private readonly ClockInterface $clock,
        ?callable $mover = null,
    ) {
        $this->mover = $mover ?? static fn (string $from, string $to): bool => move_uploaded_file($from, $to);
    }

    public static function fromSettingsFile(AttachmentRepositoryInterface $repo, string $uploadDir, string $settingsPath, ClockInterface $clock): self
    {
        $data = is_file($settingsPath) ? json_decode((string) file_get_contents($settingsPath), true) : null;
        $settings = [
            'max_upload_bytes' => 5 * 1024 * 1024,
            'max_video_bytes' => 50 * 1024 * 1024,
            'max_files_per_ticket' => 3,
        ];
        foreach (array_keys($settings) as $key) {
            if (is_array($data) && is_int($data[$key] ?? null) && $data[$key] > 0) {
                $settings[$key] = $data[$key];
            }
        }

        return new self($repo, $uploadDir, $settings, $clock);
    }

    /** PHP'nin çoklu dosya dizisini ($_FILES['ekler']) dosya başına bir diziye çevirir. */
    /**
     * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    public static function normalizeFiles(mixed $files): array
    {
        if (!is_array($files) || !is_array($files['name'] ?? null)) {
            return [];
        }

        $result = [];
        foreach (array_keys($files['name']) as $i) {
            $result[] = [
                'name' => (string) ($files['name'][$i] ?? ''),
                'type' => (string) ($files['type'][$i] ?? ''),
                'tmp_name' => (string) ($files['tmp_name'][$i] ?? ''),
                'error' => (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($files['size'][$i] ?? 0),
            ];
        }

        return $result;
    }

    public function maxFiles(): int
    {
        return $this->settings['max_files_per_ticket'] ?? 3;
    }

    /**
     * Boş dosya alanlarını atar, sayıyı ve her dosyayı doğrular. Talep kaydedilmeden ÖNCE çağrılır,
     * böylece geçersiz bir dosya yarım kalmış talep bırakmaz.
     *
     * @param list<array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
     * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    public function validateMany(array $files): array
    {
        $files = array_values(array_filter($files, static fn (array $f): bool => $f['error'] !== UPLOAD_ERR_NO_FILE));
        if (count($files) > $this->maxFiles()) {
            throw new ValidationException(['ekler' => sprintf('En fazla %d dosya ekleyebilirsiniz.', $this->maxFiles())]);
        }
        foreach ($files as $file) {
            $this->validate($file);
        }

        return $files;
    }

    /**
     * Tek dosyayı doğrular ve kaydedilecek uzantıyı döner.
     *
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     */
    public function validate(array $file): string
    {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            // php.ini'deki upload_max_filesize aşıldı: tekrar denemek işe yaramaz, kullanıcıya doğrusunu söyle.
            $mb = max(1, (int) round(self::iniBytes((string) ini_get('upload_max_filesize')) / 1024 / 1024));
            throw new ValidationException(['ekler' => "Dosya, sunucunun izin verdiği boyutu aşıyor (en fazla {$mb} MB): " . $file['name']]);
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_file($file['tmp_name'])) {
            throw new ValidationException(['ekler' => 'Dosya yüklenemedi (' . $file['name'] . '). Lütfen tekrar deneyin.']);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extension = self::ALLOWED[$mime] ?? null;
        if ($extension === null) {
            throw new ValidationException(['ekler' => 'Yalnız JPG, PNG, PDF, MP4 ve WebM dosyaları yüklenebilir.']);
        }

        $isVideo = str_starts_with($mime, 'video/');
        $limit = $isVideo ? ($this->settings['max_video_bytes'] ?? 0) : ($this->settings['max_upload_bytes'] ?? 0);
        $size = (int) filesize($file['tmp_name']);
        if ($size > $limit) {
            $mb = max(1, (int) round($limit / 1024 / 1024));
            throw new ValidationException(['ekler' => $isVideo ? "Video en fazla {$mb} MB olabilir." : "Dosya en fazla {$mb} MB olabilir."]);
        }

        // Görsel çözünürlüğü dosyayı açmadan, yalnız başlıktan okunur; talep kaydedilmeden önce reddedilir.
        if (isset(self::MAX_PIXELS[$extension])) {
            $limit = self::MAX_PIXELS[$extension];
            $info = getimagesize($file['tmp_name']);
            if ($info === false || $info[0] * $info[1] > $limit) {
                throw new ValidationException(['ekler' => sprintf('Görselin çözünürlüğü çok yüksek (en fazla %d milyon piksel).', intdiv($limit, 1_000_000))]);
            }
        }

        return $extension;
    }

    /**
     * Dosyayı doğrular, rastgele adla kaydeder, gerekiyorsa küçültür ve kaydını oluşturur.
     *
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     */
    public function store(int $ticketId, User $user, array $file): Attachment
    {
        $extension = $this->validate($file);
        $mime = (string) array_search($extension, self::ALLOWED, true);
        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        $target = $this->uploadDir . DIRECTORY_SEPARATOR . $name;

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true);
        }
        if (!($this->mover)($file['tmp_name'], $target)) {
            throw new ValidationException(['ekler' => 'Dosya kaydedilemedi. Lütfen tekrar deneyin.']);
        }
        if ($extension === 'jpg' || $extension === 'png') {
            $this->downscale($target, $extension);
        }

        return $this->repo->save(new Attachment(null, $ticketId, (int) $user->id, $name, $mime, $this->clock->now()));
    }

    /** php.ini boyut değeri ("2M", "8M", "1G") → bayt. */
    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }

    public function absolutePath(Attachment $attachment): string
    {
        return $this->uploadDir . DIRECTORY_SEPARATOR . basename($attachment->filePath);
    }

    /** NFR-08: 1920x1080'den büyük görseli oranı koruyarak küçültür. */
    private function downscale(string $path, string $extension): void
    {
        $size = getimagesize($path);
        if ($size === false) {
            return;
        }
        [$width, $height] = $size;
        if ($width <= self::MAX_WIDTH && $height <= self::MAX_HEIGHT) {
            return;
        }

        $ratio = min(self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);
        $image = $extension === 'png' ? imagecreatefrompng($path) : imagecreatefromjpeg($path);
        if (!$image instanceof GdImage) {
            return;
        }
        $scaled = imagescale($image, (int) round($width * $ratio), (int) round($height * $ratio));
        if ($scaled instanceof GdImage) {
            $extension === 'png' ? imagepng($scaled, $path) : imagejpeg($scaled, $path, 85);
        }
    }
}
