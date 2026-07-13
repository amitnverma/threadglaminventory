<?php
/**
 * Albums — event managers keep a photo album per customer/event to showcase
 * during calls, copy/move/delete photos between albums, run a proposed →
 * shortlisted → final approval flow, and share a read-only gallery link.
 *
 * Storage: files live in uploads/albums/<album_id>/, one physical file per
 * photo row (copy duplicates the file, so deleting a photo is always safe).
 * Reuses createThumbnail() from functions.php.
 */

require_once __DIR__ . '/functions.php';

const ALBUM_MIMES   = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const ALBUM_MAX     = 15 * 1024 * 1024; // 15 MB
const PHOTO_STATES  = ['proposed', 'shortlisted', 'final', 'rejected'];

/** Create the album tables once (idempotent). */
function ensureAlbumsSchema(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS albums (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NULL,
            event_id INT NULL,
            name VARCHAR(255) NOT NULL,
            event_type VARCHAR(100) NULL,
            event_date DATE NULL,
            description TEXT NULL,
            cover_photo_id INT NULL,
            is_template TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            design_approved TINYINT(1) NOT NULL DEFAULT 0,
            approved_at DATETIME NULL,
            approved_note VARCHAR(255) NULL,
            share_token VARCHAR(48) NULL,
            share_scope ENUM('all','final') NOT NULL DEFAULT 'all',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_share_token (share_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    db()->exec(
        "CREATE TABLE IF NOT EXISTS album_photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            album_id INT NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            thumbnail_path VARCHAR(500) NULL,
            caption VARCHAR(255) NULL,
            status ENUM('proposed','shortlisted','final','rejected') NOT NULL DEFAULT 'proposed',
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_album (album_id),
            FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/* ------------------------------------------------------------- queries */

function albumsAll(array $f = []): array
{
    $sql = "SELECT a.*, c.name AS customer_name, ev.title AS event_title,
                   (SELECT COUNT(*) FROM album_photos p WHERE p.album_id=a.id) AS photo_count,
                   (SELECT COUNT(*) FROM album_photos p WHERE p.album_id=a.id AND p.status='final') AS final_count
            FROM albums a
            LEFT JOIN customers c ON c.id = a.customer_id
            LEFT JOIN events ev ON ev.id = a.event_id
            WHERE 1=1";
    $args = [];
    if (($f['status'] ?? '') !== '')   { $sql .= ' AND a.status=?'; $args[] = $f['status']; }
    if (isset($f['is_template']))       { $sql .= ' AND a.is_template=?'; $args[] = (int)$f['is_template']; }
    if (isset($f['event_id']))          { $sql .= ' AND a.event_id=?'; $args[] = (int)$f['event_id']; }
    if (($f['q'] ?? '') !== '') {
        $sql .= ' AND (a.name LIKE ? OR c.name LIKE ? OR a.event_type LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($args, $like, $like, $like);
    }
    $sql .= ' ORDER BY a.is_template DESC, a.updated_at DESC, a.id DESC';
    return query($sql, $args);
}

function albumGet(int $id): ?array
{
    return queryOne(
        'SELECT a.*, c.name AS customer_name, ev.title AS event_title
         FROM albums a LEFT JOIN customers c ON c.id=a.customer_id
         LEFT JOIN events ev ON ev.id=a.event_id WHERE a.id=?',
        [$id]
    );
}

function albumGetByToken(string $token): ?array
{
    if ($token === '') return null;
    return queryOne('SELECT * FROM albums WHERE share_token=?', [$token]);
}

function albumPhotos(int $albumId, ?string $status = null): array
{
    if ($status !== null) {
        return query('SELECT * FROM album_photos WHERE album_id=? AND status=? ORDER BY sort_order, id', [$albumId, $status]);
    }
    return query('SELECT * FROM album_photos WHERE album_id=? ORDER BY sort_order, id', [$albumId]);
}

function albumPhotoGet(int $id): ?array
{
    return queryOne('SELECT * FROM album_photos WHERE id=?', [$id]);
}

/** Best display path (thumbnail) for an album's cover; '' if none. */
function albumCoverPath(array $album): string
{
    if (!empty($album['cover_photo_id'])) {
        $p = albumPhotoGet((int)$album['cover_photo_id']);
        if ($p) return $p['thumbnail_path'] ?: $p['file_path'];
    }
    $p = queryOne('SELECT file_path, thumbnail_path FROM album_photos WHERE album_id=? ORDER BY sort_order, id LIMIT 1', [$album['id']]);
    return $p ? ($p['thumbnail_path'] ?: $p['file_path']) : '';
}

/* --------------------------------------------------------- file helpers */

function albumUploadDir(int $albumId): string
{
    $config = require __DIR__ . '/../config.php';
    return $config['upload_dir'] . '/albums/' . $albumId;
}

/** Pick a non-colliding filename inside $dir. */
function albumUniqueName(string $dir, string $ext): string
{
    do { $name = uniqid('img_') . '.' . $ext; } while (is_file("$dir/$name"));
    return $name;
}

/**
 * Store one uploaded file into an album. Returns ['file_path'=>..,'thumbnail_path'=>..]
 * (paths relative to uploads/) or ['err'=>msg].
 */
function albumStoreUpload(int $albumId, array $file): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) return ['err' => 'Upload failed (' . $file['error'] . ').'];
    if ($file['size'] > ALBUM_MAX)        return ['err' => e($file['name']) . ' is too large (max 15 MB).'];
    if (!in_array($file['type'], ALBUM_MIMES, true)) return ['err' => e($file['name']) . ': only JP,PNG,GIF,WEBP images allowed.'];

    $dir = albumUploadDir($albumId);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return ['err' => 'Could not create the album folder — is uploads/ writable?'];

    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
    $name = albumUniqueName($dir, $ext);
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) return ['err' => 'Could not save ' . e($file['name']) . '.'];

    $rel   = 'albums/' . $albumId . '/' . $name;
    $thumb = null;
    if (createThumbnail("$dir/$name", "$dir/thumb_$name")) $thumb = 'albums/' . $albumId . '/thumb_' . $name;
    return ['file_path' => $rel, 'thumbnail_path' => $thumb];
}

/** Physically copy a photo's file (+thumb) into another album. Returns [file_path, thumbnail_path] or null. */
function albumCopyFiles(array $photo, int $destAlbumId): ?array
{
    $config = require __DIR__ . '/../config.php';
    $base = $config['upload_dir'] . '/';
    $src = $base . $photo['file_path'];
    if (!is_file($src)) return null;
    $dir = albumUploadDir($destAlbumId);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return null;

    $ext  = strtolower(pathinfo($src, PATHINFO_EXTENSION)) ?: 'jpg';
    $name = albumUniqueName($dir, $ext);
    if (!copy($src, "$dir/$name")) return null;
    $rel = 'albums/' . $destAlbumId . '/' . $name;

    $thumb = null;
    if (!empty($photo['thumbnail_path']) && is_file($base . $photo['thumbnail_path']) && copy($base . $photo['thumbnail_path'], "$dir/thumb_$name")) {
        $thumb = 'albums/' . $destAlbumId . '/thumb_' . $name;
    } elseif (createThumbnail("$dir/$name", "$dir/thumb_$name")) {
        $thumb = 'albums/' . $destAlbumId . '/thumb_' . $name;
    }
    return ['file_path' => $rel, 'thumbnail_path' => $thumb];
}

/* ---------------------------------------------------------------- actions */

function albumNextOrder(int $albumId): int
{
    $r = queryOne('SELECT COALESCE(MAX(sort_order),-1)+1 AS n FROM album_photos WHERE album_id=?', [$albumId]);
    return (int)($r['n'] ?? 0);
}

function albumCopyPhoto(array $photo, int $destAlbumId): bool
{
    $f = albumCopyFiles($photo, $destAlbumId);
    if (!$f) return false;
    execute(
        'INSERT INTO album_photos (album_id, file_path, thumbnail_path, caption, status, sort_order) VALUES (?,?,?,?,?,?)',
        [$destAlbumId, $f['file_path'], $f['thumbnail_path'], $photo['caption'], 'proposed', albumNextOrder($destAlbumId)]
    );
    return true;
}

function albumMovePhoto(array $photo, int $destAlbumId): bool
{
    if ((int)$photo['album_id'] === $destAlbumId) return true;
    $f = albumCopyFiles($photo, $destAlbumId);
    if (!$f) return false;
    albumDeleteFiles($photo);
    execute(
        'UPDATE album_photos SET album_id=?, file_path=?, thumbnail_path=?, sort_order=? WHERE id=?',
        [$destAlbumId, $f['file_path'], $f['thumbnail_path'], albumNextOrder($destAlbumId), $photo['id']]
    );
    execute('UPDATE albums SET cover_photo_id=NULL WHERE id=? AND cover_photo_id=?', [$photo['album_id'], $photo['id']]);
    return true;
}

function albumDeleteFiles(array $photo): void
{
    $config = require __DIR__ . '/../config.php';
    $base = $config['upload_dir'] . '/';
    if (!empty($photo['file_path']) && is_file($base . $photo['file_path'])) @unlink($base . $photo['file_path']);
    if (!empty($photo['thumbnail_path']) && is_file($base . $photo['thumbnail_path'])) @unlink($base . $photo['thumbnail_path']);
}

function albumDeletePhoto(array $photo): void
{
    albumDeleteFiles($photo);
    execute('DELETE FROM album_photos WHERE id=?', [$photo['id']]);
    execute('UPDATE albums SET cover_photo_id=NULL WHERE cover_photo_id=?', [$photo['id']]);
}

function albumDuplicate(array $album, string $newName, bool $asTemplate = false): int
{
    execute(
        'INSERT INTO albums (customer_id, event_id, name, event_type, event_date, description, is_template, status)
         VALUES (?,?,?,?,?,?,?,?)',
        [
            $asTemplate ? null : $album['customer_id'],
            $asTemplate ? null : $album['event_id'],
            $newName, $album['event_type'], $album['event_date'], $album['description'],
            $asTemplate ? 1 : 0, 'active',
        ]
    );
    $newId = (int)lastId();
    foreach (albumPhotos((int)$album['id']) as $p) {
        $f = albumCopyFiles($p, $newId);
        if (!$f) continue;
        execute(
            'INSERT INTO album_photos (album_id, file_path, thumbnail_path, caption, status, sort_order) VALUES (?,?,?,?,?,?)',
            [$newId, $f['file_path'], $f['thumbnail_path'], $p['caption'], $p['status'], $p['sort_order']]
        );
    }
    return $newId;
}

/* ---------------------------------------------------------------- misc */

function albumStatusLabel(string $s): string
{
    return [
        'proposed'    => 'Proposed',
        'shortlisted' => 'Shortlisted',
        'final'       => 'Final design',
        'rejected'    => 'Passed',
    ][$s] ?? ucfirst($s);
}

function appBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scheme . '://' . $host . $dir;
}

function albumShareUrl(array $album): string
{
    return empty($album['share_token']) ? '' : appBaseUrl() . '/share.php?a=' . $album['share_token'];
}
