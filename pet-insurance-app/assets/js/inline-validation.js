(function() {
    'use strict';

    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var maxPetAgeYears = 30;
    var minPetAgeYears = 0;

    function getFeedbackEl(input) {
        var next = input.nextElementSibling;
        if (next && next.classList.contains('invalid-feedback') && next.getAttribute('data-inline-feedback') !== null) {
            return next;
        }
        var wrapper = input.closest('.col-12, .col-md-6, .col-md-3');
        if (wrapper) {
            var fb = wrapper.querySelector('.invalid-feedback[data-inline-feedback]');
            if (fb) return fb;
        }
        return next && next.classList.contains('invalid-feedback') ? next : null;
    }

    function setInvalid(input, feedbackEl, message) {
        input.classList.add('is-invalid');
        if (feedbackEl) {
            feedbackEl.textContent = message;
            feedbackEl.style.display = 'block';
        }
    }

    function setValid(input, feedbackEl) {
        input.classList.remove('is-invalid');
        if (feedbackEl) {
            feedbackEl.textContent = '';
            feedbackEl.style.display = '';
        }
    }

    function validateEmail(input) {
        var val = (input.value || '').trim();
        var feedbackEl = getFeedbackEl(input);
        if (!val) {
            setValid(input, feedbackEl);
            return true;
        }
        if (!emailRegex.test(val)) {
            setInvalid(input, feedbackEl, 'Please enter a valid email address.');
            return false;
        }
        setValid(input, feedbackEl);
        return true;
    }

    function validateDob(input) {
        var val = (input.value || '').trim();
        var feedbackEl = getFeedbackEl(input);
        if (!val) {
            setValid(input, feedbackEl);
            return true;
        }
        var date = new Date(val);
        if (isNaN(date.getTime())) {
            setInvalid(input, feedbackEl, 'Please enter a valid date.');
            return false;
        }
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        if (date > today) {
            setInvalid(input, feedbackEl, 'Date of birth cannot be in the future.');
            return false;
        }
        var maxPast = new Date(today);
        maxPast.setFullYear(maxPast.getFullYear() - maxPetAgeYears);
        if (date < maxPast) {
            setInvalid(input, feedbackEl, 'Please enter a valid date (within the last ' + maxPetAgeYears + ' years).');
            return false;
        }
        setValid(input, feedbackEl);
        return true;
    }

    function onBlur(e) {
        var input = e.target;
        var kind = input.getAttribute('data-inline-validate');
        if (kind === 'email') validateEmail(input);
        if (kind === 'dob') validateDob(input);
    }

    function onFormSubmit(form) {
        var invalid = false;
        form.querySelectorAll('[data-inline-validate="email"]').forEach(function(input) {
            if (!validateEmail(input)) invalid = true;
        });
        form.querySelectorAll('[data-inline-validate="dob"]').forEach(function(input) {
            if (!validateDob(input)) invalid = true;
        });
        return !invalid;
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-inline-validate="email"], [data-inline-validate="dob"]').forEach(function(input) {
            input.addEventListener('blur', onBlur);
        });

        document.querySelectorAll('form').forEach(function(form) {
            if (!form.querySelector('[data-inline-validate]')) return;
            form.addEventListener('submit', function(e) {
                if (!onFormSubmit(form)) e.preventDefault();
            });
        });
    });
})();
