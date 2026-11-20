const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const read = name => fs.readFileSync(path.join(__dirname, '../htdocs/assets/js/' + name), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));

function editor() {
  const handlers = {}, formHandlers = {}, documentHandlers = {};
  const status = { textContent: '', classList: { add() {}, remove() {} } };
  let timer, requests = 0, confirms = 0;
  class Form {
    constructor() { this.fields = { title: 'Original', content: 'Text' }; this.dataset = { autosaveUrl: '/recovery' }; this.valid = true; this.submissions = 0; }
    querySelector(selector) { return selector === '[data-autosave-status]' ? status : null; }
    addEventListener(name, handler) { formHandlers[name] = handler; }
    getAttribute() { return null; }
    requestSubmit(submitter) { if (this.valid) this.submit(submitter); }
    submit(submitter) {
      const event = { target: this, submitter, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, stopPropagation() {} };
      formHandlers.submit(event);
      if (documentHandlers.submit) documentHandlers.submit(event);
      if (!event.defaultPrevented) { this.submissions++; this.lastSubmitter = submitter; }
    }
  }
  const form = new Form();
  const document = {
    getElementById() { return form; }, querySelector() { return null; }, querySelectorAll() { return []; },
    addEventListener(name, handler) { documentHandlers[name] = handler; }
  };
  const context = vm.createContext({
    document, HTMLFormElement: Form,
    window: { addEventListener(name, handler) { handlers[name] = handler; }, confirm() { confirms++; return false; } },
    FormData: class extends Map { constructor(form) { super(Object.entries(form.fields)); } },
    fetch() { requests++; return Promise.resolve({ ok: true, json: () => Promise.resolve({ ok: true, saved_at: '12:00' }) }); },
    setInterval(callback) { timer = callback; }
  });
  vm.runInContext(read('autosave.js'), context);
  vm.runInContext(read('admin.js'), context);
  return { form, status, timer: () => timer(), requests: () => requests, confirms: () => confirms,
    leave() { const event = { prevented: false, preventDefault() { this.prevented = true; } }; handlers.beforeunload(event); return event.prevented; } };
}

test('failed validation after waiting for a pending recovery keeps autosave active', async () => {
  const page = editor();
  page.form.fields.title = 'Edited';
  page.timer();
  page.form.submit({ name: 'save_draft', getAttribute() { return null; } });
  page.form.valid = false;
  await flush();
  assert.equal(page.form.submissions, 0);
  page.form.fields.title = 'Further edits';
  assert.equal(page.leave(), true);
  page.timer();
  await flush();
  assert.equal(page.requests(), 2);
});

test('trash confirmation is shown once and cancellation preserves unsaved protection', async () => {
  const page = editor();
  page.form.fields.title = 'Edited';
  page.form.submit({ name: 'form_action', getAttribute(name) { return name === 'data-confirm' ? 'Move to trash?' : null; } });
  await flush();
  assert.equal(page.confirms(), 1);
  assert.equal(page.form.submissions, 0);
  assert.equal(page.leave(), true);
  page.timer();
  await flush();
  assert.equal(page.requests(), 1);
});

test('ordinary save submits once and preserves the clicked button', async () => {
  const page = editor();
  page.form.fields.title = 'Edited';
  const publish = { name: 'publish', getAttribute() { return null; } };
  page.form.submit(publish);
  await flush();
  assert.equal(page.form.submissions, 1);
  assert.equal(page.form.lastSubmitter, publish);
  assert.equal(page.leave(), false);
});

test('preview leaves autosave and unsaved-change protection active in the editor', async () => {
  const page = editor();
  page.form.fields.title = 'Edited';
  page.form.submit({ name: 'preview', getAttribute() { return null; } });
  assert.equal(page.form.submissions, 1);
  assert.equal(page.leave(), true);
  page.timer();
  await flush();
  assert.equal(page.requests(), 1);
});
