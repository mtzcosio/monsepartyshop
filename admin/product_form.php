<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();
require_once __DIR__ . '/includes/media_picker.php';

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$product = [
    'name' => '', 'slug' => '', 'short_description' => '', 'description' => '',
    'price' => '', 'old_price' => '', 'image' => '', 'is_new' => 0, 'is_bestseller' => 0, 'is_kit' => 0,
    'is_offer' => 0, 'rating' => 5.0, 'what_includes' => '', 'what_for' => '', 'what_you_need' => '',
    'how_to_use' => '', 'difficulty_level' => '', 'file_format' => '', 'status' => 'active',
    'show_what_includes' => 1, 'show_what_for' => 1, 'show_what_you_need' => 1, 'show_how_to_use' => 1,
];

if ($id) {
    $stmt = $db->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $found = $stmt->fetch();
    if ($found) { $product = $found; }
}

$categories = $db->query('SELECT * FROM categories ORDER BY sort_order ASC')->fetchAll();

$selected_category_ids = [];
if ($id) {
    $cat_stmt = $db->prepare('SELECT category_id FROM product_categories WHERE product_id = :id');
    $cat_stmt->execute(['id' => $id]);
    $selected_category_ids = array_column($cat_stmt->fetchAll(), 'category_id');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Tu sesión expiró, intenta de nuevo.';
    }

    $name = trim($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    if ($name === '') { $errors[] = 'El nombre es obligatorio.'; }
    if ($price <= 0) { $errors[] = 'El precio debe ser mayor a 0.'; }

    $selected_category_ids = is_array($_POST['category_ids'] ?? null) ? array_map('intval', $_POST['category_ids']) : [];

    $data = [
        'name' => $name,
        'slug' => slugify(($_POST['slug'] ?? '') ?: $name),
        'short_description' => trim($_POST['short_description'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'price' => $price,
        'old_price' => !empty($_POST['old_price']) ? (float)$_POST['old_price'] : null,
        'is_new' => isset($_POST['is_new']) ? 1 : 0,
        'is_bestseller' => isset($_POST['is_bestseller']) ? 1 : 0,
        'is_kit' => isset($_POST['is_kit']) ? 1 : 0,
        'is_offer' => isset($_POST['is_offer']) ? 1 : 0,
        'rating' => (float)($_POST['rating'] ?? 5.0),
        'what_includes' => trim($_POST['what_includes'] ?? ''),
        'show_what_includes' => isset($_POST['show_what_includes']) ? 1 : 0,
        'what_for' => trim($_POST['what_for'] ?? ''),
        'show_what_for' => isset($_POST['show_what_for']) ? 1 : 0,
        'what_you_need' => trim($_POST['what_you_need'] ?? ''),
        'show_what_you_need' => isset($_POST['show_what_you_need']) ? 1 : 0,
        'how_to_use' => trim($_POST['how_to_use'] ?? ''),
        'show_how_to_use' => isset($_POST['show_how_to_use']) ? 1 : 0,
        'difficulty_level' => trim($_POST['difficulty_level'] ?? ''),
        'file_format' => trim($_POST['file_format'] ?? ''),
        'status' => ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active',
        'image' => $product['image'],
    ];

    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $filename = uniqid('prod_') . '.' . $ext;
            $dest = __DIR__ . '/../uploads/products/' . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $data['image'] = 'uploads/products/' . $filename;
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
            $sql = 'UPDATE products SET name=:name, slug=:slug, short_description=:short_description,
                    description=:description, price=:price, old_price=:old_price, image=:image, is_new=:is_new,
                    is_bestseller=:is_bestseller, is_kit=:is_kit, is_offer=:is_offer, rating=:rating,
                    what_includes=:what_includes, show_what_includes=:show_what_includes,
                    what_for=:what_for, show_what_for=:show_what_for,
                    what_you_need=:what_you_need, show_what_you_need=:show_what_you_need,
                    how_to_use=:how_to_use, show_how_to_use=:show_how_to_use,
                    difficulty_level=:difficulty_level, file_format=:file_format, status=:status
                    WHERE id=:id';
            $data['id'] = $id;
        } else {
            $sql = 'INSERT INTO products (name, slug, short_description, description, price, old_price, image,
                    is_new, is_bestseller, is_kit, is_offer, rating,
                    what_includes, show_what_includes, what_for, show_what_for,
                    what_you_need, show_what_you_need, how_to_use, show_how_to_use,
                    difficulty_level, file_format, status)
                    VALUES (:name, :slug, :short_description, :description, :price, :old_price, :image,
                    :is_new, :is_bestseller, :is_kit, :is_offer, :rating,
                    :what_includes, :show_what_includes, :what_for, :show_what_for,
                    :what_you_need, :show_what_you_need, :how_to_use, :show_how_to_use,
                    :difficulty_level, :file_format, :status)';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($data);
        $product_id = $id ?: (int)$db->lastInsertId();

        // Sincronizar categorías (relación muchos-a-muchos)
        $db->prepare('DELETE FROM product_categories WHERE product_id = :id')->execute(['id' => $product_id]);
        if ($selected_category_ids) {
            $cat_insert = $db->prepare('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (:product_id, :category_id)');
            foreach ($selected_category_ids as $cat_id) {
                $cat_insert->execute(['product_id' => $product_id, 'category_id' => $cat_id]);
            }
        }

        // Imágenes de galería y archivos descargables marcados para eliminar (se aplican
        // junto con el resto del guardado). Scoped por product_id para que un POST
        // manipulado no pueda borrar elementos de otro producto.
        $delete_image_ids = array_filter(array_map('intval', (array)($_POST['delete_images'] ?? [])));
        $delete_file_ids = array_filter(array_map('intval', (array)($_POST['delete_files'] ?? [])));
        $deleted_count = 0;

        if ($id && $delete_image_ids) {
            $del_img = $db->prepare('DELETE FROM product_images WHERE id = :id AND product_id = :product_id');
            foreach ($delete_image_ids as $image_id) {
                $del_img->execute(['id' => $image_id, 'product_id' => $product_id]);
                $deleted_count += $del_img->rowCount();
            }
        }

        if ($id && $delete_file_ids) {
            $find_file = $db->prepare('SELECT file_name FROM product_files WHERE id = :id AND product_id = :product_id');
            $del_file = $db->prepare('DELETE FROM product_files WHERE id = :id');
            foreach ($delete_file_ids as $file_id) {
                $find_file->execute(['id' => $file_id, 'product_id' => $product_id]);
                $file = $find_file->fetch();
                if (!$file) { continue; }
                $del_file->execute(['id' => $file_id]);
                $deleted_count++;
                $path = __DIR__ . '/../uploads/downloads/' . basename($file['file_name']);
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        }

        if (!empty($_FILES['gallery_images']['name'][0])) {
            $max_order = (int)$db->query('SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ' . $product_id)->fetchColumn();
            $img_stmt = $db->prepare('INSERT INTO product_images (product_id, image, sort_order) VALUES (:product_id, :image, :sort_order)');
            foreach ($_FILES['gallery_images']['name'] as $i => $gallery_name) {
                if (empty($gallery_name)) { continue; }
                $g_ext = strtolower(pathinfo($gallery_name, PATHINFO_EXTENSION));
                if (!in_array($g_ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) { continue; }
                $g_filename = uniqid('gal_') . '.' . $g_ext;
                $g_dest = __DIR__ . '/../uploads/products/' . $g_filename;
                if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $g_dest)) {
                    $max_order++;
                    $img_stmt->execute([
                        'product_id' => $product_id,
                        'image' => 'uploads/products/' . $g_filename,
                        'sort_order' => $max_order,
                    ]);
                }
            }
        }

        if (!empty($_FILES['product_files']['name'][0])) {
            // Lista blanca (no negra): solo formatos de plantillas digitales esperados.
            // uploads/downloads/ ya deniega todo acceso HTTP directo por Apache (ver su
            // .htaccess), pero esta whitelist es una segunda barrera independiente de esa
            // configuración por si el hosting no respeta el .htaccess.
            $allowed_ext = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'zip', 'rar', '7z',
                'psd', 'ai', 'eps', 'indd', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'csv',
                'mp4', 'mp3', 'ttf', 'otf', 'woff', 'woff2'];
            $max_file_order = (int)$db->query('SELECT COALESCE(MAX(sort_order), -1) FROM product_files WHERE product_id = ' . $product_id)->fetchColumn();
            $file_stmt = $db->prepare('INSERT INTO product_files (product_id, file_name, label, sort_order) VALUES (:product_id, :file_name, :label, :sort_order)');
            foreach ($_FILES['product_files']['name'] as $i => $original_name) {
                if (empty($original_name)) { continue; }
                $f_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                if (!in_array($f_ext, $allowed_ext, true)) {
                    $errors[] = 'El archivo "' . $original_name . '" no está permitido como descarga.';
                    continue;
                }
                $f_filename = uniqid('file_') . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $original_name);
                $f_dest = __DIR__ . '/../uploads/downloads/' . $f_filename;
                if (move_uploaded_file($_FILES['product_files']['tmp_name'][$i], $f_dest)) {
                    $max_file_order++;
                    $file_stmt->execute([
                        'product_id' => $product_id,
                        'file_name' => $f_filename,
                        'label' => pathinfo($original_name, PATHINFO_FILENAME),
                        'sort_order' => $max_file_order,
                    ]);
                }
            }
        }

        $saved_message = $id ? 'Producto actualizado.' : 'Producto creado.';
        if ($deleted_count > 0) {
            $saved_message .= ' Se eliminaron ' . $deleted_count . ($deleted_count === 1 ? ' elemento.' : ' elementos.');
        }
        flash_set($saved_message);
        redirect(admin_url('product_form.php?id=' . $product_id));
    }

    $product = array_merge($product, $data);
}

