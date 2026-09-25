<?php
require_once __DIR__ . '/includes/init.php';

$slug = $_GET['slug'] ?? '';
$db = get_db();
$stmt = $db->prepare("SELECT * FROM services WHERE slug = :slug AND status = 'active' LIMIT 1");
$stmt->execute(['slug' => $slug]);
$service = $stmt->fetch();

if (!$service) {
    http_response_code(404);
    $page_title = 'Servicio no encontrado';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><p>No encontramos ese servicio. Quizás fue removido o el enlace es incorrecto.</p>
          <a href="' . base_url('servicios.php') . '" class="btn btn-primary">Ver todos los servicios</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$page_title = $service['name'];
require_once __DIR__ . '/includes/header.php';

$gallery_stmt = $db->prepare('SELECT image FROM service_images WHERE service_id = :id ORDER BY sort_order ASC');
$gallery_stmt->execute(['id' => $service['id']]);
$gallery_images = array_column($gallery_stmt->fetchAll(), 'image');

$all_images = [];
if (!empty($service['image'])) { $all_images[] = $service['image']; }
foreach ($gallery_images as $gimg) { $all_images[] = $gimg; }

$related_stmt = $db->prepare("SELECT * FROM services WHERE status = 'active' AND id != :id ORDER BY sort_order ASC, created_at DESC LIMIT 4");
$related_stmt->execute(['id' => $service['id']]);
$related = $related_stmt->fetchAll();

$quote_url = base_url('contacto.php?servicio=' . urlencode($service['slug']));
?>

<div class="container">
    <div class="product-detail">
        <div class="product-gallery">
            <div class="product-detail-image" id="mainProductImage">
                <?php if (!empty($all_images)): ?>
                    <img src="<?= e($all_images[0]) ?>" alt="<?= e($service['name']) ?>" id="mainProductImageTag">
                <?php else: ?>
                    🎉
                <?php endif; ?>
            </div>
            <?php if (count($all_images) > 1): ?>
            <div class="product-thumbnails">
                <?php foreach ($all_images as $i => $img_url): ?>
                    <button type="button" class="product-thumbnail <?= $i === 0 ? 'active' : '' ?>" data-image="<?= e($img_url) ?>">
                        <img src="<?= e($img_url) ?>" alt="<?= e($service['name']) ?> <?= $i + 1 ?>">
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="product-detail-info">
            <div class="hero-brand" style="text-align:left;font-size:13px;">MONSE PARTY SHOP</div>
            <h1><?= e($service['name']) ?></h1>

            <div class="product-price-row" style="margin-bottom:8px;">
                <span class="product-price" style="font-size:30px;">
                    <?= $service['price_from'] !== null ? 'Desde ' . format_price($service['price_from']) : 'Precio a cotizar' ?>
                </span>
            </div>

            <?php if ($service['short_description']): ?>
                <p class="product-detail-desc"><?= nl2br(e($service['short_description'])) ?></p>
            <?php endif; ?>

            <?php if (!empty($service['description'])): ?>
                <div class="product-custom-description"><?= $service['description'] ?></div>
            <?php endif; ?>

            <a href="<?= e($quote_url) ?>" class="btn btn-primary btn-block">SOLICITAR COTIZACIÓN</a>
        </div>
    </div>
</div>

<?php if ($related): ?>
<section class="section">
    <div class="container">
        <h2 class="section-title text-center">También te puede interesar</h2>
        <div class="products-grid">
            <?php foreach ($related as $service): ?>
                <?php include __DIR__ . '/includes/service_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
