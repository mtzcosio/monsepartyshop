<?php
require_once __DIR__ . '/includes/init.php';

$slug = $_GET['slug'] ?? '';
$db = get_db();
$stmt = $db->prepare("SELECT * FROM products WHERE slug = :slug AND status = 'active' LIMIT 1");
$stmt->execute(['slug' => $slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $page_title = 'Producto no encontrado';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><p>No encontramos esa plantilla. Quizás fue removida o el enlace es incorrecto.</p>
          <a href="' . base_url('productos.php') . '" class="btn btn-primary">Ver todas las plantillas</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Agregar al carrito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (csrf_check($_POST['csrf_token'] ?? '')) {
        cart_add($product['id'], 1);
        flash_set('¡' . $product['name'] . ' fue agregado a tu carrito! 🎉');
    }
    redirect(base_url('producto.php?slug=' . urlencode($slug)));
}

$page_title = $product['name'];
require_once __DIR__ . '/includes/header.php';

$discount = 0;
if (!empty($product['old_price']) && $product['old_price'] > $product['price']) {
    $discount = round((1 - ($product['price'] / $product['old_price'])) * 100);
}

$product_categories_stmt = $db->prepare(
    "SELECT c.id, c.name, c.slug FROM categories c
     JOIN product_categories pc ON pc.category_id = c.id
     WHERE pc.product_id = :id ORDER BY c.sort_order ASC"
);
$product_categories_stmt->execute(['id' => $product['id']]);
$product_categories = $product_categories_stmt->fetchAll();

$related_stmt = $db->prepare(
    "SELECT p.*, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS category_name FROM products p
     LEFT JOIN product_categories pc ON pc.product_id = p.id
     LEFT JOIN categories c ON c.id = pc.category_id
     WHERE p.status = 'active' AND p.id != :id
       AND EXISTS (
         SELECT 1 FROM product_categories pc2
         WHERE pc2.product_id = p.id
           AND pc2.category_id IN (SELECT category_id FROM product_categories WHERE product_id = :pid)
       )
     GROUP BY p.id
     ORDER BY p.created_at DESC LIMIT 4"
);
$related_stmt->execute(['id' => $product['id'], 'pid' => $product['id']]);
$related = $related_stmt->fetchAll();

$gallery_stmt = $db->prepare('SELECT image FROM product_images WHERE product_id = :id ORDER BY sort_order ASC');
$gallery_stmt->execute(['id' => $product['id']]);
$gallery_images = array_column($gallery_stmt->fetchAll(), 'image');

$all_images = [];
if (!empty($product['image'])) { $all_images[] = upload_url($product['image']); }
foreach ($gallery_images as $gimg) { $all_images[] = upload_url($gimg); }
?>

<div class="container">
    <div class="product-detail">
        <div class="product-gallery">
            <?php if (empty($all_images)): ?>
                <div class="product-detail-image">🎉</div>
            <?php else: ?>
            <div class="carousel" data-carousel tabindex="0" aria-roledescription="carrusel" aria-label="Imágenes de <?= e($product['name']) ?>">
                <div class="carousel-viewport">
                    <div class="carousel-track">
                        <?php foreach ($all_images as $i => $img_url): ?>
                            <div class="carousel-slide" aria-roledescription="imagen" aria-label="<?= $i + 1 ?> de <?= count($all_images) ?>">
                                <img src="<?= e($img_url) ?>" alt="<?= e($product['name']) ?> <?= $i + 1 ?>" <?= $i > 0 ? 'loading="lazy"' : '' ?> draggable="false">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if (count($all_images) > 1): ?>
                    <button type="button" class="carousel-btn carousel-prev" data-carousel-prev aria-label="Imagen anterior">&#8249;</button>
                    <button type="button" class="carousel-btn carousel-next" data-carousel-next aria-label="Imagen siguiente">&#8250;</button>
                    <div class="carousel-dots">
                        <?php foreach ($all_images as $i => $img_url): ?>
                            <button type="button" class="carousel-dot <?= $i === 0 ? 'active' : '' ?>" data-carousel-go="<?= $i ?>" aria-label="Ir a la imagen <?= $i + 1 ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (count($all_images) > 1): ?>
            <div class="product-thumbnails">
                <?php foreach ($all_images as $i => $img_url): ?>
                    <button type="button" class="product-thumbnail carousel-thumb <?= $i === 0 ? 'active' : '' ?>" data-carousel-go="<?= $i ?>" aria-label="Ver imagen <?= $i + 1 ?>">
                        <img src="<?= e($img_url) ?>" alt="<?= e($product['name']) ?> <?= $i + 1 ?>">
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="product-detail-info">
            <div class="hero-brand" style="text-align:left;font-size:13px;">MONSE PARTY SHOP</div>
            <?php if ($product_categories): ?>
                <div class="product-category" style="margin-bottom:6px;">
                    <?php foreach ($product_categories as $i => $pc): ?>
                        <?php if ($i > 0): ?> · <?php endif; ?>
                        <a href="<?= base_url('productos.php?categoria=' . urlencode($pc['slug'])) ?>" style="color:inherit;"><?= e($pc['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <h1><?= e($product['name']) ?></h1>
            <div class="product-badges" style="position:static;flex-direction:row;margin-bottom:8px;">
                <?php if (!empty($product['is_new'])): ?><span class="badge badge-nuevo">Nuevo</span><?php endif; ?>
                <?php if (!empty($product['is_bestseller'])): ?><span class="badge badge-vendido">Más vendido</span><?php endif; ?>
                <?php if (!empty($product['is_kit'])): ?><span class="badge badge-kit">Kit</span><?php endif; ?>
                <?php if (!empty($product['is_offer'])): ?><span class="badge badge-oferta">Oferta</span><?php endif; ?>
            </div>

            <div class="product-price-row" style="margin-bottom:8px;">
                <span class="product-price" style="font-size:30px;"><?= format_price($product['price']) ?></span>
                <?php if ($discount > 0): ?>
                    <span class="product-old-price"><?= format_price($product['old_price']) ?></span>
                    <span class="product-discount">-<?= $discount ?>%</span>
                <?php endif; ?>
            </div>

            <div class="rating-stars"><?= star_rating_html($product['rating']) ?></div>

            <?php if ($product['short_description']): ?>
                <p class="product-detail-desc"><?= nl2br(e($product['short_description'])) ?></p>
            <?php endif; ?>

            <?php if (!empty($product['description'])): ?>
                <div class="product-custom-description"><?= $product['description'] ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" name="add_to_cart" class="btn btn-primary btn-block">AGREGAR AL CARRITO</button>
            </form>

            <div class="digital-notice">
                💡 Este producto es digital. No recibirás un producto físico.
            </div>

            <div class="accordion">
                <?php
                $accordion = [
                    '¿Qué incluye?' => $product['show_what_includes'] ? $product['what_includes'] : '',
                    '¿Para qué sirve?' => $product['show_what_for'] ? $product['what_for'] : '',
                    '¿Qué necesitas?' => $product['show_what_you_need'] ? $product['what_you_need'] : '',
                    '¿Cómo se utiliza?' => $product['show_how_to_use'] ? $product['how_to_use'] : '',
                    'Nivel de dificultad' => $product['difficulty_level'],
                    'Formato del archivo' => $product['file_format'],
                ];
                foreach ($accordion as $title => $content):
                    if (!$content) continue;
                ?>
                <div class="accordion-item">
                    <div class="accordion-header">
                        <span><?= e($title) ?></span>
                        <span class="chevron">▾</span>
                    </div>
                    <div class="accordion-body"><?= nl2br(e($content)) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($related): ?>
<section class="section">
    <div class="container">
        <h2 class="section-title text-center">También te puede gustar</h2>
        <div class="products-grid">
            <?php foreach ($related as $product): ?>
                <?php include __DIR__ . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
