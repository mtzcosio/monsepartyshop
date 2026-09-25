<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();
require_once __DIR__ . '/includes/email_variables.php';
require_once __DIR__ . '/includes/media_picker.php';

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$tpl = [
    'name' => '', 'code' => '', 'type' => 'Personalizado', 'status' => 'draft',
    'sender_name' => '', 'sender_email' => '', 'subject' => '', 'preheader' => '', 'content_html' => '',
];

if ($id) {
    $stmt = $db->prepare('SELECT * FROM email_templates WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $found = $stmt->fetch();
    if ($found) {
        $tpl = $found;
    } else {
        flash_set('Plantilla no encontrada.', 'error');
        redirect(admin_url('email_templates.php'));
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Tu sesión expiró, intenta de nuevo.';
    }
    $save_action = ($_POST['save_action'] ?? 'draft') === 'publish' ? 'publish' : 'draft';

    $name = trim($_POST['name'] ?? '');
    $code_input = trim($_POST['code'] ?? '');
    $code = strtoupper(str_replace('-', '_', slugify($code_input !== '' ? $code_input : $name)));
    $type = trim($_POST['type'] ?? '') ?: 'Personalizado';
    $sender_name = trim($_POST['sender_name'] ?? '');
    $sender_email = trim($_POST['sender_email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $preheader = trim($_POST['preheader'] ?? '');
    $content_html = $_POST['content_html'] ?? '';

    if ($name === '') { $errors[] = 'El nombre de la plantilla es obligatorio.'; }
    if ($code === '') { $errors[] = 'El código de la plantilla es obligatorio.'; }

    if ($code !== '') {
        $dupe_stmt = $db->prepare('SELECT id FROM email_templates WHERE code = :code AND id != :id');
        $dupe_stmt->execute(['code' => $code, 'id' => $id]);
        if ($dupe_stmt->fetch()) {
            $errors[] = 'Ya existe otra plantilla con el código "' . $code . '". El código debe ser único.';
        }
    }

    if ($sender_email !== '' && !filter_var($sender_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo del remitente no tiene un formato válido.';
    }

    if ($save_action === 'publish') {
        if ($subject === '') { $errors[] = 'El asunto es obligatorio para publicar la plantilla.'; }
        if (trim(strip_tags($content_html)) === '' && stripos($content_html, '<img') === false) {
            $errors[] = 'El contenido del correo es obligatorio para publicar la plantilla.';
        }
        if ($sender_email === '') { $errors[] = 'El correo del remitente es obligatorio para publicar la plantilla.'; }
    }

    $unknown_vars = array_unique(array_merge(
        email_find_unknown_variables($subject),
        email_find_unknown_variables($preheader),
        email_find_unknown_variables($content_html)
    ));
    foreach ($unknown_vars as $uv) {
        $errors[] = 'La variable {{' . $uv . '}} no está registrada en el catálogo de variables.';
    }

    if (!$errors) {
        $data = [
            'name' => $name, 'code' => $code, 'type' => $type,
            'status' => $save_action === 'publish' ? 'active' : 'draft',
            'sender_name' => $sender_name, 'sender_email' => $sender_email,
            'subject' => $subject, 'preheader' => $preheader, 'content_html' => $content_html,
        ];
        if ($id) {
            $data['id'] = $id;
            $db->prepare(
                'UPDATE email_templates SET name=:name, code=:code, type=:type, status=:status,
                 sender_name=:sender_name, sender_email=:sender_email, subject=:subject, preheader=:preheader,
                 content_html=:content_html WHERE id=:id'
            )->execute($data);
            flash_set($save_action === 'publish' ? 'Plantilla guardada y publicada.' : 'Borrador guardado.');
            redirect(admin_url('email_template_form.php?id=' . $id));
        } else {
            $db->prepare(
                'INSERT INTO email_templates (name, code, type, status, sender_name, sender_email, subject, preheader, content_html)
                 VALUES (:name,:code,:type,:status,:sender_name,:sender_email,:subject,:preheader,:content_html)'
            )->execute($data);
            $new_id = (int)$db->lastInsertId();
            flash_set($save_action === 'publish' ? 'Plantilla creada y publicada.' : 'Borrador creado.');
            redirect(admin_url('email_template_form.php?id=' . $new_id));
        }
    }

    $tpl = array_merge($tpl, [
        'name' => $name, 'code' => $code, 'type' => $type,
        'sender_name' => $sender_name, 'sender_email' => $sender_email,
        'subject' => $subject, 'preheader' => $preheader, 'content_html' => $content_html,
    ]);
}

$status_labels = ['active' => 'Activa', 'inactive' => 'Inactiva', 'draft' => 'Borrador'];
$page_title = $id ? 'Editar plantilla' : 'Nueva plantilla';
require_once __DIR__ . '/includes/admin_header.php';
?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

<div class="admin-topbar">
    <div>
        <h1 class="mt-0"><?= $id ? 'Editar plantilla' : 'Nueva plantilla' ?></h1>
        <p class="page-subtitle">
            <?php if ($id): ?>
                <?= e($tpl['name']) ?> &nbsp;·&nbsp; <span class="badge-status <?= e($tpl['status']) ?>"><?= e($status_labels[$tpl['status']] ?? $tpl['status']) ?></span>
            <?php else: ?>
                Completa la información para crear una nueva plantilla reutilizable.
            <?php endif; ?>
        </p>
    </div>
</div>

<?php foreach ($errors as $error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endforeach; ?>
<div class="alert alert-warning" id="liveVarWarning" style="display:none;"></div>

<form method="post" id="templateForm">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="save_action" id="saveActionField" value="draft">

    <div class="editor-grid">
        <div class="editor-main">
            <div class="section-card">
                <div class="section-card-head"><div><h3>Información general</h3></div></div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Nombre de la plantilla</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?= e($tpl['name']) ?>" placeholder="Confirmación de asistencia" required>
                    </div>
                    <div class="form-group">
                        <label for="code">Código único</label>
                        <input type="text" id="code" name="code" class="form-control" value="<?= e($tpl['code']) ?>" placeholder="Se genera del nombre si lo dejas vacío" style="font-family:'SFMono-Regular',Consolas,monospace;">
                    </div>
                    <div class="form-group">
                        <label for="type">Tipo de plantilla</label>
                        <select id="type" name="type" class="form-control">
                            <?php foreach (email_template_types() as $t): ?>
                                <option value="<?= e($t) ?>" <?= $tpl['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="statusDisplay">Estado</label>
                        <input type="text" id="statusDisplay" class="form-control" value="<?= e($status_labels[$tpl['status']] ?? 'Borrador') ?>" disabled>
                        <p class="settings-hint" style="margin-bottom:0;">Se actualiza con los botones "Guardar borrador" / "Guardar plantilla" de abajo.</p>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-card-head"><div><h3>Configuración del correo</h3></div></div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="sender_name">Nombre del remitente</label>
                        <input type="text" id="sender_name" name="sender_name" class="form-control" value="<?= e($tpl['sender_name']) ?>" placeholder="<?= e(get_setting('store_name', 'Monse Party Shop')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="sender_email">Correo del remitente</label>
                        <input type="email" id="sender_email" name="sender_email" class="form-control" value="<?= e($tpl['sender_email']) ?>" placeholder="<?= e(get_setting('email', 'hola@tudominio.com')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="subject">Asunto</label>
                    <input type="text" id="subject" name="subject" class="form-control" value="<?= e($tpl['subject']) ?>" placeholder="Tu asistencia a {{nombre_evento}} ha sido confirmada">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="preheader">Preencabezado</label>
                    <input type="text" id="preheader" name="preheader" class="form-control" value="<?= e($tpl['preheader']) ?>" placeholder="Texto corto que se ve junto al asunto en la bandeja de entrada">
                </div>
            </div>

            <div class="section-card">
                <div class="section-card-head">
                    <div>
                        <h3>Contenido del correo</h3>
                        <p>Usa el editor visual o el código HTML. Inserta variables desde el panel de la derecha.</p>
                    </div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                    <button type="button" id="toggleHtmlView" class="btn btn-secondary btn-sm">Ver/editar código HTML</button>
                    <button type="button" id="insertButtonBtn" class="btn btn-secondary btn-sm">+ Botón</button>
                    <button type="button" id="insertDividerBtn" class="btn btn-secondary btn-sm">+ Separador</button>
                </div>
                <div id="descriptionEditor" style="background:#fff;border-radius:var(--radius-sm);min-height:280px;"></div>
                <textarea id="descriptionHtmlSource" class="form-control" style="display:none;min-height:280px;font-family:monospace;font-size:13px;"></textarea>
                <textarea id="content_html" name="content_html" style="display:none;"><?= e($tpl['content_html']) ?></textarea>
                <p class="settings-hint">Sintaxis de variables: <code>{{nombre_variable}}</code>. El sistema detecta automáticamente las que uses. El ícono de imagen de la barra de herramientas abre la Biblioteca de archivos.</p>
                <?php render_media_picker_field('quill_content', '', '', true, false); ?>
            </div>

            <div class="section-card">
                <div class="filter-actions" style="justify-content:space-between;">
                    <a href="<?= admin_url('email_templates.php') ?>" class="btn btn-secondary">Cancelar</a>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <button type="button" id="previewBtn" class="btn btn-secondary">Vista previa</button>
                        <button type="button" id="sendTestBtn" class="btn btn-secondary">Enviar prueba</button>
                        <button type="submit" id="saveDraftBtn" class="btn btn-secondary">Guardar borrador</button>
                        <button type="submit" id="savePublishBtn" class="btn btn-primary">Guardar plantilla</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="editor-side">
            <div class="section-card var-panel">
                <div class="section-card-head">
                    <div><h3>Variables disponibles</h3><p>Haz clic en <strong>+</strong> para insertar en el campo o editor activo.</p></div>
                    <a href="<?= admin_url('email_variables.php') ?>" target="_blank" class="icon-action-btn" title="Crear o administrar variables personalizadas (se abre en una pestaña nueva)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </a>
                </div>
                <div class="var-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    <input type="text" id="varSearchInput" placeholder="Buscar variable...">
                </div>
                <div id="varAccordion">
                    <?php $first = true; foreach (email_variable_catalog() as $category => $vars): ?>
                        <div class="var-accordion-item <?= $first ? 'open' : '' ?>" data-category>
                            <div class="var-accordion-header">
                                <span><?= e($category) ?></span>
                                <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                            </div>
                            <div class="var-accordion-body">
                                <?php foreach ($vars as $var_name => $var_desc): ?>
                                    <div class="var-item" data-var-search="<?= e(mb_strtolower($var_name . ' ' . $var_desc)) ?>">
                                        <div class="var-item-info">
                                            <span class="var-item-code">{{<?= e($var_name) ?>}}</span>
                                            <div class="var-item-desc"><?= e($var_desc) ?></div>
                                        </div>
                                        <div class="var-item-actions">
                                            <button type="button" class="var-item-btn" data-copy-text="{{<?= e($var_name) ?>}}" title="Copiar">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                            </button>
                                            <button type="button" class="var-item-btn js-insert-var" data-var="<?= e($var_name) ?>" title="Insertar">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php $first = false; endforeach; ?>
                </div>
                <div class="var-empty" id="varEmptyState" style="display:none;">No se encontraron variables.</div>
            </div>
        </div>
    </div>
</form>

<!-- Modal: vista previa -->
<div class="modal-overlay" id="previewModal">
    <div class="modal-box modal-wide">
        <div class="modal-head">
            <div>
                <h3>Vista previa</h3>
                <p>Con datos de ejemplo — así lo vería el destinatario.</p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="email-preview-shell">
                <div class="email-preview-meta">
                    <div><strong>De:</strong> <span id="previewFrom"></span></div>
                    <div><strong>Asunto:</strong> <span id="previewSubject"></span></div>
                    <div id="previewPreheaderRow" style="display:none;"><strong>Preencabezado:</strong> <span id="previewPreheader"></span></div>
                </div>
                <iframe class="email-preview-frame" id="previewFrame"></iframe>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal: enviar prueba -->
<div class="modal-overlay" id="sendTestModal">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h3>Enviar correo de prueba</h3>
                <p>Se enviará el contenido actual del editor (aunque no lo hayas guardado).</p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="post" action="<?= admin_url('email_template_send_test.php') ?>" id="sendTestForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <input type="hidden" name="inline_subject" id="inlineSubject" value="">
            <input type="hidden" name="inline_sender_name" id="inlineSenderName" value="">
            <input type="hidden" name="inline_sender_email" id="inlineSenderEmail" value="">
            <input type="hidden" name="inline_content_html" id="inlineContentHtml" value="">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:0;">
                    <label for="sendTestEmail2">Correo electrónico de destino</label>
                    <input type="email" id="sendTestEmail2" name="test_email" class="form-control" placeholder="ejemplo@correo.com" required>
                    <p class="settings-hint">Se sustituirán las variables con datos de ejemplo antes de enviarlo.</p>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">Enviar prueba</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
var KNOWN_VARIABLES = <?= json_encode(email_variable_names()) ?>;
var SAMPLE_DATA = <?= json_encode(email_variable_sample_data()) ?>;

function renderVariables(text) {
    return String(text || '').replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, function (match, name) {
        var key = name.toLowerCase();
        return Object.prototype.hasOwnProperty.call(SAMPLE_DATA, key) ? SAMPLE_DATA[key] : match;
    });
}

function findUnknownVariables(text) {
    var found = {};
    var re = /\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g;
    var m;
    while ((m = re.exec(String(text || ''))) !== null) {
        var key = m[1].toLowerCase();
        if (KNOWN_VARIABLES.indexOf(key) === -1) { found[key] = true; }
    }
    return Object.keys(found);
}

(function () {
    var hiddenInput = document.getElementById('content_html');
    var editorDiv = document.getElementById('descriptionEditor');
    var htmlSource = document.getElementById('descriptionHtmlSource');
    var toggleBtn = document.getElementById('toggleHtmlView');
    if (!editorDiv || typeof Quill === 'undefined') { return; }

    // Quill no trae soporte para <hr> por defecto (lo descarta al pegar HTML);
    // se registra como un blot propio para poder insertar separadores.
    if (!Quill.imports['formats/divider']) {
        var BlockEmbed = Quill.import('blots/block/embed');
        class DividerBlot extends BlockEmbed {
            static create() {
                var node = super.create();
                node.setAttribute('style', 'border:none;border-top:1px solid #eeeeee;margin:20px 0;');
                return node;
            }
        }
        DividerBlot.blotName = 'divider';
        DividerBlot.tagName = 'hr';
        Quill.register(DividerBlot);
    }

    var quill = new Quill('#descriptionEditor', {
        theme: 'snow',
        modules: {
            toolbar: {
                container: [
                    [{ header: [2, 3, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link', 'image'],
                    ['clean']
                ],
                handlers: {
                    // En vez del selector de archivos nativo de Quill, el botón de
                    // imagen abre la Biblioteca de archivos (subir o reutilizar).
                    image: function () { openModal('mediaPicker_quill_content'); }
                }
            }
        }
    });
    quill.clipboard.dangerouslyPasteHTML(hiddenInput.value || '');

    window.insertQuillImage = function (url) {
        var range = currentQuillRange();
        quill.insertEmbed(range.index, 'image', url, 'user');
        quill.setSelection(range.index + 1);
        lastQuillRange = { index: range.index + 1, length: 0 };
    };

    var showingHtml = false;
    var toolbarEl = document.querySelector('#descriptionEditor').previousElementSibling;
    var lastQuillRange = null;

    quill.on('selection-change', function (range) {
        if (range) { lastQuillRange = range; lastFocusTarget = 'quill'; }
    });
    quill.on('text-change', function () { checkUnknownVariables(); });

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
        checkUnknownVariables();
    });

    // Quill guarda su propia posición de cursor al perder el foco (savedRange) y la
    // restaura con getSelection(true); es más confiable que rastrear la selección
    // nosotros mismos, que se desincroniza tras varios ciclos de foco/desenfoque
    // (por ejemplo, al usar el buscador de variables entre una inserción y otra).
    function currentQuillRange() {
        return quill.getSelection(true) || lastQuillRange || { index: quill.getLength(), length: 0 };
    }

    document.getElementById('insertButtonBtn').addEventListener('click', function () {
        var snippet = '<p style="text-align:center;"><a href="#" style="background:#FF6F91;color:#ffffff;padding:12px 28px;border-radius:999px;text-decoration:none;font-weight:bold;display:inline-block;">Texto del botón</a></p>';
        if (showingHtml) {
            htmlSource.value += snippet;
        } else {
            var range = currentQuillRange();
            quill.clipboard.dangerouslyPasteHTML(range.index, snippet);
        }
    });
    document.getElementById('insertDividerBtn').addEventListener('click', function () {
        if (showingHtml) {
            htmlSource.value += '<hr style="border:none;border-top:1px solid #eeeeee;margin:20px 0;">';
        } else {
            var range = currentQuillRange();
            quill.insertEmbed(range.index, 'divider', true, 'user');
            quill.setSelection(range.index + 1);
        }
    });

    window.getCurrentContentHtml = function () {
        return showingHtml ? htmlSource.value : quill.root.innerHTML;
    };
    window.insertIntoQuill = function (token) {
        var range = currentQuillRange();
        quill.insertText(range.index, token, 'user');
        quill.setSelection(range.index + token.length);
        lastQuillRange = { index: range.index + token.length, length: 0 };
    };

    document.getElementById('templateForm').addEventListener('submit', function () {
        hiddenInput.value = window.getCurrentContentHtml();
    });
})();

var lastFocusTarget = 'quill';
['subject', 'preheader'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) { el.addEventListener('focus', function () { lastFocusTarget = id; }); }
});

function insertAtCursor(input, text) {
    var start = input.selectionStart != null ? input.selectionStart : input.value.length;
    var end = input.selectionEnd != null ? input.selectionEnd : input.value.length;
    input.value = input.value.slice(0, start) + text + input.value.slice(end);
    input.focus();
    input.selectionStart = input.selectionEnd = start + text.length;
}

document.querySelectorAll('.js-insert-var').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var token = '{{' + btn.getAttribute('data-var') + '}}';
        if (lastFocusTarget === 'subject' || lastFocusTarget === 'preheader') {
            insertAtCursor(document.getElementById(lastFocusTarget), token);
        } else if (window.insertIntoQuill) {
            window.insertIntoQuill(token);
        }
        showToast('Variable insertada: ' + token, 'success');
        checkUnknownVariables();
    });
});

