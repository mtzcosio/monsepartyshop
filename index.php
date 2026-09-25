<?php
$page_title = 'Inicio';
require_once __DIR__ . '/includes/header.php';

$db = get_db();
$categories = $db->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();

$bestsellers = $db->query(
    "SELECT p.*, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS category_name FROM products p
     LEFT JOIN product_categories pc ON pc.product_id = p.id
     LEFT JOIN categories c ON c.id = pc.category_id
     WHERE p.status = 'active' AND p.is_bestseller = 1
     GROUP BY p.id
     ORDER BY p.created_at DESC LIMIT 8"
)->fetchAll();
?>

<section class="hero">
    <div class="hero-brand">MONSE PARTY SHOP</div>
    <h1>Plantillas digitales para celebrar momentos especiales</h1>
    <p>Descubre diseños listos para imprimir y crear tus propias decoraciones, regalos y detalles para tus eventos.</p>
    <div class="hero-buttons">
        <a href="<?= base_url('productos.php') ?>" class="btn btn-primary">EXPLORAR PLANTILLAS</a>
        <a href="<?= base_url('productos.php?destacado=vendidos') ?>" class="btn btn-secondary">VER MÁS VENDIDOS</a>
    </div>
</section>

<section class="section" id="categorias">
    <div class="container">
        <h2 class="section-title text-center">Explora por categoría</h2>
        <p class="section-subtitle">Encuentra la plantilla perfecta para cada ocasión.</p>
        <div class="categories-grid">
            <?php foreach ($categories as $cat): ?>
                <a href="<?= base_url('productos.php?categoria=' . urlencode($cat['slug'])) ?>" class="category-card">
                    <div class="category-icon"><?= e($cat['icon']) ?></div>
                    <div class="category-name"><?= e($cat['name']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="brand-message">
    <h2>CREA, DECORA Y CELEBRA</h2>
    <p>En Monse Party Shop encontrarás plantillas digitales diseñadas para ayudarte a crear detalles únicos para tus celebraciones.</p>
</section>

<?php if ($bestsellers): ?>
<section class="section">
    <div class="container">
        <h2 class="section-title text-center">Nuestros más vendidos</h2>
        <p class="section-subtitle">Los favoritos de nuestra comunidad para hacer cada evento inolvidable.</p>
        <div class="products-grid">
            <?php foreach ($bestsellers as $product): ?>
                <?php include __DIR__ . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" style="background: var(--card-color);">
    <div class="container">
        <h2 class="section-title text-center">¿Cómo funciona?</h2>
        <p class="section-subtitle">Crear algo especial nunca fue tan fácil.</p>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">01</div>
                <div class="step-title">ELIGE</div>
                <p>Encuentra la plantilla perfecta para tu evento.</p>
            </div>
            <div class="step-card">
                <div class="step-number">02</div>
                <div class="step-title">COMPRA</div>
                <p>Realiza tu pago de forma segura.</p>
            </div>
            <div class="step-card">
                <div class="step-number">03</div>
                <div class="step-title">DESCARGA</div>
                <p>Recibe tu acceso inmediatamente después de confirmar tu pago.</p>
            </div>
            <div class="step-card">
                <div class="step-number">04</div>
                <div class="step-title">CREA</div>
                <p>Imprime, arma y disfruta tu creación.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
