<?php
/** @var list<\App\Model\AuditLog> $logs */
/** @var array<int, string> $names */
$actions = [
    'create' => 'Ekleme', 'update' => 'Güncelleme', 'deactivate' => 'Pasif yapma', 'activate' => 'Aktif yapma',
    'delete_holiday' => 'Tatil silme', 'upload' => 'Yükleme',
];
$entities = [
    'setting' => 'Site ayarı', 'logo' => 'Logo', 'category' => 'Kategori', 'priority' => 'Öncelik', 'sla_policy' => 'SLA süresi',
    'work_hours' => 'Çalışma saatleri', 'holiday' => 'Tatil', 'staff' => 'Personel',
];
$show = static function (?array $value): string {
    if ($value === null) {
        return '-';
    }
    $text = (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return mb_strlen($text) > 300 ? mb_substr($text, 0, 300) . '…' : $text;
};
?>
<p><a href="/yonetici/ayarlar">← Sistem Yapılandırması</a></p>
<section class="card">
    <h1>Denetim Kaydı</h1>
    <p class="muted">Son 100 değişiklik, en yenisi üstte. Kayıtlar değiştirilemez ve silinemez.</p>
    <?php if ($logs === []): ?>
        <p class="muted">Henüz değişiklik yapılmadı.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Yönetici</th><th>İşlem</th><th>Kayıt</th><th>Eski değer</th><th>Yeni değer</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td data-label="Tarih"><?= e($log->createdAt->format('d.m.Y H:i')) ?></td>
                        <td data-label="Yönetici"><?= e($names[$log->userId] ?? '-') ?></td>
                        <td data-label="İşlem"><?= e($actions[$log->action] ?? $log->action) ?></td>
                        <td data-label="Kayıt"><?= e(($entities[$log->entity] ?? $log->entity) . ($log->entityId !== null ? ' #' . $log->entityId : '')) ?></td>
                        <td class="code-cell" data-label="Eski değer"><?= e($show($log->oldValue)) ?></td>
                        <td class="code-cell" data-label="Yeni değer"><?= e($show($log->newValue)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
