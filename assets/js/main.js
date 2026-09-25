document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.accordion-header').forEach(function (header) {
        header.addEventListener('click', function () {
            header.parentElement.classList.toggle('open');
        });
    });

    var mainImageTag = document.getElementById('mainProductImageTag');
    document.querySelectorAll('.product-thumbnail').forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            if (mainImageTag) { mainImageTag.src = thumb.getAttribute('data-image'); }
            document.querySelectorAll('.product-thumbnail').forEach(function (t) { t.classList.remove('active'); });
            thumb.classList.add('active');
        });
    });

    var navToggle = document.getElementById('navToggle');
    var mainNav = document.getElementById('mainNav');
    if (navToggle && mainNav) {
        navToggle.addEventListener('click', function () {
            var isOpen = mainNav.classList.toggle('open');
            navToggle.classList.toggle('open', isOpen);
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        mainNav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                mainNav.classList.remove('open');
                navToggle.classList.remove('open');
                navToggle.setAttribute('aria-expanded', 'false');
            });
        });
    }
});
