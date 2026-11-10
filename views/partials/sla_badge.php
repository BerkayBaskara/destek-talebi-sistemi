<?php
/** @var \App\Model\SlaStatus $sla */
/** @var int|null $ticketId */
?>
<span class="sla-badge <?= e($sla->cssClass()) ?>"<?= isset($ticketId) ? ' data-ticket-id="' . e($ticketId) . '"' : '' ?>><?= e($sla->label) ?></span>
