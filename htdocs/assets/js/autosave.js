(function () {
  'use strict';
  var form = document.getElementById('note-form');
  if (!form) return;
  var area = form.querySelector('[data-editor-area]');
  var status = form.querySelector('[data-autosave-status]');
  var baseline = snapshot(), pending = null, submitting = false, allowSubmit = false, waitingForSubmit = false;
  var blocked = !!document.querySelector('[data-recovery-banner]');
  function snapshot() {
    var values = {};
    new FormData(form).forEach(function (value, key) { values[key] = value; });
    if (area) values.content = area.innerHTML;
    return JSON.stringify(values);
  }
  function request(action) {
    var data = new FormData(form);
    if (area) data.set('content', area.innerHTML);
    data.set('autosave_action', action);
    return fetch(form.dataset.autosaveUrl, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (response) { return response.json().catch(function () { throw new Error('Recovery could not be saved. Check your connection or sign in again.'); }).then(function (result) { if (!response.ok || !result.ok) throw new Error(result.error || 'Recovery request failed.'); return result; }); });
  }
  function save() {
    if (pending || blocked || submitting || snapshot() === baseline) return pending || Promise.resolve();
    var sent = snapshot();
    status.textContent = 'Saving recovery…';
    pending = request('save').then(function (result) {
      baseline = sent; status.textContent = 'Recovery saved at ' + result.saved_at + '. Your published note is unchanged.';
      status.classList.remove('is-error');
    }).catch(function (error) { status.textContent = error.message; status.classList.add('is-error'); })
      .finally(function () { pending = null; });
    return pending;
  }
  setInterval(save, 8000);
  window.addEventListener('beforeunload', function (event) {
    if (!submitting && snapshot() !== baseline) { event.preventDefault(); event.returnValue = ''; }
  });
  form.addEventListener('submit', function (event) {
    if (event.submitter && event.submitter.name === 'preview') return;
    if (allowSubmit) {
      submitting = true;
      // A later confirmation handler may still cancel the submission.
      Promise.resolve().then(function () { if (event.defaultPrevented) submitting = false; });
      return;
    }
    event.preventDefault();
    if (waitingForSubmit) return;
    var submitter = event.submitter;
    waitingForSubmit = true;
    Promise.resolve(pending).then(function () {
      allowSubmit = true;
      try { form.requestSubmit(submitter || undefined); }
      finally { allowSubmit = false; waitingForSubmit = false; }
    });
  });
  document.addEventListener('click', function (event) {
    var recover = event.target.closest('[data-recover-note]');
    var discard = event.target.closest('[data-discard-recovery]');
    if (!recover && !discard) return;
    if (recover && snapshot() !== baseline && !window.confirm('Replace the edits currently in this form with the recovered draft?')) return;
    (recover || discard).disabled = true;
    request(recover ? 'load' : 'discard').then(function (result) {
      if (recover && result.draft) {
        Object.keys(result.draft).forEach(function (key) {
          if (key === 'version') return; // Preserve the version of the currently loaded note.
          var field = form.elements.namedItem(key);
          if (field) field.value = result.draft[key] == null ? '' : result.draft[key];
        });
        var topic = String(result.draft.topic_id || '0');
        form.elements.subject_id.dispatchEvent(new Event('change', { bubbles: true }));
        form.elements.topic_id.value = topic;
        if (area) area.innerHTML = result.draft.content || '';
        form.dispatchEvent(new Event('input', { bubbles: true }));
        status.textContent = 'Recovered draft loaded. Review it and save when ready.';
      } else { status.textContent = recover ? 'No recovered draft remains.' : 'Recovery discarded.'; }
      blocked = false;
      document.querySelector('[data-recovery-banner]').remove();
    }).catch(function (error) { status.textContent = error.message; (recover || discard).disabled = false; });
  });
})();
