/**
 * Client-side validation for forms with data-validate (novalidate + Bootstrap is-invalid).
 */
(function () {
  'use strict';

  function showError(field, message) {
    if (!field) return;
    field.classList.add('is-invalid');
    field.setAttribute('aria-invalid', 'true');
    var container = field.closest('.col-12, .col-md-6, .col-6, .mb-3');
    if (!container) container = field.parentElement;
    var fb = container.querySelector('.invalid-feedback');
    if (!fb) {
      fb = document.createElement('div');
      fb.className = 'invalid-feedback d-block';
      container.appendChild(fb);
    }
    fb.textContent = message;
    fb.setAttribute('role', 'alert');
  }

  function validateRegister(form) {
    var first = (form.querySelector('[name="first_name"]') || {}).value.trim();
    var last = (form.querySelector('[name="last_name"]') || {}).value.trim();
    var email = (form.querySelector('[name="email"]') || {}).value.trim();
    var pass = (form.querySelector('[name="password"]') || {}).value;
    var conf = (form.querySelector('[name="confirm_password"]') || {}).value;
    var ok = true;
    var firstErr = null;

    function fail(field, msg) {
      ok = false;
      showError(field, msg);
      if (!firstErr) firstErr = field;
    }

    if (!first) fail(form.querySelector('[name="first_name"]'), 'First name is required.');
    if (!last) fail(form.querySelector('[name="last_name"]'), 'Last name is required.');
    if (!email) {
      fail(form.querySelector('[name="email"]'), 'A valid email address is required.');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      fail(form.querySelector('[name="email"]'), 'Enter a valid email address.');
    }

    var perrs = [];
    if (pass.length < 8) perrs.push('at least 8 characters');
    if (!/[A-Z]/.test(pass)) perrs.push('at least one uppercase letter');
    if (!/[0-9]/.test(pass)) perrs.push('at least one number');
    if (perrs.length) {
      fail(form.querySelector('[name="password"]'), 'Password must contain: ' + perrs.join(', ') + '.');
    }
    if (pass !== conf) {
      fail(form.querySelector('[name="confirm_password"]'), 'Passwords do not match.');
    }

    var localCap = form.querySelector('#local-captcha');
    var capFb = document.getElementById('reg-captcha-feedback');
    if (capFb) {
      capFb.textContent = '';
      capFb.classList.add('d-none');
    }
    if (localCap && !localCap.checked) {
      ok = false;
      if (capFb) {
        capFb.textContent = 'Please confirm you are not a robot.';
        capFb.classList.remove('d-none');
      }
      localCap.classList.add('is-invalid');
      if (!firstErr) firstErr = localCap;
    }

    return { ok: ok, first: firstErr };
  }

  function validatePet(form) {
    var name = (form.querySelector('[name="name"]') || {}).value.trim();
    var species = (form.querySelector('[name="species"]') || {}).value;
    var dob = (form.querySelector('[name="date_of_birth"]') || {}).value;
    var ok = true;
    var firstErr = null;

    function fail(field, msg) {
      ok = false;
      showError(field, msg);
      if (!firstErr) firstErr = field;
    }

    if (!name) fail(form.querySelector('[name="name"]'), 'Pet name is required.');
    if (!species) fail(form.querySelector('[name="species"]'), 'Please select a species.');

    if (dob) {
      var t = Date.parse(dob);
      if (Number.isNaN(t)) {
        fail(form.querySelector('[name="date_of_birth"]'), 'Invalid date of birth.');
      } else if (t > Date.now()) {
        fail(form.querySelector('[name="date_of_birth"]'), 'Date of birth cannot be in the future.');
      }
    }

    return { ok: ok, first: firstErr };
  }

  function validateNewClaim(form) {
    var sel = form.querySelector('[name="subscription_id"]');
    var val = sel && String(sel.value || '').trim();
    if (val) return { ok: true, first: null };
    if (sel) {
      showError(sel, 'Please select a pet with an active policy.');
      return { ok: false, first: sel };
    }
    return { ok: false, first: null };
  }

  function bindForm(form) {
    var mode = form.getAttribute('data-validate');
    if (!mode) return;

    form.addEventListener('input', function (e) {
      var t = e.target;
      if (t.classList.contains('is-invalid')) {
        t.classList.remove('is-invalid');
        t.removeAttribute('aria-invalid');
      }
      if (t.id === 'local-captcha') {
        var capFb = document.getElementById('reg-captcha-feedback');
        if (capFb) {
          capFb.textContent = '';
          capFb.classList.add('d-none');
        }
        t.classList.remove('is-invalid');
      }
    });

    form.addEventListener('submit', function (e) {
      var result = { ok: true, first: null };
      if (mode === 'register') result = validateRegister(form);
      else if (mode === 'pet') result = validatePet(form);
      else if (mode === 'new-claim') result = validateNewClaim(form);

      if (!result.ok) {
        e.preventDefault();
        if (result.first && typeof result.first.focus === 'function') {
          result.first.focus();
        }
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-validate]').forEach(bindForm);
  });
})();
