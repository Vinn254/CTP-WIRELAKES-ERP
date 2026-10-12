(function () {
    function openDialog(message, opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            const backdrop = document.createElement('div');
            backdrop.className = 'ctp-backdrop';
            const dialog = document.createElement('div');
            dialog.className = 'ctp-dialog' + (opts.danger ? ' danger' : '');
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');

            const h3 = document.createElement('h3');
            h3.textContent = opts.title || (opts.danger ? 'Delete this?' : 'Please confirm');
            const p = document.createElement('p');
            p.textContent = message;
            const actions = document.createElement('div');
            actions.className = 'dlg-actions';

            const no = document.createElement('button');
            no.className = 'btn btn-ghost';
            no.type = 'button';
            no.textContent = 'Cancel';
            const yes = document.createElement('button');
            yes.className = 'btn ' + (opts.danger ? 'btn-danger' : 'btn-primary');
            yes.type = 'button';
            yes.textContent = opts.ok || 'Confirm';

            actions.append(no, yes);
            dialog.append(h3, p, actions);
            backdrop.append(dialog);
            document.body.append(backdrop);
            requestAnimationFrame(function () { backdrop.classList.add('in'); yes.focus(); });

            function close(value) {
                document.removeEventListener('keydown', onKey);
                backdrop.classList.remove('in');
                setTimeout(function () { backdrop.remove(); }, 180);
                resolve(value);
            }
            function onKey(e) { if (e.key === 'Escape') close(false); }

            no.addEventListener('click', function () { close(false); });
            yes.addEventListener('click', function () { close(true); });
            backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(false); });
            document.addEventListener('keydown', onKey);
        });
    }

    function toast(message, isError) {
        let wrap = document.querySelector('.toast-wrap');
        if (!wrap) { wrap = document.createElement('div'); wrap.className = 'toast-wrap'; document.body.append(wrap); }
        const el = document.createElement('div');
        el.className = 'toast' + (isError ? ' error' : '');
        const text = document.createElement('span');
        text.textContent = message;
        const close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('aria-label', 'Dismiss');
        close.textContent = '✕';
        el.append(text, close);
        wrap.append(el);
        const drop = function () {
            el.style.transition = 'opacity .25s, transform .25s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(10px)';
            setTimeout(function () { el.remove(); }, 260);
        };
        close.addEventListener('click', drop);
        setTimeout(drop, isError ? 9000 : 5200);
    }

    function ask(form) {
        if (form.dataset.confirmed === '1') return true;
        const danger = form.hasAttribute('data-danger');
        openDialog(form.getAttribute('data-confirm'), {
            danger: danger,
            title: form.getAttribute('data-title') || (danger ? 'Delete this?' : 'Please confirm'),
            ok: danger ? 'Yes, delete' : 'Confirm',
        }).then(function (yes) {
            if (!yes) return;
            form.dataset.confirmed = '1';
            form.submit();
        });
        return false;
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.flash-ok, .flash-error').forEach(function (node) {
            toast(node.textContent.trim(), node.classList.contains('flash-error'));
            node.remove();
        });

        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            if (form.id) {
                document.querySelectorAll('button[form="' + form.id + '"]').forEach(function (btn) {
                    btn.addEventListener('click', function (e) { e.preventDefault(); ask(form); });
                });
            }
            form.addEventListener('submit', function (e) {
                if (!ask(form)) e.preventDefault();
            });
        });
    });
})();
