<?php
$page_title = '¡Compra confirmada!';
require_once __DIR__ . '/includes/init.php';

$codigo = $_GET['codigo'] ?? '';
$db = get_db();
$stmt = $db->prepare("SELECT * FROM orders WHERE order_code = :code LIMIT 1");
$stmt->execute(['code' => $codigo]);
$order = $stmt->fetch();

require_once __DIR__ . '/includes/header.php';

if (!$order) {
    echo '<div class="empty-state"><p>No encontramos ese pedido. ¿Tienes tu número de pedido y correo a la mano? <a href="' . e(base_url('rastrear-pedido.php')) . '">Búscalo aquí</a>.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Si el cliente regresa de Stripe Checkout, confirmamos aquí mismo para
// que vea el resultado al instante (el webhook es el respaldo confiable).
$stripe_session_id = $_GET['session_id'] ?? '';
if ($order['status'] === 'pending' && $order['payment_gateway'] === 'stripe' && $stripe_session_id && $stripe_session_id === $order['stripe_session_id']) {
    $session_result = stripe_get_checkout_session($stripe_session_id);
    if ($session_result['ok'] && ($session_result['data']['payment_status'] ?? '') === 'paid') {
        $db->prepare("UPDATE orders SET status = 'paid' WHERE id = :id")->execute(['id' => $order['id']]);
        $order['status'] = 'paid';

        $pending_items_stmt = $db->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
        $pending_items_stmt->execute(['order_id' => $order['id']]);
        send_order_confirmation_email($order, $pending_items_stmt->fetchAll());
    }
}

$items_stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
$items_stmt->execute(['order_id' => $order['id']]);
$order_items = $items_stmt->fetchAll();

$files_stmt = $db->prepare('SELECT * FROM product_files WHERE product_id = :product_id ORDER BY sort_order ASC');
foreach ($order_items as &$item) {
    $files_stmt->execute(['product_id' => $item['product_id']]);
    $item['files'] = $files_stmt->fetchAll();
}
unset($item);

// El vencimiento se calcula con el reloj del propio servidor de base de
// datos (NOW()) para evitar desfases si el servidor web tiene otra hora.
$expiration_days = (int)get_setting('download_expiration', 30);
$exp_stmt = $db->prepare(
    "SELECT (NOW() > DATE_ADD(:created_at1, INTERVAL :days1 DAY)) AS is_expired,
            DATE_FORMAT(DATE_ADD(:created_at2, INTERVAL :days2 DAY), '%d/%m/%Y') AS expires_at_formatted"
);
$exp_stmt->execute([
    'created_at1' => $order['created_at'], 'days1' => $expiration_days,
    'created_at2' => $order['created_at'], 'days2' => $expiration_days,
]);
$exp_row = $exp_stmt->fetch();
$expired = (bool)$exp_row['is_expired'];
$expires_at_formatted = $exp_row['expires_at_formatted'];
?>

<section class="section">
    <div class="container" style="max-width:700px;">
        <?php if ($order['status'] === 'pending' && in_array($order['payment_method'], ['oxxo_cash', 'spei'], true)): ?>
            <div class="card-box text-center">
                <h1>¡Ya casi, <?= e($order['customer_name']) ?>!</h1>
                <p>Tu pedido está reservado. Solo falta que completes tu pago para que tus plantillas estén disponibles.</p>
                <p style="opacity:0.7;font-size:14px;">Número de pedido: <strong><?= e($order['order_code']) ?></strong></p>
            </div>

            <div class="card-box" style="margin-top:24px;">
                <?php if ($order['payment_method'] === 'oxxo_cash'): ?>
                    <h3>Paga en cualquier tienda OXXO</h3>
                    <p>Presenta esta referencia en caja:</p>
                <?php else: ?>
                    <h3>Transfiere por SPEI</h3>
                    <p>Usa esta CLABE desde tu banca en línea:</p>
                <?php endif; ?>
                <p style="font-size:24px;font-weight:700;color:var(--primary-color);letter-spacing:1px;"><?= e($order['payment_reference']) ?></p>
                <div class="cart-summary-row"><span>Monto a pagar</span><span style="font-weight:700;"><?= format_price($order['total']) ?></span></div>
                <?php if ($order['payment_expires_at']): ?>
                    <div class="cart-summary-row"><span>Vence</span><span><?= e(date('d/m/Y H:i', strtotime($order['payment_expires_at']))) ?></span></div>
                <?php endif; ?>
                <p style="font-size:13px;opacity:0.7;margin-top:16px;">
                    En cuanto confirmemos tu pago te avisaremos por correo y tus plantillas estarán listas para descargar en esta misma página.
                </p>
            </div>
        <?php elseif ($order['status'] === 'pending'): ?>
            <div class="card-box text-center">
                <h1>Estamos confirmando tu pago…</h1>
                <p>Tu pedido fue registrado, pero aún no confirmamos el pago. Esto puede tardar unos minutos.</p>
                <p>Te enviaremos un correo en cuanto esté listo, y también puedes recargar esta página más tarde.</p>
                <p style="opacity:0.7;font-size:14px;">Número de pedido: <strong><?= e($order['order_code']) ?></strong></p>
            </div>
        <?php elseif ($order['status'] === 'cancelled'): ?>
            <div class="card-box text-center">
                <h1>Pedido cancelado</h1>
                <p>Este pedido fue cancelado. Si crees que esto es un error, contáctanos.</p>
                <p style="opacity:0.7;font-size:14px;">Número de pedido: <strong><?= e($order['order_code']) ?></strong></p>
            </div>
        <?php else: ?>
            <div class="card-box text-center">
                <h1>¡Hola, <?= e($order['customer_name']) ?>!</h1>
                <p>Gracias por comprar en Monse Party Shop. 🎉</p>
                <p>Tu pago ha sido confirmado y tu plantilla ya está disponible.</p>
                <p style="opacity:0.7;font-size:14px;">Número de pedido: <strong><?= e($order['order_code']) ?></strong></p>
            </div>

            <div class="card-box" style="margin-top:24px;">
                <h3>Tus plantillas</h3>
                <?php foreach ($order_items as $item): ?>
                    <div style="padding:14px 0;border-bottom:1px solid rgba(75,68,83,0.08);">
                        <div style="font-weight:700;margin-bottom:8px;"><?= e($item['product_name']) ?></div>
                        <?php if ($expired): ?>
                            <span style="opacity:0.6;font-size:13px;">Enlace de descarga vencido</span>
                        <?php elseif (empty($item['files'])): ?>
                            <span style="opacity:0.6;font-size:13px;">Archivo en preparación</span>
                        <?php else: ?>
                            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                <?php foreach ($item['files'] as $file): ?>
                                    <a href="<?= base_url('descargar.php?token=' . urlencode($order['download_token']) . '&item=' . (int)$item['id'] . '&file=' . (int)$file['id']) ?>" class="btn btn-primary btn-sm">
                                        DESCARGAR<?= $file['label'] ? ' ' . e(mb_strtoupper($file['label'])) : ' MI PLANTILLA' ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <p style="font-size:13px;opacity:0.7;margin-top:16px;">
                    Podrás descargar tus plantillas hasta el <?= e($expires_at_formatted) ?>.
                    Te enviamos también un correo con este enlace.
                </p>
            </div>
        <?php endif; ?>

        <p class="text-center" style="margin-top:24px;">¡Esperamos que disfrutes creando algo especial! 💕</p>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
