<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM orders WHERE id = :id');
$stmt->execute(['id' => $id]);
$order = $stmt->fetch();

if (!$order) {
    flash_set('Pedido no encontrado.', 'error');
    redirect(admin_url('orders.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['update_status'])) {
        $status = in_array($_POST['status'], ['pending', 'paid', 'cancelled'], true) ? $_POST['status'] : $order['status'];
        $stmt = $db->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
        flash_set('Estado del pedido actualizado.');
    } elseif (isset($_POST['resend_email'])) {
        $items_stmt = $db->prepare('SELECT * FROM order_items WHERE order_id = :id');
        $items_stmt->execute(['id' => $id]);
        send_order_confirmation_email($order, $items_stmt->fetchAll());
        flash_set('Correo de confirmación reenviado.');
    } elseif (isset($_POST['send_payment_reminder'])) {
        $result = send_payment_reminder_email($order);
        flash_set(
            $result['success'] ? 'Recordatorio de pago enviado.' : ('No se pudo enviar el recordatorio: ' . $result['message']),
            $result['success'] ? 'success' : 'error'
        );
    } elseif (isset($_POST['update_download_count'])) {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $new_count = max(0, (int)($_POST['download_count'] ?? 0));
        $update_stmt = $db->prepare('UPDATE order_items SET download_count = :count WHERE id = :item_id AND order_id = :order_id');
        $update_stmt->execute(['count' => $new_count, 'item_id' => $item_id, 'order_id' => $id]);
        flash_set('Descargas actualizadas.');
    }
    redirect(admin_url('order_detail.php?id=' . $id));
}

$items_stmt = $db->prepare('SELECT * FROM order_items WHERE order_id = :id');
$items_stmt->execute(['id' => $id]);
$items = $items_stmt->fetchAll();

$page_title = 'Pedido ' . $order['order_code'];
require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>Pedido <?= e($order['order_code']) ?></h1>

<div class="card-box" style="margin-bottom:24px;">
    <p><strong>Cliente:</strong> <?= e($order['customer_name']) ?> (<?= e($order['customer_email']) ?>)</p>
    <p><strong>Fecha:</strong> <?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></p>
    <p><strong>Estado:</strong> <span class="badge-status <?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span></p>
    <p><strong>Método de pago:</strong> <?= e(['card' => 'Tarjeta', 'oxxo_cash' => 'OXXO Pay', 'spei' => 'SPEI'][$order['payment_method']] ?? 'Tarjeta (demo)') ?></p>
    <?php if ($order['payment_reference']): ?>
        <p><strong>Referencia de pago:</strong> <?= e($order['payment_reference']) ?></p>
    <?php endif; ?>

    <form method="post" style="display:flex;gap:12px;align-items:center;margin-top:16px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <select name="status" class="form-control" style="max-width:200px;">
            <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pendiente</option>
            <option value="paid" <?= $order['status'] === 'paid' ? 'selected' : '' ?>>Pagado</option>
            <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
        </select>
        <button type="submit" name="update_status" class="btn btn-primary btn-sm">Actualizar estado</button>
    </form>
    <form method="post" style="margin-top:12px;display:inline-block;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit" name="resend_email" class="btn btn-secondary btn-sm">📧 Reenviar correo de confirmación</button>
    </form>
    <?php if ($order['status'] === 'pending'): ?>
    <form method="post" style="margin-top:12px;display:inline-block;margin-left:10px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit" name="send_payment_reminder" class="btn btn-secondary btn-sm">⏰ Enviar recordatorio de pago</button>
    </form>
    <?php endif; ?>
</div>

<?php $gateway_labels = ['conekta' => 'Conekta', 'stripe' => 'Stripe', 'demo' => 'Demo (sin pasarela real)']; ?>
<h3>Rastreo del pago</h3>
<table class="admin-table" style="margin-bottom:24px;">
    <tbody>
        <tr>
            <th style="width:240px;">Pasarela de pago</th>
            <td><?= e($gateway_labels[$order['payment_gateway']] ?? ucfirst($order['payment_gateway'])) ?></td>
        </tr>
        <?php if ($order['payment_gateway'] === 'conekta' && $order['conekta_order_id']): ?>
        <tr>
            <th>Código de rastreo (Conekta)</th>
            <td><code><?= e($order['conekta_order_id']) ?></code></td>
        </tr>
        <?php elseif ($order['payment_gateway'] === 'stripe' && $order['stripe_session_id']): ?>
        <tr>
            <th>Código de rastreo (Stripe)</th>
            <td>
                <code><?= e($order['stripe_session_id']) ?></code>
                <br><span style="font-size:12px;opacity:0.7;">Es solo la sesión más reciente: si se envió un recordatorio de pago, las anteriores ya no quedan aquí, pero siguen en Stripe.</span>
            </td>
        </tr>
        <?php else: ?>
        <tr>
            <th>Código de rastreo</th>
            <td style="opacity:0.6;">No aplica (pedido en modo demo o sin sesión de pago registrada).</td>
        </tr>
        <?php endif; ?>
        <?php if ($order['payment_gateway'] === 'stripe'): ?>
        <tr>
            <th>Descripción de pago en Stripe</th>
            <td>
                <?= e(stripe_payment_description($order['order_code'], $order['customer_name'])) ?>
                <br><span style="font-size:12px;opacity:0.7;">Así debe verse este pago en la lista de "Pagos" del Dashboard de Stripe — búscalo por ese texto o por el código de rastreo de arriba.</span>
            </td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<h3>Productos</h3>
<p class="page-subtitle" style="margin-top:-8px;">
    Límite global de descargas por producto: <strong><?= (int)get_setting('max_downloads', 5) ?></strong>
    (Configuración → Ventas). Baja el contador de un producto para darle más descargas al cliente, o súbelo para bloquear descargas adicionales.
</p>
<table class="admin-table">
    <thead><tr><th>Producto</th><th>Precio</th><th>Cantidad</th><th>Descargas usadas</th></tr></thead>
    <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
            <td><?= e($item['product_name']) ?></td>
            <td><?= format_price($item['price']) ?></td>
            <td><?= (int)$item['quantity'] ?></td>
            <td>
                <form method="post" style="display:flex;gap:8px;align-items:center;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                    <input type="number" name="download_count" min="0" value="<?= (int)$item['download_count'] ?>" class="form-control" style="width:80px;">
                    <button type="submit" name="update_download_count" class="btn btn-secondary btn-sm">Guardar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="cart-summary" style="margin-top:24px;">
    <div class="cart-summary-row"><span>Subtotal</span><span><?= format_price($order['subtotal']) ?></span></div>
    <div class="cart-summary-row"><span>Impuestos</span><span><?= format_price($order['tax']) ?></span></div>
    <div class="cart-summary-row" style="font-weight:700;"><span>Total</span><span><?= format_price($order['total']) ?></span></div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
