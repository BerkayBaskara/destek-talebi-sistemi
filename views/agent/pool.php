<?php
/** @var list<array{ticket: \App\Model\Ticket, sla: \App\Model\SlaStatus}> $rows */
/** @var callable $csrfField */
/** @var \App\Core\View $view */
?>
<section class="card">
    <h1>Ortak Havuz</h1>
    <p class="muted">Henüz hiçbir personele atanmamış talepler. Üzerinize aldığınız talep size atanır; aynı talebi iki kişi alamaz.</p>
    <?php if ($rows === []): ?>
        <p class="muted">Havuzda bekleyen talep yok.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>ID</th><th>Konu Başlığı</th><th>Öncelik</th><th>Açılış</th><th>SLA Kalan Süre</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($rows as ['ticket' => $ticket, 'sla' => $sla]): ?>
                <tr>
                    <td data-label="ID">#<?= e($ticket->id) ?></td>
                    <td data-label="Konu"><a href="/talepler/<?= e($ticket->id) ?>"><?= e($ticket->title) ?></a></td>
                    <td data-label="Öncelik"><?= e($ticket->priority->label()) ?></td>
                    <td data-label="Açılış"><?= e($ticket->createdAt->format('d.m.Y H:i')) ?></td>
                    <td data-label="SLA"><?= $view->partial('partials/sla_badge', ['sla' => $sla, 'ticketId' => $ticket->id]) ?></td>
                    <td>
                        <form method="post" action="/havuz/<?= e($ticket->id) ?>/al">
                            <?= $csrfField() ?>
                            <button type="submit" class="btn">Üzerime al</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<script src="/assets/sla.js" defer></script>