/* Búsqueda de variables */
var varSearchInput = document.getElementById('varSearchInput');
if (varSearchInput) {
    varSearchInput.addEventListener('input', function () {
        var term = varSearchInput.value.trim().toLowerCase();
        var anyVisible = false;
        document.querySelectorAll('.var-accordion-item').forEach(function (item) {
            var itemHasMatch = false;
            item.querySelectorAll('.var-item').forEach(function (varEl) {
                var match = term === '' || (varEl.getAttribute('data-var-search') || '').indexOf(term) !== -1;
                varEl.style.display = match ? 'flex' : 'none';
                if (match) { itemHasMatch = true; }
            });
            item.style.display = itemHasMatch ? 'block' : 'none';
            if (term !== '' && itemHasMatch) { item.classList.add('open'); }
            if (itemHasMatch) { anyVisible = true; }
        });
        document.getElementById('varEmptyState').style.display = anyVisible ? 'none' : 'block';
    });
}

/* Alerta en vivo de variables no registradas */
function checkUnknownVariables() {
    var subject = document.getElementById('subject').value;
    var preheader = document.getElementById('preheader').value;
    var content = window.getCurrentContentHtml ? window.getCurrentContentHtml() : '';
    var unknown = findUnknownVariables(subject + ' ' + preheader + ' ' + content);
    var banner = document.getElementById('liveVarWarning');
    if (unknown.length) {
        banner.style.display = 'block';
        banner.textContent = (unknown.length === 1 ? 'La variable ' : 'Las variables ')
            + unknown.map(function (v) { return '{{' + v + '}}'; }).join(', ')
            + (unknown.length === 1 ? ' no está registrada' : ' no están registradas')
            + ' en el catálogo de variables.';
    } else {
        banner.style.display = 'none';
    }
}
document.getElementById('subject').addEventListener('input', checkUnknownVariables);
document.getElementById('preheader').addEventListener('input', checkUnknownVariables);
setTimeout(checkUnknownVariables, 400);

