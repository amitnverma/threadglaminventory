<?php
/**
 * Public, read-only album viewer for customers.
 * Reached via a private token link: share.php?a=<token>. No login, no writes.
 */
require_once __DIR__ . '/includes/album-functions.php'; // pulls in functions.php + db
ensureAlbumsSchema();

$album = albumGetByToken(trim($_GET['a'] ?? ''));
if (!$album) {
    http_response_code(404);
    $notFound = true;
} else {
    $notFound = false;
    $finals = albumPhotos((int)$album['id'], 'final');
    if ($album['share_scope'] === 'final') {
        $discussed = [];
    } else {
        $discussed = array_values(array_filter(albumPhotos((int)$album['id']), fn($p) => $p['status'] !== 'final' && $p['status'] !== 'rejected'));
    }
}

$settings = getSettings();
$brand = $settings['company_name'] ?? 'ThreadGlam Events';

function shareGallery(array $photos): void
{
    echo '<div class="grid">';
    foreach ($photos as $p) {
        $full  = e(imgUrl($p['file_path']));
        $thumb = e(imgUrl($p['thumbnail_path'] ?: $p['file_path']));
        echo '<figure class="ph" onclick="lb(\'' . $full . '\')">';
        echo '<div class="im" style="background-image:url(\'' . $thumb . '\')"></div>';
        if (!empty($p['caption'])) echo '<figcaption>' . e($p['caption']) . '</figcaption>';
        echo '</figure>';
    }
    echo '</div>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $notFound ? 'Album not found' : e($album['name']) . ' · ' . e($brand) ?></title>
<style>
  :root{ --brand:#6d28d9; --ink:#1f2937; --bg:#f5f3ff; --muted:#6b7280; --line:#e5e7eb; }
  *{box-sizing:border-box}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:var(--ink);background:var(--bg);line-height:1.6}
  header{background:#1e1b2e;color:#fff;padding:34px 24px;text-align:center}
  header .brand{letter-spacing:.28em;text-transform:uppercase;font-size:12px;color:#c4b5fd}
  header h1{font-weight:700;font-size:30px;margin:10px 0 4px}
  header .sub{color:#cbd5e1;font-size:14.5px}
  .wrap{max-width:1080px;margin:0 auto;padding:30px 24px 70px}
  .approved{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;border-radius:10px;padding:12px 18px;margin:0 0 24px;text-align:center}
  h2.sec{font-weight:700;font-size:21px;margin:34px 0 4px;display:flex;align-items:center;gap:12px}
  h2.sec:after{content:"";flex:1;height:1px;background:var(--line)}
  .lead{color:var(--muted);margin:0 0 18px;font-size:14.5px}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}
  .ph{margin:0;cursor:zoom-in;background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden;transition:.15s}
  .ph:hover{box-shadow:0 10px 28px rgba(109,40,217,.15);transform:translateY(-2px)}
  .im{aspect-ratio:4/3;background:#ede9fe center/cover no-repeat}
  figcaption{padding:9px 12px;font-size:13.5px;color:#4b5563}
  footer{text-align:center;color:var(--muted);font-size:13px;padding:26px}
  .empty{text-align:center;color:var(--muted);padding:60px 20px}
  .lightbox{position:fixed;inset:0;background:rgba(17,12,30,.92);display:none;align-items:center;justify-content:center;padding:24px;cursor:zoom-out;z-index:50}
  .lightbox.open{display:flex}
  .lightbox img{max-width:100%;max-height:100%;border-radius:6px}
</style>
</head>
<body>
<?php if ($notFound): ?>
  <div class="empty">
    <h1>Album not available</h1>
    <p>This link may have been turned off or is incorrect. Please contact <?= e($brand) ?>.</p>
  </div>
<?php else: ?>
  <header>
    <div class="brand"><?= e($brand) ?></div>
    <h1><?= e($album['name']) ?></h1>
    <div class="sub"><?= $album['event_type'] ? e($album['event_type']) : '' ?><?= $album['event_date'] ? ' · ' . formatDate($album['event_date']) : '' ?></div>
  </header>
  <div class="wrap">
    <?php if ($album['design_approved'] && $finals): ?>
      <div class="approved">✓ This is your approved final design<?= $album['approved_at'] ? ', confirmed on ' . formatDate($album['approved_at']) : '' ?>.</div>
    <?php endif; ?>

    <?php if ($finals): ?>
      <h2 class="sec">Final design</h2>
      <p class="lead">The look we've agreed on for your event.</p>
      <?php shareGallery($finals); ?>
    <?php endif; ?>

    <?php if ($discussed): ?>
      <h2 class="sec"><?= $finals ? 'Other options' : 'Options for your event' ?></h2>
      <p class="lead">A few more looks we're considering together — let us know your favourites.</p>
      <?php shareGallery($discussed); ?>
    <?php endif; ?>

    <?php if (!$finals && !$discussed): ?>
      <div class="empty"><p>Photos will appear here soon.</p></div>
    <?php endif; ?>
  </div>
  <footer>Shared privately by <?= e($brand) ?> · Please do not forward this link.</footer>
  <div class="lightbox" id="lb" onclick="this.classList.remove('open')"><img src="" alt=""></div>
  <script>function lb(src){var b=document.getElementById('lb');b.querySelector('img').src=src;b.classList.add('open');}</script>
<?php endif; ?>
</body>
</html>
