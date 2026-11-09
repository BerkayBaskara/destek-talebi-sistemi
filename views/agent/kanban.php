<?php
/** @var array<string, array{status: \App\Model\Enum\TicketStatus, cards: list<array{ticket: \App\Model\Ticket, sla: \App\Model\SlaStatus}>}> $columns */
/** @var \App\Core\View $view */

use App\Controller\KanbanController;

?>
<section class="card">
    <div class="card-head">
        <h1>Kanban</h1>
        <a class="btn btn-light" href="/havuz">Ortak havuz</a>
    </div>
    <p class="muted">Kartı sürükleyip sonraki sütuna bırakın (Alındı → İnceleniyor → Çözüldü). Telefonda kart altındaki menüyü kullanın. Değişiklik sayfa yenilenmeden kaydedilir.</p>
    <div id="kanban-error" class="alert alert-error" role="alert" hidden></div>
    <div class="kanban">
        <?php foreach ($columns as $value => $column): ?>
            <div class="kanban-col" data-status="<?= e($value) ?>">
                <h2><?= e($column['status']->label()) ?> <span class="muted count"><?= count($column['cards']) ?></span></h2>
                <div class="kanban-cards">
                    <?php foreach ($column['cards'] as ['ticket' => $ticket, 'sla' => $sla]): ?>
                        <article class="kanban-card" draggable="true" data-id="<?= e($ticket->id) ?>">
                            <a href="/talepler/<?= e($ticket->id) ?>">#<?= e($ticket->id) ?> · <?= e($ticket->title) ?></a>
                            <div class="kanban-meta">
                                <span class="badge"><?= e($ticket->priority->label()) ?></span>
                                <?= $view->partial('partials/sla_badge', ['sla' => $sla, 'ticketId' => $ticket->id]) ?>
                            </div>
                            <label class="sr-only" for="move-<?= e($ticket->id) ?>">Durum</label>
                            <select class="kanban-move" id="move-<?= e($ticket->id) ?>">
                                <?php foreach (KanbanController::COLUMNS as $status): ?>
                                    <option value="<?= e($status->value) ?>"<?= $status->value === $value ? ' selected' : '' ?>><?= e($status->label()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<script src="/assets/kanban.js" defer></script>
<script src="/assets/sla.js" defer></script>
