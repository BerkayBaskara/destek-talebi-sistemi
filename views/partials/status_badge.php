<?php
/** @var \App\Model\Enum\TicketStatus $status */
$icons = require __DIR__ . '/icons.php';
?>
<span class="badge status-<?= e($status->value) ?>" title="<?= e($status->hocaLabel()) ?>"><?= $icons[$status->value] ?><?= e($status->label()) ?></span>
