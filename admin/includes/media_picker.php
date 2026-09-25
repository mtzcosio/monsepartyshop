<?php
/**
 * Selector reutilizable de la Biblioteca de archivos: permite elegir un
 * archivo ya subido en cualquier formulario del panel, en vez de tener que
 * volver a cargarlo. Se apoya en media_library.php y en el sistema
 * genérico de modales (admin_footer.php).
 *
 * $field_id       identificador único en la página (se usa para los ids/nombres generados).
 * $label          texto del botón que abre el selector.
 * $current_url    URL ya elegida, si la hay (para precargar la vista previa).
 * $images_only    si true, el selector solo lista archivos de imagen.
 * $show_trigger   si false, no se imprime el botón (útil cuando otro control,
 *                 como el botón de imagen de Quill, ya actúa como disparador).
 */
function render_media_picker_field($field_id, $label = 'Elegir de la biblioteca', $current_url = '', $images_only = true, $show_trigger = true) {
    require_once __DIR__ . '/media_library.php';
    $db = get_db();
    if ($images_only) {
        $placeholders = implode(',', array_fill(0, count(media_image_extensions()), '?'));
        $stmt = $db->prepare("SELECT * FROM media_library WHERE file_type IN ($placeholders) ORDER BY created_at DESC");
        $stmt->execute(media_image_extensions());
    } else {
        $stmt = $db->query('SELECT * FROM media_library ORDER BY created_at DESC');
    }
    $files = $stmt->fetchAll();
    $modal_id = 'mediaPicker_' . $field_id;
    ?>
    <div class="media-picker-field" data-field="<?= e($field_id) ?>">
        <div class="media-picker-preview" id="<?= e($field_id) ?>_preview" style="<?= $current_url ? '' : 'display:none;' ?>">
            <div class="media-picker-preview-thumb"><img src="<?= e($current_url) ?>" alt=""></div>
            <div class="media-picker-preview-name"><?= e($current_url ? basename($current_url) : '') ?></div>
        </div>
        <input type="hidden" name="<?= e($field_id) ?>_media_url" id="<?= e($field_id) ?>_media_url" value="<?= e($current_url) ?>">
        <?php if ($show_trigger): ?>
        <button type="button" class="btn btn-secondary btn-sm" data-modal-open="<?= e($modal_id) ?>"><?= e($label) ?></button>
        <?php endif; ?>
    </div>

    <div class="modal-overlay" id="<?= e($modal_id) ?>">
        <div class="modal-box modal-wide">
            <div class="modal-head">
                <div>
                    <h3>Elegir de la biblioteca</h3>
                    <p>Selecciona un archivo ya subido para reutilizarlo, sin volver a cargarlo.</p>
                </div>
                <button type="button" class="modal-close-btn" data-modal-close>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="var-search" style="margin-bottom:16px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    <input type="text" class="js-media-picker-search" data-target="<?= e($modal_id) ?>" placeholder="Buscar archivo...">
                </div>
                <?php if ($files): ?>
                <div class="media-grid" style="max-height:420px;overflow-y:auto;">
                    <?php foreach ($files as $f): ?>
                        <div class="media-card selectable js-media-picker-item"
                             data-search="<?= e(mb_strtolower($f['original_name'] . ' ' . $f['category'])) ?>"
                             data-url="<?= e(base_url($f['file_path'])) ?>"
                             data-path="<?= e($f['file_path']) ?>"
                             data-name="<?= e($f['original_name']) ?>"
                             data-field="<?= e($field_id) ?>">
                            <div class="media-thumb">
                                <?php if (media_is_image_ext($f['file_type'])): ?>
                                    <img src="<?= e(base_url($f['file_path'])) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <?= media_file_icon($f['file_type']) ?>
                                <?php endif; ?>
                                <span class="media-type-tag"><?= e($f['file_type']) ?></span>
                            </div>
                            <div class="media-info" style="padding-bottom:10px;">
                                <div class="media-name" title="<?= e($f['original_name']) ?>"><?= e($f['original_name']) ?></div>
                                <div class="media-meta"><?= e($f['category']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="var-empty js-media-picker-empty" style="display:none;">No se encontraron archivos.</div>
                <?php else: ?>
                    <div class="var-empty">Aún no hay archivos <?= $images_only ? 'de imagen ' : '' ?>en la biblioteca. <a href="<?= admin_url('media_library.php') ?>" target="_blank">Sube el primero</a>.</div>
                <?php endif; ?>
            </div>
            <div class="modal-foot" style="justify-content:space-between;">
                <a href="<?= admin_url('media_library.php') ?>" target="_blank" class="btn btn-secondary btn-sm">Administrar biblioteca</a>
                <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cerrar</button>
            </div>
        </div>
    </div>
    <?php
}