/* Vista previa */
document.getElementById('previewBtn').addEventListener('click', function () {
    var senderName = document.getElementById('sender_name').value || '<?= e(get_setting('store_name', 'Monse Party Shop')) ?>';
    var senderEmail = document.getElementById('sender_email').value || '<?= e(get_setting('email', '')) ?>';
    var subject = document.getElementById('subject').value;
    var preheader = document.getElementById('preheader').value;
    var content = window.getCurrentContentHtml ? window.getCurrentContentHtml() : '';

    document.getElementById('previewFrom').textContent = senderName + ' <' + senderEmail + '>';
    document.getElementById('previewSubject').textContent = renderVariables(subject) || '(sin asunto)';
    if (preheader) {
        document.getElementById('previewPreheaderRow').style.display = 'block';
        document.getElementById('previewPreheader').textContent = renderVariables(preheader);
    } else {
        document.getElementById('previewPreheaderRow').style.display = 'none';
    }
    document.getElementById('previewFrame').srcdoc = renderVariables(content) || '<p style="font-family:sans-serif;color:#999;padding:24px;">Sin contenido todavía.</p>';
    openModal('previewModal');
});

/* Enviar prueba: sincroniza los valores actuales antes de abrir el modal */
document.getElementById('sendTestBtn').addEventListener('click', function () {
    document.getElementById('inlineSubject').value = document.getElementById('subject').value;
    document.getElementById('inlineSenderName').value = document.getElementById('sender_name').value;
    document.getElementById('inlineSenderEmail').value = document.getElementById('sender_email').value;
    document.getElementById('inlineContentHtml').value = window.getCurrentContentHtml ? window.getCurrentContentHtml() : '';
    openModal('sendTestModal');
});

/* Distingue "Guardar borrador" de "Guardar plantilla" mediante un campo oculto */
document.getElementById('saveDraftBtn').addEventListener('click', function () {
    document.getElementById('saveActionField').value = 'draft';
});
document.getElementById('savePublishBtn').addEventListener('click', function () {
    document.getElementById('saveActionField').value = 'publish';
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
