<?php
/** @var array{items: list<\App\Model\Ticket>, total: int, page: int, pages: int} $result */
/** @var list<\App\Model\SlaStatus> $slas */
/** @var array<int, string> $names */
/** @var list<\App\Model\User> $agents */
/** @var array<string, string> $filters */
/** @var \App\Core\View $view */

use App\Model\Enum\Priority;
use App\Model\Enum\TicketStatus;

?>
<section class="card">
    <div class="card-head">
        <h1>Tüm Talepler</h1>
        <a class="btn btn-light" href="/yonetici/ozet">Özet rapor</a>
    </div>

    <form class="filters" method="get" action="/yonetici">
        <div>
            <label for="durum">Durum</label>
            <select id="durum" name="durum">
                <option value="">Tümü</option>
                <?php foreach (TicketStatus::cases() as $case): ?>
                    <option value="<?= e($case->value) ?>"<?= $filters['durum'] === $case->value ? ' selected' : '' ?>><?= e($case->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="oncelik">Öncelik</label>
            <select id="oncelik" name="oncelik">
                <option value="">Tümü</option>
                <?php foreach (Priority::cases() as $case): ?>
                    <option value="<?= e($case->value) ?>"<?= $filters['oncelik'] === (string) $case->value ? ' selected' : '' ?>><?= e($case->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="personel">Personel</label>
            <select id="personel" name="personel">
                <option value="">Tümü</option>
                <?php foreach ($agents as $agent): ?>
                    <option value="<?= e($agent->id) ?>"<?= $filters['personel'] === (string) $agent->id ? ' selected' : '' ?>><?= e($agent->fullName()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grow">
            <label for="q">Ara</label>
            <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Konu veya talep no (#12)">
        </div>
        <div><button type="submit" class="btn btn-light">Filtrele</button></div>
    </form>

    <?php if ($result['items'] === []): ?>
        <p class="muted">Bu filtreye uyan talep yok.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>ID</th><th>Konu</th><th>Müşteri</th><th>Personel</th><th>Öncelik</th><th>Durum</th><th>SLA</th></tr></thead>
            <tbody>
            <?php foreach ($result['items'] as $i => $ticket): ?>
                <tr>
                    <td data-label="ID">#<?= e($ticket->id) ?></td>
                    <td data-label="Konu"><a href="/talepler/<?= e($ticket->id) ?>"><?= e($ticket->title) ?></a></td>
                    <td data-label="Müşteri"><?= e($names[$ticket->creatorId] ?? '-') ?></td>
                    <td data-label="Personel"><?= e($ticket->agentId !== null ? ($names[$ticket->agentId] ?? '-') : 'Havuzda') ?></td>
                    <td data-label="Öncelik"><?= e($ticket->priority->label()) ?></td>
                    <td data-label="Durum"><?= $view->partial('partials/status_badge', ['status' => $ticket->status]) ?></td>
                    <td data-label="SLA"><?= $view->partial('partials/sla_badge', ['sla' => $slas[$i], 'ticketId' => $ticket->id]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted"><?= e($result['total']) ?> talep</p>
    <?php endif; ?>

    <?= $view->partial('partials/pagination', ['page' => $result['page'], 'pages' => $result['pages'], 'baseUrl' => '/yonetici', 'params' => $filters]) ?>
</section>
<script src="/assets/sla.js" defer></script>
