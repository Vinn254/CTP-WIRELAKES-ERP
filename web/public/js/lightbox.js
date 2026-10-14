/*
 * Zooming a photo. Any link marked data-zoom opens the picture full screen instead of replacing
 * the page: click, scroll or use the buttons to zoom, drag to move around while zoomed, Esc or a
 * click outside the picture to close. Loaded at the end of the body, so the markup is ready.
 */
(function () {
    var box = document.createElement('div');
    box.className = 'lightbox';
    box.hidden = true;
    box.innerHTML = '<div class="lb-bar"><span class="lb-cap"></span>'
        + '<button type="button" class="lb-out" aria-label="Zoom out">&minus;</button>'
        + '<button type="button" class="lb-one" aria-label="Fit to screen">Fit</button>'
        + '<button type="button" class="lb-in" aria-label="Zoom in">+</button>'
        + '<button type="button" class="lb-close" aria-label="Close">&times;</button></div>'
        + '<img alt="Photo">';
    document.body.appendChild(box);

    var img = box.querySelector('img');
    var cap = box.querySelector('.lb-cap');
    var zoom = 1, panX = 0, panY = 0, drag = null, moved = false;

    function apply() {
        img.style.transform = 'translate(' + panX + 'px,' + panY + 'px) scale(' + zoom + ')';
        img.classList.toggle('zoomed', zoom > 1);
    }

    function setZoom(next) {
        zoom = Math.min(8, Math.max(1, next));
        if (zoom === 1) { panX = 0; panY = 0; }
        apply();
    }

    function open(src, label) {
        img.src = src;
        cap.textContent = label || '';
        box.hidden = false;
        document.body.classList.add('zooming');
        setZoom(1);
    }

    function close() {
        box.hidden = true;
        document.body.classList.remove('zooming');
        img.removeAttribute('src');
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('a[data-zoom]');
        if (trigger) {
            event.preventDefault();
            open(trigger.getAttribute('href'), trigger.getAttribute('data-zoom-label') || trigger.textContent.trim());
            return;
        }

        if (box.hidden) return;
        if (event.target.closest('.lb-close')) { close(); return; }
        if (event.target.closest('.lb-in')) { setZoom(zoom * 1.4); return; }
        if (event.target.closest('.lb-out')) { setZoom(zoom / 1.4); return; }
        if (event.target.closest('.lb-one')) { setZoom(1); return; }
        // Clicking the backdrop or the picture itself toggles between fitting and zooming in.
        // Dragging to pan is not a click, so moving the picture never changes its size.
        if (event.target === box) { close(); return; }
        if (event.target === img) {
            if (moved) { moved = false; return; }
            setZoom(zoom > 1 ? 1 : 2.4);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (box.hidden) return;
        if (event.key === 'Escape') { close(); return; }
        if (event.key === '+' || event.key === '=') { setZoom(zoom * 1.4); return; }
        if (event.key === '-' || event.key === '_') { setZoom(zoom / 1.4); return; }
        if (event.key === '0') { setZoom(1); }
    });

    // Trackpad or wheel zooms the picture; the page behind never scrolls while it is open.
    box.addEventListener('wheel', function (event) {
        event.preventDefault();
        setZoom(zoom * (event.deltaY < 0 ? 1.15 : 1 / 1.15));
    }, { passive: false });

    img.addEventListener('pointerdown', function (event) {
        if (zoom === 1) return;
        drag = { x: event.clientX - panX, y: event.clientY - panY };
        moved = false;
        img.setPointerCapture(event.pointerId);
    });

    img.addEventListener('pointermove', function (event) {
        if (! drag) return;
        moved = true;
        panX = event.clientX - drag.x;
        panY = event.clientY - drag.y;
        apply();
    });

    ['pointerup', 'pointercancel'].forEach(function (name) {
        img.addEventListener(name, function () { drag = null; });
    });
})();
