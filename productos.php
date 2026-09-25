<?php
$page_title = 'Plantillas';
require_once __DIR__ . '/includes/header.php';

$db = get_db();
$categories = $db->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();

$categoria_slug = $_GET['categoria'] ?? '';
$destacado = $_GET['destacado'] ?? '';
$buscar = trim($_GET['buscar'] ?? '');

$sql = "SELECT p.*, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS category_name FROM products p
        LEFT JOIN product_categories pc ON pc.product_id = p.id
        LEFT JOIN categories c ON c.id = pc.category_id
        WHERE p.status = 'active'";
$params = [];

if ($categoria_slug !== '') {
    $sql .= ' AND EXISTS (SELECT 1 FROM product_categories pc2
                          JOIN categories c2 ON c2.id = pc2.category_id
                          WHERE pc2.product_id = p.id AND c2.slug = :cat)';
    $params['cat'] = $categoria_slug;
}
if ($destacado === 'vendidos') {
    $sql .= ' AND p.is_bestseller = 1';
} elseif ($destacado === 'nuevo') {
    $sql .= ' AND p.is_new = 1';
} elseif ($destacado === 'oferta') {
    $sql .= ' AND p.is_offer = 1';
} elseif ($destacado === 'kit') {
    $sql .= ' AND p.is_kit = 1';
}
if ($buscar !== '') {
    $sql .= ' AND p.name LIKE :buscar';
    $params['buscar'] = '%' . $buscar . '%';
}
$sql .= ' GROUP BY p.id ORDER BY p.created_at DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$current_category_name = '';
foreach ($categories as $cat) {
    if ($cat['slug'] === $categoria_slug) { $current_category_name = $cat['name']; }
}
?>

<section class="section" style="padding-bottom:0;">
    <div class="container">
        <h1 class="section-title"><?= $current_category_name ? e($current_category_name) : 'Todas las plantillas' ?></h1>

        <form method="get" style="max-width:420px;margin:0 auto 32px;">
            <input type="text" name="buscar" class="form-control" placeholder="Buscar plantillas..." value="<?= e($buscar) ?>">
        </form>

        <div class="filters-bar">
            <a href="<?= base_url('productos.php') ?>" class="filter-chip <?= ($categoria_slug === '' && $destacado === '') ? 'active' : '' ?>">Todas</a>
            <?php foreach ($categories as $cat): ?>
                <a href="<?= base_url('productos.php?categoria=' . urlencode($cat['slug'])) ?>" class="filter-chip <?= $categoria_slug === $cat['slug'] ? 'active' : '' ?>">
                    <?= e($cat['icon']) ?> <?= e($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="filters-bar">
            <a href="<?= base_url('productos.php?destacado=vendidos') ?>" class="filter-chip <?= $destacado === 'vendidos' ? 'active' : '' ?>">⭐ Más vendidos</a>
            <a href="<?= base_url('productos.php?destacado=nuevo') ?>" class="filter-chip <?= $destacado === 'nuevo' ? 'active' : '' ?>">🆕 Nuevo</a>
            <a href="<?= base_url('productos.php?destacado=oferta') ?>" class="filter-chip <?= $destacado === 'oferta' ? 'active' : '' ?>">🔥 Oferta</a>
            <a href="<?= base_url('productos.php?destacado=kit') ?>" class="filter-chip <?= $destacado === 'kit' ? 'active' : '' ?>">📦 Kits</a>
        </div>
    </div>
</section>

<section class="section" style="padding-top:24px;">
    <div class="container">
        <?php if ($products): ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <?php include __DIR__ . '/includes/product_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No encontramos plantillas con esos filtros. ¡Prueba con otra búsqueda! 💕</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
