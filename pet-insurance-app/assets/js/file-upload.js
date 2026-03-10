/**
 * file-upload.js
 *
 * Drag-and-drop + AJAX upload helper for claim receipt scanning.
 *
 * Expected HTML structure on new-claim/view-claim pages:
 *
 *   <meta name="csrf-token" content="<?= esc(generateCsrfToken()); ?>">
 *
 *   <div id="receipt-dropzone" class="upload-dropzone">
 *     <input type="file" id="receipt-file-input" name="document" accept="image/*,.pdf" hidden>
 *     <p>Drag & drop a receipt, or <button type="button" data-upload-trigger>browse</button></p>
 *     <div class="upload-status" data-upload-status></div>
 *   </div>
 *
 *   <script src="/pet-insurance-app/assets/js/file-upload.js"></script>
 *
 * The script emits custom events on the dropzone element:
 *   - 'receipt:upload:start'
 *   - 'receipt:upload:success' (detail = response JSON)
 *   - 'receipt:upload:error'   (detail = { message })
 */

(function () {
  'use strict';

  /**
   * Initialize all dropzones on the page.
   * A dropzone is any element with [data-receipt-dropzone] attribute.
   */
  function initReceiptDropzones() {
    var zones = document.querySelectorAll('[data-receipt-dropzone]');
    if (!zones.length) return;

    zones.forEach(function (zone) {
      setupZone(zone);
    });
  }

  function setupZone(zone) {
    var fileInput = zone.querySelector('input[type="file"]');
    var triggerBtn = zone.querySelector('[data-upload-trigger]');
    var statusEl = zone.querySelector('[data-upload-status]');

    if (!fileInput) {
      console.warn('file-upload.js: no file input found inside dropzone');
      return;
    }

    var claimId = zone.getAttribute('data-claim-id');
    if (!claimId) {
      console.warn('file-upload.js: data-claim-id missing on dropzone');
    }

    var fileType = zone.getAttribute('data-file-type') || 'receipt';

    // Clicking the trigger button opens the file dialog
    if (triggerBtn) {
      triggerBtn.addEventListener('click', function (e) {
        e.preventDefault();
        fileInput.click();
      });
    }

    // Standard file selection
    fileInput.addEventListener('change', function (e) {
      var file = fileInput.files[0];
      if (!file) return;
      uploadFile(zone, file, claimId, fileType, statusEl);
    });

    // Drag & drop handlers
    ['dragenter', 'dragover'].forEach(function (eventName) {
      zone.addEventListener(eventName, function (e) {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.add('is-dragover');
      });
    });

    ['dragleave', 'dragend', 'drop'].forEach(function (eventName) {
      zone.addEventListener(eventName, function (e) {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.remove('is-dragover');
      });
    });

    zone.addEventListener('drop', function (e) {
      var dt = e.dataTransfer;
      if (!dt || !dt.files || !dt.files.length) {
        return;
      }
      var file = dt.files[0];
      uploadFile(zone, file, claimId, fileType, statusEl);
    });
  }

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function setStatus(statusEl, message, type) {
    if (!statusEl) return;
    statusEl.textContent = message || '';
    statusEl.classList.remove('text-muted', 'text-danger', 'text-success');
    if (type === 'error') {
      statusEl.classList.add('text-danger');
    } else if (type === 'success') {
      statusEl.classList.add('text-success');
    } else {
      statusEl.classList.add('text-muted');
    }
  }

  function uploadFile(zone, file, claimId, fileType, statusEl) {
    if (!file) return;
    if (!claimId) {
      setStatus(statusEl, 'Missing claim context; please refresh the page.', 'error');
      return;
    }

    var csrf = getCsrfToken();
    if (!csrf) {
      console.warn('file-upload.js: missing CSRF token meta tag');
    }

    var formData = new FormData();
    formData.append('claim_id', claimId);
    formData.append('file_type', fileType);
    formData.append('document', file);
    formData.append('csrf_token', csrf);

    // Emit start event
    zone.dispatchEvent(new CustomEvent('receipt:upload:start', { bubbles: true }));
    setStatus(statusEl, 'Uploading and scanning receipt…', 'info');
    zone.classList.add('is-uploading');

    fetch('/pet-insurance-app/api/scan-receipt.php', {
      method: 'POST',
      body: formData,
      headers: {
        'X-CSRF-Token': csrf
      }
    })
      .then(function (response) {
        return response.json().catch(function () {
          // If JSON parsing fails, wrap in a synthetic error
          return { success: false, error: 'Invalid server response.' };
        });
      })
      .then(function (data) {
        zone.classList.remove('is-uploading');

        if (!data || !data.success) {
          var message = (data && data.error) ? data.error : 'Upload failed.';
          setStatus(statusEl, message, 'error');
          zone.dispatchEvent(new CustomEvent('receipt:upload:error', {
            bubbles: true,
            detail: { message: message, response: data }
          }));
          return;
        }

        setStatus(statusEl, 'Receipt scanned successfully.', 'success');
        zone.dispatchEvent(new CustomEvent('receipt:upload:success', {
          bubbles: true,
          detail: data
        }));
      })
      .catch(function (err) {
        console.error('file-upload.js upload error:', err);
        zone.classList.remove('is-uploading');
        setStatus(statusEl, 'Network error while uploading. Please try again.', 'error');
        zone.dispatchEvent(new CustomEvent('receipt:upload:error', {
          bubbles: true,
          detail: { message: 'Network error.', error: err }
        }));
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReceiptDropzones);
  } else {
    initReceiptDropzones();
  }
})();

