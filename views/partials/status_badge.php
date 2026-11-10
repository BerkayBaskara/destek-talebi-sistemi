<?php /** @var \App\Model\Enum\TicketStatus $status */ ?>
<span class="badge status-<?= e($status->value) ?>" title="<?= e($status->hocaLabel()) ?>"><?= e($status->label()) ?></span>
