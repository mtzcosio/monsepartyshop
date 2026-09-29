document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.accordion-header').forEach(function (header) {
        header.addEventListener('click', function () {
            header.parentElement.classList.toggle('open');
        });
    });

    // Carrusel de imágenes: flechas, puntos, miniaturas (hermanas del carrusel), swipe, teclado y autoplay.
    document.querySelectorAll('[data-carousel]').forEach(function (carousel) {
        var track = carousel.querySelector('.carousel-track');
        var slides = carousel.querySelectorAll('.carousel-slide');
        var total = slides.length;
        if (!track || total < 2) { return; }

        var goButtons = carousel.parentElement.querySelectorAll('[data-carousel-go]');
        var current = 0;
        var timer = null;
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function goTo(index) {
            current = (index + total) % total;
            track.style.transform = 'translateX(-' + (current * 100) + '%)';
            goButtons.forEach(function (btn) {
                btn.classList.toggle('active', parseInt(btn.getAttribute('data-carousel-go'), 10) === current);
            });
        }
        function stop() { if (timer) { clearInterval(timer); timer = null; } }
        function start() {
            if (reduceMotion) { return; }
            stop();
            timer = setInterval(function () { goTo(current + 1); }, 5000);
        }

        carousel.querySelector('[data-carousel-prev]').addEventListener('click', function () { goTo(current - 1); start(); });
        carousel.querySelector('[data-carousel-next]').addEventListener('click', function () { goTo(current + 1); start(); });
        goButtons.forEach(function (btn) {
            btn.addEventListener('click', function () { goTo(parseInt(btn.getAttribute('data-carousel-go'), 10)); start(); });
        });

        carousel.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowLeft') { goTo(current - 1); start(); }
            if (ev.key === 'ArrowRight') { goTo(current + 1); start(); }
        });

        carousel.addEventListener('mouseenter', stop);
        carousel.addEventListener('mouseleave', start);

        var touchStartX = null;
        carousel.addEventListener('touchstart', function (ev) { touchStartX = ev.touches[0].clientX; stop(); }, { passive: true });
        carousel.addEventListener('touchend', function (ev) {
            if (touchStartX === null) { return; }
            var dx = ev.changedTouches[0].clientX - touchStartX;
            if (Math.abs(dx) > 40) { goTo(dx < 0 ? current + 1 : current - 1); }
            touchStartX = null;
            start();
        });

        start();
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
