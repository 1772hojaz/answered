/* Rich-text editor for the note admin screen.
   Self-contained: no external libraries, works offline on shared hosting. */
(function () {
  'use strict';

  var ALLOWED_TAGS = {
    P: 1, BR: 1, HR: 1, DIV: 1, SPAN: 1,
    H1: 1, H2: 1, H3: 1, H4: 1, H5: 1, H6: 1,
    STRONG: 1, B: 1, EM: 1, I: 1, U: 1, S: 1, STRIKE: 1, DEL: 1, INS: 1, MARK: 1, SMALL: 1,
    UL: 1, OL: 1, LI: 1, DL: 1, DT: 1, DD: 1,
    BLOCKQUOTE: 1, PRE: 1, CODE: 1, KBD: 1, SAMP: 1, VAR: 1,
    A: 1, IMG: 1, FIGURE: 1, FIGCAPTION: 1,
    TABLE: 1, THEAD: 1, TBODY: 1, TFOOT: 1, TR: 1, TH: 1, TD: 1, CAPTION: 1, COL: 1, COLGROUP: 1,
    SUP: 1, SUB: 1
  };
  var DROP_TAGS = { SCRIPT: 1, STYLE: 1, IFRAME: 1, OBJECT: 1, EMBED: 1, FORM: 1, INPUT: 1,
    BUTTON: 1, SELECT: 1, TEXTAREA: 1, LINK: 1, META: 1, BASE: 1, SVG: 1, MATH: 1 };

  function sanitizeHtml(html) {
    var template = document.createElement('div');
    template.innerHTML = html;
    walk(template);
    return template.innerHTML;
  }

  function walk(node) {
    var children = Array.prototype.slice.call(node.childNodes);
    children.forEach(function (child) {
      if (child.nodeType === 3) return;                    // text
      if (child.nodeType !== 1) { child.remove(); return; } // comments etc.

      var tag = child.tagName;
      if (DROP_TAGS[tag]) { child.remove(); return; }

      if (!ALLOWED_TAGS[tag]) {
        walk(child);
        while (child.firstChild) node.insertBefore(child.firstChild, child);
        child.remove();
        return;
      }

      Array.prototype.slice.call(child.attributes).forEach(function (attr) {
        var name = attr.name.toLowerCase();
        var allowed = (name === 'style' || name === 'align');
        if (tag === 'A') allowed = allowed || name === 'href' || name === 'title' || name === 'target';
        if (tag === 'IMG') allowed = allowed || name === 'src' || name === 'alt' || name === 'width' || name === 'height';
        if (tag === 'TD' || tag === 'TH') allowed = allowed || name === 'colspan' || name === 'rowspan';
        if (!allowed) child.removeAttribute(attr.name);
      });

      if (tag === 'A') {
        var href = child.getAttribute('href') || '';
        if (!/^(https?:|mailto:|\/|#)/i.test(href)) child.removeAttribute('href');
      }
      if (tag === 'IMG') {
        var src = child.getAttribute('src') || '';
        if (!/^https?:\/\//i.test(src) && src.indexOf('/uploads/') === -1) { child.remove(); return; }
      }
      walk(child);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-editor]');
    if (!root) return;

    var area = root.querySelector('[data-editor-area]');
    var source = root.querySelector('.editor-source');
    var status = root.querySelector('[data-editor-status]');
    var toolbar = root.querySelector('.editor-toolbar');
    var form = root.closest('form');
    var settings = window.REVISIONHUB || {};
    var savedRange = null;

    function sync() { source.value = area.innerHTML; }

    function setStatus(message, isError) {
      if (!status) return;
      status.textContent = message || '';
      status.classList.toggle('is-error', !!isError);
    }

    function saveRange() {
      var selection = window.getSelection();
      if (selection && selection.rangeCount && area.contains(selection.anchorNode)) {
        savedRange = selection.getRangeAt(0).cloneRange();
      }
    }

    function restoreRange() {
      area.focus();
      if (!savedRange) return;
      var selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(savedRange);
    }

    function normalizeLinks() {
      Array.prototype.forEach.call(area.querySelectorAll('a[href]'), function (link) {
        var href = link.getAttribute('href') || '';
        if (/^https?:/i.test(href)) {
          link.setAttribute('target', '_blank');
          link.setAttribute('rel', 'noopener noreferrer');
        } else {
          link.removeAttribute('target');
          link.removeAttribute('rel');
        }
      });
    }

    function insertHtml(html) {
      restoreRange();
      document.execCommand('insertHTML', false, html);
      normalizeLinks();
      saveRange();
      sync();
    }

    area.addEventListener('input', sync);
    area.addEventListener('keyup', saveRange);
    area.addEventListener('mouseup', saveRange);
    area.addEventListener('blur', saveRange);
    if (form) form.addEventListener('submit', sync);

    /* ---------------------------------------------------- toolbar ----- */
    if (toolbar) {
      toolbar.addEventListener('mousedown', function (event) {
        // Keep the caret where it is when a toolbar button is pressed.
        if (event.target.closest('button, select')) event.preventDefault();
      });

      toolbar.addEventListener('click', function (event) {
        var button = event.target.closest('button');
        if (!button) return;

        var command = button.getAttribute('data-cmd');
        var action = button.getAttribute('data-action');

        if (command) {
          restoreRange();
          document.execCommand(command, false, null);
          saveRange();
          sync();
          return;
        }

        if (action === 'link') insertLink();
        if (action === 'table') insertTable();
        if (action === 'rule') insertHtml('<hr>');
        if (action === 'image') pickImage();
      });
    }

    var formatSelect = root.querySelector('[data-editor-format]');
    if (formatSelect) {
      formatSelect.addEventListener('mousedown', function () { saveRange(); });
      formatSelect.addEventListener('change', function () {
        restoreRange();
        document.execCommand('formatBlock', false, '<' + formatSelect.value + '>');
        saveRange();
        sync();
        formatSelect.value = 'p';
      });
    }

    function insertLink() {
      saveRange();
      var url = window.prompt('Link address (https://… or /path):', 'https://');
      if (!url) return;
      url = url.trim();
      if (!/^(https?:\/\/|mailto:|\/|#)/i.test(url)) {
        setStatus('Please use a full URL (https://…) or a path starting with /.', true);
        return;
      }
      var selection = window.getSelection();
      if (savedRange && selection && savedRange.toString().length > 0) {
        restoreRange();
        document.execCommand('createLink', false, url);
        normalizeLinks();
        saveRange();
        sync();
      } else {
        insertHtml('<a href="' + url.replace(/"/g, '&quot;') + '">' + url.replace(/[<>&]/g, '') + '</a>');
      }
      setStatus('');
    }

    function insertTable() {
      saveRange();
      var rows = window.prompt('Number of rows (including the header row):', '3');
      if (rows === null) return;
      var cols = window.prompt('Number of columns:', '3');
      if (cols === null) return;
      rows = parseInt(rows, 10);
      cols = parseInt(cols, 10);
      if (!rows || !cols || rows < 1 || cols < 1 || rows > 30 || cols > 10) {
        setStatus('Rows must be 1–30 and columns 1–10.', true);
        return;
      }

      var html = '<table><thead><tr>';
      for (var c = 0; c < cols; c++) html += '<th>Heading</th>';
      html += '</tr></thead><tbody>';
      for (var r = 1; r < rows; r++) {
        html += '<tr>';
        for (var i = 0; i < cols; i++) html += '<td>&nbsp;</td>';
        html += '</tr>';
      }
      html += '</tbody></table><p><br></p>';
      insertHtml(html);
      setStatus('');
    }

    /* ----------------------------------------------------- images ----- */
    /* Images are never uploaded: paste a link to an image hosted elsewhere.
       The link is saved in the database (images table) and inserted inline. */
    function pickImage() {
      saveRange();
      var url = window.prompt('Image address (https://…):', 'https://');
      if (url === null) return;
      url = url.trim();
      if (!/^https?:\/\/[^\/\s]+\.[^\/\s]+/i.test(url)) {
        setStatus('Enter a full image URL starting with http:// or https://.', true);
        return;
      }
      if (!settings.imageApi || !settings.csrf) {
        insertHtml('<img src="' + url.replace(/"/g, '&quot;') + '" alt="">');
        setStatus('Image inserted.');
        return;
      }

      setStatus('Saving image link…');
      var data = new FormData();
      data.append('url', url);

      fetch(settings.imageApi, {
        method: 'POST',
        headers: { 'X-CSRF-Token': settings.csrf },
        body: data,
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().catch(function () { return { ok: false, error: 'Unexpected server response.' }; });
        })
        .then(function (result) {
          if (result && result.ok && result.url) {
            insertHtml('<img src="' + String(result.url).replace(/"/g, '&quot;') + '" alt="">');
            setStatus('Image inserted.');
            setTimeout(function () { setStatus(''); }, 2500);
          } else {
            setStatus((result && result.error) || 'Could not save the image link.', true);
          }
        })
        .catch(function () {
          setStatus('Could not save the image link. Check your connection and try again.', true);
        });
    }

    /* ------------------------------------------------------ paste ----- */
    area.addEventListener('paste', function (event) {
      var clipboard = event.clipboardData;
      if (!clipboard) return;
      event.preventDefault();

      var html = clipboard.getData('text/html');
      var text = clipboard.getData('text/plain');

      if (html) {
        document.execCommand('insertHTML', false, sanitizeHtml(html));
      } else if (text) {
        document.execCommand('insertText', false, text);
      }
      normalizeLinks();
      saveRange();
      sync();
    });

    sync();
  });

  /* ------------------------------------------- topic filtering -------- */
  /* The topic dropdown only ever offers topics that belong to the subject
     chosen in #note-subject. The full list ships in window.REVISIONHUB.topics
     so the dropdown can be repopulated client-side without a round trip. */
  document.addEventListener('DOMContentLoaded', function () {
    var subjectSelect = document.getElementById('note-subject');
    var topicSelect = document.getElementById('note-topic');
    if (!subjectSelect || !topicSelect) return;

    var allTopics = (window.REVISIONHUB && window.REVISIONHUB.topics) || [];
    if (!allTopics.length) return;

    function topicBelongsTo(subjectId, topicId) {
      return allTopics.some(function (topic) {
        return parseInt(topic.subject_id, 10) === subjectId && parseInt(topic.id, 10) === topicId;
      });
    }

    function rebuildTopics(keepId) {
      var subjectId = parseInt(subjectSelect.value, 10) || 0;
      var keep = parseInt(keepId, 10) || 0;
      var found = false;

      while (topicSelect.options.length > 1) {
        topicSelect.remove(1);
      }

      allTopics.forEach(function (topic) {
        if (parseInt(topic.subject_id, 10) !== subjectId) return;
        var option = document.createElement('option');
        option.value = String(topic.id);
        option.setAttribute('data-subject', String(topic.subject_id));
        option.textContent = String(topic.name);
        if (parseInt(topic.id, 10) === keep) {
          option.selected = true;
          found = true;
        }
        topicSelect.appendChild(option);
      });

      if (!found) topicSelect.value = '0';
    }

    subjectSelect.addEventListener('change', function () {
      var current = parseInt(topicSelect.value, 10) || 0;
      var nextSubject = parseInt(subjectSelect.value, 10) || 0;
      // Keep the choice only when the topic really belongs to the new subject.
      rebuildTopics(current && topicBelongsTo(nextSubject, current) ? current : 0);
    });

    // Normalise whatever the server rendered.
    rebuildTopics(parseInt(topicSelect.value, 10) || 0);
  });
})();
