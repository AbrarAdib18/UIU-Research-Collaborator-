/**
 * Shared vanilla-JS helpers reused across pages.
 * Kept intentionally small — most interactivity is plain HTML forms/links.
 */
document.addEventListener('DOMContentLoaded', function () {

    // Auto-dismiss flash alerts after 5s.
    document.querySelectorAll('.app-flash .alert').forEach(function (alertEl) {
        setTimeout(function () {
            if (window.bootstrap && window.bootstrap.Alert) {
                window.bootstrap.Alert.getOrCreateInstance(alertEl).close();
            } else {
                alertEl.remove();
            }
        }, 5000);
    });

    // Generic password show/hide toggle: <button class="password-toggle" data-target="#fieldId">
    document.querySelectorAll('.password-toggle[data-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.querySelector(btn.getAttribute('data-target'));
            if (!input) return;
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    });

    // Confirm-before-submit: <form data-confirm="Are you sure?">
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
});

/** Small toast helper for pages that want a non-blocking confirmation. */
function appToast(message) {
    var el = document.createElement('div');
    el.className = 'profile-toast';
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(function () { el.classList.add('show'); }, 10);
    setTimeout(function () {
        el.classList.remove('show');
        setTimeout(function () { el.remove(); }, 300);
    }, 2600);
}
