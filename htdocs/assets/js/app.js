/* Public site behaviour: note contents and mobile navigation. */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var content = document.querySelector('.gfg-article .note-content');
    if (content) {
      var headings = Array.prototype.filter.call(content.querySelectorAll('h1, h2, h3, h4, h5, h6'), function (heading) {
        return heading.textContent.trim() !== '';
      });

      if (headings.length > 1) {
        var contents = document.createElement('details');
        contents.className = 'note-contents';
        var summary = document.createElement('summary');
        summary.textContent = 'On this page (' + headings.length + ' sections)';
        contents.appendChild(summary);
        var index = document.createElement('nav');
        index.setAttribute('aria-label', 'On this page');
        var list = document.createElement('ol');

        headings.forEach(function (heading, position) {
          if (!heading.id) {
            var id = 'note-section-' + (position + 1);
            while (document.getElementById(id)) { id += '-section'; }
            heading.id = id;
          }
          // Let native fragment navigation move keyboard focus to the section.
          heading.setAttribute('tabindex', '-1');
          var item = document.createElement('li');
          var link = document.createElement('a');
          link.setAttribute('href', '#' + encodeURIComponent(heading.id));
          link.textContent = heading.textContent.trim();
          item.appendChild(link);
          list.appendChild(item);
        });

        index.appendChild(list);
        contents.appendChild(index);
        content.parentNode.insertBefore(contents, content);

        // Generated section IDs must also work when opening a shared link.
        try {
          var target = document.getElementById(decodeURIComponent(window.location.hash.slice(1)));
          if (target && headings.indexOf(target) !== -1) { target.scrollIntoView(); }
        } catch (error) { /* Ignore malformed URL fragments. */ }
      }
    }

    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.querySelector('[data-site-nav]');

    if (!toggle || !nav) {
      return;
    }

    function setOpen(open) {
      nav.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function () {
      setOpen(!nav.classList.contains('is-open'));
    });

    nav.addEventListener('click', function (event) {
      if (event.target.closest('a')) {
        setOpen(false);
      }
    });

    document.addEventListener('click', function (event) {
      if (nav.classList.contains('is-open') && !event.target.closest('[data-site-nav], [data-nav-toggle]')) {
        setOpen(false);
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && nav.classList.contains('is-open')) {
        setOpen(false);
        toggle.focus();
      }
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth >= 1024) {
        setOpen(false);
      }
    });
  });
})();
