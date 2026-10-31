/* Load fixed display banners only when desktop sidebars are visible. */
(function () {
  'use strict';

  var desktop = window.matchMedia('screen and (min-width: 1280px)');

  function loadSideAds() {
    if (!desktop.matches || !window.aclib || typeof window.aclib.runBanner !== 'function') {
      return;
    }
    document.querySelectorAll('[data-adcash-zone]').forEach(function (slot) {
      if (slot.dataset.adcashLoaded === 'true') {
        return;
      }
      try {
        window.aclib.runBanner({
          zoneId: slot.dataset.adcashZone,
          renderIn: '#' + slot.id
        });
        slot.dataset.adcashLoaded = 'true';
      } catch (error) {
        // Keep the page usable if the external advertising library fails.
      }
    });
  }

  loadSideAds();
  if (desktop.addEventListener) {
    desktop.addEventListener('change', loadSideAds);
  } else {
    desktop.addListener(loadSideAds);
  }
})();
