/* Общи функции за всички публични страници: източник на посещението, съгласие за бисквитки, Pixel/GA4 */
(function () {
  'use strict';

  function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* недостъпно */ } }

  // Източник на първото посещение (UTM + referrer) – пази се 90 дни в браузъра и се изпраща със запитването
  try {
    var now = Date.now(), cur = JSON.parse(lsGet('fp_src') || 'null');
    if (!cur || !cur.ts || now - cur.ts > 90 * 864e5) {
      var q = new URLSearchParams(location.search), data = { ts: now };
      ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(function (k) {
        if (q.get(k)) data[k] = q.get(k).slice(0, 190);
      });
      if (q.get('fbclid')) data.utm_source = data.utm_source || 'facebook';
      if (q.get('gclid')) data.utm_source = data.utm_source || 'google';
      var ref = document.referrer;
      if (ref && ref.indexOf(location.host) === -1) data.referrer = ref.slice(0, 500);
      data.landing = (location.pathname + location.search).slice(0, 500);
      lsSet('fp_src', JSON.stringify(data));
    }
  } catch (e) { /* продължаваме без източник */ }

  /* ---------- Проследяване: само при въведени ID-та и след съгласие ---------- */
  var TRACK = window.FP_TRACK || null;
  var CONSENT_KEY = 'fp_consent_v1';
  var queue = [], loaded = false;

  // Наше име на събитие -> [име в GA4, име в Meta Pixel, стандартно ли е за Pixel]
  var EVENTS = {
    configurator_open: ['configurator_open', 'ConfiguratorOpen', false],
    select_model: ['select_model', 'SelectModel', false],
    add_inscription: ['add_inscription', 'AddInscription', false],
    Lead: ['generate_lead', 'Lead', true]
  };

  function send(name, params) {
    var map = EVENTS[name] || [name, name, false];
    try {
      if (TRACK.ga4 && window.gtag) window.gtag('event', map[0], params || {});
      if (TRACK.pixel && window.fbq) window.fbq(map[2] ? 'track' : 'trackCustom', map[1], params || {});
    } catch (e) { /* */ }
  }

  function consent() {
    var v = lsGet(CONSENT_KEY);
    return v === 'granted' || v === 'denied' ? v : null;
  }

  function loadTrackers() {
    if (loaded || !TRACK) return;
    loaded = true;
    if (TRACK.ga4) {
      window.dataLayer = window.dataLayer || [];
      window.gtag = function () { window.dataLayer.push(arguments); };
      window.gtag('consent', 'default', { ad_storage: 'granted', analytics_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted' });
      window.gtag('js', new Date());
      window.gtag('config', TRACK.ga4);
      var s = document.createElement('script');
      s.async = true;
      s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(TRACK.ga4);
      document.head.appendChild(s);
    }
    if (TRACK.pixel) {
      /* Стандартният код на Meta Pixel */
      !function (f, b, e, v, n, t, s) {
        if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
        if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = [];
        t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
      }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
      window.fbq('init', TRACK.pixel);
      window.fbq('track', 'PageView');
    }
    queue.splice(0).forEach(function (ev) { send(ev[0], ev[1]); });
  }

  // Събитие от страниците: изпраща се веднага, чака съгласие или се изхвърля при отказ
  window.fpEvent = function (name, params) {
    if (!TRACK) return;
    var c = consent();
    if (c === 'granted') { loadTrackers(); send(name, params); }
    else if (c === null) queue.push([name, params]);
  };

  var banner = document.getElementById('cookieBanner');
  function showBanner(show) { if (banner) banner.hidden = !show; }

  function clearTrackerCookies() {
    document.cookie.split(';').forEach(function (c) {
      var name = c.split('=')[0].trim();
      if (/^(_ga|_gid|_gat|_fbp|_fbc)/.test(name)) {
        var host = location.hostname, parts = host.split('.');
        for (var i = 0; i < parts.length - 1; i++) {
          var d = parts.slice(i).join('.');
          document.cookie = name + '=; Max-Age=0; path=/; domain=.' + d;
        }
        document.cookie = name + '=; Max-Age=0; path=/';
      }
    });
  }

  function setConsent(v) {
    var before = consent();
    lsSet(CONSENT_KEY, v);
    showBanner(false);
    if (v === 'granted') loadTrackers();
    else {
      queue.length = 0;
      if (before === 'granted') { clearTrackerCookies(); location.reload(); }
    }
  }

  if (TRACK && banner) {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-consent]');
      if (b) { setConsent(b.getAttribute('data-consent')); return; }
      if (e.target.closest('[data-cookie-settings]')) showBanner(true);
    });
    var c = consent();
    if (c === 'granted') loadTrackers();
    else if (c === null) showBanner(true);
  }
})();
