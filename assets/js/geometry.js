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
  function textBand(wallH, hasOrn, L) {
    var b = hasOrn ? L.band_orn_text : L.band_plain;
    var top = Math.max(b[0] * wallH, L.edge_mm), bottom = Math.min(b[1] * wallH, wallH - L.edge_mm);
    return [top, Math.max(top + 1, bottom)];
  }

  /** Побира 1–2 реда в най-тясната декорирана стена (същото като text_fit() в PHP) */
  function textFit(lines, fontKey, walls, hasOrn, fonts, L) {
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
    var band = textBand(wallH, hasOrn, L);
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
  function checkText(lines, fontKey, walls, hasOrn, minMm, fonts, L) {
    var text = lines.join('\n');
    if (textLength(text) > L.max_chars) return { kind: 'long', error: 'Надписът е твърде дълъг – до ' + L.max_chars + ' знака.', fit: null, split: null };
    var bad = [];
    chars(text.replace(/\n/g, '')).forEach(function (ch) { if (fonts.allowed.indexOf(ch) < 0 && bad.indexOf(ch) < 0) bad.push(ch); });
    if (bad.length) return { kind: 'chars', error: 'Тези знаци не могат да се изрежат: ' + bad.join(' '), fit: null, split: null };
    var fit = textFit(lines, fontKey, walls, hasOrn, fonts, L);
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

  global.FPGeo = {
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