$gallery_images = [];
$product_files = [];
if ($id) {
    $gallery_stmt = $db->prepare('SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order ASC');
    $gallery_stmt->execute(['id' => $id]);
    $gallery_images = $gallery_stmt->fetchAll();

    $files_stmt = $db->prepare('SELECT * FROM product_files WHERE product_id = :id ORDER BY sort_order ASC');
    $files_stmt->execute(['id' => $id]);
    $product_files = $files_stmt->fetchAll();
}

$page_title = $id ? 'Editar producto' : 'Nuevo producto';
$gallery_count = count($gallery_images);
$files_count = count($product_files);
require_once __DIR__ . '/includes/admin_header.php';
?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

<div class="admin-topbar">
    <div>
        <h1 class="mt-0"><?= $id ? 'Editar producto' : 'Nuevo producto' ?></h1>
        <p class="page-subtitle"><?= $id ? e($product['name']) : 'Completa la información del nuevo producto.' ?></p>
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
        <button type="button" class="tab-btn" data-tab="tab-archivos tab-archivos-existing">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            Archivos
            <?php if ($files_count > 0): ?><span class="filter-count-badge"><?= $files_count ?></span><?php endif; ?>
        </button>
    </div>

    <form method="post" enctype="multipart/form-data" id="productForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div id="tab-general" class="tab-panel active">
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Nombre del producto</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?= e($product['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="slug">Slug (opcional, se genera solo)</label>
                    <input type="text" id="slug" name="slug" class="form-control" value="<?= e($product['slug']) ?>">
                </div>
                <div class="form-group">
                    <label for="status">Estado</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="price">Precio</label>
                    <input type="number" step="0.01" id="price" name="price" class="form-control" value="<?= e($product['price']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="old_price">Precio anterior (opcional)</label>
                    <input type="number" step="0.01" id="old_price" name="old_price" class="form-control" value="<?= e($product['old_price']) ?>">
                </div>
                <div class="form-group">
                    <label for="rating">Calificación (0-5)</label>
                    <input type="number" step="0.1" min="0" max="5" id="rating" name="rating" class="form-control" value="<?= e($product['rating']) ?>">
                </div>
                <div class="form-group">
                    <label for="difficulty_level">Nivel de dificultad</label>
                    <input type="text" id="difficulty_level" name="difficulty_level" class="form-control" value="<?= e($product['difficulty_level']) ?>" placeholder="Fácil, Intermedio, Avanzado">
                </div>
                <div class="form-group">
                    <label for="file_format">Formato del archivo</label>
                    <input type="text" id="file_format" name="file_format" class="form-control" value="<?= e($product['file_format']) ?>" placeholder="PDF, PNG, Canva...">
                </div>
            </div>

            <div class="settings-subcard">
                <h4>Categorías</h4>
                <p class="settings-hint">Puedes elegir varias.</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px 20px;">
                    <?php foreach ($categories as $cat): ?>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;">
                            <input type="checkbox" name="category_ids[]" value="<?= (int)$cat['id'] ?>" <?= in_array($cat['id'], $selected_category_ids) ? 'checked' : '' ?>>
                            <?= e($cat['icon'] . ' ' . $cat['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="settings-subcard">
                <h4>Etiquetas</h4>
                <p class="settings-hint">Se muestran como distintivos sobre la imagen del producto en la tienda.</p>
                <label style="display:inline-block;margin-right:16px;font-weight:400;"><input type="checkbox" name="is_new" <?= $product['is_new'] ? 'checked' : '' ?>> Nuevo</label>
                <label style="display:inline-block;margin-right:16px;font-weight:400;"><input type="checkbox" name="is_bestseller" <?= $product['is_bestseller'] ? 'checked' : '' ?>> Más vendido</label>
                <label style="display:inline-block;margin-right:16px;font-weight:400;"><input type="checkbox" name="is_kit" <?= $product['is_kit'] ? 'checked' : '' ?>> Kit</label>
                <label style="display:inline-block;margin-right:16px;font-weight:400;"><input type="checkbox" name="is_offer" <?= $product['is_offer'] ? 'checked' : '' ?>> Oferta</label>
            </div>
        </div>

        <div id="tab-contenido" class="tab-panel">
            <div class="form-group">
                <label for="short_description">Descripción corta</label>
                <textarea id="short_description" name="short_description" class="form-control"><?= e($product['short_description']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="description">Descripción personalizada</label>
                <div style="margin-bottom:8px;">
                    <button type="button" id="toggleHtmlView" class="btn btn-secondary btn-sm">Ver/editar código HTML</button>
                </div>
                <div id="descriptionEditor" style="background:#fff;border-radius:var(--radius-sm);min-height:220px;"></div>
                <textarea id="descriptionHtmlSource" class="form-control" style="display:none;min-height:220px;font-family:monospace;font-size:13px;"></textarea>
                <textarea id="description" name="description" style="display:none;"><?= e($product['description']) ?></textarea>
                <p class="settings-hint">
                    Usa el editor visual para dar formato (negritas, listas, enlaces, imágenes) o cambia a "código HTML" para pegar/editar HTML directamente.
                </p>
            </div>

            <div class="settings-subcard">
                <h4>Detalles para la ficha del producto</h4>
                <p class="settings-hint">Desmarca una sección para ocultarla en la página del producto, aunque tenga contenido escrito.</p>
                <div class="form-group">
                    <label for="what_includes" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                        <span>¿Qué incluye?</span>
                        <span style="display:inline-flex;align-items:center;gap:6px;font-weight:400;font-size:13px;"><input type="checkbox" name="show_what_includes" <?= $product['show_what_includes'] ? 'checked' : '' ?>> Mostrar en la tienda</span>
                    </label>
                    <textarea id="what_includes" name="what_includes" class="form-control"><?= e($product['what_includes']) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="what_for" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                        <span>¿Para qué sirve?</span>
                        <span style="display:inline-flex;align-items:center;gap:6px;font-weight:400;font-size:13px;"><input type="checkbox" name="show_what_for" <?= $product['show_what_for'] ? 'checked' : '' ?>> Mostrar en la tienda</span>
                    </label>
                    <textarea id="what_for" name="what_for" class="form-control"><?= e($product['what_for']) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="what_you_need" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                        <span>¿Qué necesitas?</span>
                        <span style="display:inline-flex;align-items:center;gap:6px;font-weight:400;font-size:13px;"><input type="checkbox" name="show_what_you_need" <?= $product['show_what_you_need'] ? 'checked' : '' ?>> Mostrar en la tienda</span>
                    </label>
                    <textarea id="what_you_need" name="what_you_need" class="form-control"><?= e($product['what_you_need']) ?></textarea>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="how_to_use" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                        <span>¿Cómo se utiliza?</span>
                        <span style="display:inline-flex;align-items:center;gap:6px;font-weight:400;font-size:13px;"><input type="checkbox" name="show_how_to_use" <?= $product['show_how_to_use'] ? 'checked' : '' ?>> Mostrar en la tienda</span>
                    </label>
                    <textarea id="how_to_use" name="how_to_use" class="form-control"><?= e($product['how_to_use']) ?></textarea>
                </div>
            </div>
        </div>

        <div id="tab-imagenes" class="tab-panel">
            <div class="form-group">
                <label for="image">Imagen principal</label>
                <?php if ($product['image']): ?><img src="<?= e(upload_url($product['image'])) ?>" style="max-width:100px;border-radius:8px;margin-bottom:8px;"><?php endif; ?>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <input type="file" id="image" name="image" class="form-control" accept="image/*" style="flex:1;min-width:200px;">
                    <?php render_media_picker_field('image', 'Elegir de la biblioteca'); ?>
                </div>
                <p class="settings-hint" style="margin-bottom:0;">Sube un archivo nuevo o elige uno ya existente de la biblioteca.</p>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label for="gallery_images">Galería de imágenes adicionales</label>
                <input type="file" id="gallery_images" name="gallery_images[]" class="form-control" accept="image/*" multiple>
                <p class="settings-hint">Puedes seleccionar varias imágenes a la vez. Se agregan a la galería del producto al guardar.</p>
            </div>
        </div>

        <div id="tab-archivos" class="tab-panel">
            <div class="form-group" style="margin-bottom:0;">
                <label for="product_files">Archivos descargables (PDF, ZIP, etc.)</label>
                <input type="file" id="product_files" name="product_files[]" class="form-control" multiple>
                <p class="settings-hint">Puedes subir varios archivos (por ejemplo, PDF + Canva + PNG). El cliente los verá todos disponibles para descargar tras su compra.</p>
            </div>
        </div>

    </form>

    <?php
    // Si el guardado falló por validación, conservar lo que ya estaba marcado para eliminar.
    $marked_images = array_map('intval', (array)($_POST['delete_images'] ?? []));
    $marked_files = array_map('intval', (array)($_POST['delete_files'] ?? []));
    ?>

    <?php if ($id && $gallery_images): ?>
    <div id="tab-imagenes-existing" class="tab-panel" style="margin-top:24px;">
        <div class="settings-subcard">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <h4 style="margin:0;">Imágenes de la galería</h4>
                <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" class="js-select-all" data-target="delete_images[]"> Seleccionar todas
                </label>
            </div>
            <p class="settings-hint" style="margin:6px 0 0;">Marca las imágenes que quieras quitar; se eliminarán al pulsar "Guardar producto".</p>
            <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:12px;">
                <?php foreach ($gallery_images as $img): ?>
                    <?php $checked = in_array((int)$img['id'], $marked_images, true); ?>
                    <label class="js-delete-item <?= $checked ? 'marked-delete' : '' ?>" style="text-align:center;cursor:pointer;padding:6px;border-radius:var(--radius-sm);border:2px solid transparent;">
                        <img src="<?= e(upload_url($img['image'])) ?>" style="width:120px;height:120px;object-fit:cover;border-radius:var(--radius-sm);display:block;margin-bottom:8px;">
                        <span style="font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                            <input type="checkbox" name="delete_images[]" value="<?= (int)$img['id'] ?>" form="productForm" <?= $checked ? 'checked' : '' ?>> Eliminar
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($id && $product_files): ?>
    <div id="tab-archivos-existing" class="tab-panel" style="margin-top:24px;">
        <div class="settings-subcard">
            <h4 style="margin:0;">Archivos descargables</h4>
            <p class="settings-hint" style="margin:6px 0 0;">Marca los archivos que quieras quitar; se eliminarán al pulsar "Guardar producto". Los clientes que ya compraron dejarán de poder descargarlos.</p>
            <table class="admin-table" style="margin-top:12px;">
                <thead><tr>
                    <th style="width:40px;"><input type="checkbox" class="js-select-all" data-target="delete_files[]" title="Seleccionar todos"></th>
                    <th>Archivo</th><th>Nombre visible</th><th></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($product_files as $file): ?>
                    <?php $checked = in_array((int)$file['id'], $marked_files, true); ?>
                    <tr class="js-delete-item <?= $checked ? 'marked-delete' : '' ?>">
                        <td><input type="checkbox" name="delete_files[]" value="<?= (int)$file['id'] ?>" form="productForm" <?= $checked ? 'checked' : '' ?> aria-label="Eliminar <?= e($file['label']) ?>"></td>
                        <td><?= e($file['file_name']) ?></td>
                        <td><?= e($file['label']) ?></td>
                        <td>
                            <a href="<?= admin_url('product_file_preview.php?file_id=' . (int)$file['id']) ?>" target="_blank" class="btn btn-secondary btn-sm">Previsualizar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <style>
        .js-delete-item.marked-delete { border-color: var(--primary-color-dark) !important; background: var(--admin-primary-tint); }
        .js-delete-item.marked-delete img { opacity: 0.45; }
        tr.js-delete-item.marked-delete td { text-decoration: line-through; opacity: 0.7; }
        tr.js-delete-item.marked-delete td:first-child, tr.js-delete-item.marked-delete td:last-child { text-decoration: none; }
    </style>

    <div class="filter-actions" style="justify-content:flex-start;border-top:1px solid var(--admin-border);margin-top:28px;padding-top:22px;">
        <button type="submit" form="productForm" class="btn btn-primary">GUARDAR PRODUCTO</button>
        <span id="deletePendingNote" style="display:none;font-size:13px;color:var(--primary-color-dark);align-self:center;"></span>
        <a href="<?= admin_url('products.php') ?>" class="btn btn-secondary">Cancelar</a>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('productForm');
    var note = document.getElementById('deletePendingNote');
    var boxes = document.querySelectorAll('input[name="delete_images[]"], input[name="delete_files[]"]');
    if (!form || !boxes.length) { return; }

    function markedCount() {
        return document.querySelectorAll('input[name="delete_images[]"]:checked, input[name="delete_files[]"]:checked').length;
    }
    function refresh() {
        boxes.forEach(function (box) {
            var item = box.closest('.js-delete-item');
            if (item) { item.classList.toggle('marked-delete', box.checked); }
        });
        document.querySelectorAll('.js-select-all').forEach(function (all) {
            var group = document.querySelectorAll('input[name="' + all.getAttribute('data-target') + '"]');
            var checked = Array.prototype.filter.call(group, function (b) { return b.checked; }).length;
            all.checked = group.length > 0 && checked === group.length;
            all.indeterminate = checked > 0 && checked < group.length;
        });
        var n = markedCount();
        note.style.display = n ? '' : 'none';
        note.textContent = n ? n + (n === 1 ? ' elemento marcado' : ' elementos marcados') + ' para eliminar al guardar' : '';
    }

    boxes.forEach(function (box) { box.addEventListener('change', refresh); });
    document.querySelectorAll('.js-select-all').forEach(function (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('input[name="' + all.getAttribute('data-target') + '"]').forEach(function (b) { b.checked = all.checked; });
            refresh();
        });
    });

    form.addEventListener('submit', function (ev) {
        var n = markedCount();
        if (n && !confirm('Se eliminarán ' + n + (n === 1 ? ' elemento' : ' elementos') + ' de forma permanente al guardar. ¿Continuar?')) {
            ev.preventDefault();
        }
    });

    refresh();
})();
</script>

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
