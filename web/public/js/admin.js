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

    function ask(form, message, submitter) {
        if (form.dataset.confirmed === '1') return true;
        const danger = form.hasAttribute('data-danger');
        openDialog(message || form.getAttribute('data-confirm'), {
            danger: danger,
            title: form.getAttribute('data-title') || (danger ? 'Delete this?' : 'Please confirm'),
            ok: danger ? 'Yes, delete' : 'Confirm',
        }).then(function (yes) {
            if (!yes) return;
            form.dataset.confirmed = '1';
            // A button that was clicked carries its own value into the submit, so resubmit through it.
            submitter ? form.requestSubmit(submitter) : form.submit();
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

    // A time log counts the day itself: the two clock times, an hour off for lunch, two units per hour.
    function bindClock(form) {
        const inEl = form.querySelector('[name=time_in]');
        const outEl = form.querySelector('[name=time_out]');
        if (!inEl || !outEl) return;
        const hours = form.querySelector('[data-clock-hours]');
        const units = form.querySelector('[name=units]');
        const minutes = function (value) {
            const parts = /^(\d{1,2}):(\d{2})$/.exec(value || '');
            return parts ? Number(parts[1]) * 60 + Number(parts[2]) : null;
        };
        function sync() {
            const a = minutes(inEl.value);
            const b = minutes(outEl.value);
            let worked = a === null || b === null ? null : Math.max(0, Math.min(b - a - 60, 480)) / 60;
            worked = worked === null || worked === 0 ? null : Math.round(worked * 100) / 100;
            if (hours) hours.value = worked === null ? '' : worked.toFixed(2);
            if (units) units.value = worked === null ? '' : Math.round(worked * 2);
        }
        [inEl, outEl].forEach(function (el) { el.addEventListener('input', sync); });
        sync();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('select[data-choices]').forEach(bindChoices);
        document.querySelectorAll('form[data-clock]').forEach(bindClock);
        frameTables();
        document.querySelectorAll('.flash-ok, .flash-error').forEach(function (node) {
            toast(node.textContent.trim(), node.classList.contains('flash-error'));
            node.remove();
        });

        // A form asks before it goes. A submit button may carry its own question, so one form can save with one
        // button and hand the same paper in with another.
        document.querySelectorAll('form[data-confirm], form[data-ask]').forEach(function (form) {
            if (form.id) {
                document.querySelectorAll('button[form="' + form.id + '"]').forEach(function (btn) {
                    btn.addEventListener('click', function (e) { e.preventDefault(); ask(form, btn.dataset.confirm, btn); });
                });
            }
            form.addEventListener('submit', function (e) {
                const btn = e.submitter;
                const message = (btn && btn.dataset.confirm) || form.getAttribute('data-confirm');
                if (! message) return;
                if (! ask(form, message, btn)) e.preventDefault();
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
                    holder.querySelectorAll('form[data-confirm], form[data-ask]').forEach(function (form) {
                        form.addEventListener('submit', function (ev) {
                            const btn = ev.submitter;
                            const message = (btn && btn.dataset.confirm) || form.getAttribute('data-confirm');
                            if (! message) return;
                            if (! window.wlAsk(form, message, btn)) ev.preventDefault();
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

    // An Edit button on a register may point at the card that lives on the record's own page: #sheet-<name>.
    document.addEventListener('DOMContentLoaded', function () {
        var hash = location.hash || '';
        if (hash.indexOf('#sheet-') !== 0) return;
        var el = card(decodeURIComponent(hash.slice(7)));
        if (el) show(el);
    });
})();

// Every office page prints and leaves as a spreadsheet from the two buttons in the header.
(function () {
    function clean(cell) {
        if (!cell) return '';
        var copy = cell.cloneNode(true);
        copy.querySelectorAll('button, a.btn, input, select, textarea, script, noscript').forEach(function (el) { el.remove(); });
        // innerText only keeps the line under a name when the node is laid out, so the copy is hung off screen.
        var holder = document.createElement('div');
        holder.style.cssText = 'position:fixed;left:-9999px;top:0';
        holder.appendChild(copy);
        document.body.appendChild(holder);
        var text = (copy.innerText || copy.textContent || '').replace(/\s*\n\s*/g, ' · ').replace(/\s+/g, ' ').trim();
        holder.remove();
        return text;
    }

    function quote(value) {
        return '"' + String(value).replace(/"/g, '""') + '"';
    }

    // The buttons on a row are not a column of the record, so a header with no words drops out with them.
    function columns(table) {
        var out = [];
        Array.prototype.forEach.call(table.querySelectorAll('thead th'), function (th, i) {
            var text = clean(th);
            if (text) out.push({ text: text, index: i });
        });
        return out;
    }

    function body(table) {
        var cols = columns(table);
        var lines = [cols.map(function (c) { return c.text; })];
        Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (tr) {
            // A row that spans the table is a note under the record above it, not a line of the register.
            if (tr.querySelector('td[colspan]')) return;
            lines.push(cols.map(function (c) { return clean(tr.children[c.index]); }));
        });
        return lines.map(function (line) { return line.map(quote).join(','); }).join('\r\n');
    }

    function tables() {
        return Array.prototype.filter.call(document.querySelectorAll('main.admin-content table.data'), function (t) {
            return t.querySelectorAll('tbody tr').length;
        });
    }

    function download(sheet, name) {
        var link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob(['\ufeff' + sheet], { type: 'text/csv;charset=utf-8' }));
        link.download = name + '.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function () { URL.revokeObjectURL(link.href); }, 4000);
    }

    function slug(text) {
        return (text || 'office').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'office';
    }

    function pageName() {
        var head = document.querySelector('.admin-topbar h1');
        return head ? head.innerText : '';
    }

    // The topbar button takes the whole page: two registers on it come out one under the other.
    function exportCsv() {
        var list = tables();
        if (list.length) download(list.map(body).join('\r\n\r\n'), slug(pageName()));
    }

    /** The heading a register sits under names the sheet taken from it. */
    function tableName(table, i) {
        var el = table;
        while (el) {
            if (el.previousElementSibling) {
                el = el.previousElementSibling;
                if (/^(H1|H2|H3|H4|CAPTION)$/.test(el.tagName) && el.innerText.trim()) return el.innerText;
            } else {
                el = el.parentElement;
            }
        }
        return slug(pageName()) + (tables().length > 1 ? '-' + (i + 1) : '');
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-print]')) { window.print(); return; }
        var one = e.target.closest('[data-csv-table]');
        if (one) {
            var t = tables()[parseInt(one.getAttribute('data-csv-table'), 10)];
            if (t) download(body(t), slug(tableName(t, parseInt(one.getAttribute('data-csv-table'), 10))));
            return;
        }
        if (e.target.closest('[data-csv]')) exportCsv();
    });

    // A page with no table on it has nothing to take away, so the button is not offered. Every register that is
    // on the page carries its own Download, so a desk with three tables takes the one it is looking at.
    document.addEventListener('DOMContentLoaded', function () {
        var list = tables();
        var csv = document.querySelector('[data-csv]');
        if (csv && !list.length) csv.remove();
        list.forEach(function (table, i) {
            var at = table.classList.contains('data') && table.parentElement.classList.contains('table-scroll')
                ? table.parentElement : table;
            var tools = document.createElement('div');
            tools.className = 'table-tools';
            tools.innerHTML = '<button class="btn btn-sm btn-ghost" type="button" data-csv-table="' + i + '"'
                + ' title="Save this table as a spreadsheet (.csv)">'
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
                + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                + '<path d="M12 3v12m0 0 4-4m-4 4-4-4"/>'
                + '<path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg><span>Download</span></button>';
            at.parentNode.insertBefore(tools, at);
        });
    });
})();
