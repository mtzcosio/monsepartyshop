<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();
require_once __DIR__ . '/includes/media_library.php';

$db = get_db();
$errors = [];
$upload_success_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Tu sesión expiró, intenta de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_upload'])) {
    $category = trim($_POST['category'] ?? '');
    $alt_text = trim($_POST['alt_text'] ?? '');

    if (empty($_FILES['media_files']) || empty($_FILES['media_files']['name'][0])) {
        $errors[] = 'Selecciona al menos un archivo para subir.';
    } else {
        foreach ($_FILES['media_files']['name'] as $i => $original_name) {
            if (empty($original_name)) { continue; }
            if ($_FILES['media_files']['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = "No se pudo subir \"{$original_name}\".";
                continue;
            }
            $result = media_handle_upload($_FILES['media_files']['tmp_name'][$i], $original_name, $category, $alt_text);
            if ($result['success']) {
                $upload_success_count++;
            } else {
                $errors[] = $result['error'];
            }
        }
        if ($upload_success_count && !$errors) {
            flash_set($upload_success_count === 1 ? 'Archivo subido a la biblioteca.' : "{$upload_success_count} archivos subidos a la biblioteca.");
            redirect(admin_url('media_library.php'));
        } elseif ($upload_success_count) {
            flash_set("{$upload_success_count} archivo(s) subido(s), pero hubo errores con otros.", 'error');
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_edit'])) {
    $id = (int)($_POST['id'] ?? 0);
    $category = trim($_POST['category'] ?? '') ?: 'Sin categoría';
    $alt_text = trim($_POST['alt_text'] ?? '');
    if ($id) {
        $stmt = $db->prepare('UPDATE media_library SET category = :category, alt_text = :alt_text WHERE id = :id');
        $stmt->execute(['category' => $category, 'alt_text' => $alt_text, 'id' => $id]);
        flash_set('Archivo actualizado.');
        redirect(admin_url('media_library.php'));
    }
}

$q = trim($_GET['q'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');
$tipo = $_GET['tipo'] ?? '';
$has_filters = ($q !== '' || $categoria !== '' || $tipo !== '');

$where_sql = ' WHERE 1=1';
$params = [];
if ($q !== '') {
    $where_sql .= ' AND original_name LIKE :q';
    $params['q'] = '%' . $q . '%';
}
if ($categoria !== '') {
    $where_sql .= ' AND category = :categoria';
    $params['categoria'] = $categoria;
}
$active_filter_count = ($q !== '' ? 1 : 0) + ($categoria !== '' ? 1 : 0) + ($tipo !== '' ? 1 : 0);

$stmt = $db->prepare('SELECT * FROM media_library' . $where_sql . ' ORDER BY category ASC, created_at DESC');
$stmt->execute($params);
$all_files = $stmt->fetchAll();

if ($tipo === 'imagenes') {
    $all_files = array_values(array_filter($all_files, function ($f) { return media_is_image_ext($f['file_type']); }));
} elseif ($tipo === 'documentos') {
    $all_files = array_values(array_filter($all_files, function ($f) { return !media_is_image_ext($f['file_type']); }));
}

$total_records = count($all_files);
$total_size = array_sum(array_column($all_files, 'file_size'));

$grouped_files = [];
foreach ($all_files as $f) {
    $grouped_files[$f['category']][] = $f;
}

$all_categories = media_category_names();

$page_title = 'Biblioteca de archivos';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
    <div>
        <h1 class="mt-0">Biblioteca de archivos</h1>
        <p class="page-subtitle">Repositorio centralizado de imágenes y archivos, listos para reutilizar en cualquier sección del portal sin volver a subirlos.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm" id="openUploadModalBtn">+ Subir archivos</button>
</div>

<?php foreach ($errors as $error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endforeach; ?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= $total_records ?></div>
            <div class="stat-label">Archivos<?= $has_filters ? ' (filtrados)' : '' ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= media_human_filesize($total_size) ?></div>
            <div class="stat-label">Espacio utilizado</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41L11 3.83A2 2 0 0 0 9.59 3.2L4 3a1 1 0 0 0-1 1l.2 5.59a2 2 0 0 0 .58 1.41l9.59 9.59a2 2 0 0 0 2.83 0l4.39-4.39a2 2 0 0 0 0-2.83z"/><circle cx="7.5" cy="7.5" r="1.1"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= count($all_categories) ?></div>
            <div class="stat-label">Categorías</div>
        </div>
    </div>
</div>

<div class="admin-topbar" style="margin-bottom:12px;">
    <div></div>
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
            <a href="<?= admin_url('media_library.php') ?>" class="filter-clear-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                Limpiar filtros
            </a>
        <?php endif; ?>
    </div>
    <div class="filter-grid">
        <div class="form-group">
            <label for="q">Nombre del archivo</label>
            <input type="text" id="q" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Buscar archivo...">
        </div>
        <div class="form-group">
            <label for="categoria">Categoría</label>
            <select id="categoria" name="categoria" class="form-control">
                <option value="">Todas</option>
                <?php foreach ($all_categories as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $categoria === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="tipo">Tipo</label>
            <select id="tipo" name="tipo" class="form-control">
                <option value="">Todos</option>
                <option value="imagenes" <?= $tipo === 'imagenes' ? 'selected' : '' ?>>Imágenes</option>
                <option value="documentos" <?= $tipo === 'documentos' ? 'selected' : '' ?>>Documentos</option>
            </select>
        </div>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary btn-sm">Aplicar filtros</button>
    </div>
</form>

<?php if ($grouped_files): ?>
    <?php foreach ($grouped_files as $category => $files): ?>
        <div class="section-card" style="margin-bottom:20px;">
            <div class="var-accordion-item open" data-category style="border-bottom:none;">
                <div class="var-accordion-header" style="padding-top:0;">
                    <span><?= e($category) ?> <span class="filter-count-badge" style="margin-left:6px;"><?= count($files) ?></span></span>
                    <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                </div>
                <div class="var-accordion-body" style="padding-bottom:0;">
                    <div class="media-grid">
                        <?php foreach ($files as $f): ?>
                            <div class="media-card">
                                <div class="media-thumb">
                                    <?php if (media_is_image_ext($f['file_type'])): ?>
                                        <img src="<?= e(base_url($f['file_path'])) ?>" alt="<?= e($f['alt_text']) ?>" loading="lazy">
                                    <?php else: ?>
                                        <?= media_file_icon($f['file_type']) ?>
                                    <?php endif; ?>
                                    <span class="media-type-tag"><?= e($f['file_type']) ?></span>
                                </div>
                                <div class="media-info">
                                    <div class="media-name" title="<?= e($f['original_name']) ?>"><?= e($f['original_name']) ?></div>
                                    <div class="media-meta"><?= media_human_filesize($f['file_size']) ?> · <?= e(date('d/m/Y', strtotime($f['created_at']))) ?></div>
                                </div>
                                <div class="media-actions">
                                    <button type="button" class="icon-action-btn" title="Copiar URL" data-copy-text="<?= e('http://' . $_SERVER['HTTP_HOST'] . base_url($f['file_path'])) ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                    </button>
                                    <a class="icon-action-btn" href="<?= e(base_url($f['file_path'])) ?>" target="_blank" title="Ver / descargar">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <button type="button" class="icon-action-btn js-edit-media-btn" title="Editar"
                                            data-id="<?= (int)$f['id'] ?>" data-category="<?= e($f['category']) ?>" data-alt="<?= e($f['alt_text']) ?>" data-name="<?= e($f['original_name']) ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button type="button" class="icon-action-btn danger js-delete-media-btn" title="Eliminar"
                                            data-id="<?= (int)$f['id'] ?>" data-name="<?= e($f['original_name']) ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="section-card">
        <div class="var-empty"><?= $has_filters ? 'No hay archivos que coincidan con esos filtros.' : 'Aún no hay archivos en la biblioteca. Usa "+ Subir archivos" para agregar el primero.' ?></div>
    </div>
<?php endif; ?>

<!-- Modal: subir archivos -->
<div class="modal-overlay" id="uploadMediaModal">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h3>Subir archivos</h3>
                <p>Se agregan a la biblioteca y quedan listos para usarse donde los necesites.</p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="post" enctype="multipart/form-data" id="uploadMediaForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="do_upload" value="1">
            <div class="modal-body">
                <div class="media-dropzone" id="mediaDropzone">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <div class="media-dropzone-title">Arrastra tus archivos aquí</div>
                    <div class="media-dropzone-hint">o haz clic para elegirlos — JPG, PNG, WEBP, SVG, GIF, PDF, DOC, XLS, ZIP</div>
                    <input type="file" id="mediaFileInput" name="media_files[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,.doc,.docx,.xls,.xlsx,.csv,.zip" style="display:none;">
                </div>
                <div class="media-upload-queue" id="mediaUploadQueue"></div>

                <div class="form-group" style="margin-top:16px;">
                    <label for="uploadCategory">Categoría</label>
                    <input type="text" id="uploadCategory" name="category" class="form-control" list="mediaCategoryOptions" placeholder="Banners, Logos, Productos...">
                    <datalist id="mediaCategoryOptions">
                        <?php foreach ($all_categories as $cat): ?><option value="<?= e($cat) ?>"><?php endforeach; ?>
                    </datalist>
                    <p class="settings-hint" style="margin-bottom:0;">Escribe una categoría existente para agrupar ahí, o una nueva para crearla. Se aplica a todos los archivos de esta subida.</p>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="uploadAlt">Texto alternativo (opcional)</label>
                    <input type="text" id="uploadAlt" name="alt_text" class="form-control" placeholder="Descripción breve, útil para imágenes">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm" id="uploadSubmitBtn" disabled>Subir</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: editar metadatos -->
<div class="modal-overlay" id="editMediaModal">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h3>Editar archivo</h3>
                <p id="editMediaModalSubtitle">Nombre del archivo</p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="do_edit" value="1">
            <input type="hidden" name="id" id="editMediaId" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label for="editMediaCategory">Categoría</label>
                    <input type="text" id="editMediaCategory" name="category" class="form-control" list="mediaCategoryOptions">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="editMediaAlt">Texto alternativo</label>
                    <input type="text" id="editMediaAlt" name="alt_text" class="form-control">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: confirmar eliminación -->
<div class="modal-overlay" id="deleteMediaModal">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h3>Eliminar archivo</h3>
                <p id="deleteMediaModalSubtitle">¿Eliminar este archivo?</p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="post" action="<?= admin_url('media_delete.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" id="deleteMediaId" value="">
            <div class="modal-body">
                <p style="margin:0;font-size:13.5px;color:var(--admin-text-muted);">Se eliminará permanentemente de la biblioteca y del servidor. Si este archivo ya está usado en algún producto, plantilla o configuración, dejará de mostrarse ahí.</p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm" style="background:var(--primary-color-dark);">Eliminar definitivamente</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('openUploadModalBtn').addEventListener('click', function () { openModal('uploadMediaModal'); });

(function () {
    var dropzone = document.getElementById('mediaDropzone');
    var input = document.getElementById('mediaFileInput');
    var queue = document.getElementById('mediaUploadQueue');
    var submitBtn = document.getElementById('uploadSubmitBtn');
    var fileIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';

    function renderQueue() {
        queue.innerHTML = '';
        var files = input.files;
        submitBtn.disabled = files.length === 0;
        for (var i = 0; i < files.length; i++) {
            var item = document.createElement('div');
            item.className = 'media-upload-queue-item';
            item.innerHTML = fileIcon + '<span>' + files[i].name + '</span>';
            queue.appendChild(item);
        }
    }

    dropzone.addEventListener('click', function () { input.click(); });
    input.addEventListener('change', renderQueue);

    ['dragenter', 'dragover'].forEach(function (evt) {
        dropzone.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); dropzone.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
        dropzone.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); dropzone.classList.remove('dragover'); });
    });
    dropzone.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            renderQueue();
        }
    });
})();

document.querySelectorAll('.js-edit-media-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('editMediaId').value = btn.getAttribute('data-id');
        document.getElementById('editMediaCategory').value = btn.getAttribute('data-category');
        document.getElementById('editMediaAlt').value = btn.getAttribute('data-alt');
        document.getElementById('editMediaModalSubtitle').textContent = btn.getAttribute('data-name');
        openModal('editMediaModal');
    });
});
document.querySelectorAll('.js-delete-media-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('deleteMediaId').value = btn.getAttribute('data-id');
        document.getElementById('deleteMediaModalSubtitle').textContent = '¿Eliminar "' + btn.getAttribute('data-name') + '"?';
        openModal('deleteMediaModal');
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
