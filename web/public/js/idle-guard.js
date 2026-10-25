/*
 * Five minutes without a click and the tab signs itself out, the same way the server-side session expires.
 * The client only makes that feel immediate — a closed or refreshed tab is still governed by the session lifetime.
 */
(function () {
    var minutes = parseInt(document.documentElement.dataset.idleMinutes || '5', 10);
    var limit = minutes * 60 * 1000;
    var key = 'wirelakes-last-activity';

    function beat() {
        try { sessionStorage.setItem(key, String(Date.now())); } catch (e) {}
    }

    function signOut() {
        var form = document.querySelector('form[action$="/logout"]');
        if (form) {
            form.submit();
        } else {
            window.location.href = '/login?idle=1';
        }
    }

    function check() {
        var last;
        try { last = parseInt(sessionStorage.getItem(key) || '0', 10); } catch (e) { return; }
        if (!last) { beat(); return; }
        if (Date.now() - last > limit) { signOut(); }
    }

    var quiet = false;
    ['click', 'keydown', 'scroll', 'touchstart', 'mousemove'].forEach(function (event) {
        window.addEventListener(event, function () {
            if (quiet) { return; }
            beat();
            quiet = true;
            setTimeout(function () { quiet = false; }, 2000);
        }, true);
    });

    window.addEventListener('focus', check);
    setInterval(check, 15000);
    beat();
})();
