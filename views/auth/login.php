<?php
/** @var array<string, string> $old */
/** @var string|null $error */
/** @var callable $csrfField */
?>
<section class="card narrow">
    <h1>Giriş</h1>
    <?php if (!empty($error)): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/giris" novalidate>
        <?= $csrfField() ?>
        <div class="form-row">
            <label for="customer_no">Müşteri / personel numarası</label>
            <input type="text" id="customer_no" name="customer_no" value="<?= e($old['customer_no'] ?? '') ?>" placeholder="Ör. M123456" autocomplete="username" required>
        </div>
        <div class="form-row">
            <label for="password">Şifre</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn">Giriş yap</button>
    </form>
    <p class="muted">Hesabınız yok mu? <a href="/kayit">Kayıt olun</a> · <a href="/sifre-sifirla">Şifremi unuttum</a></p>
</section>
