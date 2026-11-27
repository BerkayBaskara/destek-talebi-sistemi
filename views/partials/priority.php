<?php
/*
 * Öncelik adı. Sistem anahtar kelimeyle önceliği yükselttiyse adın sağ üstünde yıldız görünür; yıldızın
 * üzerine gelince, odaklanınca, tıklayınca ya da dokununca "Öncelik seviyesi yükseltildi" bilgisi çıkar.
 */
/** @var \App\Model\Ticket $ticket */
/** @var array<int, string> $priorityNames */
?>
<span class="priority"><?= e($priorityNames[$ticket->priorityId] ?? '-') ?><?php if ($ticket->priorityRaised()): ?><button type="button" class="tip" aria-label="Öncelik seviyesi yükseltildi" data-tip="Öncelik seviyesi yükseltildi">★</button><?php endif; ?></span>
