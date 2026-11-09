<?php
/** @var list<\App\Model\Category> $categories */
/** @var list<\App\Model\Enum\Priority> $priorities */
/** @var array<string, string> $old */
/** @var array<string, string> $errors */
/** @var callable $csrfField */
/** @var int $maxFiles */
$fieldError = static fn (string $f): string => isset($errors[$f]) ? '<div class="field-error">' . e($errors[$f]) . '</div>' : '';
?>
<section class="card narrow-wide">
    <h1>Yeni Destek Talebi Oluştur</h1>
    <?php if ($errors !== []): ?>
        <div class="alert alert-error" role="alert">Formda düzeltilmesi gereken alanlar var.</div>
    <?php endif; ?>
    <form method="post" action="/talepler" enctype="multipart/form-data" novalidate>
        <?= $csrfField() ?>
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
                    <option value="<?= e($priority->value) ?>"<?= ($old['priority'] ?? '2') === (string) $priority->value ? ' selected' : '' ?>><?= e($priority->label()) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $fieldError('priority') ?>
        </div>
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
            <label for="ekler">Ek dosya (en fazla <?= e($maxFiles) ?> dosya; görsel/PDF 5 MB, video 50 MB)</label>
            <input type="file" id="ekler" name="ekler[]" multiple accept=".jpg,.jpeg,.png,.pdf,.mp4,.webm,image/jpeg,image/png,application/pdf,video/mp4,video/webm">
            <div class="muted">İzinli türler: JPG, PNG, PDF, MP4, WebM</div>
            <?= $fieldError('ekler') ?>
        </div>
        <button type="submit" class="btn">Talebi Gönder</button>
    </form>
</section>
