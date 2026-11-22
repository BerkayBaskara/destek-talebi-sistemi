<?php
/** @var array<string, mixed> $filters */
/** @var list<\App\Model\Ticket> $tickets */
/** @var int $total */
/** @var array<string, int> $byStatus */
/** @var list<array{agent: \App\Model\User, total: int, resolved: int, metPercent: ?int, avgMinutes: ?int, score: int}> $perAgent */
/** @var list<\App\Model\Category> $categories */
/** @var list<\App\Model\Priority> $priorities */
/** @var list<\App\Model\User> $agents */
/** @var array<int, string> $names */
/** @var callable(int): string $format */
/** @var string $query */
/** @var \App\Core\View $view */

use App\Model\Enum\TicketStatus;

$f = static fn (string $key): string => is_string($filters[$key] ?? null) ? $filters[$key] : '';
?>
<section class="card">
    <div class="card-head">
        <h1>Ayrıntılı Rapor</h1>
        <a class="btn btn-light" href="/yonetici/rapor.csv<?= $query !== '' ? '?' . e($query) : '' ?>">CSV indir</a>
    </div>
    <form class="filters" method="get" action="/yonetici/rapor">
        <div><label for="baslangic">Başlangıç</label><input type="date" id="baslangic" name="baslangic" value="<?= e($f('baslangic')) ?>"></div>
        <div><label for="bitis">Bitiş</label><input type="date" id="bitis" name="bitis" value="<?= e($f('bitis')) ?>"></div>
        <div>
            <label for="durum">Durum</label>
            <select id="durum" name="durum"><option value="">Tümü</option>
                <?php foreach (TicketStatus::cases() as $case): ?>
                    <option value="<?= e($case->value) ?>"<?= $f('durum') === $case->value ? ' selected' : '' ?>><?= e($case->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="oncelik">Öncelik</label>
            <select id="oncelik" name="oncelik"><option value="">Tümü</option>
                <?php foreach ($priorities as $p): ?>
                    <option value="<?= e($p->id) ?>"<?= $f('oncelik') === (string) $p->id ? ' selected' : '' ?>><?= e($p->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="kategori">Kategori</label>
            <select id="kategori" name="kategori"><option value="">Tümü</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= e($c->id) ?>"<?= $f('kategori') === (string) $c->id ? ' selected' : '' ?>><?= e($c->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="personel">Personel</label>
            <select id="personel" name="personel"><option value="">Tümü</option>
                <?php foreach ($agents as $a): ?>
                    <option value="<?= e($a->id) ?>"<?= $f('personel') === (string) $a->id ? ' selected' : '' ?>><?= e($a->fullName()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><button type="submit" class="btn btn-light">Süz</button></div>
    </form>
    <div class="stats">
        <div class="stat"><div class="muted">Toplam</div><div class="stat-value"><?= e($total) ?></div></div>
        <?php foreach ($byStatus as $label => $count): ?>
            <div class="stat"><div class="muted"><?= e($label) ?></div><div class="stat-value"><?= e($count) ?></div></div>
        <?php endforeach; ?>
    </div>
</section>
<section class="card">
    <h2>Personel Performansı</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Personel</th><th>Talep</th><th>Çözülen</th><th>SLA uyumu</th><th>Ortalama çözüm süresi</th><th>Performans puanı</th></tr></thead>
            <tbody>
            <?php foreach ($perAgent as $row): ?>
                <tr>
                    <td data-label="Personel"><?= e($row['agent']->fullName()) ?></td>
                    <td data-label="Talep"><?= e($row['total']) ?></td>
                    <td data-label="Çözülen"><?= e($row['resolved']) ?></td>
                    <td data-label="SLA uyumu"><?= $row['metPercent'] !== null ? '%' . e($row['metPercent']) : '-' ?></td>
                    <td data-label="Ortalama çözüm"><?= $row['avgMinutes'] !== null ? e($format($row['avgMinutes'])) : '-' ?></td>
                    <td data-label="Performans puanı"><?= e($row['score']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="card">
    <h2>Talepler <span class="muted">(en fazla 200 gösterilir; tamamı CSV'de)</span></h2>
    <?php if ($tickets === []): ?>
        <p class="muted">Bu süzgece uyan talep yok.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Kod</th><th>Konu</th><th>Durum</th><th>Personel</th><th>Açılış</th></tr></thead>
                <tbody>
                <?php foreach ($tickets as $t): ?>
                    <tr class="row-link" data-href="/talepler/<?= e($t->id) ?>">
                        <td data-label="Kod"><?= e($t->displayCode()) ?></td>
                        <td data-label="Konu"><a href="/talepler/<?= e($t->id) ?>"><?= e($t->title) ?></a></td>
                        <td data-label="Durum"><?= $view->partial('partials/status_badge', ['status' => $t->status]) ?></td>
                        <td data-label="Personel"><?= e($t->agentId !== null ? ($names[$t->agentId] ?? '-') : 'Görev Havuzu') ?></td>
                        <td data-label="Açılış"><?= e($t->createdAt->format('d.m.Y H:i')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
