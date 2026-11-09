<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * views/ altındaki PHP şablonlarını çalıştırıp HTML üretir.
 * Şablonlar yalnız gösterim yapar; çıktıyı her zaman e() ile kaçırır (XSS önlemi).
 */
final class View
{
    /** @var array<string, mixed> Tüm şablonlara verilen ortak değişkenler. */
    private array $shared = [];

    public function __construct(private readonly string $viewsDir)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = $this->renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($layout, $data + ['content' => $content]);
    }

    /**
     * Şablon içinden başka bir parça (partial) çağırmak için.
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        return $this->renderFile($template, $data);
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        $file = $this->viewsDir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Görünüm bulunamadı: ' . $template);
        }

        $vars = $data + $this->shared + ['view' => $this];
        $render = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__file;

                return (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
        };

        return $render($file, $vars);
    }
}
