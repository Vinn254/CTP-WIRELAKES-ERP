// A password is the one field people get wrong by typing it blind, so every password box on the site gets a
// Show / Hide switch. Only the input's type changes — the name, the value and the browser's own validation
// stay exactly as they were, so nothing about the form submission moves.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type=password]').forEach(function (input) {
        if (input.closest('.pw-wrap')) return;

        const wrap = document.createElement('span');
        wrap.className = 'pw-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'pw-toggle';
        toggle.textContent = 'Show';
        toggle.setAttribute('aria-label', 'Show the password in plain text');
        toggle.setAttribute('aria-pressed', 'false');

        toggle.addEventListener('click', function () {
            const shown = input.type === 'text';
            input.type = shown ? 'password' : 'text';
            toggle.textContent = shown ? 'Show' : 'Hide';
            toggle.setAttribute('aria-label', shown ? 'Show the password in plain text' : 'Hide the password');
            toggle.setAttribute('aria-pressed', String(!shown));
            input.focus();
        });

        wrap.appendChild(toggle);
    });
});
