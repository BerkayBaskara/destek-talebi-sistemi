<?php
/** @var list<\App\Model\Category> $categories */
/** @var list<\App\Model\Priority> $priorities */
/** @var array<string, string> $old */
/** @var array<string, string> $errors */
/** @var callable $csrfField */
/** @var int $maxFiles */
/** @var list<array{id: int, category_id: int, label: string, type: string, required: bool}> $extraFields */
/** @var array<mixed> $oldExtra */
/** @var list<\App\Model\User> $customers */
/** @var string $oldCustomer */
/** @var int $maxImageMb */
/** @var int $maxVideoMb */
$fieldError = static fn (string $f): string => isset($errors[$f]) ? '<div class="field-error">' . e($errors[$f]) . '</div>' : '';
?>
<section class="card narrow-wide">
    <h1>Yeni Destek Talebi Oluştur</h1>
    <?php if ($errors !== []): ?>
        <div class="alert alert-error" role="alert">Formda düzeltilmesi gereken alanlar var.</div>
    <?php endif; ?>
    <form method="post" action="/talepler" enctype="multipart/form-data" novalidate>
        <?= $csrfField() ?>
        <?php if ($customers !== []): ?>
            <div class="form-row">
                <label for="customer_id">Müşteri adına (boş bırakılırsa talep kendi hesabınızla açılır)</label>
                <select id="customer_id" name="customer_id">
                    <option value="">Kendi adıma / işletme adına</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= e($c->id) ?>"<?= $oldCustomer === (string) $c->id ? ' selected' : '' ?>><?= e($c->fullName() . ' (' . $c->customerNo . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="form-row">
            <label for="category_id">Kategori</label>
            <select id="category_id" name="category_id" required>
                <option value="">Seçiniz</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category->id) ?>"<?= ($old['category_id'] ?? '') === (string) $category->id ? ' selected' : '' ?>><?= e($category->name) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $fieldError('category_id') ?>
        </div>
        <div class="form-row">
            <label for="priority">Öncelik seviyesi</label>
            <select id="priority" name="priority" required>
                <?php foreach ($priorities as $priority): ?>
                    <option value="<?= e($priority->id) ?>"<?= ($old['priority'] ?? '2') === (string) $priority->id ? ' selected' : '' ?>><?= e($priority->name) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $fieldError('priority') ?>
        </div>
        <?php foreach ($extraFields as $field): ?>
            <?php
            $fid = $field['id'];
            $categoryName = '';
            foreach ($categories as $category) {
                if ((int) $category->id === $field['category_id']) {
                    $categoryName = $category->name;
                }
            }
            if ($categoryName === '') {
                continue; // pasif kategorinin alanı formda gösterilmez
            }
            $value = $oldExtra[$fid] ?? $oldExtra[(string) $fid] ?? '';
            ?>
            <div class="form-row extra-field" data-category="<?= e($field['category_id']) ?>">
                <label for="extra-<?= e($fid) ?>"><?= e($field['label']) ?><?= $field['required'] ? '' : ' (isteğe bağlı)' ?> <span class="muted extra-field-category">· <?= e($categoryName) ?></span></label>
                <input type="<?= $field['type'] === 'number' ? 'number' : ($field['type'] === 'date' ? 'date' : 'text') ?>" id="extra-<?= e($fid) ?>" name="extra[<?= e($fid) ?>]" maxlength="200" value="<?= e(is_string($value) ? $value : '') ?>">
                <?= $fieldError('extra_' . $fid) ?>
            </div>
        <?php endforeach; ?>
        <div class="form-row">
            <label for="title">Konu başlığı</label>
            <input type="text" id="title" name="title" maxlength="150" value="<?= e($old['title'] ?? '') ?>" required>
            <?= $fieldError('title') ?>
        </div>
        <div class="form-row">
            <label for="description">Detaylı açıklama</label>
            <textarea id="description" name="description" placeholder="Lütfen yaşadığınız sorunu detaylıca açıklayın..." required><?= e($old['description'] ?? '') ?></textarea>
            <?= $fieldError('description') ?>
        </div>
        <div class="form-row">
            <label for="ekler">Ek dosya (en fazla <?= e($maxFiles) ?> dosya; görsel/PDF <?= e($maxImageMb) ?> MB, video <?= e($maxVideoMb) ?> MB)</label>
            <input type="file" id="ekler" name="ekler[]" multiple accept=".jpg,.jpeg,.png,.pdf,.mp4,.webm,image/jpeg,image/png,application/pdf,video/mp4,video/webm">
            <div class="muted">İzinli türler: JPG, PNG, PDF, MP4, WebM</div>
            <?= $fieldError('ekler') ?>
        </div>
        <button type="submit" class="btn">Talebi Gönder</button>
    </form>
</section>
