<?php
/** @var list<array{ticket: \App\Model\Ticket, sla: \App\Model\SlaStatus}> $rows */
/** @var callable $csrfField */
/** @var \App\Core\View $view */
?>
<section class="card">
    <h1>Görev Havuzu</h1>
    <p class="muted">Henüz hiçbir personele atanmamış talepler. Üzerinize aldığınız talep size atanır; aynı talebi iki kişi alamaz.</p>
    <?php if ($rows === []): ?>
        <p class="muted">Havuzda bekleyen talep yok.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>ID</th><th>Konu Başlığı</th><th>Öncelik</th><th>Açılış</th><th>SLA Kalan Süre</th><th><span class="sr-only">Üzerime al</span></th></tr></thead>
            <tbody>
            <?php foreach ($rows as ['ticket' => $ticket, 'sla' => $sla]): ?>
                <tr>
                    <td data-label="ID"><?= e($ticket->displayCode()) ?></td>
                    <td data-label="Konu"><a href="/talepler/<?= e($ticket->id) ?>"><?= e($ticket->title) ?></a></td>
                    <td data-label="Öncelik"><?= $view->partial('partials/priority', ['ticket' => $ticket]) ?></td>
                    <td data-label="Açılış"><?= e($ticket->createdAt->format('d.m.Y H:i')) ?></td>
                    <td data-label="SLA"><?= $view->partial('partials/sla_badge', ['sla' => $sla, 'ticketId' => $ticket->id]) ?></td>
                    <td>
                        <?php if ($ticket->holdUntil !== null): ?>
                            <span class="badge status-in_review">Müşteri kararı bekleniyor</span>
                            <div class="muted"><?= e($ticket->holdUntil->format('d.m.Y H:i')) ?> tarihine kadar</div>
                        <?php elseif ($ticket->awaitingAdmin): ?>
                            <span class="badge status-cancelled">Yönetici müdahalesi bekliyor</span>
                        <?php else: ?>
                            <form method="post" action="/havuz/<?= e($ticket->id) ?>/al">
                                <?= $csrfField() ?>
                                <button type="submit" class="btn">Üzerime al</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<script src="/assets/sla.js" defer></script>
