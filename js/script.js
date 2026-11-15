document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            var msg = el.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var firstInvalid = null;

            form.querySelectorAll('[required]').forEach(function (field) {
                var value = (field.value || '').trim();
                field.classList.remove('field-error');
                if (value === '') {
                    field.classList.add('field-error');
                    if (!firstInvalid) { firstInvalid = field; }
                }
            });

            var marks = form.querySelector('input[name="marks"]');
            if (marks && marks.value !== '') {
                var m = parseFloat(marks.value);
                if (isNaN(m) || m < 0 || m > 100) {
                    marks.classList.add('field-error');
                    if (!firstInvalid) { firstInvalid = marks; }
                }
            }

            var email = form.querySelector('input[type="email"]');
            if (email && email.value.trim() !== '') {
                var ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());
                if (!ok) {
                    email.classList.add('field-error');
                    if (!firstInvalid) { firstInvalid = email; }
                }
            }

            form.querySelectorAll('[data-phone]').forEach(function (field) {
                field.classList.remove('field-error');
                if (field.value.trim() === '') { return; }   // optional field
                if (normalizePhone(field.value) === null) {
                    field.classList.add('field-error');
                    if (!firstInvalid) { firstInvalid = field; }
                }
            });

            if (firstInvalid) {
                e.preventDefault();
                firstInvalid.focus();
                showFormMessage(form, 'Please correct the highlighted fields.');
            }
        });
    });
    document.querySelectorAll('[data-phone]').forEach(function (field) {
        field.addEventListener('input', function () {
            var cleaned = field.value.replace(/[^0-9+\s().-]/g, '');
            if (cleaned !== field.value) {
                var pos = field.selectionStart - 1;
                field.value = cleaned;
                try { field.setSelectionRange(pos, pos); } catch (err) { /* ignore */ }
            }
            field.classList.remove('field-error');
        });

        field.addEventListener('blur', function () {
            if (field.value.trim() === '') { return; }
            var normalized = normalizePhone(field.value);
            if (normalized) {
                field.value = normalized;
            } else {
                field.classList.add('field-error');
            }
        });
    });
    var marksInput = document.querySelector('input[name="marks"]');
    var gradePreview = document.getElementById('grade-preview');
    if (marksInput && gradePreview) {
        var update = function () {
            var m = parseFloat(marksInput.value);
            gradePreview.textContent = isNaN(m) ? '—' : gradeFor(m);
        };
        marksInput.addEventListener('input', update);
        update();
    }
});
function normalizePhone(raw) {
    raw = (raw || '').trim();
    if (raw === '') { return null; }

    if (/[^0-9+\s().-]/.test(raw)) { return null; }
    if (raw.indexOf('+') > 0) { return null; }

    var digits = raw.replace(/\D+/g, '');
    var subscriber;
    if (digits.indexOf('00254') === 0)    { subscriber = digits.slice(5); }
    else if (digits.indexOf('254') === 0) { subscriber = digits.slice(3); }
    else if (digits.indexOf('0') === 0)   { subscriber = digits.slice(1); }
    else                                  { subscriber = digits; }

    return /^[71][0-9]{8}$/.test(subscriber) ? '0' + subscriber : null;
}
function gradeFor(m) {
    if (m < 0) m = 0; if (m > 100) m = 100;
    if (m >= 80) return 'A';
    if (m >= 75) return 'A-';
    if (m >= 70) return 'B+';
    if (m >= 65) return 'B';
    if (m >= 60) return 'B-';
    if (m >= 55) return 'C+';
    if (m >= 50) return 'C';
    if (m >= 45) return 'C-';
    if (m >= 40) return 'D+';
    if (m >= 35) return 'D';
    if (m >= 30) return 'D-';
    return 'E';
}
function showFormMessage(form, text) {
    var box = form.querySelector('.js-msg');
    if (!box) {
        box = document.createElement('div');
        box.className = 'alert alert-error js-msg';
        form.parentNode.insertBefore(box, form);
    }
    box.textContent = text;
}
