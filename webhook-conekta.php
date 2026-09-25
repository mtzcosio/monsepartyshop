<?php
/**
 * Webhook de Conekta. Se le notifica aquí cuando cambia el estado de una orden
 * (por ejemplo, cuando un cliente paga su referencia OXXO o hace la transferencia SPEI).
 *
 * Por seguridad, nunca confiamos en el contenido del webhook por sí solo: al recibirlo,
 * consultamos directamente la API de Conekta con nuestra llave privada para confirmar
 * el estado real antes de marcar algo como pagado.
 */
require_once __DIR__ . '/includes/init.php';

http_response_code(200); // Conekta solo necesita un 200 para no reintentar.

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

$conekta_order_id = $payload['data']['object']['id'] ?? null;
if (!$conekta_order_id) {
    exit;
}

$result = conekta_get_order($conekta_order_id);
if (!$result['ok']) {
    exit;
}

$conekta_order = $result['data'];
$charge = $conekta_order['charges']['data'][0] ?? [];
$is_paid = ($charge['status'] ?? '') === 'paid';

if (!$is_paid) {
    exit;
}

$db = get_db();
$stmt = $db->prepare("SELECT * FROM orders WHERE conekta_order_id = :id LIMIT 1");
$stmt->execute(['id' => $conekta_order_id]);
$order = $stmt->fetch();

if (!$order || $order['status'] === 'paid') {
    exit;
}

$update = $db->prepare("UPDATE orders SET status = 'paid' WHERE id = :id");
$update->execute(['id' => $order['id']]);

$items_stmt = $db->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
$items_stmt->execute(['order_id' => $order['id']]);
$items = $items_stmt->fetchAll();

send_order_confirmation_email($order, $items);
