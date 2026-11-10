<?php /** @var string $customerNo */ ?>
<section class="card narrow">
    <h1>Kaydınız tamamlandı</h1>
    <p>Müşteri numaranız:</p>
    <p class="big-number"><?= e($customerNo) ?></p>
    <p class="alert alert-error">Bu numarayı not alın. Giriş yaparken şifrenizle birlikte bu numarayı kullanacaksınız; bu sayfa bir daha gösterilmez.</p>
    <a class="btn" href="/giris">Giriş yap</a>
</section>
