/* Геометрия, побиране на надписа, цена и тегло.
 * ВАЖНО: огледало на inc/geometry.php и inc/pricing.php – при промяна сменете и двете.
 */
(function (global) {
  'use strict';

  function normalizeText(t) {
    return String(t || '').replace(/\s+/g, ' ').trim();
  }

  function chars(t) {
    return Array.from(t);
  }

  function textLength(t) {
    return chars(t).length;
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

  /** Побира надписа в най-тясната декорирана стена (същото като text_fit() в PHP) */
  function textFit(text, fontKey, walls, hasOrnament, fonts, L) {
    var font = fonts.fonts[fontKey];
    if (!text || !font || !walls.length) return null;
    var minW = Math.min.apply(null, walls.map(function (w) { return w[0]; }));
    var wallH = walls[0][1];
    var sumAdv = 0, yMax = 0, yMin = 0, hasLower = false, list = chars(text);
    for (var i = 0; i < list.length; i++) {
      var ch = list[i], g = font.g[ch];
      if (!g) return null;
      sumAdv += g[0];
      yMax = Math.max(yMax, g[2]);
      yMin = Math.min(yMin, g[1]);
      if (ch.toUpperCase() !== ch) hasLower = true;
    }
    var upm = font.upm;
    var margin = Math.max(L.margin_min_mm, L.margin_ratio * minW);
    var availW = minW - 2 * margin;
    var band = hasOrnament ? L.band_orn_text : L.band_plain;
    var bandH = (band[1] - band[0]) * wallH;
    var size = Math.min(availW / (sumAdv / upm), bandH / (Math.max(1, yMax - yMin) / upm));
    var k = size / upm;
    return {
      size_mm: size,
      width_mm: sumAdv * k,
      letter_mm: Math.round(((hasLower ? font.xh : font.cap) * k) * 10) / 10,
      cap_mm: font.cap * k,
      has_lower: hasLower,
      y_max: yMax * k,
      y_min: yMin * k,
      band: band
    };
  }

  /* Средна ширина на буква (главни или малки български букви) – за прогноза на оставащите знаци */
  var avgCache = {};
  function avgAdvance(font, fontKey, lower) {
    var key = fontKey + (lower ? 'l' : 'u');
    if (avgCache[key]) return avgCache[key];
    var up = 'АБВГДЕЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЬЮЯ', set = lower ? up.toLowerCase() : up, sum = 0, n = 0;
    chars(set).forEach(function (c) { if (font.g[c]) { sum += font.g[c][0]; n++; } });
    return (avgCache[key] = n ? sum / n : font.upm * 0.6);
  }

  /**
   * Колко още знака се побират, преди буквите да паднат под минималната височина.
   * Прогноза по средната ширина на буква за избрания шрифт; ограничава се и от max_chars на модела.
   */
  function textCapacity(text, fontKey, walls, fonts, L, minMm, maxChars) {
    var font = fonts.fonts[fontKey];
    if (!font || !walls.length) return null;
    var list = chars(text), sumAdv = 0, hasLower = false;
    list.forEach(function (ch) {
      var g = font.g[ch];
      sumAdv += g ? g[0] : font.upm * 0.6;
      if (ch.toUpperCase() !== ch) hasLower = true;
    });
    var minW = Math.min.apply(null, walls.map(function (w) { return w[0]; }));
    var availW = minW - 2 * Math.max(L.margin_min_mm, L.margin_ratio * minW);
    var ref = hasLower ? font.xh : font.cap;
    var maxUnits = availW * ref / minMm;            // обща ширина (в единици на шрифта) при минималната височина
    var byHeight = Math.floor((maxUnits - sumAdv) / avgAdvance(font, fontKey, hasLower));
    var byModel = maxChars - list.length;
    return { remaining: Math.min(byHeight, byModel), byHeight: byHeight, byModel: byModel, limitedBy: byHeight <= byModel ? 'height' : 'model' };
  }

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
    normalizeText: normalizeText,
    textLength: textLength,
    chars: chars,
    modelWalls: modelWalls,
    decoratedWalls: decoratedWalls,
    textFit: textFit,
    textCapacity: textCapacity,
    sheetArea: sheetArea,
    calcPrice: calcPrice,
    calcWeight: calcWeight
  };
})(window);
