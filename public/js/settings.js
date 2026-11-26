/**
 * Add Browser Search settings page: character counters, a copy button, and a preview that reads the
 * description from the real address, exactly as a browser does. No dependencies; every text the
 * script shows comes from the page's markup (data-* attributes), which PHP has translated.
 */
(function () {
  'use strict';

  document.querySelectorAll('[data-abs-count]').forEach(function (field) {
    var max = parseInt(field.getAttribute('data-abs-count'), 10);
    var out = field.parentNode.querySelector('.abs-count');

    if (!out || !max) {
      return;
    }

    var update = function () {
      var length = Array.from(field.value).length;
      out.textContent = length + ' / ' + max;
      out.classList.toggle('is-full', length >= max);
    };

    field.addEventListener('input', update);
    update();
  });

  document.querySelectorAll('[data-abs-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var source = document.querySelector(button.getAttribute('data-abs-copy'));
      var label = button.textContent;

      if (!source) {
        return;
      }

      source.focus();
      source.select();

      var done = function () {
        button.textContent = button.getAttribute('data-copied') || label;
        setTimeout(function () {
          button.textContent = label;
        }, 1500);
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(source.value).then(done, function () {});
      } else if (document.execCommand && document.execCommand('copy')) {
        done();
      }
    });
  });

  var preview = document.getElementById('abs-xml');

  if (preview && window.fetch) {
    fetch(preview.getAttribute('data-abs-url'), { credentials: 'omit', cache: 'no-store' })
      .then(function (response) {
        return response.ok ? response.text() : null;
      })
      .then(function (text) {
        // Only replaces what PHP rendered when the real address answers: a server that blocks .xml
        // keeps the server-side preview.
        if (text && text.indexOf('<OpenSearchDescription') !== -1) {
          preview.textContent = text;
        }
      })
      .catch(function () {});
  }
})();
