<?php
$page_title = 'Panel';
require_once __DIR__ . '/includes/admin_header.php';

$db = get_db();
$total_products = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$total_categories = $db->query("SELECT COUNT(*) FROM categories WHERE is_active=1")->fetchColumn();
$total_orders = $db->query("SELECT COUNT(*) FROM orders WHERE status='paid'")->fetchColumn();
$total_revenue = $db->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='paid'")->fetchColumn();
$orders_this_month = (int)$db->query(
    "SELECT COUNT(*) FROM orders WHERE status='paid' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
)->fetchColumn();
$avg_ticket = $total_orders > 0 ? $total_revenue / $total_orders : 0;

/* ---------- Top 5 productos más vendidos (por unidades, entre pedidos pagados) ---------- */
$best_selling_products = $db->query(
    "SELECT oi.product_id, oi.product_name, SUM(oi.quantity) AS qty_sold, SUM(oi.price * oi.quantity) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.status = 'paid'
     GROUP BY oi.product_id, oi.product_name
     ORDER BY qty_sold DESC
     LIMIT 5"
)->fetchAll();

/* ---------- Filtro de periodo, compartido por ambas gráficas ---------- */
$period_options = [
    '7d' => ['label' => 'Últimos 7 días', 'unit' => 'day', 'count' => 7],
    '30d' => ['label' => 'Últimos 30 días', 'unit' => 'day', 'count' => 30],
    '6m' => ['label' => 'Últimos 6 meses', 'unit' => 'month', 'count' => 6],
    '12m' => ['label' => 'Últimos 12 meses', 'unit' => 'month', 'count' => 12],
    'all' => ['label' => 'Todo el tiempo', 'unit' => 'month', 'count' => null],
];
$periodo = $_GET['periodo'] ?? '6m';
if (!isset($period_options[$periodo])) { $periodo = '6m'; }
$period = $period_options[$periodo];

$period_start = null; // null = sin límite (periodo "all")
if ($period['unit'] === 'day') {
    $period_start = date('Y-m-d 00:00:00', strtotime('-' . ($period['count'] - 1) . ' days'));
} elseif ($period['count'] !== null) {
    $period_start = date('Y-m-01 00:00:00', strtotime('-' . ($period['count'] - 1) . ' months'));
}

/* ---------- Ingresos por periodo (para la gráfica de barras) ---------- */
$month_abbr = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

if ($period['unit'] === 'day') {
    $sql = "SELECT DATE(created_at) bucket, SUM(total) revenue FROM orders WHERE status='paid'";
    $params = [];
    if ($period_start) { $sql .= ' AND created_at >= :start'; $params['start'] = $period_start; }
    $sql .= ' GROUP BY bucket';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $revenue_by_bucket = [];
    foreach ($stmt->fetchAll() as $r) { $revenue_by_bucket[$r['bucket']] = (float)$r['revenue']; }

    $chart_points = [];
    for ($i = $period['count'] - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chart_points[] = ['label' => date('d/m', strtotime($date)), 'value' => $revenue_by_bucket[$date] ?? 0.0];
    }
} else {
    // Para "Todo el tiempo" se calculan los meses desde el primer pedido pagado.
    $months_count = $period['count'];
    if ($months_count === null) {
        $first_paid = $db->query("SELECT MIN(created_at) FROM orders WHERE status='paid'")->fetchColumn();
        if ($first_paid) {
            $first = new DateTime($first_paid);
            $now = new DateTime();
            $months_count = ($now->format('Y') - $first->format('Y')) * 12 + ((int)$now->format('n') - (int)$first->format('n')) + 1;
            $months_count = max(1, min($months_count, 60));
        } else {
            $months_count = 6;
        }
    }

    $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') bucket, SUM(total) revenue FROM orders WHERE status='paid'";
    $params = [];
    if ($period_start) { $sql .= ' AND created_at >= :start'; $params['start'] = $period_start; }
    $sql .= ' GROUP BY bucket';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $revenue_by_bucket = [];
    foreach ($stmt->fetchAll() as $r) { $revenue_by_bucket[$r['bucket']] = (float)$r['revenue']; }

    $chart_points = [];
    for ($i = $months_count - 1; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("-$i months"));
        $chart_points[] = [
            'label' => $month_abbr[(int)date('n', strtotime($ym . '-01'))],
            'value' => $revenue_by_bucket[$ym] ?? 0.0,
        ];
    }
}
$max_chart_value = max(array_column($chart_points, 'value'));

