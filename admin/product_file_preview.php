<?php
/**
 * Previsualización de un archivo descargable, solo para administradores autenticados.
 * uploads/downloads/ está bloqueado a nivel de Apache (ver su .htaccess) para forzar
 * que los clientes pasen siempre por descargar.php; este endpoint lee el archivo
 * desde PHP (que sí puede acceder al filesystem) y lo sirve directamente al admin.
 */
require_once __DIR__ . '/includes/auth.php';
require_admin_login();

$file_id = (int)($_GET['file_id'] ?? 0);

$stmt = get_db()->prepare('SELECT * FROM product_files WHERE id = :id');
$stmt->execute(['id' => $file_id]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    die('Archivo no encontrado.');
}

$path = __DIR__ . '/../uploads/downloads/' . basename($file['file_name']);
if (!file_exists($path)) {
    http_response_code(404);
    die('El archivo no se encuentra disponible en el servidor.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime_types = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
    'zip' => 'application/zip',
    'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'csv' => 'text/csv',
];
$mime = $mime_types[$ext] ?? 'application/octet-stream';

// Solo PDF e imágenes rasterizadas se muestran "inline" en el navegador; el resto se
// descarga. Se excluye SVG a propósito: puede contener <script> y el navegador lo
// ejecutaría si se abre como documento HTML de nivel superior.
$inline_ext = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
$disposition = in_array($ext, $inline_ext, true) ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($file['file_name']) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
