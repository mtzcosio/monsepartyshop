<?php
require_once __DIR__ . '/includes/init.php';

$token = $_GET['token'] ?? '';
$item_id = (int)($_GET['item'] ?? 0);
$file_id = (int)($_GET['file'] ?? 0);

$db = get_db();
$stmt = $db->prepare("SELECT * FROM orders WHERE download_token = :token AND status = 'paid' LIMIT 1");
$stmt->execute(['token' => $token]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(403);
    die('Enlace de descarga inválido.');
}

$item_stmt = $db->prepare(
    "SELECT oi.* FROM order_items oi
     WHERE oi.id = :item_id AND oi.order_id = :order_id LIMIT 1"
);
$item_stmt->execute(['item_id' => $item_id, 'order_id' => $order['id']]);
$item = $item_stmt->fetch();

if (!$item) {
    http_response_code(404);
    die('Producto no encontrado en este pedido.');
}

$file_stmt = $db->prepare('SELECT * FROM product_files WHERE id = :file_id AND product_id = :product_id LIMIT 1');
$file_stmt->execute(['file_id' => $file_id, 'product_id' => $item['product_id']]);
$file = $file_stmt->fetch();

if (!$file) {
    http_response_code(404);
    die('Archivo no encontrado para este producto.');
}

// Se calcula con el reloj del propio servidor de base de datos (NOW())
// para evitar desfases si el servidor web tiene otra hora.
$expiration_days = (int)get_setting('download_expiration', 30);
$exp_stmt = $db->prepare('SELECT (NOW() > DATE_ADD(:created_at, INTERVAL :days DAY)) AS is_expired');
$exp_stmt->execute(['created_at' => $order['created_at'], 'days' => $expiration_days]);
if ((bool)$exp_stmt->fetchColumn()) {
    die('Este enlace de descarga ha vencido.');
}

$max_downloads = (int)get_setting('max_downloads', 5);
if ($item['download_count'] >= $max_downloads) {
    die('Alcanzaste el número máximo de descargas para esta plantilla.');
}

$file_path = __DIR__ . '/uploads/downloads/' . basename($file['file_name']);
if (!file_exists($file_path)) {
    die('El archivo no se encuentra disponible en este momento.');
}

$update = $db->prepare('UPDATE order_items SET download_count = download_count + 1 WHERE id = :id');
$update->execute(['id' => $item['id']]);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: must-revalidate');
readfile($file_path);
exit;
