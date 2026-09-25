<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();
require_once __DIR__ . '/includes/pagination.php';
require_once __DIR__ . '/includes/email_variables.php';

$db = get_db();
$errors = [];
$form_values = ['id' => '', 'name' => '', 'category' => '', 'description' => '', 'sample_value' => ''];
$reopen_modal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Tu sesión expiró, intenta de nuevo.';
    }

    $id = (int)($_POST['id'] ?? 0);
    $name_input = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sample_value = trim($_POST['sample_value'] ?? '');
    $name = strtolower(str_replace('-', '_', slugify($name_input)));

    if ($name === '') { $errors[] = 'El nombre de la variable es obligatorio.'; }
    if ($category === '') { $errors[] = 'La categoría es obligatoria.'; }
    if ($name !== '' && email_custom_variable_name_taken($name, $id ?: null)) {
        $errors[] = 'Ya existe una variable con el nombre "{{' . $name . '}}". Elige otro nombre.';
    }

    if (!$errors) {
        if ($id) {
            $stmt = $db->prepare(
                'UPDATE email_custom_variables SET category=:category, name=:name, description=:description, sample_value=:sample_value WHERE id=:id'
            );
            $stmt->execute(['category' => $category, 'name' => $name, 'description' => $description, 'sample_value' => $sample_value, 'id' => $id]);
            flash_set('Variable actualizada.');
        } else {
            $stmt = $db->prepare(
                'INSERT INTO email_custom_variables (category, name, description, sample_value) VALUES (:category, :name, :description, :sample_value)'
            );
            $stmt->execute(['category' => $category, 'name' => $name, 'description' => $description, 'sample_value' => $sample_value]);
            flash_set('Variable creada.');
        }
        redirect(admin_url('email_variables.php'));
    }

    $form_values = ['id' => $id, 'name' => $name_input, 'category' => $category, 'description' => $description, 'sample_value' => $sample_value];
    $reopen_modal = true;
}

