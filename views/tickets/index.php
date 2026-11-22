<?php
/** @var array{items: list<\App\Model\Ticket>, total: int, page: int, pages: int} $result */
/** @var \App\Model\Enum\TicketStatus|null $status */
/** @var string $query */
/** @var \App\Core\View $view */

use App\Model\Enum\TicketStatus;

?>
<section class="card">
    <div class="card-head">
        <h1>Destek Taleplerim</h1>
        <a class="btn btn-new" href="/talepler/yeni">+ Yeni Talep Aç</a>
    </div>

    <form class="filters" method="get" action="/talepler">
        <div>
            <label for="durum">Durum</label>
            <select id="durum" name="durum">
                <option value="">Tümü</option>
                <?php foreach (TicketStatus::cases() as $case): ?>
                    <option value="<?= e($case->value) ?>"<?= $status === $case ? ' selected' : '' ?>><?= e($case->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grow">
            <label for="q">Ara</label>
            <input type="search" id="q" name="q" value="<?= e($query) ?>" placeholder="Konu başlığı ya da talep numarası giriniz">
        </div>
        <div><button type="submit" class="btn btn-light">Ara</button></div>
    </form>

    <?php if ($result['items'] === []): ?>
        <p class="muted">Gösterilecek talep yok.</p>
    <?php else: ?>
        <table class="table">
            <thead>
            <tr><th>Talep No</th><th>Konu Başlığı</th><th>Durum</th><th>Tarih</th></tr>
            </thead>
            <tbody>
            <?php foreach ($result['items'] as $ticket): ?>
                <tr class="row-link" data-href="/talepler/<?= e($ticket->id) ?>">
                    <td data-label="Talep No">#<?= e($ticket->id) ?></td>
                    <td data-label="Konu"><a href="/talepler/<?= e($ticket->id) ?>"><?= e($ticket->title) ?></a></td>
                    <td data-label="Durum"><?= $view->partial('partials/status_badge', ['status' => $ticket->status]) ?></td>
                    <td data-label="Tarih"><?= e($ticket->createdAt->format('d.m.Y H:i')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted"><?= e($result['total']) ?> talep</p>
    <?php endif; ?>

    <?= $view->partial('partials/pagination', [
        'page' => $result['page'],
        'pages' => $result['pages'],
        'baseUrl' => '/talepler',
        'params' => ['durum' => $status?->value ?? '', 'q' => $query],
    ]) ?>
</section>
