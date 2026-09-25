<?php
/**
 * Webhook de Stripe. Confirma el pago de una sesión de Checkout cuando
 * Stripe nos avisa que se completó (evento checkout.session.completed).
 *
 * La firma del webhook (header Stripe-Signature) se verifica con el
 * secreto configurado en Configuración antes de confiar en el contenido.
 */
require_once __DIR__ . '/includes/init.php';

$raw = file_get_contents('php://input');
$signature_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$webhook_secret = get_setting('stripe_webhook_secret', '');

if (!stripe_verify_webhook_signature($raw, $signature_header, $webhook_secret)) {
    http_response_code(400);
    exit;
}

http_response_code(200); // Ya validado; confirmamos recepción para evitar reintentos.

$event = json_decode($raw, true);
$event_type = $event['type'] ?? '';

if (!in_array($event_type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
    exit;
}

$session = $event['data']['object'] ?? [];
$session_id = $session['id'] ?? null;
$payment_status = $session['payment_status'] ?? '';

if (!$session_id || $payment_status !== 'paid') {
    exit;
}

$db = get_db();
$stmt = $db->prepare('SELECT * FROM orders WHERE stripe_session_id = :id LIMIT 1');
$stmt->execute(['id' => $session_id]);
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