/* ---------- Distribución de pedidos por estado (para la métrica circular), mismo periodo ---------- */
$status_sql = 'SELECT status, COUNT(*) c FROM orders';
$status_params = [];
if ($period_start) { $status_sql .= ' WHERE created_at >= :start'; $status_params['start'] = $period_start; }
$status_stmt = $db->prepare($status_sql . ' GROUP BY status');
$status_stmt->execute($status_params);
$status_counts = ['paid' => 0, 'pending' => 0, 'cancelled' => 0];
foreach ($status_stmt->fetchAll() as $r) { $status_counts[$r['status']] = (int)$r['c']; }
$status_total = array_sum($status_counts);

$status_segments = [
    ['key' => 'paid', 'label' => 'Pagados', 'color' => '#4CAF6D'],
    ['key' => 'pending', 'label' => 'Pendientes', 'color' => '#FFC75F'],
    ['key' => 'cancelled', 'label' => 'Cancelados', 'color' => '#FF6F91'],
];
$conic_stops = [];
$deg = 0;
foreach ($status_segments as $seg) {
    $pct = $status_total > 0 ? $status_counts[$seg['key']] / $status_total : 0;
    $start = $deg;
    $deg += $pct * 360;
    $conic_stops[] = $seg['color'] . ' ' . round($start, 1) . 'deg ' . round($deg, 1) . 'deg';
}
$conic_gradient = $status_total > 0 ? 'conic-gradient(' . implode(', ', $conic_stops) . ')' : 'rgba(75,68,83,0.08)';

/* ---------- Top 5 clientes por monto pagado ---------- */
$top_customers = $db->query(
    "SELECT customer_name, customer_email, COUNT(*) orders_count, SUM(total) total_spent
     FROM orders WHERE status = 'paid'
     GROUP BY customer_email, customer_name
     ORDER BY total_spent DESC LIMIT 5"
)->fetchAll();