$q = trim($_GET['q'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');
$has_filters = ($q !== '' || $categoria !== '');

$where_sql = ' WHERE 1=1';
$params = [];
if ($q !== '') {
    $where_sql .= ' AND (name LIKE :q OR description LIKE :q2)';
    $params['q'] = '%' . $q . '%';
    $params['q2'] = '%' . $q . '%';
}
if ($categoria !== '') {
    $where_sql .= ' AND category = :categoria';
    $params['categoria'] = $categoria;
}
$active_filter_count = ($q !== '' ? 1 : 0) + ($categoria !== '' ? 1 : 0);

$stmt = $db->prepare('SELECT * FROM email_custom_variables' . $where_sql . ' ORDER BY category ASC, name ASC');
$stmt->execute($params);
$custom_vars = $stmt->fetchAll();
$total_records = count($custom_vars);

/* Se agrupan por categoría (tal como el admin la escribió) para mostrarlas
   igual que el panel de variables del editor de plantillas: acordeón por
   categoría libre, en vez de una tabla plana. */
$grouped_custom_vars = [];
foreach ($custom_vars as $v) {
    $grouped_custom_vars[$v['category']][] = $v;
}

$all_categories = email_variable_category_names();

$page_title = 'Variables de correo';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
    <div>
        <h1 class="mt-0">Variables de correo</h1>
        <p class="page-subtitle">Crea tus propias variables <code>{{nombre_variable}}</code> y agrúpalas por categoría para usarlas en cualquier plantilla.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm" id="newVariableBtn">+ Nueva variable</button>
</div>

<?php foreach ($errors as $error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endforeach; ?>

<div class="section-card" style="margin-bottom:24px;">
    <div class="section-card-head">
        <div>
            <h3>Variables integradas</h3>
            <p>Vienen listas de fábrica y no se pueden editar ni eliminar; se muestran aquí solo como referencia.</p>
        </div>
    </div>
    <div id="builtinAccordion">
        <?php foreach (email_builtin_variable_catalog() as $category => $vars): ?>
            <div class="var-accordion-item" data-category>
                <div class="var-accordion-header">
                    <span><?= e($category) ?></span>
                    <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                </div>
                <div class="var-accordion-body">
                    <?php foreach ($vars as $var_name => $var_desc): ?>
                        <div class="var-item">
                            <div class="var-item-info">
                                <span class="var-item-code">{{<?= e($var_name) ?>}}</span>
                                <div class="var-item-desc"><?= e($var_desc) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="admin-topbar" style="margin-bottom:12px;">
    <h3 class="mt-0">Variables personalizadas</h3>
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
            <a href="<?= admin_url('email_variables.php') ?>" class="filter-clear-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                Limpiar filtros
            </a>
        <?php endif; ?>
    </div>
    <div class="filter-grid">
        <div class="form-group">
            <label for="q">Nombre o descripción</label>
            <input type="text" id="q" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Buscar variable...">
        </div>
        <div class="form-group">
            <label for="categoria">Categoría</label>
            <select id="categoria" name="categoria" class="form-control">
                <option value="">Todas</option>
                <?php foreach (array_unique(array_column($db->query('SELECT DISTINCT category FROM email_custom_variables ORDER BY category ASC')->fetchAll(), 'category')) as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $categoria === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary btn-sm">Aplicar filtros</button>
    </div>
</form>

<div class="grid-toolbar" style="grid-template-columns:1fr;">
    <?php render_admin_records_count($total_records, 'variables'); ?>
</div>

<div class="section-card">
    <?php if ($custom_vars): ?>
        <div id="customVarsAccordion">
            <?php foreach ($grouped_custom_vars as $category => $vars): ?>
                <div class="var-accordion-item open" data-category>
                    <div class="var-accordion-header">
                        <span><?= e($category) ?> <span class="filter-count-badge" style="margin-left:6px;"><?= count($vars) ?></span></span>
                        <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                    </div>
                    <div class="var-accordion-body">
                        <?php foreach ($vars as $v): ?>
                            <div class="var-item">
                                <div class="var-item-info">
                                    <span class="var-item-code">{{<?= e($v['name']) ?>}}</span>
                                    <div class="var-item-desc">
                                        <?= e($v['description'] ?: 'Sin descripción.') ?>
                                        <?php if ($v['sample_value']): ?><br>Ejemplo: <em><?= e($v['sample_value']) ?></em><?php endif; ?>
                                    </div>
                                </div>
                                <div class="var-item-actions">
                                    <button type="button" class="var-item-btn js-edit-var-btn" title="Editar"
                                            data-id="<?= (int)$v['id'] ?>" data-name="<?= e($v['name']) ?>" data-category="<?= e($v['category']) ?>"
                                            data-description="<?= e($v['description']) ?>" data-sample="<?= e($v['sample_value']) ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button type="button" class="var-item-btn js-delete-var-btn" title="Eliminar"
                                            data-id="<?= (int)$v['id'] ?>" data-name="<?= e($v['name']) ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="var-empty"><?= $has_filters ? 'No hay variables que coincidan con esos filtros.' : 'Aún no has creado variables personalizadas. Usa "+ Nueva variable" para agregar la primera.' ?></div>
    <?php endif; ?>
</div>

<!-- Modal: crear/editar variable -->
<div class="modal-overlay" id="variableModal">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h3 id="variableModalTitle">Nueva variable</h3>
                <p>Sintaxis: <code>{{nombre_variable}}</code></p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="post" id="variableForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" id="varId" value="<?= e($form_values['id']) ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label for="varName">Nombre de la variable</label>
                    <input type="text" id="varName" name="name" class="form-control" value="<?= e($form_values['name']) ?>" placeholder="nombre_del_padrino" required>
                    <p class="settings-hint" style="margin-bottom:0;">Se guardará como <strong id="varNamePreview">{{nombre_del_padrino}}</strong> (minúsculas, sin espacios ni acentos).</p>
                </div>
                <div class="form-group">
                    <label for="varCategory">Categoría</label>
                    <input type="text" id="varCategory" name="category" class="form-control" list="categoryOptions" value="<?= e($form_values['category']) ?>" placeholder="Datos del destinatario, Información del evento..." required>
                    <datalist id="categoryOptions">
                        <?php foreach ($all_categories as $cat): ?><option value="<?= e($cat) ?>"><?php endforeach; ?>
                    </datalist>
                    <p class="settings-hint" style="margin-bottom:0;">Escribe una categoría existente para agrupar ahí, o una nueva para crearla.</p>
                </div>
                <div class="form-group">
                    <label for="varDescription">Descripción</label>
                    <input type="text" id="varDescription" name="description" class="form-control" value="<?= e($form_values['description']) ?>" placeholder="Para qué se usa esta variable">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="varSample">Valor de ejemplo</label>
                    <input type="text" id="varSample" name="sample_value" class="form-control" value="<?= e($form_values['sample_value']) ?>" placeholder="Se usa en la vista previa y el correo de prueba">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm" id="variableSubmitBtn">Crear variable</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: confirmar eliminación -->
<div class="modal-overlay" id="deleteVarModal">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h3>Eliminar variable</h3>
                <p id="deleteVarModalSubtitle">¿Eliminar esta variable?</p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="post" action="<?= admin_url('email_variable_delete.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" id="deleteVarId" value="">
            <div class="modal-body">
                <p style="margin:0;font-size:13.5px;color:var(--admin-text-muted);">Si alguna plantilla ya usa esta variable, dejará de sustituirse y se marcará como no registrada la próxima vez que se edite o publique.</p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm" style="background:var(--primary-color-dark);">Eliminar definitivamente</button>
            </div>
        </form>
    </div>
</div>

<script>
function slugifyVarName(text) {
    var NFD_MARKS = new RegExp('[' + String.fromCharCode(0x0300) + '-' + String.fromCharCode(0x036f) + ']', 'g');
    return (text || '')
        .normalize('NFD').replace(NFD_MARKS, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
}

var varNameInput = document.getElementById('varName');
var varNamePreview = document.getElementById('varNamePreview');
function updateVarNamePreview() {
    varNamePreview.textContent = '{{' + (slugifyVarName(varNameInput.value) || 'nombre_variable') + '}}';
}
varNameInput.addEventListener('input', updateVarNamePreview);
updateVarNamePreview();

function openVariableModal(mode, data) {
    document.getElementById('variableModalTitle').textContent = mode === 'edit' ? 'Editar variable' : 'Nueva variable';
    document.getElementById('variableSubmitBtn').textContent = mode === 'edit' ? 'Guardar cambios' : 'Crear variable';
    document.getElementById('varId').value = data.id || '';
    document.getElementById('varName').value = data.name || '';
    document.getElementById('varCategory').value = data.category || '';
    document.getElementById('varDescription').value = data.description || '';
    document.getElementById('varSample').value = data.sample || '';
    updateVarNamePreview();
    openModal('variableModal');
}

document.getElementById('newVariableBtn').addEventListener('click', function () {
    openVariableModal('create', {});
});
document.querySelectorAll('.js-edit-var-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        openVariableModal('edit', {
            id: btn.getAttribute('data-id'),
            name: btn.getAttribute('data-name'),
            category: btn.getAttribute('data-category'),
            description: btn.getAttribute('data-description'),
            sample: btn.getAttribute('data-sample'),
        });
    });
});
document.querySelectorAll('.js-delete-var-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('deleteVarId').value = btn.getAttribute('data-id');
        document.getElementById('deleteVarModalSubtitle').textContent = '¿Eliminar "{{' + btn.getAttribute('data-name') + '}}"?';
        openModal('deleteVarModal');
    });
});

<?php if ($reopen_modal): ?>
document.addEventListener('DOMContentLoaded', function () {
    openVariableModal(<?= $form_values['id'] ? "'edit'" : "'create'" ?>, {
        id: <?= json_encode($form_values['id']) ?>,
        name: <?= json_encode($form_values['name']) ?>,
        category: <?= json_encode($form_values['category']) ?>,
        description: <?= json_encode($form_values['description']) ?>,
        sample: <?= json_encode($form_values['sample_value']) ?>
    });
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
