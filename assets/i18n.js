/* Site-wide PT/EN switch. Pages hold both copies marked lang="pt" / lang="en";
   site.css hides the inactive one. Load in <head> without defer so the right
   language is set before first paint. ?lang=en|pt overrides the saved choice. */
(function () {
  var KEY = 'site-lang', root = document.documentElement, listeners = [];
  function valid(l) { return l === 'pt' || l === 'en' ? l : null; }
  function saved() { try { return valid(localStorage.getItem(KEY)); } catch (e) { return null; } }
  function fromQuery() { try { return valid(new URLSearchParams(location.search).get('lang')); } catch (e) { return null; } }
  function fromBrowser() { return /^pt\b/i.test(navigator.language || 'pt') ? 'pt' : 'en'; }
  var lang = fromQuery() || saved() || fromBrowser();

  function swapAttrs() {
    var els = document.querySelectorAll('[data-i18n-attr]');
    for (var i = 0; i < els.length; i++) {
      var names = els[i].getAttribute('data-i18n-attr').split(',');
      for (var j = 0; j < names.length; j++) {
        var v = els[i].getAttribute('data-' + lang + '-' + names[j].trim());
        if (v !== null) els[i].setAttribute(names[j].trim(), v);
      }
    }
    var btns = document.querySelectorAll('[data-set-lang]');
    for (var k = 0; k < btns.length; k++) btns[k].setAttribute('aria-pressed', String(btns[k].getAttribute('data-set-lang') === lang));
  }

  function apply(notify) {
    root.setAttribute('data-lang', lang);
    root.lang = lang === 'pt' ? 'pt-BR' : 'en';
    var title = root.getAttribute('data-' + lang + '-title');
    if (title) document.title = title;
    if (document.readyState !== 'loading') swapAttrs();
    if (notify) for (var i = 0; i < listeners.length; i++) listeners[i](lang);
  }

  window.siteLang = {
    get: function () { return lang; },
    set: function (l) {
      if (!valid(l) || l === lang) return;
      lang = l;
      try { localStorage.setItem(KEY, l); } catch (e) {}
      apply(true);
    },
    on: function (fn) { listeners.push(fn); },
    t: function (pt, en) { return lang === 'en' ? en : pt; }
  };

  apply(false);
  document.addEventListener('DOMContentLoaded', swapAttrs);
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-set-lang]');
    if (b) window.siteLang.set(b.getAttribute('data-set-lang'));
  });
})();
