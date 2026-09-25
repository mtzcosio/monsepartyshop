<?php
/**
 * Biblioteca de archivos: repositorio centralizado de imágenes/archivos
 * reutilizables en cualquier parte del portal (productos, configuración,
 * plantillas de correo, etc.) sin necesidad de volver a subir el mismo
 * archivo cada vez.
 */

define('MEDIA_UPLOAD_DIR', __DIR__ . '/../../uploads/media/');
define('MEDIA_UPLOAD_URL', 'uploads/media/');

/** Extensiones permitidas para subir a la biblioteca (bloquea cualquier script ejecutable). */
function media_allowed_extensions() {
    return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'zip'];
}

function media_image_extensions() {
    return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
}

function media_is_image_ext($ext) {
    return in_array(strtolower($ext), media_image_extensions(), true);
}

function media_human_filesize($bytes) {
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) { return round($bytes / 1048576, 1) . ' MB'; }
    if ($bytes >= 1024) { return round($bytes / 1024, 1) . ' KB'; }
    return $bytes . ' B';
}

/** Ícono (SVG inline) según el tipo de archivo, para los que no son imagen. */
function media_file_icon($ext) {
    $ext = strtolower($ext);
    if ($ext === 'pdf') {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
    }
    if (in_array($ext, ['doc', 'docx'], true)) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>';
    }
    if (in_array($ext, ['xls', 'xlsx', 'csv'], true)) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg>';
    }
    if ($ext === 'zip') {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-2-2h-6l-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2z"/><line x1="12" y1="10" x2="12" y2="16"/></svg>';
    }
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
}

/** Categorías existentes (para el filtro y el autocompletado), ordenadas alfabéticamente. */
function media_category_names() {
    $rows = get_db()->query('SELECT DISTINCT category FROM media_library ORDER BY category ASC')->fetchAll();
    return array_column($rows, 'category');
}

/**
 * Procesa un único archivo subido (una entrada de $_FILES ya desglosada) y lo
 * guarda en la biblioteca. Devuelve ['success'=>bool, 'error'=>?string, 'row'=>?array].
 */
function media_handle_upload($tmp_name, $original_name, $category, $alt_text) {
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if (!in_array($ext, media_allowed_extensions(), true)) {
        return ['success' => false, 'error' => "El archivo \"{$original_name}\" tiene un formato no permitido.", 'row' => null];
    }

    $category = trim($category) !== '' ? trim($category) : 'Sin categoría';
    $filename = uniqid('media_') . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($original_name, PATHINFO_FILENAME)) . '.' . $ext;
    $dest = MEDIA_UPLOAD_DIR . $filename;

    if (!move_uploaded_file($tmp_name, $dest)) {
        return ['success' => false, 'error' => "No se pudo guardar \"{$original_name}\".", 'row' => null];
    }

    $mime = @mime_content_type($dest) ?: '';
    $size = @filesize($dest) ?: 0;
    $file_path = MEDIA_UPLOAD_URL . $filename;

    $stmt = get_db()->prepare(
        'INSERT INTO media_library (filename, original_name, file_path, file_type, mime_type, file_size, category, alt_text)
         VALUES (:filename, :original_name, :file_path, :file_type, :mime_type, :file_size, :category, :alt_text)'
    );
    $stmt->execute([
        'filename' => $filename,
        'original_name' => $original_name,
        'file_path' => $file_path,
        'file_type' => $ext,
        'mime_type' => $mime,
        'file_size' => $size,
        'category' => $category,
        'alt_text' => trim($alt_text),
    ]);

    $id = (int)get_db()->lastInsertId();
    $stmt = get_db()->prepare('SELECT * FROM media_library WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return ['success' => true, 'error' => null, 'row' => $stmt->fetch()];
}

/** Elimina un archivo de la biblioteca (fila + archivo físico). */
function media_delete($id) {
    $stmt = get_db()->prepare('SELECT * FROM media_library WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) { return false; }

    $path = MEDIA_UPLOAD_DIR . basename($row['filename']);
    if (file_exists($path)) { @unlink($path); }

    get_db()->prepare('DELETE FROM media_library WHERE id = :id')->execute(['id' => $id]);
    return true;
}
