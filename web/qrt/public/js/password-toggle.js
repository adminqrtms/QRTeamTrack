// Adds a show/hide (eye) button to password fields on the page,
// except "Confirm Password" fields, which stay hidden.
(function () {
    const eye = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    const eyeOff = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

    document.querySelectorAll('input[type="password"]').forEach(input => {
        if (input.dataset.toggleReady || /_confirmation$/.test(input.name)) return;
        input.dataset.toggleReady = '1';

        // Wrap the field so the button can sit inside it on the right
        const wrap = document.createElement('div');
        wrap.className = 'password-toggle-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        // Keep Bootstrap's error text (".is-invalid ~ .invalid-feedback") visible
        if (input.classList.contains('is-invalid')) wrap.classList.add('is-invalid');

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-toggle';
        button.innerHTML = eye;
        button.setAttribute('aria-label', 'Show password');
        button.title = 'Show password';
        wrap.appendChild(button);

        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.innerHTML = show ? eyeOff : eye;
            const label = show ? 'Hide password' : 'Show password';
            button.setAttribute('aria-label', label);
            button.title = label;
            input.focus();
        });
    });
})();
