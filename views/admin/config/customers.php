<?php
/** @var array{items: list<\App\Model\User>, total: int, page: int, pages: int} $result */
/** @var string $query */
/** @var callable $csrfField */
/** @var \App\Core\View $view */
?>
<p><a href="/yonetici/ayarlar">← Sistem Yapılandırması</a></p>
<section class="card">
    <h1>Müşteriler</h1>
    <p class="muted">Pasif müşteri giriş yapamaz; açık oturumu bir sonraki işlemde kapanır. Talepleri ve geçmişi silinmez.</p>
    <form method="get" action="/yonetici/musteriler" class="filters" role="search">
        <label for="q" class="sr-only">Müşteri ara</label>
        <input type="search" id="q" name="q" value="<?= e($query) ?>" placeholder="Ad, soyad veya müşteri numarası">
        <button type="submit" class="btn">Ara</button>
    </form>
    <?php if ($result['items'] === []): ?>
        <p class="muted"><?= $query === '' ? 'Henüz müşteri yok.' : 'Aramaya uyan müşteri yok.' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Numara</th><th>Ad Soyad</th><th>Durum</th><th><span class="sr-only">İşlem</span></th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $customer): ?>
                    <tr data-customer-row>
                        <td data-label="Numara"><?= e($customer->customerNo) ?></td>
                        <td data-label="Ad Soyad"><?= e($customer->name . ' ' . $customer->surname) ?></td>
                        <td data-label="Durum"><span class="badge <?= $customer->active ? 'status-resolved' : 'status-cancelled' ?>"><?= $customer->active ? 'Aktif' : 'Pasif' ?></span></td>
                        <td data-label="İşlem">
                            <form method="post" action="/yonetici/musteriler/<?= e($customer->id) ?>/durum"
                                  data-confirm="<?= e($customer->customerNo) ?> numaralı müşteri <?= $customer->active ? 'pasif' : 'aktif' ?> yapılsın mı?">
                                <?= $csrfField() ?>
                                <input type="hidden" name="active" value="<?= $customer->active ? '0' : '1' ?>">
                                <button type="submit" class="btn btn-light"><?= $customer->active ? 'Pasif yap' : 'Aktif yap' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted"><?= e($result['total']) ?> müşteri</p>
    <?php endif; ?>
    <?= $view->partial('partials/pagination', [
        'page' => $result['page'],
        'pages' => $result['pages'],
        'baseUrl' => '/yonetici/musteriler',
        'params' => ['q' => $query],
    ]) ?>
</section>
