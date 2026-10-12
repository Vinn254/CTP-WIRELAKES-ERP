function toggleTheme(btn) {
    const dark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('ctp-theme', dark ? 'dark' : 'light');
    document.cookie = 'ctp-theme=' + (dark ? 'dark' : 'light') + ';path=/;max-age=31536000';
}

document.addEventListener('DOMContentLoaded', function () {
    if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const els = document.querySelectorAll('.card, .donate-strip');
        els.forEach(function (el) {
            el.classList.add('reveal');
            const siblings = Array.from(el.parentElement.children);
            el.style.transitionDelay = (siblings.indexOf(el) % 4) * 80 + 'ms';
        });
        const io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.classList.add('in');
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.12 });
        els.forEach(function (el) { io.observe(el); });
    }
});
