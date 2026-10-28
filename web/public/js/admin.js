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

    window.copyText = function (text, btn) {
        navigator.clipboard.writeText(text).then(function () {
            const label = btn.textContent;
            btn.textContent = 'Copied ✓';
            setTimeout(function () { btn.textContent = label; }, 1800);
        });
    };

    /* Registers read as columns, not as a wall of wrapped text. Each table is measured once the page has painted:
       a column whose cells are all figures — an amount, a count, a percentage — is right-aligned with tabular
       digits so the thousands line up; a column whose cells are all short is kept on one line so a date or a
       reference never breaks in half; a column of prose is given a floor wide enough to hold two real words, so
       the table never squeezes a name into a stack of one-word lines. A department can settle a column by hand
       instead: put num, text or nowrap on its <th> and the table keeps that reading. No column is ever dropped
       or hidden — when the lines stop fitting, the table scrolls inside its own frame and the two columns you
       act on (the reference on the left, the buttons on the right) stay pinned. */
    const FIGURE = /^(?:[-+~≈]\s*)?(?:(?:ksh|kes|sh|ugx|cfa)\s*[-+~≈]?\s*[\d][\d,.]*|[\d][\d,.]*\s*(?:%|kg|kgs|g|tonnes?|km|hrs?|hours?|days?|weeks?|months?|years?|x)?)$/i;
    const BLANK = /^[\s.—–-]*$/;
    const PROSE_FLOOR = 152;

    function isFigure(text) {
        return FIGURE.test(text.replace(/\s+/g, ' ').trim());
    }

    function alignColumns(table) {
        const head = table.querySelector('thead tr');
        if (!head) return;
        const headers = [...head.children];
        const body = table.querySelector('tbody');
        if (!body) return;
        // Rows that span the table (a note, an empty state) say nothing about any column.
        const rows = [...body.querySelectorAll(':scope > tr')]
            .filter((tr) => tr.children.length === headers.length && ![...tr.children].some((td) => td.colSpan > 1));
        if (!rows.length) return;

        headers.forEach((th, i) => {
            const cells = rows.map((tr) => tr.children[i]);
            const marks = th.classList;
            if (marks.contains('num') || marks.contains('text') || marks.contains('nowrap')) {
                if (marks.contains('num')) cells.forEach((td) => td.classList.add('num'));
                if (marks.contains('nowrap')) cells.forEach((td) => td.classList.add('nowrap'));
                return;
            }
            const hasControl = cells.some((td) => td.querySelector('input, select, textarea, button, details'));
            if (hasControl || cells.some((td) => td.querySelector('.bar'))) return;
            const texts = cells.map((td) => td.textContent.replace(/\s+/g, ' ').trim());
            if (texts.some((t) => t.length > 200)) return;
            const filled = texts.filter((t) => !BLANK.test(t));
            if (filled.length && filled.every(isFigure)) {
                marks.add('num');
                cells.forEach((td) => td.classList.add('num'));
                return;
            }
            if (texts.every((t) => t.length <= 26)) {
                marks.add('nowrap');
                cells.forEach((td) => td.classList.add('nowrap'));
                return;
            }
            // Prose: it may wrap, but not into ribbons.
            th.style.minWidth = PROSE_FLOOR + 'px';
        });
    }

    /* A row that spans the whole register — a note under a line, or the empty state — must stay readable while the
       columns slide, so its text keeps a reading measure and rides with the frame instead of running off the edge. */
    function noteRows(table) {
        table.querySelectorAll('tbody > tr > td[colspan]').forEach((td) => {
            if (td.firstElementChild && td.firstElementChild.classList.contains('span-note')) return;
            const box = document.createElement('div');
            box.className = 'span-note';
            while (td.firstChild) { box.appendChild(td.firstChild); }
            td.appendChild(box);
        });
    }

    function frameTables() {
        document.querySelectorAll('table.data').forEach((table) => {
            alignColumns(table);
            noteRows(table);
            if (table.parentElement && !table.parentElement.classList.contains('table-scroll')) {
                const frame = document.createElement('div');
                frame.className = 'table-scroll';
                table.parentNode.insertBefore(frame, table);
                frame.appendChild(table);
            }
            const head = table.querySelector('thead tr');
            if (!head) return;
            // Pin only when the frame actually has to scroll: the reference and the actions stay under the eye
            // while the middle columns slide past. An inline form widens its own column as it opens.
            if (table.scrollWidth > table.parentElement.clientWidth + 2) table.classList.add('pin-first', 'pin-last');
        });
    }

    /* One form that bills anyone has fields only some bills need — the CTP picker is noise on a donor invoice.
       A field carrying data-for-choice stays hidden until the select beside it (data-choices) answers with one of
       the values listed there. Nothing is dropped from the page permanently; the server re-checks the pair anyway. */
    function bindChoices(select) {
        const scope = select.closest('form') || document;
        const guarded = scope.querySelectorAll('[data-for-choice]');
        if (!guarded.length) return;
        function sync() {
            guarded.forEach(function (el) {
                const answers = el.getAttribute('data-for-choice').split('|');
                el.hidden = answers.indexOf(select.value) === -1;
            });
        }
        sync();
        select.addEventListener('change', sync);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('select[data-choices]').forEach(bindChoices);
        frameTables();
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

        window.wlAsk = ask;
    });
})();

// A fill-in card opens on the screen as a popup, so the register you are reading stays where it was.
(function () {
    function card(name) { return document.getElementById('sheet-' + name); }

    function show(el) {
        el.classList.add('open');
        document.body.classList.add('sheet-lock');
        var first = el.querySelector('input:not([type=hidden]),select,textarea');
        if (first) first.focus();
    }

    function close(el) {
        el.classList.remove('open');
        document.body.classList.remove('sheet-lock');
    }

    // A row can open the same popup already filled with its own figures, so one form serves the whole page.
    function fill(el, json) {
        var data;
        try { data = JSON.parse(json); } catch (err) { return; }
        Object.keys(data).forEach(function (name) {
            var field = el.querySelector('[name="' + name + '"]');
            if (field && data[name] !== null) field.value = data[name];
        });
    }

    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-sheet]');
        if (opener) {
            e.preventDefault();
            var el = card(opener.getAttribute('data-sheet'));
            if (el) {
                var pre = opener.getAttribute('data-sheet-fill');
                if (pre) fill(el, pre);
                // A register with one form per row shares a single popup: the row's own form is copied in.
                var from = opener.getAttribute('data-sheet-form');
                var source = from && document.querySelector(from);
                var holder = source && el.querySelector('.sheet-body');
                if (holder) {
                    holder.innerHTML = source.innerHTML;
                    holder.hidden = false;
                    holder.querySelectorAll('form[data-confirm]').forEach(function (form) {
                        form.addEventListener('submit', function (ev) {
                            if (!window.wlAsk(form)) ev.preventDefault();
                        });
                    });
                }
                show(el);
            }

            return;
        }

        var shut = e.target.closest('[data-close-sheet]');
        if (shut) {
            var sheet = shut.closest('.sheet');
            if (sheet) close(sheet);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') document.querySelectorAll('.sheet.open').forEach(close);
    });
})();
