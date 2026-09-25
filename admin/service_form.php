<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();
require_once __DIR__ . '/includes/media_picker.php';

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$service = [
    'name' => '', 'slug' => '', 'short_description' => '', 'description' => '',
    'price_from' => '', 'image' => '', 'status' => 'active', 'sort_order' => 0,
];

if ($id) {
    $stmt = $db->prepare('SELECT * FROM services WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $found = $stmt->fetch();
    if ($found) { $service = $found; }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Tu sesión expiró, intenta de nuevo.';
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') { $errors[] = 'El nombre es obligatorio.'; }

    $data = [
        'name' => $name,
        'slug' => slugify(($_POST['slug'] ?? '') ?: $name),
        'short_description' => trim($_POST['short_description'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'price_from' => $_POST['price_from'] !== '' ? (float)$_POST['price_from'] : null,
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'status' => ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active',
        'image' => $service['image'],
    ];

    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $filename = uniqid('srv_') . '.' . $ext;
            $dest = __DIR__ . '/../uploads/services/' . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $data['image'] = base_url('uploads/services/' . $filename);
            }
        } else {
            $errors[] = 'La imagen debe ser JPG, PNG, WEBP o GIF.';
        }
    } elseif (!empty($_POST['image_media_url'])) {
        // Imagen elegida desde la Biblioteca de archivos en vez de subir un archivo nuevo.
        $data['image'] = trim($_POST['image_media_url']);
    }

    if (!$errors) {
        if ($id) {
            $sql = 'UPDATE services SET name=:name, slug=:slug, short_description=:short_description,
                    description=:description, price_from=:price_from, image=:image, sort_order=:sort_order, status=:status
                    WHERE id=:id';
            $data['id'] = $id;
        } else {
            $sql = 'INSERT INTO services (name, slug, short_description, description, price_from, image, sort_order, status)
                    VALUES (:name, :slug, :short_description, :description, :price_from, :image, :sort_order, :status)';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($data);
        $service_id = $id ?: (int)$db->lastInsertId();

        if (!empty($_FILES['gallery_images']['name'][0])) {
            $max_order = (int)$db->query('SELECT COALESCE(MAX(sort_order), -1) FROM service_images WHERE service_id = ' . $service_id)->fetchColumn();
            $img_stmt = $db->prepare('INSERT INTO service_images (service_id, image, sort_order) VALUES (:service_id, :image, :sort_order)');
            foreach ($_FILES['gallery_images']['name'] as $i => $gallery_name) {
                if (empty($gallery_name)) { continue; }
                $g_ext = strtolower(pathinfo($gallery_name, PATHINFO_EXTENSION));
                if (!in_array($g_ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) { continue; }
                $g_filename = uniqid('gal_') . '.' . $g_ext;
                $g_dest = __DIR__ . '/../uploads/services/' . $g_filename;
                if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $g_dest)) {
                    $max_order++;
                    $img_stmt->execute([
                        'service_id' => $service_id,
                        'image' => base_url('uploads/services/' . $g_filename),
                        'sort_order' => $max_order,
                    ]);
                }
            }
        }

        flash_set($id ? 'Servicio actualizado.' : 'Servicio creado.');
        redirect(admin_url('service_form.php?id=' . $service_id));
    }

    $service = array_merge($service, $data);
}

$gallery_images = [];
if ($id) {
    $gallery_stmt = $db->prepare('SELECT * FROM service_images WHERE service_id = :id ORDER BY sort_order ASC');
    $gallery_stmt->execute(['id' => $id]);
    $gallery_images = $gallery_stmt->fetchAll();
}

$page_title = $id ? 'Editar servicio' : 'Nuevo servicio';
$gallery_count = count($gallery_images);
require_once __DIR__ . '/includes/admin_header.php';
?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

<div class="admin-topbar">
    <div>
        <h1 class="mt-0"><?= $id ? 'Editar servicio' : 'Nuevo servicio' ?></h1>
        <p class="page-subtitle"><?= $id ? e($service['name']) : 'Completa la información del nuevo servicio.' ?></p>
    </div>
</div>

