/* Load each placement once, only when visible and close to the viewport. */
(function () {
  'use strict';
  var slots = Array.from(document.querySelectorAll('[data-ad-frame], [data-native-ad]'));
  if (!slots.length) return;
  var observer = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) { entry.target.dataset.adNear = 'true'; load(entry.target); }
    });
  }, { rootMargin: '250px 0px' }) : null;

  function load(slot) {
    if (slot.dataset.adLoaded || (observer && slot.dataset.adNear !== 'true') || !slot.getClientRects().length) return;
    var placement = slot.closest('.ad-placement');
    if (!placement || !placement.getClientRects().length) return;
    if (slot.dataset.adFrame) {
      var width = Number(slot.dataset.adWidth), height = Number(slot.dataset.adHeight);
      if (placement.clientWidth < width || slot.getBoundingClientRect().width < width) return;
      var frame = document.createElement('iframe');
      frame.title = 'Advertisement ' + width + ' by ' + height;
      frame.width = String(width); frame.height = String(height);
      frame.setAttribute('sandbox', 'allow-scripts allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation');
      frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
      frame.scrolling = 'no';
      frame.src = slot.dataset.adFrame;
      slot.appendChild(frame);
    } else {
      var script = document.createElement('script');
      script.async = true;
      script.setAttribute('data-cfasync', 'false');
      script.src = slot.dataset.nativeAd;
      script.addEventListener('error', function () { placement.hidden = true; });
      // The target container already exists before the async provider executes.
      placement.appendChild(script);
    }
    slot.dataset.adLoaded = 'true';
    if (observer) observer.unobserve(slot);
  }

  var resize = 'ResizeObserver' in window ? new ResizeObserver(function (entries) {
    entries.forEach(function (entry) { load(entry.target); });
  }) : null;
  slots.forEach(function (slot) {
    if (observer) observer.observe(slot);
    if (resize) resize.observe(slot);
    load(slot);
  });
  window.addEventListener('resize', function () { slots.forEach(load); });
})();
