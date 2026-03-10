/**
 * quote-dashboard.js
 *
 * Lightweight helpers for quote listing/detail interactions.
 *
 * This file does not make assumptions about a specific HTML structure,
 * but provides a few patterns:
 *
 *  - Auto-highlight selected quote rows (table with [data-quotes-table])
 *  - Click rows to navigate to a detail page using data-href attribute
 *  - Format currency values in elements with [data-money]
 */

(function () {
  'use strict';

  function initQuoteTableNavigation() {
    var tables = document.querySelectorAll('[data-quotes-table]');
    if (!tables.length) return;

    tables.forEach(function (table) {
      table.addEventListener('click', function (e) {
        var row = e.target.closest('tr[data-href]');
        if (!row) return;
        var href = row.getAttribute('data-href');
        if (href) {
          window.location.href = href;
        }
      });
    });
  }

  function initMoneyFormatting() {
    var els = document.querySelectorAll('[data-money]');
    if (!els.length) return;

    els.forEach(function (el) {
      var raw = el.getAttribute('data-money');
      if (raw == null || raw === '') return;

      var value = parseFloat(raw);
      if (isNaN(value)) return;

      var currency = el.getAttribute('data-money-currency') || 'USD';
      try {
        var formatted = new Intl.NumberFormat(undefined, {
          style: 'currency',
          currency: currency
        }).format(value);
        el.textContent = formatted;
      } catch (e) {
        el.textContent = value.toFixed(2);
      }
    });
  }

  function init() {
    initQuoteTableNavigation();
    initMoneyFormatting();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

