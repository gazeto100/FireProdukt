/* Геометрия, побиране на надписа, цена и тегло.
 * ВАЖНО: огледало на inc/geometry.php, inc/validate.php (text_check) и inc/pricing.php – при промяна сменете и двете.
 */
(function (global) {
  'use strict';

  function chars(t) { return Array.from(t); }

  /** До 2 реда, всеки без излишни интервали (като text_lines() в PHP) */
  function textLines(t, L) {
    var max = (L && L.max_lines) || 2;
    var lines = String(t || '').replace(/\r\n?/g, '\n').split('\n').map(function (ln) {
      return ln.replace(/[ \t ]+/g, ' ').trim();
    }).filter(function (ln) { return ln !== ''; });
    if (lines.length > max) lines = [lines[0], lines.slice(1).join(' ')];
    return lines;
  }
  function normalizeText(t, L) { return textLines(t, L).join('\n'); }
  function textLength(t) { return chars(String(t).replace(/\n/g, '')).length; }

  /** Разделя ред на два при интервала, най-близък до средата (по брой знаци); null ако няма интервал */
  function splitTwoLines(line) {
    var c = chars(line), mid = c.length / 2, best = -1;
    c.forEach(function (ch, i) { if (ch === ' ' && (best < 0 || Math.abs(i - mid) < Math.abs(best - mid))) best = i; });
    if (best < 0) return null;
    return [c.slice(0, best).join('').trim(), c.slice(best + 1).join('').trim()];
  }

  function minLetterFor(thickness, S) {
    return thickness <= 2.5 ? S.min_letter_2mm : S.min_letter_3mm;
  }

  /** Стени в мм: [ширина, височина] – отпред, дясно, отзад, ляво */
  function modelWalls(m) {
    var h = m.h * 10;
    if (m.shape === 'hex') {
      var s = m.w / 2 * 10;
      return [0, 1, 2, 3, 4, 5].map(function () { return [s, h]; });
    }
    if (m.shape === 'octagon') {
      var so = m.w * 10 * Math.sin(Math.PI / 8);
      return [0, 1, 2, 3, 4, 5, 6, 7].map(function () { return [so, h]; });
    }
    if (m.shape === 'cross') return [[m.w * 10 / 2, h]];
    if (m.shape === 'ringbowl') return [[m.w * 10 * 0.72 * 0.62, h * 0.55]];
    var w = m.w * 10, d = m.d * 10;
    return [[w, h], [d, h], [w, h], [d, h]];
  }

  function decoratedWalls(m, sides) {
    var walls = modelWalls(m);
    if (sides === 'all') return walls;
    var n = Math.max(1, Math.min(walls.length, parseInt(sides, 10) || 1));
    return walls.slice(0, n);
  }

  /** Вертикална зона за надписа с поле от ръба */
  function textBand(wallH, hasOrn, L, shape) {
    var b = (L.shape_bands && L.shape_bands[shape]) || (hasOrn ? L.band_orn_text : L.band_plain);
    var top = Math.max(b[0] * wallH, L.edge_mm), bottom = Math.min(b[1] * wallH, wallH - L.edge_mm);
    return [top, Math.max(top + 1, bottom)];
  }

  /** Побира 1–2 реда в най-тясната декорирана стена (същото като text_fit() в PHP) */
  function textFit(lines, fontKey, walls, hasOrn, fonts, L, shape) {
    var font = fonts.fonts[fontKey];
    if (typeof lines === 'string') lines = textLines(lines, L);
    if (!lines.length || !font || !walls.length) return null;
    var minW = Math.min.apply(null, walls.map(function (w) { return w[0]; }));
    var wallH = walls[0][1], adv = [], yMax = 0, yMin = 0, hasLower = false;
    for (var i = 0; i < lines.length; i++) {
      adv[i] = 0;
      var list = chars(lines[i]);
      for (var j = 0; j < list.length; j++) {
        var ch = list[j], g = font.g[ch];
        if (!g) return null;
        adv[i] += g[0];
        yMax = Math.max(yMax, g[2]);
        yMin = Math.min(yMin, g[1]);
        if (ch.toUpperCase() !== ch) hasLower = true;
      }
    }
    var upm = font.upm, n = lines.length;
    var availW = minW - 2 * L.edge_mm;
    var band = textBand(wallH, hasOrn, L, shape);
    var lineH = Math.max(1, yMax - yMin), gap = L.line_gap * font.cap;
    var totalH = n * lineH + (n - 1) * gap;
    var size = Math.min(availW / (Math.max.apply(null, adv) / upm), (band[1] - band[0]) / (totalH / upm));
    var k = size / upm;
    return {
      size_mm: size,
      widths_mm: adv.map(function (a) { return a * k; }),
      letter_mm: Math.round(((hasLower ? font.xh : font.cap) * k) * 10) / 10,
      cap_mm: font.cap * k,
      xh_mm: font.xh * k,
      has_lower: hasLower,
      lines: lines.slice(),
      y_max: yMax * k,
      y_min: yMin * k,
      line_h: lineH * k,
      gap: gap * k,
      band: band
    };
  }

  /**
   * Единственото правило, което спира надписа: минималната височина (плюс технически таван и позволени знаци).
   * Връща { error, kind: 'ok'|'small'|'chars'|'long', fit, split }
   */
  function checkText(lines, fontKey, walls, hasOrn, minMm, fonts, L, shape) {
    var text = lines.join('\n');
    if (textLength(text) > L.max_chars) return { kind: 'long', error: 'Надписът е твърде дълъг – до ' + L.max_chars + ' знака.', fit: null, split: null };
    var bad = [];
    chars(text.replace(/\n/g, '')).forEach(function (ch) { if (fonts.allowed.indexOf(ch) < 0 && bad.indexOf(ch) < 0) bad.push(ch); });
    if (bad.length) return { kind: 'chars', error: 'Тези знаци не могат да се изрежат: ' + bad.join(' '), fit: null, split: null };
    var fit = textFit(lines, fontKey, walls, hasOrn, fonts, L, shape);
    if (!fit) return { kind: 'chars', error: 'Надписът не може да бъде разположен.', fit: null, split: null };
    if (fit.letter_mm + 0.05 >= minMm) return { kind: 'ok', error: null, fit: fit, split: null };
    var split = lines.length === 1 ? splitTwoLines(lines[0]) : null;
    var err = 'Буквите стават ' + Math.floor(fit.letter_mm) + ' мм, а минимумът е ' + fmt(minMm) + ' мм. ' +
      (split ? 'Съкрати надписа, избери по-тесен шрифт или го раздели на два реда.' : 'Съкрати надписа или избери по-тесен шрифт.');
    return { kind: 'small', error: err, fit: fit, split: split };
  }

  function fmt(n) { return String(Math.round(n * 10) / 10).replace('.', ','); }

  function sheetArea(m) {
    if (m.sheet_area_m2) return m.sheet_area_m2;
    var w = m.w, d = m.d, h = m.h;
    if (m.shape === 'hex') {
      var s = w / 2;
      return (3 * Math.sqrt(3) / 2 * s * s + 6 * s * h) / 10000;
    }
    var r = w / 2;
    if (m.shape === 'octagon') return (2 * Math.sqrt(2) * r * r + 8 * w * Math.sin(Math.PI / 8) * h) / 10000;
    if (m.shape === 'trapezoid') { var t = 0.22 * h; return (w * w + 4 * (w + t) * Math.sqrt(h * h + t * t)) / 10000; }
    if (m.shape === 'pedestal') { var rp = 0.45 * w; return (4 * w * h + w * d + Math.PI * rp * rp * 1.25) / 10000; }
    if (m.shape === 'cross') return 2 * w * h / 10000;
    if (m.shape === 'ringbowl') return (Math.PI * r * r * 1.3 + Math.PI * 0.72 * w * 0.55 * h) / 10000;
    return (w * d + 2 * (w + d) * h) / 10000;
  }

  /** sel: [{opt, mo}] */
  function calcPrice(model, sel, decoratedCount, isDecorated, qty) {
    if (model.price_min === undefined) return null; // цените са изключени
    var fMin = 1, fMax = 1, aMin = 0, aMax = 0;
    sel.forEach(function (s) {
      var o = s.opt, pmin = s.mo.price_min || 0, pmax = s.mo.price_max || 0;
      if (o.price_type === 'factor') { fMin *= pmin || 1; fMax *= pmax || 1; return; }
      if (o.group === 'sides' && !isDecorated) return;
      var mult = o.render && o.render.per_side ? decoratedCount : 1;
      aMin += pmin * mult; aMax += pmax * mult;
    });
    // единичната цена се закръгля до 5, после се умножава по количеството
    var uMin = Math.floor((model.price_min * fMin + aMin) / 5) * 5;
    var uMax = Math.ceil((model.price_max * fMax + aMax) / 5) * 5;
    return { unit_min: uMin, unit_max: uMax, min: uMin * qty, max: uMax * qty };
  }

  function calcWeight(model, sel) {
    var t = 2, dens = 7.93, extra = 0;
    sel.forEach(function (s) {
      var o = s.opt;
      if (o.group === 'material') {
        t = parseFloat(o.render.thickness_mm) || t;
        dens = parseFloat(o.render.density) || dens;
      }
      extra += o.weight_kg || 0;
    });
    var area = sheetArea(model);
    return { kg: Math.round((area * t * dens + extra) * 10) / 10, area_m2: area };
  }

  /* ---------- Размер по избор (огледало на custom_dims_check / sheet_fit / calc_price_custom в PHP) ---------- */

  /**
   * Връща стойността към най-близката допустима (стъпка, мин./макс., отношение височина/ширина).
   * lim = form.custom; dims = {w, d, h}. Връща {dims, messages}.
   */
  function clampCustom(lim, dims) {
    var out = { w: dims.w, d: dims.d, h: dims.h }, msgs = [], step = lim.step;
    var label = { w: lim.mode === 'dh' ? 'Диаметърът' : 'Ширината', d: 'Дълбочината', h: 'Височината' };
    var keys = lim.mode === 'wdh' ? ['w', 'd', 'h'] : ['w', 'h'];
    keys.forEach(function (k) {
      var v = parseFloat(String(out[k]).replace(',', '.'));
      if (isNaN(v)) v = lim[k + '_min'];
      var r = lim[k + '_min'] + Math.round((v - lim[k + '_min']) / step) * step;
      if (r < lim[k + '_min']) { r = lim[k + '_min']; msgs.push(label[k] + ' е върната до минимума ' + fmt(r) + ' см.'); }
      else if (r > lim[k + '_max']) { r = lim[k + '_max']; msgs.push(label[k] + ' е върната до максимума ' + fmt(r) + ' см.'); }
      else if (Math.abs(r - v) > 0.01) { msgs.push(label[k] + ' е закръглена до ' + fmt(r) + ' см (стъпка ' + fmt(step) + ' см).'); }
      out[k] = Math.round(r * 10) / 10;
    });
    if (lim.mode !== 'wdh') out.d = out.w;
    var hMax = Math.floor((lim.max_h_ratio * out.w - lim.h_min) / step + 1e-9) * step + lim.h_min;
    if (out.h > hMax + 0.01) {
      out.h = Math.max(lim.h_min, hMax);
      msgs.push('Височината е намалена до ' + fmt(out.h) + ' см – може да е най-много ' + fmt(lim.max_h_ratio) + ' от ширината.');
    }
    return { dims: out, messages: msgs };
  }

  /** Побиране на плоските детайли в листа; S = settings (sheet_w_mm, sheet_h_mm). Връща {fits, part} */
  function sheetFit(m, S) {
    var L = Math.max(S.sheet_w_mm, S.sheet_h_mm), Sh = Math.min(S.sheet_w_mm, S.sheet_h_mm);
    var fits = function (a, b) { return (a <= L && b <= Sh) || (a <= Sh && b <= L); };
    var w = m.w * 10, d = m.d * 10, h = m.h * 10, mm = function (v) { return Math.round(v); };
    if (m.shape === 'hex' || m.shape === 'octagon') {
      var side = m.shape === 'hex' ? w / 2 : w * Math.sin(Math.PI / 8);
      if (!fits(side, h)) return { fits: false, part: 'стена ' + mm(side) + '×' + mm(h) + ' мм' };
      if (!fits(w, w)) return { fits: false, part: 'дъно Ø' + mm(w) + ' мм' };
      return { fits: true, part: '' };
    }
    if (m.shape === 'cross') return fits(w, h) ? { fits: true, part: '' } : { fits: false, part: 'плоча ' + mm(w) + '×' + mm(h) + ' мм' };
    if (m.shape === 'ringbowl') {
      var circ = Math.PI * 0.72 * w;
      if (circ > L) return { fits: false, part: 'пръстен с обиколка ' + mm(circ) + ' мм' };
      if (w > Sh) return { fits: false, part: 'купа Ø' + mm(w) + ' мм' };
      return { fits: true, part: '' };
    }
    var wallW = m.shape === 'trapezoid' ? w + 2 * 0.22 * h : w, parts = [[wallW, h, 'стена'], [d, h, 'стена'], [w, d, 'дъно']];
    for (var i = 0; i < parts.length; i++) {
      if (!fits(parts[i][0], parts[i][1])) return { fits: false, part: parts[i][2] + ' ' + mm(parts[i][0]) + '×' + mm(parts[i][1]) + ' мм' };
    }
    if (w + 2 * h > Sh || d > L) return { fits: false, part: 'разгъната заготовка ' + mm(w + 2 * h) + ' мм (листът е ' + mm(Sh) + ' мм)' };
    return { fits: true, part: '' };
  }

  /** (площ × цена на m² за материала + фиксирана сума) × (1 + надценка %) + опциите; закръгляне до 5, после × брой */
  function calcPriceCustom(model, lim, sel, decoratedCount, isDecorated, qty, S) {
    if (lim.fixed === undefined || S.custom_surcharge_pct === undefined) return null; // цените са изключени
    var area = sheetArea(Object.assign({}, model, { sheet_area_m2: null })), m2min = 0, m2max = 0, aMin = 0, aMax = 0;
    sel.forEach(function (s) {
      var o = s.opt;
      if (o.group === 'material') { m2min = o.price_m2_min || 0; m2max = o.price_m2_max || 0; return; }
      if (o.price_type === 'factor') return;
      if (o.group === 'sides' && !isDecorated) return;
      var mult = o.render && o.render.per_side ? decoratedCount : 1;
      aMin += (s.mo.price_min || 0) * mult; aMax += (s.mo.price_max || 0) * mult;
    });
    var k = 1 + S.custom_surcharge_pct / 100;
    var uMin = Math.floor(((area * m2min + lim.fixed) * k + aMin) / 5) * 5;
    var uMax = Math.ceil(((area * m2max + lim.fixed) * k + aMax) / 5) * 5;
    return { unit_min: uMin, unit_max: uMax, min: uMin * qty, max: uMax * qty };
  }

  global.FPGeo = {
    clampCustom: clampCustom,
    sheetFit: sheetFit,
    calcPriceCustom: calcPriceCustom,
    chars: chars,
    textLines: textLines,
    normalizeText: normalizeText,
    textLength: textLength,
    splitTwoLines: splitTwoLines,
    minLetterFor: minLetterFor,
    modelWalls: modelWalls,
    decoratedWalls: decoratedWalls,
    textBand: textBand,
    textFit: textFit,
    checkText: checkText,
    sheetArea: sheetArea,
    calcPrice: calcPrice,
    calcWeight: calcWeight
  };
})(window);
