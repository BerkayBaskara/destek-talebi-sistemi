<?php
/** @var array<string, string> $old */
/** @var string|null $error */
/** @var callable $csrfField */
?>
<section class="card narrow">
    <h1>Şifremi unuttum</h1>
    <p class="muted">Müşteri numaranızı girin; kayıt olurken seçtiğiniz gizli soru sorulacak.</p>
    <?php if (!empty($error)): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/sifre-sifirla" novalidate>
        <?= $csrfField() ?>
        <div class="form-row">
            <label for="customer_no">Müşteri numarası</label>
            <input type="text" id="customer_no" name="customer_no" value="<?= e($old['customer_no'] ?? '') ?>" placeholder="Ör. M123456" required>
        </div>
        <button type="submit" class="btn">Devam</button>
    </form>
    <p class="muted"><a href="/giris">Girişe dön</a></p>
</section>
