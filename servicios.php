<?php
$page_title = 'Servicios';
require_once __DIR__ . '/includes/header.php';

$db = get_db();
$buscar = trim($_GET['buscar'] ?? '');

$sql = "SELECT * FROM services WHERE status = 'active'";
$params = [];
if ($buscar !== '') {
    $sql .= ' AND name LIKE :buscar';
    $params['buscar'] = '%' . $buscar . '%';
}
$sql .= ' ORDER BY sort_order ASC, created_at DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();
?>

<section class="section" style="padding-bottom:0;">
    <div class="container">
        <h1 class="section-title">Nuestros servicios</h1>
        <p class="section-subtitle text-center">Renta de mobiliario, decoración, cabinas y más para tu evento.</p>

        <form method="get" style="max-width:420px;margin:0 auto 32px;">
            <input type="text" name="buscar" class="form-control" placeholder="Buscar servicios..." value="<?= e($buscar) ?>">
        </form>
    </div>
</section>

<section class="section" style="padding-top:24px;">
    <div class="container">
        <?php if ($services): ?>
            <div class="products-grid">
                <?php foreach ($services as $service): ?>
                    <?php include __DIR__ . '/includes/service_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No encontramos servicios con esos filtros. ¡Prueba con otra búsqueda! 💕</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
