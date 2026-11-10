<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Model\CalendarConfig;
use App\Repository\CalendarRepositoryInterface;
use InvalidArgumentException;
use JsonException;

/**
 * Takvimi storage/config/calendar.json dosyasından okur.
 * Dosya yoksa, bozuksa veya şemaya uymuyorsa sistem çökmez: kod içindeki varsayılan
 * mesaiye döner ve durumu hata günlüğüne yazar (NFR-07).
 */
final class JsonCalendarRepository implements CalendarRepositoryInterface
{
    /** @var callable(string): void */
    private $logger;

    private ?CalendarConfig $config = null;

    /** @param (callable(string): void)|null $logger */
    public function __construct(private readonly string $path, ?callable $logger = null)
    {
        $this->logger = $logger ?? static function (string $message): void {
            error_log($message);
        };
    }

    public function getConfig(): CalendarConfig
    {
        return $this->config ??= $this->load();
    }

    public function getBusinessHours(): array
    {
        return $this->getConfig()->workDays;
    }

    public function getHolidays(): array
    {
        $holidays = array_keys($this->getConfig()->holidays);
        sort($holidays);

        return $holidays;
    }

    private function load(): CalendarConfig
    {
        try {
            if (!is_file($this->path)) {
                throw new InvalidArgumentException('dosya bulunamadı');
            }
            $content = (string) file_get_contents($this->path);
            $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content; // BOM (Notepad/PowerShell)
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new InvalidArgumentException('kök bir nesne olmalı');
            }

            return CalendarConfig::fromArray($data);
        } catch (JsonException | InvalidArgumentException $e) {
            ($this->logger)('Takvim dosyası okunamadı, varsayılan mesai kullanılıyor: ' . $e->getMessage());

            return CalendarConfig::default();
        }
    }
}
