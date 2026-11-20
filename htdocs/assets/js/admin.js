/* Admin behaviour: confirmation prompts for destructive actions. */
(function () {
  'use strict';

  document.querySelectorAll('[data-seo-panel]').forEach(function (panel) {
    var form = panel.closest('form');
    function updatePreview() {
      var title = panel.querySelector('[data-seo-title]').value.trim();
      var titleField = form.elements.namedItem('title');
      title = title || (titleField && titleField.value.trim()) || 'Your resource title';
      if (!/zimsec/i.test(title)) title += ' - ZIMSEC';
      panel.querySelector('[data-seo-preview-title]').textContent = title;
      var description = panel.querySelector('[data-seo-description]').value.trim() || 'Explore this ZIMSEC resource, understand key topics, and prepare for your next exam.';
      if (!/zimsec/i.test(description)) description = 'ZIMSEC revision: ' + description;
      panel.querySelector('[data-seo-preview-description]').textContent = description.length > 160 ? description.slice(0, 159) + '…' : description;
    }
    form.addEventListener('input', updatePreview);
    updatePreview();
  });

  // Keep every content table readable as labelled cards on small screens.
  document.querySelectorAll('table.table').forEach(function (table) {
    var headings = Array.from(table.querySelectorAll('thead th')).map(function (heading) {
      return heading.textContent.trim();
    });
    table.querySelectorAll('tbody tr').forEach(function (row) {
      Array.from(row.cells).forEach(function (cell, index) {
        if (!cell.hasAttribute('data-label') && headings[index]) {
          cell.setAttribute('data-label', headings[index]);
        }
      });
    });
    if (!table.parentElement.classList.contains('table-wrap')) {
      var wrapper = document.createElement('div');
      wrapper.className = 'table-wrap';
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    }
  });

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('[data-password-toggle]');
    if (!toggle) return;
    var input = document.getElementById(toggle.getAttribute('aria-controls'));
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    toggle.textContent = show ? 'Hide' : 'Show';
    toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
  });

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (event.defaultPrevented) return;

    var message = (event.submitter && event.submitter.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
      event.stopPropagation();
    }
  });
})();