<?php foreach ($errors as $error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endforeach; ?>

<div class="tabs-wrap">
    <div class="settings-tabs-nav">
        <button type="button" class="tab-btn active" data-tab="tab-general">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            General
        </button>
        <button type="button" class="tab-btn" data-tab="tab-contenido">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Contenido
        </button>
        <button type="button" class="tab-btn" data-tab="tab-imagenes tab-imagenes-existing">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            Imágenes
            <?php if ($gallery_count > 0): ?><span class="filter-count-badge"><?= $gallery_count ?></span><?php endif; ?>
        </button>
    </div>

    <form method="post" enctype="multipart/form-data" id="serviceForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div id="tab-general" class="tab-panel active">
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Nombre del servicio</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?= e($service['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="slug">Slug (opcional, se genera solo)</label>
                    <input type="text" id="slug" name="slug" class="form-control" value="<?= e($service['slug']) ?>">
                </div>
                <div class="form-group">
                    <label for="status">Estado</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" <?= $service['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactive" <?= $service['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="price_from">Precio desde (opcional)</label>
                    <input type="number" step="0.01" id="price_from" name="price_from" class="form-control" value="<?= e($service['price_from']) ?>" placeholder="Déjalo vacío si el precio siempre se cotiza">
                </div>
                <div class="form-group">
                    <label for="sort_order">Orden</label>
                    <input type="number" id="sort_order" name="sort_order" class="form-control" value="<?= e($service['sort_order']) ?>">
                </div>
            </div>
        </div>

        <div id="tab-contenido" class="tab-panel">
            <div class="form-group">
                <label for="short_description">Descripción corta</label>
                <textarea id="short_description" name="short_description" class="form-control"><?= e($service['short_description']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="description">Descripción</label>
                <div style="margin-bottom:8px;">
                    <button type="button" id="toggleHtmlView" class="btn btn-secondary btn-sm">Ver/editar código HTML</button>
                </div>
                <div id="descriptionEditor" style="background:#fff;border-radius:var(--radius-sm);min-height:220px;"></div>
                <textarea id="descriptionHtmlSource" class="form-control" style="display:none;min-height:220px;font-family:monospace;font-size:13px;"></textarea>
                <textarea id="description" name="description" style="display:none;"><?= e($service['description']) ?></textarea>
                <p class="settings-hint">
                    Usa el editor visual para dar formato (negritas, listas, enlaces, imágenes) o cambia a "código HTML" para pegar/editar HTML directamente.
                </p>
            </div>
        </div>

        <div id="tab-imagenes" class="tab-panel">
            <div class="form-group">
                <label for="image">Imagen principal</label>
                <?php if ($service['image']): ?><img src="<?= e($service['image']) ?>" style="max-width:100px;border-radius:8px;margin-bottom:8px;"><?php endif; ?>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <input type="file" id="image" name="image" class="form-control" accept="image/*" style="flex:1;min-width:200px;">
                    <?php render_media_picker_field('image', 'Elegir de la biblioteca'); ?>
                </div>
                <p class="settings-hint" style="margin-bottom:0;">Sube un archivo nuevo o elige uno ya existente de la biblioteca.</p>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label for="gallery_images">Galería de imágenes adicionales</label>
                <input type="file" id="gallery_images" name="gallery_images[]" class="form-control" accept="image/*" multiple>
                <p class="settings-hint">Puedes seleccionar varias imágenes a la vez. Se agregan a la galería del servicio al guardar.</p>
            </div>
        </div>

    </form>

    <?php if ($id && $gallery_images): ?>
    <div id="tab-imagenes-existing" class="tab-panel" style="margin-top:24px;">
        <div class="settings-subcard">
            <h4>Imágenes de la galería</h4>
            <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:12px;">
                <?php foreach ($gallery_images as $img): ?>
                    <div style="text-align:center;">
                        <img src="<?= e($img['image']) ?>" style="width:120px;height:120px;object-fit:cover;border-radius:var(--radius-sm);display:block;margin-bottom:8px;">
                        <form method="post" action="<?= admin_url('service_image_delete.php') ?>" onsubmit="return confirm('¿Eliminar esta imagen de la galería?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                            <input type="hidden" name="service_id" value="<?= (int)$id ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Eliminar</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="filter-actions" style="justify-content:flex-start;border-top:1px solid var(--admin-border);margin-top:28px;padding-top:22px;">
        <button type="submit" form="serviceForm" class="btn btn-primary">GUARDAR SERVICIO</button>
        <a href="<?= admin_url('services.php') ?>" class="btn btn-secondary">Cancelar</a>
    </div>
</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
(function () {
    var hiddenInput = document.getElementById('description');
    var editorDiv = document.getElementById('descriptionEditor');
    var htmlSource = document.getElementById('descriptionHtmlSource');
    var toggleBtn = document.getElementById('toggleHtmlView');
    if (!editorDiv || typeof Quill === 'undefined') { return; }

    var quill = new Quill('#descriptionEditor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link', 'image'],
                ['clean']
            ]
        }
    });
    quill.clipboard.dangerouslyPasteHTML(hiddenInput.value || '');

    var showingHtml = false;
    var toolbarEl = document.querySelector('#descriptionEditor').previousElementSibling;

    toggleBtn.addEventListener('click', function () {
        if (!showingHtml) {
            htmlSource.value = quill.root.innerHTML;
            editorDiv.style.display = 'none';
            if (toolbarEl && toolbarEl.classList.contains('ql-toolbar')) { toolbarEl.style.display = 'none'; }
            htmlSource.style.display = 'block';
            toggleBtn.textContent = 'Ver editor visual';
        } else {
            quill.clipboard.dangerouslyPasteHTML(htmlSource.value);
            editorDiv.style.display = 'block';
            if (toolbarEl && toolbarEl.classList.contains('ql-toolbar')) { toolbarEl.style.display = 'block'; }
            htmlSource.style.display = 'none';
            toggleBtn.textContent = 'Ver/editar código HTML';
        }
        showingHtml = !showingHtml;
    });

    hiddenInput.closest('form').addEventListener('submit', function () {
        hiddenInput.value = showingHtml ? htmlSource.value : quill.root.innerHTML;
    });
})();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
