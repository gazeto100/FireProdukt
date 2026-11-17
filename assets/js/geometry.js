/* Геометрия, побиране на надписа, цена и тегло.
 * ВАЖНО: огледало на inc/geometry.php, inc/validate.php (text_check) и inc/pricing.php – при промяна сменете и двете.
 */
(function (global) {
  'use strict';

  function chars(t) { return Array.from(t); }

  /** Кръгли форми (Ø × височина) и пропорции на новите форми – огледало на inc/geometry.php */
  var ROUND_SHAPES = ['hex', 'octagon', 'ringbowl', 'plancha', 'bowl', 'ringbench'];
  var PROP = { planchaBase: 0.62, planchaHole: 0.45, planchaPanelH: 0.85, planchaChord: 0.7, bbqLid: 0.6, crossTray: 0.3, crossStand: 0.8 };
  /** Огнище на кръстата стойка – детайли в мм (огледало на cross_parts() в PHP) */
  function crossParts(W, H) {
    var Ht = Math.min(PROP.crossTray * W, 0.6 * H), t = Ht / 2, B = W - 2 * t;
    var Ws = Math.min(0.92 * W, Math.max(PROP.crossStand * W, B + 0.06 * W)), over = Math.max(0, Ws / 2 - B / 2);
    var rise = t > 0 ? over * Ht / t : 0, Hb = H - Ht, slant = Math.sqrt(Ht * Ht + t * t);
    return { Ht: Ht, t: t, B: B, Ws: Ws, Hb: Hb, rise: rise, plateH: Hb + rise, slant: slant,
      tray_mm2: B * B + 4 * (W + B) / 2 * slant, plates_mm2: 2 * (Ws * Hb + over * rise) };
  }
  /** Форми за обекти – детайли в мм (огледало на obj_parts() в PHP) */
  var OBJ_CHORD = 0.62, BENCH_SEAT = 330;
  function objParts(shape, W, D, H) {
    if (shape === 'bowl') {
      var R = W / 2, Hb = Math.min(0.6 * R, 0.6 * H), Hc = 0.45 * Hb;
      return { R: R, Hb: Hb, Hp: H - Hb, Hc: Hc, chord: W * OBJ_CHORD,
        mm2: Math.PI * R * R * 1.15 + Math.PI * W * Hc + 2 * Math.PI * 0.2 * R * (H - Hb) + Math.PI * Math.pow(0.42 * R, 2) };
    }
    if (shape === 'ringbench') {
      var Ro = W / 2, Ri = Math.max(0.5 * Ro, Ro - BENCH_SEAT), Rp = Math.min(430, 0.45 * Ri);
      return { Ro: Ro, Ri: Ri, Rp: Rp, Hb: H, Hpit: 1.4 * H, chord: 2 * Rp * OBJ_CHORD,
        mm2: 2 * Math.PI * (Ro + Ri) * H + 2 * Math.PI * Rp * 1.4 * H + Math.PI * Rp * Rp };
    }
    if (shape === 'firetable') {
      var r = 0.36 * Math.min(W, D);
      return { r: r, mm2: 2 * (W + D) * H + W * D - Math.PI * r * r + Math.PI * r * r * 1.3 };
    }
    if (shape === 'column') return { mm2: 4 * W * H + 2 * W * W };
    return {};
  }
  /** Огнище с пейка-пръстен: брой сегменти на пейката (огледало на bench_segments()) */
  function benchSegments(m, S) {
    if (m.shape !== 'ringbench') return 0;
    return Math.max(1, Math.ceil(Math.PI * m.w * 10 / Math.max(S.sheet_w_mm, S.sheet_h_mm)));
  }
  function isRound(shape) { return ROUND_SHAPES.indexOf(shape) >= 0; }

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
    if (m.shape === 'cross') { var cp = crossParts(m.w * 10, h); return [0, 1, 2, 3].map(function () { return [cp.B, cp.Ht]; }); }
    if (m.shape === 'ringbowl') return [[m.w * 10 * 0.72 * 0.62, h * 0.55]];
    if (m.shape === 'bowl') { var bp = objParts('bowl', m.w * 10, m.d * 10, h); return [[bp.chord, bp.Hc]]; }
    if (m.shape === 'ringbench') { var rp = objParts('ringbench', m.w * 10, m.d * 10, h); return [[rp.chord, rp.Hpit]]; }
    if (m.shape === 'suitcase' || (m.shape === 'bbq' && m.text_place === 'lid')) return [[m.w * 10, m.d * 10]];
    if (m.shape === 'plancha') return [[m.w * 10 * PROP.planchaBase * PROP.planchaChord, h * PROP.planchaPanelH]];
    if (m.shape === 'plancha_sq') return [[m.w * 10 * PROP.planchaBase, h * PROP.planchaPanelH]];
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
    if (m.shape === 'cross') { var cq = crossParts(w * 10, h * 10); return (cq.tray_mm2 + cq.plates_mm2) / 1000000; }
    if (['bowl', 'ringbench', 'firetable', 'column'].indexOf(m.shape) >= 0) return objParts(m.shape, w * 10, d * 10, h * 10).mm2 / 1000000;
    if (m.shape === 'ringbowl') return (Math.PI * r * r * 1.3 + Math.PI * 0.72 * w * 0.55 * h) / 10000;
    if (m.shape === 'suitcase') return (w * d + 2 * (w + d) * h + w * d + 2 * (w + d) * 2 + 400) / 10000;
    if (m.shape === 'bbq') return (w * d + 2 * (w + d) * h + w * d + 2 * (w + d) * PROP.bbqLid * h + 2400) / 10000;
    var fb = Math.PI * PROP.planchaHole * w * 0.3 * h + Math.PI * Math.pow(PROP.planchaHole * w / 2, 2);
    if (m.shape === 'plancha') return (Math.PI * PROP.planchaBase * w * h + fb) / 10000;
    if (m.shape === 'plancha_sq') return (4 * PROP.planchaBase * w * h + fb) / 10000;
    if (m.shape === 'cheverme') return (w * d + 2 * (w + d) * h + 1560 + 360 + 4 * w) / 10000;
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

  /** Площ на плочата за печене (m²); 0 за формите без плоча */
  function plateArea(m) {
    var w = m.w, hole = Math.PI * Math.pow(PROP.planchaHole * w / 2, 2);
    if (m.shape === 'plancha') return (Math.PI * Math.pow(w / 2, 2) - hole) / 10000;
    if (m.shape === 'plancha_sq') return (w * w - hole) / 10000;
    return 0;
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
      // плоча за печене: теглото е от избраната дебелина (черна стомана 7,85 кг/дм³)
      if (o.render && o.render.plate_mm) extra += plateArea(model) * parseFloat(o.render.plate_mm) * 7.85;
    });
    var area = sheetArea(model);
    return { kg: Math.round((area * t * dens + extra) * 10) / 10, area_m2: area };
  }

  /* ---------- Размер по избор (огледало на custom_dims_check / sheet_fit / calc_price_custom в PHP) ---------- */

  /**
   * Връща стойността към най-близката допустима (стъпка, мин./макс., отношение височина/ширина).
   * lim = form.custom; dims = {w, d, h}. Връща {dims, messages}.
   */
  function clampCustom(lim, dims, shape) {
    var out = { w: dims.w, d: dims.d, h: dims.h }, msgs = [], step = lim.step;
    var label = { w: (shape ? isRound(shape) : lim.mode === 'dh') ? 'Диаметърът' : 'Ширината', d: 'Дълбочината', h: 'Височината' };
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

  /** Побиране на плоските детайли в листа; S = settings (sheet_w_mm, sheet_h_mm);
   *  construction: 'panels' (отделни панели, по подразбиране) или 'bent' (огънат корпус от едно парче). Връща {fits, part} */
  function sheetFit(m, S, construction) {
    var L = Math.max(S.sheet_w_mm, S.sheet_h_mm), Sh = Math.min(S.sheet_w_mm, S.sheet_h_mm);
    var fits = function (a, b) { return (a <= L && b <= Sh) || (a <= Sh && b <= L); };
    var w = m.w * 10, d = m.d * 10, h = m.h * 10, mm = function (v) { return Math.round(v); };
    var bent = construction === 'bent', parts = [];
    if (m.shape === 'ringbowl') {
      var circ = Math.PI * 0.72 * w;
      if (circ > L) return { fits: false, part: 'пръстен с обиколка ' + mm(circ) + ' мм (листът е ' + mm(L) + ' мм)' };
      parts.push([w, w, 'дъно на купата Ø' + mm(w) + ' мм']);
    } else if (m.shape === 'cross') {
      var cx = crossParts(w, h);
      parts.push([w, cx.slant, 'стена на тавата ' + mm(w) + '×' + mm(cx.slant) + ' мм']);
      parts.push([cx.B, cx.B, 'дъно на тавата ' + mm(cx.B) + '×' + mm(cx.B) + ' мм']);
      parts.push([cx.Ws, cx.plateH, 'плоча на стойката ' + mm(cx.Ws) + '×' + mm(cx.plateH) + ' мм']);
    } else if (m.shape === 'bowl') {
      parts.push([w, w, 'заготовка на купата Ø' + mm(w) + ' мм']);
    } else if (m.shape === 'ringbench') {
      var rb = objParts('ringbench', w, d, h), rpc = 2 * Math.PI * rb.Rp;
      if (rpc > L) return { fits: false, part: 'огнище с обиколка ' + mm(rpc) + ' мм (листът е ' + mm(L) + ' мм)' };
      parts.push([rpc, rb.Hpit, 'стена на огнището ' + mm(rpc) + '×' + mm(rb.Hpit) + ' мм']);
    } else if (m.shape === 'firetable') {
      parts.push([w, h, 'стена ' + mm(w) + '×' + mm(h) + ' мм']);
      parts.push([d, h, 'стена ' + mm(d) + '×' + mm(h) + ' мм']);
      parts.push([w, d, 'плот ' + mm(w) + '×' + mm(d) + ' мм']);
    } else if (m.shape === 'column') {
      parts.push([w, h, 'стена ' + mm(w) + '×' + mm(h) + ' мм']);
    } else if (m.shape === 'plancha') {
      var pc = Math.PI * PROP.planchaBase * w;
      if (pc > L) return { fits: false, part: 'основа с обиколка ' + mm(pc) + ' мм (листът е ' + mm(L) + ' мм)' };
      parts.push([pc, h, 'основа ' + mm(pc) + '×' + mm(h) + ' мм']);
      parts.push([w, w, 'плоча Ø' + mm(w) + ' мм']);
    } else if (m.shape === 'plancha_sq') {
      parts.push([PROP.planchaBase * w, h, 'стена на основата ' + mm(PROP.planchaBase * w) + '×' + mm(h) + ' мм']);
      parts.push([w, w, 'плоча ' + mm(w) + '×' + mm(w) + ' мм']);
    } else if (m.shape === 'hex' || m.shape === 'octagon') {
      var n = m.shape === 'hex' ? 6 : 8, side = m.shape === 'hex' ? w / 2 : w * Math.sin(Math.PI / 8);
      if (bent) parts.push([n * side, h, 'разгъната заготовка ' + mm(n * side) + '×' + mm(h) + ' мм']);
      else parts.push([side, h, 'стена ' + mm(side) + '×' + mm(h) + ' мм']);
      parts.push([w, w, 'дъно ' + mm(w) + '×' + mm(w) + ' мм']);
    } else {
      var wallW = m.shape === 'trapezoid' ? w + 2 * 0.22 * h : w;
      if (bent) {
        if (w + 2 * h > Sh || d > L) return { fits: false, part: 'разгъната заготовка ' + mm(w + 2 * h) + ' мм (късата страна на листа е ' + mm(Sh) + ' мм)' };
        parts.push([wallW, h, 'стена ' + mm(wallW) + '×' + mm(h) + ' мм']);
      } else {
        parts.push([wallW, h, 'стена ' + mm(wallW) + '×' + mm(h) + ' мм']);
        parts.push([d, h, 'стена ' + mm(d) + '×' + mm(h) + ' мм']);
        parts.push([w, d, 'дъно ' + mm(w) + '×' + mm(d) + ' мм']);
      }
    }
    for (var i = 0; i < parts.length; i++) {
      if (!fits(parts[i][0], parts[i][1])) return { fits: false, part: parts[i][2] };
    }
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
    ROUND_SHAPES: ROUND_SHAPES,
    PROP: PROP,
    crossParts: crossParts,
    objParts: objParts,
    benchSegments: benchSegments,
    isRound: isRound,
    plateArea: plateArea,
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
