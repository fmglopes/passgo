// PASS GO: tiny progressive enhancement. The app works without this file.
(function () {
    'use strict';

    document.documentElement.classList.add('js');

    // Document checklist: save automatically when a box is ticked.
    document.querySelectorAll('form[data-autosubmit]').forEach(function (form) {
        form.addEventListener('change', function (event) {
            if (event.target && event.target.type === 'checkbox') {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }
        });
    });
})();
