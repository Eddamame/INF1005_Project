/**
 * review-pay.js
 *
 * Handles the Review & Pay page behaviour:
 *  - Summaries the selected pet & plan
 *  - Calls backend to create a Stripe Checkout Session
 *  - Redirects to Stripe
 *  - Warns if user tries to navigate away mid-setup
 */

(function () {
  'use strict';

  var form = document.getElementById('review-pay-form');
  if (!form) return;

  var errorEl = document.getElementById('review-error');
  var summaryEl = document.getElementById('review-summary');
  var confirmBtn = document.getElementById('confirm-pay-btn');

  var dirty = false;

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function setError(msg) {
    if (!errorEl) return;
    if (!msg) {
      errorEl.style.display = 'none';
      errorEl.textContent = '';
    } else {
      errorEl.style.display = 'block';
      errorEl.textContent = msg;
    }
  }

  function updateSummary() {
    if (!summaryEl) return;

    var petSelect = document.getElementById('pet_id');
    var planRadios = form.querySelectorAll('input[name="plan_id"]');

    var petText = '';
    if (petSelect && petSelect.value) {
      petText = petSelect.options[petSelect.selectedIndex].textContent.trim();
    }

    var planText = '';
    planRadios.forEach(function (radio) {
      if (radio.checked) {
        var label = form.querySelector('label[for="' + radio.id + '"]');
        if (label) planText = label.textContent.trim();
      }
    });

    var parts = [];
    if (petText) parts.push('Pet: ' + petText);
    if (planText) parts.push('Plan: ' + planText);

    summaryEl.textContent = parts.join(' \u00b7 ');
  }

  function markDirty() {
    dirty = true;
  }

  window.addEventListener('beforeunload', function (e) {
    if (!dirty) return;
    var msg = 'You have not finished setting up your subscription. If you leave now, your selections will be lost.';
    e.preventDefault();
    e.returnValue = msg;
    return msg;
  });

  form.addEventListener('change', function () {
    markDirty();
    updateSummary();
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    setError('');

    var formData = new FormData(form);
    var csrf = getCsrfToken();
    if (!csrf) {
      setError('Missing CSRF token. Please refresh the page and try again.');
      return;
    }

    var petId = formData.get('pet_id');
    var planId = formData.get('plan_id');
    if (!petId || !planId) {
      setError('Please select both a pet and a plan.');
      return;

    }

    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Redirecting to Stripe…';

    var apiUrl = form.getAttribute('data-checkout-api') || (document.querySelector('meta[name="base-path"]') && document.querySelector('meta[name="base-path"]').getAttribute('content') + '/api/create-checkout-session.php') || '/api/create-checkout-session.php';

    fetch(apiUrl, {
      method: 'POST',
      body: formData,
      headers: {
        'X-CSRF-Token': csrf
      }
    })
      .then(function (response) {
        return response.text().then(function (text) {
          try {
            return JSON.parse(text);
          } catch (e) {
            return { success: false, error: response.ok ? 'Invalid server response.' : ('Request failed: ' + (response.status === 503 ? 'Payment system not configured.' : response.status)) };
          }
        });
      })
      .then(function (data) {
        if (!data || !data.success || !data.checkout_url) {
          var msg = (data && data.error) ? data.error : 'Could not start checkout. Please try again.';
          setError(msg);
          confirmBtn.disabled = false;
          confirmBtn.textContent = 'Confirm and Pay';
          return;
        }

        // User is intentionally leaving, no warning
        dirty = false;
        window.location.href = data.checkout_url;
      })
      .catch(function (err) {
        console.error('review-pay: checkout error', err);
        setError('Network error while contacting payment server. Please try again.');
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Confirm and Pay';
      });
  });

  // Initial summary
  updateSummary();
})();

