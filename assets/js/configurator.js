/* Конфигуратор: стъпки, състояние, преглед и запитване */
(function () {
  'use strict';

  var CAT = window.FP_CATALOG, CFG = window.FP_CFG, G = window.FPGeo;
  if (!CAT || !CAT.models.length) return;

  var L = CAT.layout, FONTS = CAT.fonts, SET = CAT.settings;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  var groupsByCode = {};
  CAT.groups.forEach(function (g) { groupsByCode[g.code] = g; });

  var state = { type: null, model: null, opts: {}, extras: [], text: '', qty: 1 };
  var tracked = { text: false };

  /* ---------- помощни ---------- */
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function num(n) { return String(Math.round(n * 10) / 10).replace('.', ','); }
  function money(n) { return Math.round(n).toLocaleString('bg-BG').replace(/ /g, ' '); }
  function model() { return CAT.models.filter(function (m) { return m.code === state.model; })[0]; }
  function modelsOfType(t) { return CAT.models.filter(function (m) { return m.type === t; }); }

  /** Опциите на модела, групирани: { group: [{opt, mo}] } */
  function modelOptions(m) {
    var out = {};
    m.options.forEach(function (mo) {
      var o = CAT.options[mo.id];
      if (!o) return;
      (out[o.group] = out[o.group] || []).push({ opt: o, mo: mo });
    });
    return out;
  }

  function selected(m) {
    var mo = modelOptions(m), sel = [];
    Object.keys(mo).forEach(function (g) {
      mo[g].forEach(function (x) {
        if (groupsByCode[g] && groupsByCode[g].multi) {
          if (state.extras.indexOf(x.opt.code) >= 0) sel.push(x);
        } else if (state.opts[g] === x.opt.code) {
          sel.push(x);
        }
      });
    });
    return sel;
  }

  function renderParam(group, key, def) {
    var m = model(), list = modelOptions(m)[group] || [];
    for (var i = 0; i < list.length; i++) {
      if (list[i].opt.code === state.opts[group]) {
        var v = list[i].opt.render[key];
        return v === undefined ? def : v;
      }
    }
    return def;
  }

  /** Привежда избора в съответствие с модела: несъвместимите опции се заменят с подразбиращите се */
  function normalizeState() {
    var m = model(), mo = modelOptions(m);
    CAT.groups.forEach(function (g) {
      var list = mo[g.code] || [];
      if (g.multi) {
        state.extras = state.extras.filter(function (c) { return list.some(function (x) { return x.opt.code === c; }); });
        return;
      }
      if (!list.length) { delete state.opts[g.code]; return; }
      var ok = list.some(function (x) { return x.opt.code === state.opts[g.code]; });
      if (!ok) {
        var def = list.filter(function (x) { return x.mo.default; })[0] || list[0];
        state.opts[g.code] = def.opt.code;
      }
    });
  }

  /* ---------- изчисления ---------- */
  function compute() {
    var m = model();
    var text = G.normalizeText(state.text);
    var fontKey = renderParam('font', 'font', 'russo');
    var sides = renderParam('sides', 'sides', 1);
    var pattern = renderParam('ornament', 'pattern', null);
    var walls = G.decoratedWalls(m, sides);
    var res = { m: m, text: text, fontKey: fontKey, sides: sides, pattern: pattern, walls: walls, fit: null, error: '' };
    if (text) {
      var len = G.textLength(text), bad = [];
      G.chars(text).forEach(function (ch) { if (FONTS.allowed.indexOf(ch) < 0 && bad.indexOf(ch) < 0) bad.push(ch); });
      if (bad.length) {
        res.error = 'Тези знаци не могат да се изрежат: ' + bad.join(' ');
      } else {
        res.fit = G.textFit(text, fontKey, walls, !!pattern, FONTS, L);
        if (len > m.max_chars) {
          res.error = 'Надписът е ' + len + ' знака, а за този модел са позволени до ' + m.max_chars + '.';
          res.errorType = 'max';
        } else if (res.fit && res.fit.letter_mm + 0.05 < SET.min_letter_mm) {
          res.error = 'Буквите стават ' + num(res.fit.letter_mm) + ' мм, а минимумът за изрязване е ' + num(SET.min_letter_mm) +
            ' мм. Съкратете надписа, изберете по-тесен шрифт или главни букви.';
          res.errorType = 'small';
          res.betterFont = bestFont(m, text, walls, !!pattern, fontKey);
        }
      }
    }
    res.cap = G.textCapacity(text, fontKey, walls, FONTS, L, SET.min_letter_mm, m.max_chars);
    var sel = selected(m);
    var isDec = !!text || !!pattern;
    res.decoratedCount = isDec ? walls.length : 0;
    res.price = G.calcPrice(m, sel, walls.length, isDec, state.qty);
    res.weight = G.calcWeight(m, sel);
    res.legCm = renderParam('legs', 'leg_cm', 0);
    res.finish = renderParam('material', 'finish', 'inox');
    return res;
  }

  /** Шрифтът (от позволените за модела), с който буквите стават най-високи */
  function bestFont(m, text, walls, hasOrn, current) {
    var best = null, bestMm = -1, curMm = -1;
    (modelOptions(m).font || []).forEach(function (x) {
      var fit = G.textFit(text, x.opt.render.font, walls, hasOrn, FONTS, L);
      if (!fit) return;
      if (x.opt.render.font === current) curMm = fit.letter_mm;
      if (fit.letter_mm > bestMm) { bestMm = fit.letter_mm; best = { code: x.opt.code, name: x.opt.name, mm: fit.letter_mm }; }
    });
    return best && bestMm > curMm + 0.05 ? best : null;
  }

  function renderOpts(r, extra) {
    var ex = {};
    state.extras.forEach(function (c) {
      var list = modelOptions(r.m).extras || [];
      list.forEach(function (x) { if (x.opt.code === c) ex[x.opt.render.extra || c] = true; });
    });
    var o = {
      model: r.m, finish: r.finish, legCm: r.legCm, text: r.error && !r.fit ? '' : r.text, fit: r.fit, fontKey: r.fontKey,
      pattern: r.pattern, decoratedCount: r.walls.length, extras: ex, layout: L, id: 'pv', title: r.m.name
    };
    for (var k in extra || {}) o[k] = extra[k];
    return o;
  }

  /* ---------- изграждане на стъпките ---------- */
  var stepsEl = $('#steps'), previewEl = $('#preview'), specsEl = $('#specs');

  function stepHtml(id, title, body, hint) {
    return '<section class="step" id="' + id + '"><h2><span class="num"></span>' + esc(title) + '</h2>' +
      (hint ? '<p class="step-hint">' + esc(hint) + '</p>' : '') + '<div class="step-body">' + body + '</div></section>';
  }

  function optCard(name, value, checked, title, hint, extraClass, swatch, type) {
    return '<label class="opt ' + (extraClass || '') + '"><input type="' + (type || 'radio') + '" name="' + esc(name) + '" value="' + esc(value) + '"' +
      (checked ? ' checked' : '') + '><span class="opt-in">' + (swatch || '') + '<span class="opt-txt"><b>' + esc(title) + '</b>' +
      (hint ? '<small>' + esc(hint) + '</small>' : '') + '</span></span></label>';
  }

  function ornamentIcon(pattern) {
    if (!pattern) return '<span class="sw sw-none"></span>';
    var d = window.FPRender.patterns[pattern](4, 6, 92, 28);
    return '<svg class="sw sw-orn" viewBox="0 0 100 40" aria-hidden="true"><path d="' + d + '"/></svg>';
  }

  function build() {
    var m = model(), mo = modelOptions(m), html = '';

    // 1. Вид
    if (CAT.types.length) {
      html += stepHtml('st-type', 'Вид', '<div class="opts opts-2">' + CAT.types.map(function (t) {
        return optCard('type', t.code, state.type === t.code, t.name, t.hint, 'opt-big');
      }).join('') + '</div>');
    }
    // 2. Модел
    html += stepHtml('st-model', 'Модел', '<div class="opts opts-models">' + modelsOfType(state.type).map(function (x) {
      var dims = x.shape === 'hex' ? 'Ø' + num(x.w) + ' × ' + num(x.h) + ' см' : num(x.w) + ' × ' + num(x.d) + ' × ' + num(x.h) + ' см';
      var thumb = '<span class="thumb" data-thumb="' + esc(x.code) + '"></span>';
      return optCard('model', x.code, state.model === x.code, x.name, dims, 'opt-model', thumb);
    }).join('') + '</div>');
    // 3. Материал
    if (mo.material) {
      html += stepHtml('st-material', groupsByCode.material.name, '<div class="opts">' + mo.material.map(function (x) {
        return optCard('material', x.opt.code, state.opts.material === x.opt.code, x.opt.name, x.opt.hint, '', '<span class="sw sw-' + esc(x.opt.render.finish || 'inox') + '"></span>');
      }).join('') + '</div>');
    }
    // 4. Крачета
    if (mo.legs) {
      html += stepHtml('st-legs', groupsByCode.legs.name, '<div class="opts opts-3">' + mo.legs.map(function (x) {
        return optCard('legs', x.opt.code, state.opts.legs === x.opt.code, x.opt.name, x.opt.hint, 'opt-compact');
      }).join('') + '</div>');
    }
    // 5. Надпис
    var body = '<div class="field"><label for="txt">Вашият надпис <span class="muted">(по желание)</span></label>' +
      '<input id="txt" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" value="' + esc(state.text) + '" placeholder="напр. СЕМЕЙСТВО ИВАНОВИ">' +
      '<div class="txt-meta"><span id="txtCount"></span><span id="txtLetter"></span></div>' +
      '<p class="err" id="txtErr" role="alert" hidden></p>' +
      '<button type="button" class="btn btn-sm btn-ghost narrow-btn" id="narrowBtn" hidden>Пробвай с по-тесния шрифт</button></div>';
    if (mo.font) {
      body += '<p class="sub">Шрифт</p><div class="opts opts-3 fonts">' + mo.font.map(function (x) {
        return '<label class="opt opt-font"><input type="radio" name="font" value="' + esc(x.opt.code) + '"' + (state.opts.font === x.opt.code ? ' checked' : '') +
          '><span class="opt-in"><span class="font-sample" style="font-family:\'fp-' + esc(x.opt.render.font) + '\'">Огън</span><small>' + esc(x.opt.name) + '</small></span></label>';
      }).join('') + '</div>';
    }
    if (mo.sides) {
      body += '<p class="sub">' + esc(groupsByCode.sides.name) + '</p><div class="opts opts-3">' + mo.sides.map(function (x) {
        var label = x.opt.render.sides === 'all' ? x.opt.name + ' (' + G.modelWalls(m).length + ')' : x.opt.name;
        return optCard('sides', x.opt.code, state.opts.sides === x.opt.code, label, '', 'opt-compact');
      }).join('') + '</div><p class="step-hint">' + esc(groupsByCode.sides.hint || '') + '</p>';
    }
    html += stepHtml('st-text', 'Надпис', body, 'До ' + m.max_chars + ' знака. Надписът се побира автоматично; минимална височина на буквите ' + num(SET.min_letter_mm) + ' мм.');
    // 6. Орнамент
    if (mo.ornament) {
      html += stepHtml('st-ornament', groupsByCode.ornament.name, '<div class="opts opts-orn">' + mo.ornament.map(function (x) {
        return optCard('ornament', x.opt.code, state.opts.ornament === x.opt.code, x.opt.name, '', 'opt-orn', ornamentIcon(x.opt.render.pattern));
      }).join('') + '</div>');
    }
    // 7. Екстри
    if (mo.extras) {
      html += stepHtml('st-extras', groupsByCode.extras.name, '<div class="opts">' + mo.extras.map(function (x) {
        return optCard('extras', x.opt.code, state.extras.indexOf(x.opt.code) >= 0, x.opt.name, x.opt.hint, 'opt-check', '<span class="tick"></span>', 'checkbox');
      }).join('') + '</div>', groupsByCode.extras.hint);
    }

    stepsEl.innerHTML = html;
    numberSteps();
    drawThumbs();
    updateText();
  }

  function numberSteps() {
    var i = 0;
    $$('.step').forEach(function (s) { if (!s.hidden) { i++; $('.num', s).textContent = i; } });
  }

  function drawThumbs() {
    $$('[data-thumb]').forEach(function (el) {
      var m = CAT.models.filter(function (x) { return x.code === el.getAttribute('data-thumb'); })[0];
      if (!m) return;
      el.innerHTML = window.FPRender.render({ model: m, finish: 'inox', legCm: 0, layout: L, id: 'th' + m.id, extras: {}, pattern: null, decoratedCount: 1 });
    });
  }

  /* ---------- обновяване ---------- */
  var pending = false, last = null;
  function update() {
    if (pending) return;
    pending = true;
    // microtask: събира няколко промени в едно прерисуване, без да зависи от requestAnimationFrame
    Promise.resolve().then(function () {
      pending = false;
      var r = compute();
      last = r;
      previewEl.innerHTML = window.FPRender.render(renderOpts(r));
      var m = r.m;
      var dims = m.shape === 'hex' ? 'Ø' + num(m.w) + ' × ' + num(m.h) + ' см' : num(m.w) + ' × ' + num(m.d) + ' × ' + num(m.h) + ' см';
      if (r.legCm) dims += ' <span class="muted">(+' + num(r.legCm) + ' см крачета)</span>';
      var spec = '<span>' + dims + '</span><span>≈ ' + num(r.weight.kg) + ' кг</span>';
      if (r.decoratedCount > 1) spec += '<span>' + r.decoratedCount + ' декорирани страни</span>';
      specsEl.innerHTML = spec;
      var priceTxt = r.price ? (r.price.min === r.price.max ? money(r.price.min) : money(r.price.min) + ' – ' + money(r.price.max)) + ' ' + SET.currency : '';
      $$('[data-price]').forEach(function (el) {
        el.hidden = !r.price;
        if (r.price) el.innerHTML = 'Ориентировъчно: <b>' + priceTxt + '</b>' + (state.qty > 1 ? ' <span class="muted">за ' + state.qty + ' бр.</span>' : '');
      });
      $('#barModel').textContent = m.name;
      $('#barInfo').textContent = r.price ? priceTxt : '≈ ' + num(r.weight.kg) + ' кг';
      updateTextUi(r);
      save();
    });
  }

  function updateText() { update(); }

  function updateTextUi(r) {
    var input = $('#txt');
    if (!input) return;
    var len = G.textLength(r.text), max = r.m.max_chars, cap = r.cap, cnt = $('#txtCount'), txt;
    if (r.errorType === 'max') {
      txt = 'С ' + (len - max) + ' знака над лимита от ' + max;
    } else if (r.errorType === 'small') {
      txt = 'Твърде дълъг за този шрифт';
    } else if (cap && cap.remaining >= 0) {
      if (cap.limitedBy === 'model') txt = 'Остават ' + cap.remaining + ' от ' + max + ' знака';
      else txt = (len ? 'Остават' : 'Побират се') + ' около ' + cap.remaining + ' ' + (cap.remaining === 1 ? 'знак' : 'знака') + ' с този шрифт';
    } else {
      txt = len ? 'Не остават свободни знаци с този шрифт' : '';
    }
    cnt.textContent = txt;
    cnt.className = r.errorType ? 'over' : '';
    var nb = $('#narrowBtn');
    nb.hidden = !r.betterFont;
    if (r.betterFont) {
      nb.setAttribute('data-font', r.betterFont.code);
      nb.textContent = 'Пробвай с по-тесния шрифт (' + r.betterFont.name + ', ≈ ' + Math.round(r.betterFont.mm) + ' мм)';
    }
    $('#txtLetter').textContent = r.fit ? 'Височина на буквите ≈ ' + Math.round(r.fit.letter_mm) + ' мм' : '';
    var err = $('#txtErr');
    err.hidden = !r.error;
    err.textContent = r.error;
    input.classList.toggle('invalid', !!r.error);
    input.setAttribute('aria-invalid', r.error ? 'true' : 'false');
    var sendBtn = $('#sendBtn');
    if (sendBtn) sendBtn.disabled = !!r.error || sending;
    var formErr = $('#textBlock');
    if (formErr) formErr.hidden = !r.error;
    // образците на шрифтовете показват надписа на клиента
    $$('.font-sample').forEach(function (s) {
      var t = r.text ? G.chars(r.text).slice(0, 8).join('') : 'Огън';
      if (s.textContent !== t) s.textContent = t;
    });
  }

  /* ---------- събития ---------- */
  stepsEl.addEventListener('change', function (e) {
    var t = e.target, name = t.name;
    if (name === 'type') {
      state.type = t.value;
      var first = modelsOfType(state.type)[0];
      if (first) state.model = first.code;
      normalizeState(); build(); track('model_select');
    } else if (name === 'model') {
      state.model = t.value;
      normalizeState(); build(); track('model_select');
    } else if (name === 'extras') {
      if (t.checked) { if (state.extras.indexOf(t.value) < 0) state.extras.push(t.value); }
      else state.extras = state.extras.filter(function (c) { return c !== t.value; });
      update();
    } else if (groupsByCode[name]) {
      state.opts[name] = t.value;
      update();
    }
  });

  stepsEl.addEventListener('click', function (e) {
    var b = e.target.closest('#narrowBtn');
    if (!b || b.hidden) return;
    var code = b.getAttribute('data-font'), radio = stepsEl.querySelector('input[name="font"][value="' + code + '"]');
    if (radio) { radio.checked = true; state.opts.font = code; update(); }
  });

  var textTimer = 0;
  stepsEl.addEventListener('input', function (e) {
    if (e.target.id !== 'txt') return;
    state.text = e.target.value;
    update();
    clearTimeout(textTimer);
    if (!tracked.text && G.normalizeText(state.text)) {
      textTimer = setTimeout(function () {
        if (!last || last.error) return;
        tracked.text = true;
        track('text_entered');
      }, 1500);
    }
  });

  /* ---------- проследяване ---------- */
  function track(type) {
    var m = model();
    try {
      var fd = new FormData();
      fd.append('type', type);
      fd.append('model', m ? m.id : '');
      fd.append('csrf', CFG.csrf);
      if (navigator.sendBeacon) navigator.sendBeacon(CFG.trackUrl, fd);
      else fetch(CFG.trackUrl, { method: 'POST', body: fd, keepalive: true });
    } catch (err) { /* без значение за потребителя */ }
    if (window.fpEvent) {
      var map = { model_select: ['select_model', { model: m && m.code }], text_entered: ['add_inscription', { model: m && m.code }] };
      if (map[type]) window.fpEvent(map[type][0], map[type][1]);
    }
  }

  /* ---------- запазване на избора и полетата в браузъра (localStorage, до 30 дни) ---------- */
  var STORE_CFG = 'fp_cfg_v1', STORE_FORM = 'fp_form_v1', STORE_TTL = 30 * 864e5;
  function storeGet(key) {
    try {
      var v = JSON.parse(localStorage.getItem(key) || 'null');
      if (v && v.ts && Date.now() - v.ts < STORE_TTL) return v.data;
      if (v) localStorage.removeItem(key);
    } catch (e) { /* няма достъп до localStorage – работим без запазване */ }
    return null;
  }
  function storeSet(key, data) {
    try { localStorage.setItem(key, JSON.stringify({ ts: Date.now(), data: data })); } catch (e) { /* */ }
  }
  function storeClear() {
    try { localStorage.removeItem(STORE_CFG); localStorage.removeItem(STORE_FORM); } catch (e) { /* */ }
  }
  function save() { storeSet(STORE_CFG, state); }
  function restore() {
    try {
      var s = storeGet(STORE_CFG);
      if (s && CAT.models.some(function (m) { return m.code === s.model; })) {
        state.model = s.model;
        state.opts = s.opts || {};
        state.extras = Array.isArray(s.extras) ? s.extras : [];
        state.text = typeof s.text === 'string' ? s.text : '';
        state.qty = Math.max(1, Math.min(50, parseInt(s.qty, 10) || 1));
        return true;
      }
    } catch (e) { /* няма достъп */ }
    return false;
  }

  /* ---------- запитване ---------- */
  var form = $('#inqForm');
  var qtyInput = $('#qty');
  var sending = false; // бутонът стои заключен, докато тече заявката

  $$('[data-qty]').forEach(function (b) {
    b.addEventListener('click', function () {
      state.qty = Math.max(1, Math.min(50, state.qty + parseInt(b.getAttribute('data-qty'), 10)));
      qtyInput.value = state.qty;
      update();
    });
  });
  qtyInput.addEventListener('input', function () {
    var v = parseInt(qtyInput.value, 10);
    if (v >= 1 && v <= 50) { state.qty = v; update(); }
  });

  function fieldError(name, msg) {
    var el = form.querySelector('[data-err="' + name + '"]');
    var input = form.querySelector('[name="' + name + '"]');
    if (el) { el.textContent = msg || ''; el.hidden = !msg; }
    if (input) { input.classList.toggle('invalid', !!msg); input.setAttribute('aria-invalid', msg ? 'true' : 'false'); }
  }

  function validateForm() {
    var errs = {}, v = function (n) { return (form.elements[n].value || '').trim(); };
    if (v('name').length < 2) errs.name = 'Въведете име.';
    var digits = v('phone').replace(/\D/g, '');
    if (!v('phone')) errs.phone = 'Въведете телефон.';
    else if (!/^[\d\s+()\-\/]+$/.test(v('phone')) || digits.length < 8 || digits.length > 15) errs.phone = 'Проверете телефона – напр. 0888 123 456.';
    if (v('email') && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v('email'))) errs.email = 'Невалиден имейл.';
    if (!form.elements.consent.checked) errs.consent = 'Нужно е съгласие, за да се свържем с вас.';
    ['name', 'phone', 'email', 'consent'].forEach(function (n) { fieldError(n, errs[n]); });
    return errs;
  }

  var fontCache = {};
  function fontCss(key) {
    if (fontCache[key]) return Promise.resolve(fontCache[key]);
    return fetch(CFG.fontsUrl + key + '.woff2').then(function (r) { return r.arrayBuffer(); }).then(function (buf) {
      var bin = '', bytes = new Uint8Array(buf);
      for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
      fontCache[key] = "@font-face{font-family:'fp-" + key + "';src:url(data:font/woff2;base64," + btoa(bin) + ") format('woff2')}";
      return fontCache[key];
    }).catch(function () { return ''; });
  }

  function src() {
    try { return JSON.parse(localStorage.getItem('fp_src') || '{}'); } catch (e) { return {}; }
  }

  function postJson(url, body) {
    return fetch(url, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin'
    }).then(function (res) { return res.json().catch(function () { return { ok: false, message: 'Сървърът върна неочакван отговор.' }; }); });
  }

  /**
   * Изпраща запитването. Ако сесията е изтекла (code=csrf), взима нов токен
   * и изпраща същото запитване още веднъж – клиентът не вижда грешка и не губи нищо.
   */
  function sendInquiry(payload, retried) {
    return postJson(CFG.inquiryUrl, payload).then(function (data) {
      if (data && data.code === 'csrf' && !retried) {
        return postJson(CFG.tokenUrl, {}).then(function (t) {
          if (!t || !t.csrf) return data;
          CFG.csrf = t.csrf;
          payload.csrf = t.csrf;
          return sendInquiry(payload, true);
        });
      }
      return data;
    });
  }

  /* попълнените полета се пазят в браузъра (без отметката за съгласие – тя се дава наново) */
  var FORM_FIELDS = ['name', 'phone', 'email', 'city', 'note'];
  var formTimer = 0;
  form.addEventListener('input', function (e) {
    if (FORM_FIELDS.indexOf(e.target.name) < 0) return;
    clearTimeout(formTimer);
    formTimer = setTimeout(function () {
      var d = {};
      FORM_FIELDS.forEach(function (n) { d[n] = form.elements[n].value.slice(0, 2000); });
      storeSet(STORE_FORM, d);
    }, 300);
  });
  function restoreForm() {
    var d = storeGet(STORE_FORM);
    if (!d) return false;
    FORM_FIELDS.forEach(function (n) { if (typeof d[n] === 'string' && !form.elements[n].value) form.elements[n].value = d[n]; });
    return true;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (sending) return;
    var r = compute();
    var errs = validateForm();
    if (r.error) {
      scrollToStep($('#st-text'));
      return;
    }
    if (Object.keys(errs).length) {
      var first = form.querySelector('.invalid');
      if (first) first.focus();
      return;
    }
    var btn = $('#sendBtn'), msg = $('#formMsg');
    sending = true;
    btn.disabled = true;
    btn.classList.add('loading');
    btn.setAttribute('aria-busy', 'true');
    msg.hidden = true;
    function unlock() {
      sending = false;
      btn.classList.remove('loading');
      btn.removeAttribute('aria-busy');
      btn.disabled = !!compute().error;
    }

    fontCss(r.fontKey).then(function (css) {
      var svg = window.FPRender.render(renderOpts(r, { id: 'sn', fontCss: r.text ? css : '' }));
      var payload = {
        csrf: CFG.csrf,
        t: CFG.t,
        website: form.elements.website.value,
        config: { model: state.model, options: Object.assign({}, state.opts, { extras: state.extras }), text: state.text, quantity: state.qty },
        contact: {
          name: form.elements.name.value, phone: form.elements.phone.value, email: form.elements.email.value,
          city: form.elements.city.value, note: form.elements.note.value, consent: form.elements.consent.checked
        },
        svg: svg,
        src: src()
      };
      return sendInquiry(payload, false);
    }).then(function (data) {
      if (data && data.ok) {
        storeClear();
        window.location.href = data.redirect;
        return;
      }
      unlock();
      var errsS = (data && data.errors) || {};
      Object.keys(errsS).forEach(function (k) {
        if (k.indexOf('contact.') === 0) fieldError(k.slice(8), errsS[k]);
      });
      if (errsS.text) {
        var te = $('#txtErr'); te.textContent = errsS.text; te.hidden = false;
      }
      msg.textContent = (data && data.message) || 'Запитването не беше изпратено. Проверете полетата и опитайте отново.';
      msg.hidden = false;
    }).catch(function () {
      unlock();
      msg.textContent = 'Няма връзка със сървъра. Проверете интернета и опитайте отново.';
      msg.hidden = false;
    });
  });

  /* ---------- долна лента на телефон ---------- */
  var bar = $('#bar'), finalStep = $('#st-send'), stage = $('.cfg-stage');
  function syncBar() {
    bar.classList.toggle('hide', finalStep.getBoundingClientRect().top < window.innerHeight * 0.75);
  }
  window.addEventListener('scroll', syncBar, { passive: true });
  window.addEventListener('resize', syncBar);
  syncBar();
  $('#barBtn').addEventListener('click', function () { scrollToStep(finalStep); });

  /** Скрол до стъпка, без заглавието да остане под залепения преглед */
  function scrollToStep(el) {
    var offset = getComputedStyle(stage).position === 'sticky' && window.innerWidth < 900 ? stage.offsetHeight : 0;
    window.scrollTo({ top: el.getBoundingClientRect().top + window.pageYOffset - offset - 8, behavior: 'smooth' });
  }

  /* ---------- старт ---------- */
  var restored = restore();
  if (!restored) {
    var first = CAT.models[0];
    state.model = first.code;
  }
  // ?model=код (напр. от галерията на началната страница) има предимство
  var qModel = new URLSearchParams(location.search).get('model');
  if (qModel && CAT.models.some(function (m) { return m.code === qModel; }) && qModel !== state.model) {
    state.model = qModel;
    state.opts = {};
    state.extras = [];
  }
  // параметърът важи само веднъж: при презареждане да не изтрива избора на клиента
  if (qModel && window.history && history.replaceState) {
    var u = new URL(location.href);
    u.searchParams.delete('model');
    history.replaceState(null, '', u.pathname + u.search + u.hash);
  }
  state.type = model().type;
  normalizeState();
  qtyInput.value = state.qty;
  restoreForm();
  build();
  // прерисуване, когато шрифтовете се заредят
  if (document.fonts && document.fonts.load) {
    Promise.all(['russo', 'oswald', 'dela'].map(function (k) { return document.fonts.load("40px 'fp-" + k + "'", 'АБВ'); }))
      .then(function () { update(); }, function () { /* */ });
  }
  if (window.fpEvent) window.fpEvent('configurator_open', {});
})();
