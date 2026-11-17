/* Страница „По ваша идея“: избор на файлове (камера или файлове), проверки и изпращане (multipart) */
(function () {
  'use strict';
  var CFG = window.FP_ICFG, form = document.getElementById('ideaForm');
  if (!CFG || !form) return;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var btn = $('#ideaBtn'), msg = $('#ideaMsg'), list = $('#fileList'), sum = $('#fileSum'), sending = false;
  var ALLOWED = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf', 'svg', 'dxf'], PREVIEW = ['jpg', 'jpeg', 'png', 'webp'];
  var files = []; // избраните файлове (от камерата и от „Избери файлове“)

  function mb(b) { return b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
  function ext(n) { return (n.split('.').pop() || '').toLowerCase(); }
  function fieldError(name, text) {
    var el = form.querySelector('[data-err="' + name + '"]'), input = form.elements[name];
    if (el) { el.textContent = text || ''; el.hidden = !text; }
    if (input && input.classList) { input.classList.toggle('invalid', !!text); input.setAttribute('aria-invalid', text ? 'true' : 'false'); }
  }

  /* ---------- файлове ---------- */
  function total() { return files.reduce(function (a, f) { return a + f.size; }, 0); }
  function drawFiles() {
    list.innerHTML = '';
    files.forEach(function (f, i) {
      var li = document.createElement('li'), e = ext(f.name);
      var th = document.createElement('span');
      th.className = 'thumb';
      if (PREVIEW.indexOf(e) >= 0 && window.URL) {
        var img = document.createElement('img');
        img.alt = '';
        img.src = URL.createObjectURL(f);
        img.onload = function () { URL.revokeObjectURL(img.src); };
        th.appendChild(img);
      } else {
        th.textContent = e.toUpperCase();
      }
      var tx = document.createElement('span');
      tx.className = 'fname';
      tx.textContent = f.name + ' · ' + mb(f.size);
      var rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'link-btn';
      rm.textContent = 'Махни';
      rm.setAttribute('aria-label', 'Махни ' + f.name);
      rm.addEventListener('click', function () { files.splice(i, 1); drawFiles(); });
      li.appendChild(th); li.appendChild(tx); li.appendChild(rm);
      list.appendChild(li);
    });
    sum.textContent = files.length
      ? files.length + ' от ' + CFG.maxFiles + ' файла · ' + mb(total()) + ' от 25 MB'
      : 'JPG, PNG, WebP, HEIC, PDF, SVG или DXF · до 8 MB на файл, до 25 MB общо.';
  }
  /** Добавя избраните файлове; всеки отхвърлен – съобщение кой, защо и какво да се направи */
  function addFiles(fl) {
    var errs = [];
    Array.prototype.forEach.call(fl || [], function (f) {
      var q = '„' + f.name + '“';
      if (files.length >= CFG.maxFiles) { errs.push(q + ': вече има ' + CFG.maxFiles + ' файла – това е максимумът. Махнете някой, ако искате да добавите този.'); return; }
      if (ALLOWED.indexOf(ext(f.name)) < 0) { errs.push(q + ': този тип файл не се приема. Изпратете JPG, PNG, WebP, HEIC, PDF, SVG или DXF.'); return; }
      if (f.size > CFG.maxFile) { errs.push(q + ' е ' + mb(f.size) + ' – максимумът е 8 MB на файл. Намалете го (напр. снимка в по-малък размер) и опитайте отново.'); return; }
      if (total() + f.size > CFG.maxTotal) { errs.push(q + ': с него файловете стават над 25 MB общо. Намалете снимките или изпратете по-малко файлове.'); return; }
      files.push(f);
    });
    fieldError('files', errs.join('\n'));
    drawFiles();
  }
  $('#i-camera').addEventListener('change', function (e) { addFiles(e.target.files); e.target.value = ''; });
  $('#i-files').addEventListener('change', function (e) { addFiles(e.target.files); e.target.value = ''; });

  /* ---------- описание: брояч ---------- */
  var desc = form.elements.description, cnt = $('#descCount');
  function syncCount() {
    var n = desc.value.trim().length;
    cnt.textContent = n < 20 ? 'още ' + (20 - n) + ' знака (поне 20)' : n + ' знака';
  }
  desc.addEventListener('input', syncCount);

  /* ---------- запазване на полетата в браузъра (без файловете и отметките) ---------- */
  var STORE = 'fp_idea_v1', TTL = 30 * 864e5, FIELDS = ['description', 'w', 'd', 'h', 'inscription', 'quantity', 'budget', 'name', 'phone', 'email', 'city'];
  function storeSave() {
    var d = {};
    FIELDS.forEach(function (n) { d[n] = String(form.elements[n].value || '').slice(0, 3000); });
    ['what', 'material'].forEach(function (n) { var c = form.querySelector('input[name="' + n + '"]:checked'); d[n] = c ? c.value : ''; });
    try { localStorage.setItem(STORE, JSON.stringify({ ts: Date.now(), data: d })); } catch (e) { /* */ }
  }
  (function restore() {
    try {
      var v = JSON.parse(localStorage.getItem(STORE) || 'null');
      if (!v || !v.ts || Date.now() - v.ts > TTL) return;
      FIELDS.forEach(function (n) { if (typeof v.data[n] === 'string' && v.data[n] !== '') form.elements[n].value = v.data[n]; });
      ['what', 'material'].forEach(function (n) { var c = v.data[n] && form.querySelector('input[name="' + n + '"][value="' + v.data[n] + '"]'); if (c) c.checked = true; });
    } catch (e) { /* */ }
  })();
  syncCount();
  var saveTimer = 0;
  form.addEventListener('input', function () { clearTimeout(saveTimer); saveTimer = setTimeout(storeSave, 300); });
  form.addEventListener('change', function () { clearTimeout(saveTimer); saveTimer = setTimeout(storeSave, 100); });

  /* ---------- проверка и изпращане ---------- */
  function validate() {
    var e = {}, v = function (n) { return String(form.elements[n].value || '').trim(); };
    if (desc.value.trim().length < 20) e.description = 'Опишете идеята с поне 20 знака.';
    [['w', 'ширината', true], ['d', 'дълбочината', false], ['h', 'височината', true]].forEach(function (x) {
      var s = v(x[0]).replace(',', '.'), n = parseFloat(s);
      if (s === '') { if (x[2]) e[x[0]] = 'Въведете ' + x[1] + ' в см (приблизително).'; }
      else if (!(n > 0 && n <= 1000)) e[x[0]] = 'Число между 1 и 1000 см.';
    });
    var q = parseInt(v('quantity'), 10);
    if (!(q >= 1 && q <= 500)) e.quantity = 'Брой между 1 и 500.';
    if (!files.length) e.files = 'Прикачете поне една снимка, скица или чертеж.';
    if (v('name').length < 2) e.name = 'Въведете име.';
    var digits = v('phone').replace(/\D/g, '');
    if (!v('phone')) e.phone = 'Въведете телефон.';
    else if (!/^[\d\s+()\-\/]+$/.test(v('phone')) || digits.length < 8 || digits.length > 15) e.phone = 'Проверете телефона – напр. 0888 123 456.';
    if (!v('email')) e.email = 'Въведете имейл.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v('email'))) e.email = 'Невалиден имейл.';
    if (!form.elements.rights.checked) e.rights = 'Потвърдете, че имате право да използвате изображенията и идеята.';
    if (!form.elements.consent.checked) e.consent = 'Нужно е съгласие, за да се свържем с вас.';
    ['description', 'w', 'd', 'h', 'quantity', 'name', 'phone', 'email', 'rights', 'consent'].forEach(function (n) { fieldError(n, e[n]); });
    if (e.files) fieldError('files', e.files);
    return e;
  }
  function src() { try { return localStorage.getItem('fp_src') || '{}'; } catch (e) { return '{}'; } }
  function post(fd) {
    return fetch(CFG.url, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Сървърът върна неочакван отговор.' }; }); });
  }
  /** При изтекла сесия: нов токен и още един опит със същите данни и файлове */
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
  function unlock() { sending = false; btn.disabled = false; btn.classList.remove('loading'); }
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (sending) return;
    var errs = validate();
    if (Object.keys(errs).length) {
      var first = form.querySelector('.invalid') || form.querySelector('[data-err="files"]:not([hidden])');
      if (first) { if (first.focus) first.focus(); if (first.scrollIntoView) first.scrollIntoView({ block: 'center' }); }
      return;
    }
    sending = true;
    btn.disabled = true;
    btn.classList.add('loading');
    msg.hidden = true;
    var fd = new FormData(form);
    fd.delete('website');
    fd.set('website', form.elements.website.value);
    files.forEach(function (f) { fd.append('files[]', f, f.name); });
    fd.set('csrf', CFG.csrf);
    fd.set('src', src());
    send(fd, false).then(function (data) {
      if (data && data.ok) {
        try { localStorage.removeItem(STORE); } catch (e) { /* */ }
        location.href = data.redirect;
        return;
      }
      unlock();
      var es = (data && data.errors) || {};
      Object.keys(es).forEach(function (k) { fieldError(k, es[k]); });
      msg.textContent = (data && data.message) || 'Запитването не беше изпратено. Опитайте отново.';
      msg.hidden = false;
    }).catch(function () {
      unlock();
      msg.textContent = 'Няма връзка със сървъра. Данните ви са запазени – опитайте отново.';
      msg.hidden = false;
    });
  });
})();
