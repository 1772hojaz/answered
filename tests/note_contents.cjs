const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../htdocs/assets/js/app.js'), 'utf8');

function render(titles, hash = '', occupiedIds = []) {
  const ids = new Map(occupiedIds.map(id => [id, {}]));
  function element(tagName, textContent = '') {
    let id = '';
    return {
      tagName, textContent, children: [], attributes: {},
      get id() { return id; },
      set id(value) { id = value; ids.set(id, this); },
      setAttribute(name, value) { this.attributes[name] = value; },
      appendChild(child) { this.children.push(child); },
      scrollIntoView() { this.scrolled = true; }
    };
  }
  const headings = titles.map(title => element('h2', title));
  let contents;
  const content = {
    querySelectorAll() { return headings; },
    parentNode: { insertBefore(node, reference) { assert.equal(reference, content); contents = node; } }
  };
  const document = {
    addEventListener(name, callback) { if (name === 'DOMContentLoaded') callback(); },
    querySelector(selector) { return selector === '.gfg-article .note-content' && titles.length ? content : null; },
    getElementById(id) { return ids.get(id) || null; },
    createElement: element
  };
  vm.runInNewContext(source, { document, window: { location: { hash } } });
  return { contents, headings };
}

test('notes with fewer than two nonempty headings leave the reading page untouched', () => {
  for (const titles of [[], ['Introduction'], ['Introduction', '  ']]) {
    assert.equal(render(titles).contents, undefined);
  }
});

test('contents links reach each section, including repeated headings', () => {
  const { contents, headings } = render(['Overview', 'Examples', 'Examples']);
  assert.equal(contents.tagName, 'details');
  assert.equal(contents.children[0].textContent, 'On this page (3 sections)');
  const nav = contents.children[1];
  assert.equal(nav.attributes['aria-label'], 'On this page');
  nav.children[0].children.forEach((item, index) => {
    const link = item.children[0];
    assert.equal(link.attributes.href, '#' + headings[index].id);
    assert.equal(link.textContent, headings[index].textContent);
    assert.equal(headings[index].attributes.tabindex, '-1');
  });
  assert.equal(new Set(headings.map(heading => heading.id)).size, 3);
});

test('section IDs avoid collisions elsewhere on the page', () => {
  const { headings } = render(['First', 'Second'], '', ['note-section-1', 'note-section-1-section']);
  assert.equal(headings[0].id, 'note-section-1-section-section');
});

test('heading labels remain plain text and empty headings are omitted', () => {
  const { contents } = render(['  ', '<img src=x onerror=alert(1)>', 'Biology & cells']);
  const items = contents.children[1].children[0].children;
  assert.equal(items.length, 2);
  assert.equal(items[0].children[0].textContent, '<img src=x onerror=alert(1)>');
  assert.equal(items[0].children[0].children.length, 0);
});

test('shared section links scroll after generated headings exist', () => {
  assert.equal(render(['First', 'Second'], '#note-section-2').headings[1].scrolled, true);
  assert.equal(render(['First', 'Second'], '#%6Eote-section-2').headings[1].scrolled, true);
  for (const hash of ['#%invalid', '#missing', '#unrelated']) {
    assert.doesNotThrow(() => render(['First', 'Second'], hash, ['unrelated']));
  }
});
