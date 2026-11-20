const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../htdocs/assets/js/ads.js'), 'utf8');

function slot(type, width = 728, height = 90, visible = true) {
  const placement = {
    clientWidth: width, children: [], hidden: false,
    getClientRects() { return visible ? [{}] : []; },
    appendChild(child) { this.children.push(child); }
  };
  return {
    dataset: type === 'native' ? { nativeAd: 'https://bauval.org/21/450869d5ebb53b1108ab92ae905cc15f' }
      : { adFrame: '/ad-frame.php?placement=' + type, adWidth: String(width), adHeight: String(height) },
    children: [], placement,
    getClientRects() { return visible ? [{}] : []; },
    getBoundingClientRect() { return { width }; },
    closest() { return placement; },
    appendChild(child) { this.children.push(child); },
    show() { visible = true; }
  };
}
function start(slots, observers = true) {
  const handlers = {}, instances = {};
  class IntersectionObserver {
    constructor(callback, options) { this.callback = callback; this.options = options; instances.intersection = this; }
    observe() {}
    unobserve() {}
  }
  class ResizeObserver {
    constructor(callback) { this.callback = callback; instances.resize = this; }
    observe() {}
  }
  const window = { addEventListener(name, handler) { handlers[name] = handler; } };
  if (observers) { Object.assign(window, { IntersectionObserver, ResizeObserver }); }
  const document = {
    querySelectorAll() { return slots; },
    createElement(tagName) {
      return { tagName, attributes: {}, events: {}, setAttribute(key, value) { this.attributes[key] = value; },
        addEventListener(name, callback) { this.events[name] = callback; } };
    }
  };
  vm.runInNewContext(source, { window, document, IntersectionObserver, ResizeObserver });
  return { handlers, instances, near(target) { instances.intersection.callback([{ target, isIntersecting: true }]); } };
}

test('offscreen placements do not load provider resources', () => {
  const ad = slot('leaderboard'); start([ad]); assert.equal(ad.children.length, 0);
});
test('fixed banners use distinct documents and load only once', () => {
  const tall = slot('skyscraper', 160, 600), wide = slot('leaderboard');
  const browser = start([tall, wide]); browser.near(tall); browser.near(wide); browser.near(tall); browser.handlers.resize();
  assert.equal(tall.children.length, 1); assert.equal(wide.children.length, 1);
  assert.notEqual(tall.children[0].src, wide.children[0].src);
  assert.equal(tall.children[0].width, '160'); assert.equal(tall.children[0].height, '600');
  assert.equal(wide.children[0].width, '728'); assert.equal(wide.children[0].height, '90');
  assert.ok(tall.children[0].attributes.sandbox.includes('allow-scripts'));
  assert.ok(!tall.children[0].attributes.sandbox.includes('allow-same-origin'));
});
test('hidden mobile banners wait until the desktop placement becomes visible', () => {
  const ad = slot('skyscraper', 160, 600, false), browser = start([ad]); browser.near(ad);
  assert.equal(ad.children.length, 0); ad.show(); browser.handlers.resize(); assert.equal(ad.children.length, 1);
});
test('a leaderboard never loads into a container narrower than 728 pixels', () => {
  const ad = slot('leaderboard'); ad.placement.clientWidth = 500;
  const browser = start([ad]); browser.near(ad); assert.equal(ad.children.length, 0);
  ad.placement.clientWidth = 728; browser.handlers.resize(); assert.equal(ad.children.length, 1);
});
test('the mobile banner preserves its 320 by 50 dimensions and stays hidden when it cannot fit', () => {
  const ad = slot('mobile', 320, 50); ad.placement.clientWidth = 280;
  const browser = start([ad]); browser.near(ad); assert.equal(ad.children.length, 0);
  ad.placement.clientWidth = 320; browser.handlers.resize();
  assert.equal(ad.children.length, 1); assert.equal(ad.children[0].width, '320'); assert.equal(ad.children[0].height, '50');
  assert.equal(ad.children[0].src, '/ad-frame.php?placement=mobile');
});
test('native banners preserve the supplied async script and Cloudflare attribute', () => {
  const ad = slot('native'), browser = start([ad]); browser.near(ad); browser.near(ad);
  assert.equal(ad.placement.children.length, 1);
  const script = ad.placement.children[0];
  assert.equal(script.src, ad.dataset.nativeAd); assert.equal(script.async, true); assert.equal(script.attributes['data-cfasync'], 'false');
  script.events.error(); assert.equal(ad.placement.hidden, true);
});
test('browsers without observers still load visible placements once', () => {
  const ad = slot('leaderboard'), browser = start([ad], false);
  browser.handlers.resize(); assert.equal(ad.children.length, 1);
});
