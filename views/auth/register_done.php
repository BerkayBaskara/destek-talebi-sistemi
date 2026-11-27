<?php /** @var string $customerNo */ ?>
<section class="card narrow success-card">
    <div class="success-icon" aria-hidden="true">
        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M4 12.5l5 5L20 6.5"/></svg>
    </div>
    <h1>Kaydınız tamamlandı</h1>
    <p>Müşteri numaranız</p>
    <p class="big-number"><?= e($customerNo) ?></p>
    <p class="alert alert-warning">Bu numarayı not alın. Giriş yaparken şifrenizle birlikte bu numarayı kullanacaksınız; bu sayfa bir daha gösterilmez.</p>
    <p class="success-links"><a href="/giris">Giriş yapmak için buraya tıklayınız</a></p>
</section>
