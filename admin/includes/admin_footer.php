        </main>
    </div>
</div>
<div class="toast-container" id="toastContainer"></div>
<script>
function showToast(message, type) {
    var container = document.getElementById('toastContainer');
    if (!container) { return; }
    var toast = document.createElement('div');
    toast.className = 'toast' + (type ? ' toast-' + type : '');
    toast.textContent = message;
    container.appendChild(toast);
    requestAnimationFrame(function () { toast.classList.add('show'); });
    setTimeout(function () {
        toast.classList.remove('show');
        setTimeout(function () { toast.remove(); }, 250);
    }, 2600);
}

function openModal(id) {
    var modal = document.getElementById(id);
    if (modal) { modal.classList.add('open'); }
}
function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) { modal.classList.remove('open'); }
}

document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
    btn.addEventListener('click', function () { openModal(btn.getAttribute('data-modal-open')); });
});
document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var overlay = btn.closest('.modal-overlay');
        if (overlay) { closeModal(overlay.id); }
    });
});
document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) { closeModal(overlay.id); }
    });
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(function (m) { m.classList.remove('open'); });
    }
});

document.querySelectorAll('.var-accordion-header').forEach(function (header) {
    header.addEventListener('click', function () {
        var item = header.closest('.var-accordion-item');
        if (item) { item.classList.toggle('open'); }
    });
});

document.querySelectorAll('[data-copy-text]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var text = btn.getAttribute('data-copy-text');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () { showToast('Copiado: ' + text, 'success'); });
        } else {
            showToast('No se pudo copiar automáticamente.', 'error');
        }
    });
});
</script>
<script>
(function () {
    var toggle = document.getElementById('adminNavToggle');
    var closeBtn = document.getElementById('adminSidebarClose');
    var sidebar = document.getElementById('adminSidebar');
    var overlay = document.getElementById('adminOverlay');
    if (!sidebar || !overlay) { return; }

    function closeMenu() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
    }
    function openMenu() {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        if (toggle) { toggle.setAttribute('aria-expanded', 'true'); }
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (sidebar.classList.contains('open')) { closeMenu(); } else { openMenu(); }
        });
    }
    if (closeBtn) { closeBtn.addEventListener('click', closeMenu); }
    overlay.addEventListener('click', closeMenu);
    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeMenu);
    });
})();

(function () {
    var dropdowns = document.querySelectorAll('.dropdown-wrap');
    dropdowns.forEach(function (wrap) {
        var toggleBtn = wrap.querySelector('button');
        if (!toggleBtn) { return; }
        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = wrap.classList.contains('open');
            dropdowns.forEach(function (w) { w.classList.remove('open'); });
            if (!isOpen) { wrap.classList.add('open'); }
        });
    });
    document.addEventListener('click', function () {
        dropdowns.forEach(function (w) { w.classList.remove('open'); });
    });
})();

document.querySelectorAll('.js-media-picker-item').forEach(function (card) {
    card.addEventListener('click', function () {
        var field = card.getAttribute('data-field');
        var url = card.getAttribute('data-url');
        var path = card.getAttribute('data-path');
        var name = card.getAttribute('data-name');

        if (field === 'quill_content' && window.insertQuillImage) {
            window.insertQuillImage(url);
        } else {
            var hidden = document.getElementById(field + '_media_url');
            if (hidden) { hidden.value = path; }
            var preview = document.getElementById(field + '_preview');
            if (preview) {
                preview.style.display = 'flex';
                var img = preview.querySelector('img');
                if (img) { img.src = url; }
                var nameEl = preview.querySelector('.media-picker-preview-name');
                if (nameEl) { nameEl.textContent = name; }
            }
        }

        var overlay = card.closest('.modal-overlay');
        if (overlay) { closeModal(overlay.id); }
        showToast('Archivo seleccionado de la biblioteca', 'success');
    });
});
document.querySelectorAll('.js-media-picker-search').forEach(function (input) {
    input.addEventListener('input', function () {
        var term = input.value.trim().toLowerCase();
        var modal = document.getElementById(input.getAttribute('data-target'));
        if (!modal) { return; }
        var anyVisible = false;
        modal.querySelectorAll('.js-media-picker-item').forEach(function (card) {
            var match = term === '' || (card.getAttribute('data-search') || '').indexOf(term) !== -1;
            card.style.display = match ? '' : 'none';
            if (match) { anyVisible = true; }
        });
        var empty = modal.querySelector('.js-media-picker-empty');
        if (empty) { empty.style.display = anyVisible ? 'none' : 'block'; }
    });
});

document.querySelectorAll('.color-field').forEach(function (field) {
    var swatch = field.querySelector('.color-swatch-input');
    var hex = field.querySelector('.color-hex-input');
    if (!swatch || !hex) { return; }
    var hexPattern = /^#[0-9A-Fa-f]{6}$/;

    swatch.addEventListener('input', function () {
        hex.value = swatch.value.toUpperCase();
    });
    hex.addEventListener('input', function () {
        var v = hex.value.trim();
        if (hexPattern.test(v)) { swatch.value = v; }
    });
    hex.addEventListener('blur', function () {
        var v = hex.value.trim();
        hex.value = hexPattern.test(v) ? v.toUpperCase() : swatch.value.toUpperCase();
    });
});

document.querySelectorAll('.settings-tabs, .tabs-wrap').forEach(function (wrap) {
    var buttons = wrap.querySelectorAll('.tab-btn');
    var panels = wrap.querySelectorAll('.tab-panel');
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            buttons.forEach(function (b) { b.classList.remove('active'); });
            panels.forEach(function (p) { p.classList.remove('active'); });
            btn.classList.add('active');
            btn.getAttribute('data-tab').split(/\s+/).forEach(function (id) {
                var target = document.getElementById(id);
                if (target) { target.classList.add('active'); }
            });
        });
    });
});

document.querySelectorAll('[data-toggle-target]').forEach(function (btn) {
    var target = document.getElementById(btn.getAttribute('data-toggle-target'));
    if (!target) { return; }
    var label = btn.querySelector('.filter-toggle-label') || btn;
    btn.addEventListener('click', function () {
        var isHidden = target.style.display === 'none';
        target.style.display = isHidden ? 'block' : 'none';
        label.textContent = isHidden ? (btn.getAttribute('data-label-open') || 'Ocultar') : (btn.getAttribute('data-label-closed') || 'Mostrar');
    });
});
</script>
</body>
</html>
