<?php
/** @var string $customerNo */
/** @var string $question */
/** @var array<string, string> $errors */
/** @var callable $csrfField */
?>
<section class="card narrow">
    <h1>Yeni şifre belirleyin</h1>
    <form method="post" action="/sifre-sifirla/yeni" novalidate>
        <?= $csrfField() ?>
        <input type="hidden" name="customer_no" value="<?= e($customerNo) ?>">
        <div class="form-row">
            <label for="secret_answer"><?= e($question) ?></label>
            <input type="text" id="secret_answer" name="secret_answer" required>
            <?php if (isset($errors['secret_answer'])): ?><div class="field-error"><?= e($errors['secret_answer']) ?></div><?php endif; ?>
        </div>
        <div class="form-row">
            <label for="password">Yeni şifre (en az 8 karakter)</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required>
            <?php if (isset($errors['password'])): ?><div class="field-error"><?= e($errors['password']) ?></div><?php endif; ?>
        </div>
        <button type="submit" class="btn">Şifreyi değiştir</button>
    </form>
</section>
