/* Страница „За хотели и къщи за гости“: кратко запитване по проект с лого (картите рисува home.js) */
(function () {
  'use strict';
  var CFG = window.FP_PCFG;
  var $ = function (sel, r) { return (r || document).querySelector(sel); };

  /* ---------- форма ---------- */
  var form = $('#projForm');
  if (!form) return;
  var btn = $('#projBtn'), msg = $('#projMsg'), sending = false;
  var STORE = 'fp_proj_v1', TTL = 30 * 864e5;
  var TEXT_FIELDS = ['object', 'city', 'quantity', 'deadline', 'note', 'contact_name', 'phone', 'email'];

  function storeGet() {
    try {
      var v = JSON.parse(localStorage.getItem(STORE) || 'null');
      if (v && v.ts && Date.now() - v.ts < TTL) return v.data;
      if (v) localStorage.removeItem(STORE);
    } catch (e) { /* */ }
    return null;
  }
  function storeSave() {
    var d = { interests: [] };
    TEXT_FIELDS.forEach(function (n) { d[n] = String(form.elements[n].value || '').slice(0, 3000); });
    var mat = form.querySelector('input[name="material"]:checked');
    d.material = mat ? mat.value : '';
    Array.prototype.forEach.call(form.querySelectorAll('input[name="interests[]"]:checked'), function (c) { d.interests.push(c.value); });
    try { localStorage.setItem(STORE, JSON.stringify({ ts: Date.now(), data: d })); } catch (e) { /* */ }
  }
  (function restore() {
    var d = storeGet();
    if (!d) return;
    TEXT_FIELDS.forEach(function (n) { if (typeof d[n] === 'string' && d[n] !== '') form.elements[n].value = d[n]; });
    if (d.material) { var m = form.querySelector('input[name="material"][value="' + d.material + '"]'); if (m) m.checked = true; }
    (d.interests || []).forEach(function (c) { var x = form.querySelector('input[name="interests[]"][value="' + c + '"]'); if (x) x.checked = true; });
  })();
  var saveTimer = 0;
  form.addEventListener('input', function () { clearTimeout(saveTimer); saveTimer = setTimeout(storeSave, 300); });
  form.addEventListener('change', function () { clearTimeout(saveTimer); saveTimer = setTimeout(storeSave, 100); });

  function fieldError(name, text) {
    var el = form.querySelector('[data-err="' + name + '"]'), input = form.elements[name];
    if (el) { el.textContent = text || ''; el.hidden = !text; }
    if (input && input.classList) { input.classList.toggle('invalid', !!text); input.setAttribute('aria-invalid', text ? 'true' : 'false'); }
  }

  var logo = form.elements.logo, logoInfo = $('#logoInfo');
  function checkLogo() {
    var f = logo.files && logo.files[0];
    logoInfo.textContent = '';
    if (!f) { fieldError('logo', ''); return ''; }
    var ext = (f.name.split('.').pop() || '').toLowerCase();
    var err = '';
    if (['png', 'jpg', 'jpeg', 'svg', 'pdf'].indexOf(ext) < 0) err = 'Позволени са само PNG, JPG, SVG и PDF.';
    else if (f.size > CFG.maxBytes) err = 'Файлът е ' + (f.size / 1048576).toFixed(1).replace('.', ',') + ' MB. Максимумът е 5 MB.';
    fieldError('logo', err);
    if (!err) logoInfo.textContent = f.name + ' · ' + (f.size >= 1048576 ? (f.size / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' KB');
    return err;
  }
  logo.addEventListener('change', checkLogo);

  function validate() {
    var e = {}, v = function (n) { return String(form.elements[n].value || '').trim(); };
    if (v('object').length < 2) e.object = 'Напишете кой е обектът.';
    var q = parseInt(v('quantity'), 10);
    if (!(q >= 1 && q <= 500)) e.quantity = 'Брой между 1 и 500.';
    if (v('contact_name').length < 2) e.contact_name = 'Въведете име.';
    var digits = v('phone').replace(/\D/g, '');
    if (!v('phone')) e.phone = 'Въведете телефон.';
    else if (!/^[\d\s+()\-\/]+$/.test(v('phone')) || digits.length < 8 || digits.length > 15) e.phone = 'Проверете телефона – напр. 0888 123 456.';
    if (!v('email')) e.email = 'Въведете имейл.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v('email'))) e.email = 'Невалиден имейл.';
    if (!form.elements.consent.checked) e.consent = 'Нужно е съгласие, за да се свържем с вас.';
    ['object', 'quantity', 'contact_name', 'phone', 'email', 'consent'].forEach(function (n) { fieldError(n, e[n]); });
    var le = checkLogo();
    if (le) e.logo = le;
    return e;
  }

  function src() { try { return localStorage.getItem('fp_src') || '{}'; } catch (e) { return '{}'; } }

  function post(fd) {
    return fetch(CFG.url, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Сървърът върна неочакван отговор.' }; }); });
  }
  /** При изтекла сесия: нов токен и още един опит със същите данни (и същия файл) */
  function send(fd, retried) {
    return post(fd).then(function (data) {
      if (data && data.code === 'csrf' && !retried) {
        return fetch(CFG.tokenUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: '{}' })
          .then(function (r) { return r.json(); }).then(function (t) {
            if (!t || !t.csrf) return data;
            CFG.csrf = t.csrf;
            fd.set('csrf', t.csrf);
            return send(fd, true);
          }, function () { return data; });
      }
      return data;
    });
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (sending) return;
    var errs = validate();
    if (Object.keys(errs).length) {
      var first = form.querySelector('.invalid');
      if (first) first.focus();
      return;
    }
    sending = true;
    btn.disabled = true;
    btn.classList.add('loading');
    msg.hidden = true;
    var fd = new FormData(form);
    fd.set('csrf', CFG.csrf);
    fd.set('src', src());
    send(fd, false).then(function (data) {
      if (data && data.ok) {
        try { localStorage.removeItem(STORE); } catch (e) { /* */ }
        location.href = data.redirect;
        return;
      }
      sending = false;
      btn.disabled = false;
      btn.classList.remove('loading');
      var es = (data && data.errors) || {};
      Object.keys(es).forEach(function (k) { fieldError(k, es[k]); });
      msg.textContent = (data && data.message) || 'Запитването не беше изпратено. Опитайте отново.';
      msg.hidden = false;
    }).catch(function () {
      sending = false;
      btn.disabled = false;
      btn.classList.remove('loading');
      msg.textContent = 'Няма връзка със сървъра. Данните ви са запазени – опитайте отново.';
      msg.hidden = false;
    });
  });
})();
