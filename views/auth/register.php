<?php
/** @var array<string, string> $old */
/** @var array<string, string> $errors */
/** @var list<string> $questions */
/** @var callable $csrfField */
$fieldError = static fn (string $f): string => isset($errors[$f]) ? '<div class="field-error">' . e($errors[$f]) . '</div>' : '';
?>
<section class="card narrow">
    <h1>Kayıt ol</h1>
    <p class="muted">E-posta gerekmez. Kayıt sonunda size bir müşteri numarası verilecek; giriş yaparken bu numarayı kullanacaksınız.</p>
    <form method="post" action="/kayit" novalidate>
        <?= $csrfField() ?>
        <div class="form-row">
            <label for="name">Ad</label>
            <input type="text" id="name" name="name" value="<?= e($old['name'] ?? '') ?>" required>
            <?= $fieldError('name') ?>
        </div>
        <div class="form-row">
            <label for="surname">Soyad</label>
            <input type="text" id="surname" name="surname" value="<?= e($old['surname'] ?? '') ?>" required>
            <?= $fieldError('surname') ?>
        </div>
        <div class="form-row">
            <label for="password">Şifre (en az 8 karakter)</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required>
            <?= $fieldError('password') ?>
        </div>
        <div class="form-row">
            <label for="password_confirm">Şifre (tekrar)</label>
            <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
            <?= $fieldError('password_confirm') ?>
        </div>
        <div class="form-row">
            <label for="secret_question">Gizli soru (şifrenizi unutursanız sorulacak)</label>
            <select id="secret_question" name="secret_question" required>
                <option value="">Seçiniz</option>
                <?php foreach ($questions as $question): ?>
                    <option value="<?= e($question) ?>"<?= ($old['secret_question'] ?? '') === $question ? ' selected' : '' ?>><?= e($question) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $fieldError('secret_question') ?>
        </div>
        <div class="form-row">
            <label for="secret_answer">Gizli sorunun cevabı</label>
            <input type="text" id="secret_answer" name="secret_answer" value="<?= e($old['secret_answer'] ?? '') ?>" required>
            <?= $fieldError('secret_answer') ?>
        </div>
        <button type="submit" class="btn">Kayıt ol</button>
    </form>
    <p class="muted">Zaten hesabınız var mı? <a href="/giris">Giriş yapın</a></p>
</section>
