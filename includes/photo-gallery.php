<?php if (!empty($images)): ?>
<div class="photo-gallery">
    <?php foreach ($images as $img): ?>
    <div class="photo-card">
        <a href="<?= e(imgUrl($img['file_path'])) ?>" target="_blank" class="photo-link">
            <img src="<?= e(imgUrl($img['thumbnail_path'] ?: $img['file_path'])) ?>" alt="<?= e($img['caption'] ?? 'Photo') ?>" loading="lazy">
        </a>
        <?php if (!empty($allowDelete)): ?>
        <form method="post" class="photo-delete" onsubmit="return confirm('Delete this image?')">
            <input type="hidden" name="action" value="delete_image">
            <input type="hidden" name="attachment_id" value="<?= (int)$img['id'] ?>">
            <button type="submit" title="Delete image">&times;</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-photos">
    <img src="assets/img/no-image.svg" alt="" class="empty-photo-icon">
    <p>No photos uploaded yet</p>
</div>
<?php endif; ?>

<div class="upload-zone">
    <form method="post" enctype="multipart/form-data" id="upload-form-<?= $uploadId ?? 'default' ?>">
        <input type="file" name="image" accept="image/*" id="file-<?= $uploadId ?? 'default' ?>" onchange="this.form.submit()">
        <label for="file-<?= $uploadId ?? 'default' ?>" class="upload-label">
            <span class="upload-icon">📷</span>
            <span>Click to upload or drag a photo here</span>
        </label>
    </form>
</div>