$codigo = trim($_GET['codigo'] ?? '');
$cliente = trim($_GET['cliente'] ?? '');
$estado = $_GET['estado'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$has_filters = ($codigo !== '' || $cliente !== '' || $estado !== '' || $fecha_desde !== '' || $fecha_hasta !== '');

$where_sql = ' WHERE 1=1';
$params = [];

if ($codigo !== '') {
    $where_sql .= ' AND order_code LIKE :codigo';
    $params['codigo'] = '%' . $codigo . '%';
}
if ($cliente !== '') {
    $where_sql .= ' AND (customer_name LIKE :cliente OR customer_email LIKE :cliente2)';
    $params['cliente'] = '%' . $cliente . '%';
    $params['cliente2'] = '%' . $cliente . '%';
}
if (in_array($estado, ['pending', 'paid', 'cancelled'], true)) {
    $where_sql .= ' AND status = :estado';
    $params['estado'] = $estado;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
    $where_sql .= ' AND created_at >= :fecha_desde';
    $params['fecha_desde'] = $fecha_desde . ' 00:00:00';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
    $where_sql .= ' AND created_at <= :fecha_hasta';
    $params['fecha_hasta'] = $fecha_hasta . ' 23:59:59';
}

$per_page = admin_get_per_page(10);
$page = admin_current_page();

$count_stmt = $db->prepare('SELECT COUNT(*) FROM orders' . $where_sql);
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_records / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$stmt = $db->prepare('SELECT * FROM orders' . $where_sql . " ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$recent_orders = $stmt->fetchAll();
?>

<div class="admin-topbar">
    <div>
        <h1 class="mt-0">Hola, <?= e($_SESSION['admin_name'] ?: 'Monse') ?> 👋</h1>
        <p class="page-subtitle">Aquí tienes un resumen de tu tienda hoy, <?= e(date('d/m/Y')) ?>.</p>
    </div>
</div>

<div class="stat-grid stat-grid-dashboard">
    <a href="<?= admin_url('products.php?estado=active') ?>" class="stat-card stat-card-link">
        <div class="stat-icon pink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$total_products ?></div>
            <div class="stat-label">Productos activos</div>
        </div>
    </a>
    <a href="<?= admin_url('categories.php?estado=active') ?>" class="stat-card stat-card-link">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41L11 3.83A2 2 0 0 0 9.59 3.2L4 3a1 1 0 0 0-1 1l.2 5.59a2 2 0 0 0 .58 1.41l9.59 9.59a2 2 0 0 0 2.83 0l4.39-4.39a2 2 0 0 0 0-2.83z"/><circle cx="7.5" cy="7.5" r="1.1"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$total_categories ?></div>
            <div class="stat-label">Categorías</div>
        </div>
    </a>
    <a href="<?= admin_url('orders.php?estado=paid') ?>" class="stat-card stat-card-link">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l1 13H5L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$total_orders ?></div>
            <div class="stat-label">Pedidos pagados</div>
            <div class="stat-sub"><?= (int)$orders_this_month ?> este mes</div>
        </div>
    </a>
    <div class="stat-card">
        <div class="stat-icon gold">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= format_price($total_revenue) ?></div>
            <div class="stat-label">Ingresos totales</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= format_price($avg_ticket) ?></div>
            <div class="stat-label">Ticket promedio</div>
        </div>
    </div>
    <a href="<?= admin_url('orders.php?estado=pending') ?>" class="stat-card stat-card-link">
        <div class="stat-icon pink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$notif_count ?></div>
            <div class="stat-label">Pedidos pendientes</div>
        </div>
    </a>
</div>

<div class="dashboard-grid">
    <div class="dashboard-main">
        <div class="section-card">
            <div class="section-card-head">
                <div>
                    <h3>Ingresos</h3>
                    <p>Pedidos pagados · <?= e($period['label']) ?></p>
                </div>
            </div>
            <div class="period-chip-row">
                <?php foreach ($period_options as $key => $opt): ?>
                    <a href="?<?= e(http_build_query(array_merge($_GET, ['periodo' => $key]))) ?>" class="filter-chip <?= $periodo === $key ? 'active' : '' ?>"><?= e($opt['label']) ?></a>
                <?php endforeach; ?>
            </div>
            <div class="bar-chart-scroll">
                <div class="bar-chart">
                    <?php foreach ($chart_points as $point): ?>
                        <?php $height_pct = $max_chart_value > 0 ? max(4, round(($point['value'] / $max_chart_value) * 100)) : 4; ?>
                        <div class="bar-col">
                            <div class="bar-value"><?= $point['value'] > 0 ? format_price($point['value']) : '—' ?></div>
                            <div class="bar-track">
                                <div class="bar-fill <?= $point['value'] > 0 ? '' : 'empty' ?>" style="height:<?= $height_pct ?>%;"></div>
                            </div>
                            <div class="bar-label"><?= e($point['label']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div>
            <?php $active_filter_count = ($codigo !== '' ? 1 : 0) + ($cliente !== '' ? 1 : 0) + (in_array($estado, ['pending', 'paid', 'cancelled'], true) ? 1 : 0) + ($fecha_desde !== '' ? 1 : 0) + ($fecha_hasta !== '' ? 1 : 0); ?>
            <div class="admin-topbar" style="margin-bottom:12px;">
                <h3 class="mt-0"><?= $has_filters ? 'Pedidos' : 'Pedidos recientes' ?></h3>
                <button type="button" class="btn-filter-toggle" data-toggle-target="filtersCard" data-label-open="Ocultar filtros" data-label-closed="Mostrar filtros">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                    <span class="filter-toggle-label"><?= $has_filters ? 'Ocultar filtros' : 'Mostrar filtros' ?></span>
                    <?php if ($active_filter_count > 0): ?><span class="filter-count-badge"><?= $active_filter_count ?></span><?php endif; ?>
                </button>
            </div>

            <form method="get" class="filter-panel" id="filtersCard" style="<?= $has_filters ? '' : 'display:none;' ?>">
                <div class="filter-panel-head">
                    <div class="filter-panel-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                        Filtrar resultados
                    </div>
                    <?php if ($has_filters): ?>
                        <a href="<?= admin_url('index.php') ?>" class="filter-clear-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                            Limpiar filtros
                        </a>
                    <?php endif; ?>
                </div>
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="codigo">Código de pedido</label>
                        <input type="text" id="codigo" name="codigo" class="form-control" value="<?= e($codigo) ?>" placeholder="MPS-...">
                    </div>
                    <div class="form-group">
                        <label for="cliente">Cliente (nombre o correo)</label>
                        <input type="text" id="cliente" name="cliente" class="form-control" value="<?= e($cliente) ?>" placeholder="nombre@ejemplo.com">
                    </div>
                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado" class="form-control">
                            <option value="">Todos</option>
                            <option value="pending" <?= $estado === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="paid" <?= $estado === 'paid' ? 'selected' : '' ?>>Pagado</option>
                            <option value="cancelled" <?= $estado === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="fecha_desde">Desde</label>
                        <input type="date" id="fecha_desde" name="fecha_desde" class="form-control" value="<?= e($fecha_desde) ?>">
                    </div>
                    <div class="form-group">
                        <label for="fecha_hasta">Hasta</label>
                        <input type="date" id="fecha_hasta" name="fecha_hasta" class="form-control" value="<?= e($fecha_hasta) ?>">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Aplicar filtros</button>
                </div>
            </form>

            <div class="grid-toolbar">
                <div></div>
                <?php render_admin_records_count($total_records, 'pedidos'); ?>
                <?php render_admin_per_page_select($per_page); ?>
            </div>

            <table class="admin-table">
                <thead><tr><th>Código</th><th>Cliente</th><th>Total</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php foreach ($recent_orders as $order): ?>
                    <tr>
                        <td><a href="<?= admin_url('order_detail.php?id=' . (int)$order['id']) ?>"><?= e($order['order_code']) ?></a></td>
                        <td><?= e($order['customer_name']) ?></td>
                        <td><?= format_price($order['total']) ?></td>
                        <td><span class="badge-status <?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span></td>
                        <td><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent_orders): ?>
                    <tr><td colspan="5" class="text-center"><?= $has_filters ? 'No hay pedidos que coincidan con esos filtros.' : 'Aún no hay pedidos.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php render_admin_pagination($page, $total_pages); ?>
        </div>
    </div>

    <div class="dashboard-side">
        <div class="section-card">
            <div class="section-card-head">
                <div>
                    <h3>Estado de pedidos</h3>
                    <p>Distribución · <?= e($period['label']) ?></p>
                </div>
            </div>
            <?php if ($status_total > 0): ?>
                <div class="donut-wrap">
                    <div class="donut" style="background:<?= $conic_gradient ?>;">
                        <div class="donut-center">
                            <strong><?= (int)$status_total ?></strong>
                            <span>pedidos</span>
                        </div>
                    </div>
                    <div class="donut-legend">
                        <?php foreach ($status_segments as $seg): ?>
                            <div class="legend-item">
                                <span class="legend-dot" style="background:<?= $seg['color'] ?>;"></span>
                                <?= e($seg['label']) ?>
                                <span class="legend-count">(<?= (int)$status_counts[$seg['key']] ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <p class="page-subtitle">Aún no hay pedidos registrados.</p>
            <?php endif; ?>
        </div>

        <div class="section-card">
            <div class="section-card-head">
                <div>
                    <h3>Productos más vendidos</h3>
                    <p>Por unidades · pedidos pagados</p>
                </div>
            </div>
            <div class="customer-list">
                <?php foreach ($best_selling_products as $i => $p): ?>
                    <a href="<?= admin_url('product_form.php?id=' . (int)$p['product_id']) ?>" class="customer-card">
                        <div class="customer-avatar"><?= (int)$i + 1 ?></div>
                        <div class="customer-info">
                            <div class="customer-name"><?= e($p['product_name']) ?></div>
                            <div class="customer-email"><?= (int)$p['qty_sold'] ?> unidad<?= (int)$p['qty_sold'] === 1 ? '' : 'es' ?> vendida<?= (int)$p['qty_sold'] === 1 ? '' : 's' ?></div>
                        </div>
                        <div class="customer-amount"><?= format_price($p['revenue']) ?></div>
                    </a>
                <?php endforeach; ?>
                <?php if (!$best_selling_products): ?>
                    <p class="page-subtitle">Aún no hay productos vendidos.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-card">
            <div class="section-card-head">
                <div>
                    <h3>Mejores clientes</h3>
                    <p>Por monto total pagado</p>
                </div>
            </div>
            <div class="customer-list">
                <?php foreach ($top_customers as $c): ?>
                    <?php
                        $name_parts = preg_split('/\s+/', trim($c['customer_name']) ?: '?');
                        $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts) - 1], 0, 1));
                    ?>
                    <div class="customer-card">
                        <div class="customer-avatar"><?= e($initials) ?></div>
                        <div class="customer-info">
                            <div class="customer-name"><?= e($c['customer_name']) ?></div>
                            <div class="customer-email"><?= e($c['customer_email']) ?></div>
                        </div>
                        <div class="customer-amount">
                            <?= format_price($c['total_spent']) ?>
                            <small><?= (int)$c['orders_count'] ?> pedido<?= (int)$c['orders_count'] === 1 ? '' : 's' ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$top_customers): ?>
                    <p class="page-subtitle">Aún no hay clientes con pedidos pagados.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
