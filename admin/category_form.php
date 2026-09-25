<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$category = ['name' => '', 'slug' => '', 'icon' => '', 'sort_order' => 0, 'is_active' => 1];

if ($id) {
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $found = $stmt->fetch();
    if ($found) { $category = $found; }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Tu sesión expiró, intenta de nuevo.';
    }
    $name = trim($_POST['name'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $slug = slugify($_POST['slug'] ?? $name ?: $name);

    if ($name === '') { $errors[] = 'El nombre es obligatorio.'; }

    if (!$errors) {
        if ($id) {
            $stmt = $db->prepare('UPDATE categories SET name=:name, slug=:slug, icon=:icon, sort_order=:sort_order, is_active=:is_active WHERE id=:id');
            $stmt->execute(['name' => $name, 'slug' => $slug, 'icon' => $icon, 'sort_order' => $sort_order, 'is_active' => $is_active, 'id' => $id]);
            flash_set('Categoría actualizada.');
        } else {
            $stmt = $db->prepare('INSERT INTO categories (name, slug, icon, sort_order, is_active) VALUES (:name,:slug,:icon,:sort_order,:is_active)');
            $stmt->execute(['name' => $name, 'slug' => $slug, 'icon' => $icon, 'sort_order' => $sort_order, 'is_active' => $is_active]);
            flash_set('Categoría creada.');
        }
        redirect(admin_url('categories.php'));
    }
    $category = array_merge($category, ['name' => $name, 'slug' => $slug, 'icon' => $icon, 'sort_order' => $sort_order, 'is_active' => $is_active]);
}

$page_title = $id ? 'Editar categoría' : 'Nueva categoría';
require_once __DIR__ . '/includes/admin_header.php';
?>

<h1><?= $id ? 'Editar categoría' : 'Nueva categoría' ?></h1>
<?php foreach ($errors as $error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endforeach; ?>

<form method="post" class="card-box">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="form-grid">
        <div class="form-group">
            <label for="name">Nombre</label>
            <input type="text" id="name" name="name" class="form-control" value="<?= e($category['name']) ?>" required>
        </div>
        <div class="form-group">
            <label for="icon">Ícono (emoji)</label>
            <input type="text" id="icon" name="icon" class="form-control" value="<?= e($category['icon']) ?>" placeholder="🎀">
        </div>
        <div class="form-group">
            <label for="slug">Slug (opcional, se genera solo)</label>
            <input type="text" id="slug" name="slug" class="form-control" value="<?= e($category['slug']) ?>">
        </div>
        <div class="form-group">
            <label for="sort_order">Orden</label>
            <input type="number" id="sort_order" name="sort_order" class="form-control" value="<?= (int)$category['sort_order'] ?>">
        </div>
    </div>
    <div class="form-group">
        <label><input type="checkbox" name="is_active" <?= $category['is_active'] ? 'checked' : '' ?>> Categoría activa</label>
    </div>
    <button type="submit" class="btn btn-primary">GUARDAR</button>
    <a href="<?= admin_url('categories.php') ?>" class="btn btn-secondary">Cancelar</a>
</form>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
