<?php
define('ADMIN_PER_PAGE_OPTIONS', [10, 20, 50, 100]);

function admin_current_page() {
    $page = (int)($_GET['page'] ?? 1);
    return $page > 0 ? $page : 1;
}

function admin_get_per_page($default = 20) {
    $per_page = (int)($_GET['per_page'] ?? $default);
    return in_array($per_page, ADMIN_PER_PAGE_OPTIONS, true) ? $per_page : $default;
}

function admin_pagination_query($extra = []) {
    $params = $_GET;
    unset($params['page']);
    $params = array_merge($params, $extra);
    return e(http_build_query($params));
}

/** Contador de registros, centrado, para colocar arriba de la grilla. $label_plural va en plural (ej. "productos"). */
function render_admin_records_count($total_records, $label_plural = 'registros') {
    $total_records = (int)$total_records;
    $label = $total_records === 1 ? rtrim($label_plural, 's') : $label_plural;
    echo '<div class="records-count">' . $total_records . ' ' . e($label) . ' encontrado' . ($total_records === 1 ? '' : 's') . '</div>';
}

/** Selector de "registros por página", siempre visible (fuera del panel de filtros). */
function render_admin_per_page_select($per_page) {
    echo '<form method="get" class="per-page-form">';
    foreach ($_GET as $k => $v) {
        if ($k === 'per_page' || $k === 'page' || is_array($v)) { continue; }
        echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    echo '<label for="per_page" class="per-page-label">Mostrar</label>';
    echo '<select name="per_page" id="per_page" class="form-control" onchange="this.form.submit()">';
    foreach (ADMIN_PER_PAGE_OPTIONS as $opt) {
        echo '<option value="' . $opt . '"' . ($opt === $per_page ? ' selected' : '') . '>' . $opt . '</option>';
    }
    echo '</select>';
    echo '</form>';
}

/** Controles de navegación entre páginas (sin el contador, que ahora va arriba). */
function render_admin_pagination($page, $total_pages) {
    if ($total_pages <= 1) { return; }

    echo '<div class="pagination-links">';
    $window = 2;
    $printed_left_ellipsis = false;
    $printed_right_ellipsis = false;

    if ($page > 1) {
        echo '<a class="btn btn-secondary btn-sm" href="?' . admin_pagination_query(['page' => $page - 1]) . '">‹ Anterior</a>';
    }
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i === 1 || $i === $total_pages || ($i >= $page - $window && $i <= $page + $window)) {
            if ($i === $page) {
                echo '<span class="btn btn-primary btn-sm pagination-current">' . $i . '</span>';
            } else {
                echo '<a class="btn btn-secondary btn-sm" href="?' . admin_pagination_query(['page' => $i]) . '">' . $i . '</a>';
            }
        } elseif ($i < $page && !$printed_left_ellipsis) {
            echo '<span class="pagination-ellipsis">…</span>';
            $printed_left_ellipsis = true;
        } elseif ($i > $page && !$printed_right_ellipsis) {
            echo '<span class="pagination-ellipsis">…</span>';
            $printed_right_ellipsis = true;
        }
    }
    if ($page < $total_pages) {
        echo '<a class="btn btn-secondary btn-sm" href="?' . admin_pagination_query(['page' => $page + 1]) . '">Siguiente ›</a>';
    }
    echo '</div>';
}
