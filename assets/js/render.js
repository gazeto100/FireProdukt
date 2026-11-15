/* Параметричен SVG изглед на огнище/скара.
 * Коса (кабинетна) проекция: предната стена е без изкривяване, скосена е само страничната.
 * Всички размери са в мм.
 */
(function (global) {
  'use strict';

  var ANG = 35 * Math.PI / 180;
  var KX = Math.cos(ANG) * 0.5;
  var KY = Math.sin(ANG) * 0.5;

  var PALETTE = {
    inox: {
      front: ['#f1f3f5', '#cfd4d9', '#b3b9bf', '#dde1e5'],
      side: ['#a9afb5', '#8d939a'],
      sideL: ['#c9ced3', '#e4e7ea'],
      top: ['#e9ecef', '#c3c8cd'],
      inBack: ['#7b8188', '#4b5056'],
      inLeft: '#5a6066',
      rim: '#fbfcfd',
      edge: 'rgba(40,46,52,.35)',
      leg: ['#9aa0a7', '#767c83'],
      caption: 'rgba(35,40,46,.72)',
      brush: true
    },
    black: {
      front: ['#4b4b4f', '#38383b', '#2c2c2f', '#3d3d41'],
      side: ['#2a2a2d', '#1e1e20'],
      sideL: ['#36363a', '#424246'],
      top: ['#48484c', '#2c2c2f'],
      inBack: ['#2a2a2c', '#141415'],
      inLeft: '#1a1a1c',
      rim: '#6a6a70',
      edge: 'rgba(0,0,0,.6)',
      leg: ['#2f2f32', '#1c1c1e'],
      caption: 'rgba(255,255,255,.62)',
      brush: false
    },
    corten: {
      front: ['#b0632f', '#93491f', '#7a3a18', '#9c5226'],
      side: ['#6f3417', '#582812'],
      sideL: ['#8f4a22', '#a2582b'],
      top: ['#a85c2e', '#7c3e1d'],
      inBack: ['#5c2d15', '#2c150a'],
      inLeft: '#3e1e0e',
      rim: '#c98352',
      edge: 'rgba(50,20,5,.5)',
      leg: ['#7c3e1d', '#5a2c14'],
      caption: 'rgba(255,236,220,.75)',
      brush: false
    }
  };
  var WOOD = ['#b07a45', '#8a5a2e'];

  function f(n) { return (Math.round(n * 10) / 10).toString(); }
  function P(x, y, z) { return [x + z * KX, -y - z * KY]; }
  function pts(list) { return list.map(function (p) { return f(p[0]) + ',' + f(p[1]); }).join(' '); }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------- Орнаменти: само отделни отвори без "острови" ---------- */
  var PATTERNS = {
    flames: function (x0, y0, w, h) {
      var n = Math.max(3, Math.round(w / (h * 0.62))), cell = w / n, d = '';
      for (var i = 0; i < n; i++) {
        var cx = x0 + cell * (i + 0.5), fw = cell * 0.66, hh = h * (i % 2 ? 0.8 : 1);
        var b = y0 + h, s = i % 2 ? -1 : 1;
        d += 'M' + f(cx) + ',' + f(b) +
          'C' + f(cx - s * fw * 0.55) + ',' + f(b - hh * 0.04) + ' ' + f(cx - s * fw * 0.62) + ',' + f(b - hh * 0.42) + ' ' + f(cx - s * fw * 0.2) + ',' + f(b - hh * 0.66) +
          'C' + f(cx - s * fw * 0.02) + ',' + f(b - hh * 0.8) + ' ' + f(cx + s * fw * 0.02) + ',' + f(b - hh * 0.9) + ' ' + f(cx + s * fw * 0.12) + ',' + f(b - hh) +
          'C' + f(cx + s * fw * 0.3) + ',' + f(b - hh * 0.74) + ' ' + f(cx + s * fw * 0.64) + ',' + f(b - hh * 0.52) + ' ' + f(cx + s * fw * 0.5) + ',' + f(b - hh * 0.22) +
          'C' + f(cx + s * fw * 0.44) + ',' + f(b - hh * 0.04) + ' ' + f(cx + s * fw * 0.2) + ',' + f(b) + ' ' + f(cx) + ',' + f(b) + 'Z';
      }
      return d;
    },
    mountains: function (x0, y0, w, h) {
      var k = Math.max(3, Math.round(w / (h * 1.15))), hf = [0.62, 1, 0.74, 0.92, 0.58, 0.86, 0.7];
      var list = [[x0, y0 + h]];
      for (var j = 0; j <= 2 * k; j++) {
        var x = x0 + w * j / (2 * k), y;
        if (j === 0 || j === 2 * k) y = y0 + h * 0.72;
        else if (j % 2) y = y0 + h * (1 - hf[((j - 1) / 2) % hf.length]);
        else y = y0 + h * (0.55 + 0.12 * ((j / 2) % 2));
        list.push([x, y]);
      }
      list.push([x0 + w, y0 + h]);
      return 'M' + list.map(function (p) { return f(p[0]) + ',' + f(p[1]); }).join('L') + 'Z';
    },
    pines: function (x0, y0, w, h) {
      var n = Math.max(3, Math.round(w / (h * 0.55))), cell = w / n, d = '';
      for (var i = 0; i < n; i++) {
        var sc = i % 2 ? 0.84 : 1, th = h * sc, top = y0 + h - th, cx = x0 + cell * (i + 0.5), tw = cell * 0.82 * sc;
        var b0 = top + th * 0.36, b1 = top + th * 0.6, b2 = top + th * 0.84;
        var h0 = tw / 2 * 0.52, h1 = tw / 2 * 0.76, h2 = tw / 2, tr = tw * 0.08;
        var L = [[cx, top], [cx + h0, b0], [cx + h0 * 0.42, b0], [cx + h1, b1], [cx + h1 * 0.45, b1], [cx + h2, b2],
          [cx + tr, b2], [cx + tr, y0 + h], [cx - tr, y0 + h], [cx - tr, b2], [cx - h2, b2], [cx - h1 * 0.45, b1],
          [cx - h1, b1], [cx - h0 * 0.42, b0], [cx - h0, b0]];
        d += 'M' + L.map(function (p) { return f(p[0]) + ',' + f(p[1]); }).join('L') + 'Z';
      }
      return d;
    },
    shevitsa: function (x0, y0, w, h) {
      var d = '';
      function rh(cx, cy, rx, ry) {
        d += 'M' + f(cx) + ',' + f(cy - ry) + 'L' + f(cx + rx) + ',' + f(cy) + 'L' + f(cx) + ',' + f(cy + ry) + 'L' + f(cx - rx) + ',' + f(cy) + 'Z';
      }
      var border = h >= 60;
      var t = h * 0.11, step = h * 0.26;
      if (border) {
        var cnt = Math.floor(w / step), off = x0 + (w - (cnt - 1) * step) / 2;
        for (var k = 0; k < cnt; k++) {
          var tx = off + k * step;
          d += 'M' + f(tx - t) + ',' + f(y0) + 'L' + f(tx + t) + ',' + f(y0) + 'L' + f(tx) + ',' + f(y0 + t * 1.1) + 'Z';
          d += 'M' + f(tx - t) + ',' + f(y0 + h) + 'L' + f(tx + t) + ',' + f(y0 + h) + 'L' + f(tx) + ',' + f(y0 + h - t * 1.1) + 'Z';
        }
      }
      var my0 = border ? y0 + h * 0.22 : y0, mh = border ? h * 0.56 : h, cell = mh * 1.15;
      var n = Math.max(1, Math.floor(w / cell)), start = x0 + (w - n * cell) / 2, cy = my0 + mh / 2;
      for (var i = 0; i < n; i++) {
        var cx = start + cell * (i + 0.5);
        if (i % 2 === 0) {
          rh(cx, cy, mh * 0.42, mh * 0.5);
        } else {
          var r = mh * 0.15, o = mh * 0.27;
          rh(cx - o, cy, r, r); rh(cx + o, cy, r, r); rh(cx, cy - o, r, r); rh(cx, cy + o, r, r);
        }
      }
      return d;
    }
  };

  /* ---------- Декорация на една стена (в локални координати u, v надолу) ---------- */
  /* ---------- Мостчета: къси вертикални, само в буквите със затворен контур ----------
   * [позиция по ширината на буквата (0–1), вид]; вид: top – през горната черта, bot – през долната,
   * bar – през напречната черта (А, 4). Височините са части от главна буква (или от x-височина при малките).
   */
  var BRIDGE_Y = { top: [0.80, 1.06], bot: [-0.06, 0.20], bar: [0.20, 0.48] };
  var TB = [[0.5, 'top'], [0.5, 'bot']];
  var BRIDGES = {
    'О': TB, 'O': TB, 'Q': TB, '0': TB, 'D': [[0.45, 'top'], [0.45, 'bot']], 'Ю': [[0.68, 'top'], [0.68, 'bot']],
    'А': [[0.5, 'bar']], 'A': [[0.5, 'bar']], '4': [[0.55, 'bar']],
    'Р': [[0.45, 'top']], 'P': [[0.45, 'top']], 'R': [[0.42, 'top']], 'Я': [[0.55, 'top']], '9': [[0.5, 'top']],
    'Б': [[0.55, 'bot']], 'Ъ': [[0.64, 'bot']], 'Ь': [[0.56, 'bot']], '6': [[0.5, 'bot']], 'Д': [[0.5, 'bot']],
    'В': [[0.45, 'top'], [0.45, 'bot']], 'B': [[0.45, 'top'], [0.45, 'bot']], '8': TB,
    'Ф': [[0.24, 'top'], [0.76, 'top']],
    'о': TB, 'o': TB, 'а': [[0.42, 'bot']], 'a': [[0.42, 'bot']], 'е': [[0.5, 'top']], 'e': [[0.5, 'top']],
    'р': [[0.58, 'top']], 'p': [[0.58, 'top']], 'q': [[0.42, 'top']], 'я': [[0.5, 'top']],
    'б': [[0.55, 'bot']], 'b': [[0.58, 'bot']], 'd': [[0.42, 'bot']], 'ъ': [[0.62, 'bot']], 'ь': [[0.56, 'bot']], 'д': [[0.5, 'bot']],
    'в': [[0.45, 'top'], [0.45, 'bot']], 'ю': [[0.68, 'top'], [0.68, 'bot']], 'ф': [[0.24, 'top'], [0.76, 'top']]
  };

  function wallDecor(W, H, o, showText) {
    var L = o.layout, holes = '', bridges = '', edge = L.edge_mm || 25;
    if (o.pattern && PATTERNS[o.pattern]) {
      var ob = L.band_orn, oTop = Math.max(ob[0] * H, edge), oBot = Math.min(ob[1] * H, H - edge);
      if (oBot > oTop + 10) holes += '<path d="' + PATTERNS[o.pattern](edge, oTop, W - 2 * edge, oBot - oTop) + '"/>';
    }
    var fit = o.fit;
    if (showText && o.text && fit && fit.lines) {
      var n = fit.lines.length, total = n * fit.line_h + (n - 1) * fit.gap;
      var top0 = (fit.band[0] + fit.band[1]) / 2 - total / 2;
      var font = o.fonts && o.fonts.fonts[o.fontKey || 'russo'], k = font ? fit.size_mm / font.upm : 0, bw = L.bridge_mm || 3;
      fit.lines.forEach(function (line, i) {
        var base = top0 + fit.y_max + i * (fit.line_h + fit.gap), wi = fit.widths_mm[i], x = (W - wi) / 2;
        holes += '<text x="' + f(x) + '" y="' + f(base) + '" font-family="' + o.fontFamily + '" font-size="' + f(fit.size_mm) +
          '" textLength="' + f(wi) + '" lengthAdjust="spacingAndGlyphs" style="font-kerning:none;font-variant-ligatures:none">' + esc(line) + '</text>';
        if (!font) return;
        Array.from(line).forEach(function (ch) {
          var g = font.g[ch], adv = g ? g[0] * k : 0, spec = BRIDGES[ch];
          if (spec) {
            var ref = ch.toUpperCase() !== ch ? fit.xh_mm : fit.cap_mm;
            spec.forEach(function (b) {
              var yr = BRIDGE_Y[b[1]];
              bridges += '<rect x="' + f(x + b[0] * adv - bw / 2) + '" y="' + f(base - yr[1] * ref) + '" width="' + f(bw) + '" height="' + f((yr[1] - yr[0]) * ref) + '"/>';
            });
          }
          x += adv;
        });
      });
    }
    return { holes: holes, bridges: bridges };
  }

  function wallMarkup(id, W, H, transform, fill, o, decorate, pal, showText) {
    var dec = decorate ? wallDecor(W, H, o, showText !== false) : { holes: '', bridges: '' };
    var s = '<g transform="' + transform + '">';
    var masked = '';
    if (dec.holes) {
      s += '<mask id="' + id + '" maskUnits="userSpaceOnUse" x="0" y="0" width="' + f(W) + '" height="' + f(H) + '">' +
        '<rect width="' + f(W) + '" height="' + f(H) + '" fill="#fff"/>' +
        '<g fill="#000">' + dec.holes + '</g><g fill="#fff">' + dec.bridges + '</g></mask>';
      s += '<rect width="' + f(W) + '" height="' + f(H) + '" fill="url(#' + o.id + 'glow)" class="fp-glow"/>';
      masked = ' mask="url(#' + id + ')"';
    }
    s += '<rect width="' + f(W) + '" height="' + f(H) + '" fill="' + fill + '"' + masked + '/>';
    if (pal.brush) s += '<rect width="' + f(W) + '" height="' + f(H) + '" fill="url(#' + o.id + 'brush)"' + masked + '/>';
    if (dec.holes) {
      // светлина, която "прелива" около отворите
      s += '<g fill="#ff8a2a" opacity="' + (o.lid ? 0.3 : 0.42) + '" filter="url(#' + o.id + 'halo)">' + dec.holes + '</g>';
    }
    s += '<rect width="' + f(W) + '" height="' + f(H) + '" fill="none" stroke="' + pal.edge + '" stroke-width="2"/>';
    return s + '</g>';
  }

  function flamesMarkup(cx, baseY, fw, fh, id) {
    var parts = [
      [-0.3, 0.62, 0.42, 'b'], [0.28, 0.7, 0.44, 'b'], [0, 1, 0.56, 'a'], [-0.12, 0.5, 0.3, 'c'], [0.14, 0.56, 0.3, 'c']
    ];
    var s = '<g class="fp-flames">';
    parts.forEach(function (p, i) {
      var x = cx + p[0] * fw, h = fh * p[1], w = fw * p[2];
      var d = 'M' + f(x - w / 2) + ',' + f(baseY) +
        'C' + f(x - w * 0.62) + ',' + f(baseY - h * 0.45) + ' ' + f(x - w * 0.05) + ',' + f(baseY - h * 0.55) + ' ' + f(x + w * 0.04) + ',' + f(baseY - h) +
        'C' + f(x + w * 0.2) + ',' + f(baseY - h * 0.6) + ' ' + f(x + w * 0.62) + ',' + f(baseY - h * 0.4) + ' ' + f(x + w / 2) + ',' + f(baseY) + 'Z';
      var fill = p[3] === 'c' ? 'url(#' + id + 'flameIn)' : 'url(#' + id + 'flame)';
      s += '<path class="fl fl' + i + '" d="' + d + '" fill="' + fill + '"/>';
    });
    return s + '</g>';
  }

  /* ---------- Форми: контур отгоре (x, z), обиколен обратно на часовника; ръб i = стена i ---------- */
  function outlineFor(m) {
    var W = m.w * 10, D = m.d * 10;
    if (m.shape === 'hex') {
      // плоска стена отпред; ред на стените: отпред, дясно-отпред, дясно-отзад, отзад, ляво-отзад, ляво-отпред
      var R = W / 2;
      return [240, 300, 0, 60, 120, 180].map(function (a) {
        var r = a * Math.PI / 180;
        return [R * Math.cos(r), R * Math.sin(r)];
      });
    }
    return [[0, 0], [W, 0], [W, D], [0, D]];
  }
  function sx(x, z) { return x + z * KX; }
  function minOf(a) { return Math.min.apply(null, a); }
  function maxOf(a) { return Math.max.apply(null, a); }

  function makeDefs(id, pal) {
    var d = '';
    d += '<linearGradient id="' + id + 'front" x1="0" y1="0" x2="1" y2="0">' +
      pal.front.map(function (c, i) { return '<stop offset="' + f(i / (pal.front.length - 1)) + '" stop-color="' + c + '"/>'; }).join('') + '</linearGradient>';
    d += '<linearGradient id="' + id + 'side" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="' + pal.side[0] + '"/><stop offset="1" stop-color="' + pal.side[1] + '"/></linearGradient>';
    d += '<linearGradient id="' + id + 'sideL" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="' + pal.sideL[0] + '"/><stop offset="1" stop-color="' + pal.sideL[1] + '"/></linearGradient>';
    d += '<linearGradient id="' + id + 'top" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' + pal.top[0] + '"/><stop offset="1" stop-color="' + pal.top[1] + '"/></linearGradient>';
    d += '<linearGradient id="' + id + 'inBack" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' + pal.inBack[0] + '"/><stop offset="1" stop-color="' + pal.inBack[1] + '"/></linearGradient>';
    d += '<radialGradient id="' + id + 'glow" cx=".5" cy=".9" r=".95"><stop offset="0" stop-color="#fff6c4"/><stop offset=".3" stop-color="#ffc24a"/><stop offset=".62" stop-color="#ff7417"/><stop offset="1" stop-color="#a42d00"/></radialGradient>';
    d += '<radialGradient id="' + id + 'inGlow" cx=".5" cy=".7" r=".6"><stop offset="0" stop-color="#ffb347" stop-opacity=".95"/><stop offset=".55" stop-color="#ff6a00" stop-opacity=".45"/><stop offset="1" stop-color="#ff6a00" stop-opacity="0"/></radialGradient>';
    d += '<linearGradient id="' + id + 'flame" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#ffd34d"/><stop offset=".4" stop-color="#ff8c1a"/><stop offset=".8" stop-color="#e2410b" stop-opacity=".85"/><stop offset="1" stop-color="#b8290a" stop-opacity="0"/></linearGradient>';
    d += '<linearGradient id="' + id + 'flameIn" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#fffbe0"/><stop offset=".5" stop-color="#ffe066"/><stop offset="1" stop-color="#ffb01f" stop-opacity="0"/></linearGradient>';
    d += '<radialGradient id="' + id + 'shadow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#000" stop-opacity=".45"/><stop offset="1" stop-color="#000" stop-opacity="0"/></radialGradient>';
    d += '<filter id="' + id + 'halo" x="-20%" y="-40%" width="140%" height="180%"><feGaussianBlur stdDeviation="7"/></filter>';
    if (pal.brush) {
      d += '<pattern id="' + id + 'brush" patternUnits="userSpaceOnUse" width="240" height="9">' +
        '<line x1="0" y1="1.5" x2="240" y2="1.5" stroke="#fff" stroke-opacity=".28" stroke-width=".8"/>' +
        '<line x1="0" y1="5" x2="160" y2="5" stroke="#5b636b" stroke-opacity=".12" stroke-width=".7"/>' +
        '<line x1="90" y1="7.5" x2="240" y2="7.5" stroke="#fff" stroke-opacity=".18" stroke-width=".6"/></pattern>';
    }
    return d;
  }

  /** Посока на стената -> запълване: отпред, дясна (по-тъмна) или лява (по-светла) */
  function faceFill(id, dz) {
    if (Math.abs(dz) < 1e-6) return 'url(#' + id + 'front)';
    return dz > 0 ? 'url(#' + id + 'side)' : 'url(#' + id + 'sideL)';
  }

  function legSpots(m, outline, cx, cz) {
    if (m.shape === 'hex') {
      return [0, 2, 4].map(function (i) {
        var p = outline[i];
        return [p[0] + (cx - p[0]) * 0.14, p[1] + (cz - p[1]) * 0.14];
      });
    }
    var xs = outline.map(function (p) { return p[0]; }), zs = outline.map(function (p) { return p[1]; });
    var x0 = minOf(xs) + 27, x1 = maxOf(xs) - 27, z0 = minOf(zs) + 27, z1 = maxOf(zs) - 27;
    return [[x0, z0], [x1, z0], [x1, z1], [x0, z1]];
  }

  function legsMarkup(spots, legMM, pal) {
    if (!legMM) return '';
    var h = 13, out = '';
    if (legMM > 300) {
      // напречни тръби при високите крачета
      var y = legMM * 0.22;
      spots.forEach(function (a, i) {
        var b = spots[(i + 1) % spots.length], pa = P(a[0], y, a[1]), pb = P(b[0], y, b[1]);
        out += '<line x1="' + f(pa[0]) + '" y1="' + f(pa[1]) + '" x2="' + f(pb[0]) + '" y2="' + f(pb[1]) + '" stroke="' + (Math.abs(a[1] - b[1]) < 1 ? pal.leg[0] : pal.leg[1]) + '" stroke-width="16"/>';
      });
    }
    spots.slice().sort(function (a, b) { return b[1] - a[1]; }).forEach(function (sp) {
      var x = sp[0] - h, z = sp[1] - h, s = 2 * h;
      out += '<polygon points="' + pts([P(x + s, legMM, z), P(x + s, legMM, z + s), P(x + s, 0, z + s), P(x + s, 0, z)]) + '" fill="' + pal.leg[1] + '"/>';
      out += '<polygon points="' + pts([P(x, legMM, z), P(x + s, legMM, z), P(x + s, 0, z), P(x, 0, z)]) + '" fill="' + pal.leg[0] + '"/>';
    });
    return out;
  }

  function line(a, b, attrs) {
    return '<line x1="' + f(a[0]) + '" y1="' + f(a[1]) + '" x2="' + f(b[0]) + '" y2="' + f(b[1]) + '" ' + attrs + '/>';
  }

  /* ---------- Общ рендер за огнища и мангали (призма по контура) ---------- */
  function renderPrism(o) {
    var m = o.model, pal = PALETTE[o.finish] || PALETTE.inox, id = o.id;
    var H = m.h * 10, Lg = (o.legCm || 0) * 10, top = Lg + H;
    var outline = outlineFor(m), n = outline.length;
    var xs = outline.map(function (p) { return p[0]; }), zs = outline.map(function (p) { return p[1]; });
    var minX = minOf(xs), maxX = maxOf(xs), minZ = minOf(zs), maxZ = maxOf(zs);
    var cx = (minX + maxX) / 2, cz = (minZ + maxZ) / 2, spanX = maxX - minX, spanZ = maxZ - minZ;
    var ex = o.extras || {}, isGrill = m.shape === 'grill';
    var fireH = Math.min(spanX, spanZ) * (isGrill ? 0.45 : 0.72);
    var armH = spanX * 0.95;
    var tableW = ex.side_table && m.shape !== 'hex' ? Math.min(320, spanX * 0.55) : 0;
    var topPoly = outline.map(function (p) { return P(p[0], top, p[1]); });

    var up = Math.max(fireH, ex.arm ? armH + 20 : 0, ex.lid ? 60 : 0);
    var sxs = outline.map(function (p) { return sx(p[0], p[1]); });
    var bagW = ex.bag ? spanX * 1.05 : 0, bagH = Math.max(H * 1.6 + 40, spanX * 0.45);
    var vx = minOf(sxs) - 50 - (bagW ? bagW + 40 : 0), vy = -(top + up) - maxZ * KY - 20;
    var vb = [vx, vy, maxOf(sxs) + (ex.arm ? 70 : 0) + tableW + 40 - vx, -minZ * KY + 50 - vy];

    var defs = makeDefs(id, pal);
    defs += '<clipPath id="' + id + 'open"><polygon points="' + pts(topPoly) + '"/></clipPath>';

    var s = '';
    // сянка
    var sc = P(cx, 0, cz);
    s += '<ellipse cx="' + f(sc[0]) + '" cy="' + f(sc[1] + 8) + '" rx="' + f((maxOf(sxs) - minOf(sxs)) * 0.62) + '" ry="' + f(spanZ * KY + 46) + '" fill="url(#' + id + 'shadow)"/>';
    // калъф за пренасяне – стои вляво на земята
    if (bagW) {
      var bx1 = minX - 40, bx0 = bx1 - bagW, bz0 = minZ, bz1 = minZ + Math.max(60, spanZ * 0.35);
      var bf = [P(bx0, 0, bz0), P(bx1, 0, bz0), P(bx1, bagH, bz0), P(bx0, bagH, bz0)];
      s += '<polygon points="' + pts([P(bx1, 0, bz0), P(bx1, 0, bz1), P(bx1, bagH, bz1), P(bx1, bagH, bz0)]) + '" fill="#57533b"/>';
      s += '<polygon points="' + pts([P(bx0, bagH, bz0), P(bx1, bagH, bz0), P(bx1, bagH, bz1), P(bx0, bagH, bz1)]) + '" fill="#8a8562"/>';
      s += '<polygon points="' + pts(bf) + '" fill="#6f6a4b" stroke="#4b4833" stroke-width="3"/>';
      var hc = (bx0 + bx1) / 2, hw = bagW * 0.16, ha = P(hc - hw, bagH, bz0), hb = P(hc + hw, bagH, bz0);
      s += '<path d="M' + f(ha[0]) + ',' + f(ha[1]) + 'C' + f(ha[0]) + ',' + f(ha[1] - bagH * 0.35) + ' ' + f(hb[0]) + ',' + f(hb[1] - bagH * 0.35) + ' ' + f(hb[0]) + ',' + f(hb[1]) + '" fill="none" stroke="#3f3c2a" stroke-width="9" stroke-linecap="round"/>';
      s += line(P(bx0 + bagW * 0.08, bagH * 0.82, bz0), P(bx1 - bagW * 0.08, bagH * 0.82, bz0), 'stroke="#4b4833" stroke-width="4" stroke-dasharray="10 7"');
    }
    // крачета
    s += legsMarkup(legSpots(m, outline, cx, cz), Lg, pal);

    // стени: видими са тези, чийто ръб върви надясно на екрана
    var walls = outline.map(function (a, i) {
      var b = outline[(i + 1) % n];
      return { i: i, a: a, b: b, len: Math.hypot(b[0] - a[0], b[1] - a[1]), dz: b[1] - a[1], visible: sx(b[0], b[1]) - sx(a[0], a[1]) > 1e-6 };
    });

    if (!o.table) {
    // вътрешност: през отвора се виждат вътрешните страни на задните стени
    s += '<g clip-path="url(#' + id + 'open)">';
    s += '<polygon points="' + pts(topPoly) + '" fill="' + pal.inLeft + '"/>';
    walls.forEach(function (w) {
      if (w.visible) return;
      var poly = [P(w.a[0], top, w.a[1]), P(w.b[0], top, w.b[1]), P(w.b[0], Lg, w.b[1]), P(w.a[0], Lg, w.a[1])];
      s += '<polygon points="' + pts(poly) + '" fill="' + (w.dz < -1e-6 ? pal.inLeft : 'url(#' + id + 'inBack)') + '"/>';
    });
    if (!ex.lid) {
      var gc = P(cx, top, minZ + spanZ * 0.3);
      s += '<ellipse class="fp-glow" cx="' + f(gc[0]) + '" cy="' + f(gc[1]) + '" rx="' + f(spanX * 0.62) + '" ry="' + f(spanZ * KY * 1.4) + '" fill="url(#' + id + 'inGlow)"/>';
    }
    s += '</g>';

    // скара за печене
    if (ex.grate && !ex.lid) {
      var g = '', rods = Math.max(5, Math.round(spanZ / 45));
      for (var r = 1; r <= rods; r++) {
        var z = minZ + spanZ * r / (rods + 1);
        g += line(P(minX, top + 3, z), P(maxX, top + 3, z), '');
      }
      s += '<g clip-path="url(#' + id + 'open)" stroke="#c9ced3" stroke-width="5" stroke-linecap="round">' + g + '</g>';
      s += '<polygon points="' + pts(outline.map(function (p) { return P(p[0] + (cx - p[0]) * 0.02, top + 3, p[1] + (cz - p[1]) * 0.02); })) + '" fill="none" stroke="#aeb4ba" stroke-width="7"/>';
    }

    // огън
    if (!ex.lid) {
      var fc = P(cx, top, cz), frontRimY = P(0, top, minZ)[1];
      s += flamesMarkup(fc[0], frontRimY + 30, spanX * (isGrill ? 0.7 : 0.62), fireH + (frontRimY - fc[1]) + 30, id);
    }
    } // !o.table

    // видими стени с надпис и орнамент
    var decorate = !!(o.text && o.fit) || !!o.pattern, nDec = o.decoratedCount || 1;
    walls.forEach(function (w) {
      if (!w.visible) return;
      var tr = 'matrix(' + ((sx(w.b[0], w.b[1]) - sx(w.a[0], w.a[1])) / w.len).toFixed(4) + ',' + (-w.dz * KY / w.len).toFixed(4) + ',0,1,' +
        f(sx(w.a[0], w.a[1])) + ',' + f(-top - w.a[1] * KY) + ')';
      // На скосените стени надписът не се изрязва в изгледа (би бил нечетлив) – вместо него кратка бележка
      var skewed = Math.abs(w.dz) > 1e-6, dec = decorate && w.i < nDec;
      s += wallMarkup(id + 'm' + w.i, w.len, H, tr, faceFill(id, w.dz), o, dec, pal, !skewed);
      if (skewed && dec && o.text && o.fit && o.fit.band) {
        var a = (sx(w.b[0], w.b[1]) - sx(w.a[0], w.a[1])) / w.len, bb = -w.dz * KY / w.len, u = w.len / 2;
        var cxp = sx(w.a[0], w.a[1]) + a * u, cyp = -top - w.a[1] * KY + bb * u + (o.fit.band[0] + o.fit.band[1]) / 2;
        // размер по видимата (проектираната) ширина на стената: най-дългият ред е ~8 em
        var fs = Math.min(Math.abs(a) * w.len * 0.8 / 8.2, 30);
        s += '<text class="fp-caption" x="' + f(cxp) + '" y="' + f(cyp) + '" text-anchor="middle" font-family="system-ui, Segoe UI, Arial, sans-serif" font-size="' + f(fs) + '" fill="' + (pal.caption || 'rgba(40,46,52,.7)') + '">' +
          '<tspan x="' + f(cxp) + '" dy="-0.9em">надписът</tspan><tspan x="' + f(cxp) + '" dy="1.2em">се повтаря</tspan><tspan x="' + f(cxp) + '" dy="1.2em">на тази страна</tspan></text>';
      }
    });

    // ръбове: задните (тънки), предните (по-плътни) и вертикалните ъгли между видими стени
    walls.forEach(function (w) {
      s += line(P(w.a[0], top, w.a[1]), P(w.b[0], top, w.b[1]), 'stroke="' + pal.rim + '"' +
        (w.visible ? ' stroke-width="5" stroke-linecap="round"' : ' stroke-opacity=".55" stroke-width="3"'));
    });
    walls.forEach(function (w, i) {
      if (w.visible && walls[(i + 1) % n].visible) {
        s += line(P(w.b[0], top, w.b[1]), P(w.b[0], Lg, w.b[1]), 'stroke="' + pal.rim + '" stroke-opacity=".7" stroke-width="2.5"');
      }
    });

    // огнище маса на дърва: кръгла огнена купа, вградена в плота, с мрежест искрогасител отгоре
    if (o.table) {
      var hr = o.table.r, hole = circ(cx, cz, hr, top, 0, 2 * Math.PI, 56);
      defs += '<pattern id="' + id + 'mesh" patternUnits="userSpaceOnUse" width="16" height="16" patternTransform="rotate(45)">' +
        '<path d="M0 0H16M0 0V16" stroke="#d6dadf" stroke-width="1.8" stroke-opacity=".75"/></pattern>';
      s += '<polygon points="' + pts(hole) + '" fill="url(#' + id + 'inBack)"/>';
      s += '<polygon class="fp-glow" points="' + pts(circ(cx, cz, hr * 0.78, top, 0, 2 * Math.PI, 40)) + '" fill="url(#' + id + 'inGlow)"/>';
      var outer = 'M' + topPoly.map(function (p) { return f(p[0]) + ',' + f(p[1]); }).join('L') + 'Z';
      var inner = 'M' + hole.map(function (p) { return f(p[0]) + ',' + f(p[1]); }).join('L') + 'Z';
      s += '<path d="' + outer + inner + '" fill="url(#' + id + 'top)" fill-rule="evenodd"/>';
      // огън от дърва (цепеници + пламъци)
      var lc = P(cx, top - 40, cz);
      s += '<g stroke="#3b2414" stroke-width="22" stroke-linecap="round"><line x1="' + f(lc[0] - hr * 0.5) + '" y1="' + f(lc[1] + 8) + '" x2="' + f(lc[0] + hr * 0.45) + '" y2="' + f(lc[1] - 12) + '"/>' +
        '<line x1="' + f(lc[0] - hr * 0.4) + '" y1="' + f(lc[1] - 14) + '" x2="' + f(lc[0] + hr * 0.5) + '" y2="' + f(lc[1] + 10) + '"/></g>';
      s += flamesMarkup(lc[0], P(cx, top, cz - hr * 0.2)[1], hr * 1.5, hr * 1.25, id);
      // ръб на купата
      s += '<polygon points="' + pts(hole) + '" fill="none" stroke="' + pal.rim + '" stroke-width="7"/>';
      // искрогасител: мрежест купол над купата
      var L0 = P(cx + hr * Math.cos(TH0 + Math.PI), top, cz + hr * Math.sin(TH0 + Math.PI)), R0 = P(cx + hr * Math.cos(TH0), top, cz + hr * Math.sin(TH0));
      var dh = hr * 1.05, front = circ(cx, cz, hr, top, FRONT[0], FRONT[1], 40).reverse();
      var dome = 'M' + f(L0[0]) + ',' + f(L0[1]) + 'C' + f(L0[0]) + ',' + f(L0[1] - dh * 1.3) + ' ' + f(R0[0]) + ',' + f(R0[1] - dh * 1.3) + ' ' + f(R0[0]) + ',' + f(R0[1]) +
        'L' + front.map(function (p) { return f(p[0]) + ',' + f(p[1]); }).join('L') + 'Z';
      s += '<path d="' + dome + '" fill="url(#' + id + 'mesh)" stroke="#c4c9ce" stroke-width="5"/>';
      var tk = P(cx, top + dh * 0.98, cz);
      s += '<path d="M' + f(tk[0] - 26) + ',' + f(tk[1]) + 'q26,-34 52,0" fill="none" stroke="#9aa0a7" stroke-width="8" stroke-linecap="round"/>';
    }

    // капак
    if (ex.lid) {
      var yl = top + 4, t = 26, big = outline.map(function (p) {
        var dx = p[0] - cx, dz2 = p[1] - cz, k = 1 + 12 / Math.max(1, Math.hypot(dx, dz2));
        return [cx + dx * k, cz + dz2 * k];
      });
      s += '<polygon points="' + pts(big.map(function (p) { return P(p[0], yl, p[1]); })) + '" fill="url(#' + id + 'top)"/>';
      big.forEach(function (a, i) {
        var b = big[(i + 1) % n];
        if (sx(b[0], b[1]) - sx(a[0], a[1]) <= 1e-6) return;
        s += '<polygon points="' + pts([P(a[0], yl, a[1]), P(b[0], yl, b[1]), P(b[0], yl - t, b[1]), P(a[0], yl - t, a[1])]) + '" fill="' + faceFill(id, b[1] - a[1]) + '"/>';
      });
      var h1 = P(cx - 70, yl, cz), h2 = P(cx + 70, yl, cz);
      s += '<path d="M' + f(h1[0]) + ',' + f(h1[1]) + 'C' + f(h1[0]) + ',' + f(h1[1] - 55) + ' ' + f(h2[0]) + ',' + f(h2[1] - 55) + ' ' + f(h2[0]) + ',' + f(h2[1]) + '" fill="none" stroke="#3b3f44" stroke-width="12" stroke-linecap="round"/>';
    }

    // страничен плот (вдясно)
    if (tableW) {
      var y = top - 2, tt = 18, x0 = maxX, x1 = maxX + tableW;
      s += line(P(x0, top - Math.min(140, H * 0.8), cz), P(x0 + tableW * 0.75, y - tt, cz), 'stroke="' + pal.leg[1] + '" stroke-width="10"');
      s += '<polygon points="' + pts([P(x0, y, minZ), P(x1, y, minZ), P(x1, y, maxZ), P(x0, y, maxZ)]) + '" fill="url(#' + id + 'top)"/>';
      s += '<polygon points="' + pts([P(x0, y, minZ), P(x1, y, minZ), P(x1, y - tt, minZ), P(x0, y - tt, minZ)]) + '" fill="url(#' + id + 'front)"/>';
      s += '<polygon points="' + pts([P(x1, y, minZ), P(x1, y, maxZ), P(x1, y - tt, maxZ), P(x1, y - tt, minZ)]) + '" fill="url(#' + id + 'side)"/>';
    }

    // рамо за котле
    if (ex.arm) {
      var zA = m.shape === 'hex' ? 0 : minZ + spanZ * 0.78, xA = maxX + 28;
      var p0 = P(xA, Lg + 25, zA), p1 = P(xA, top + armH, zA), p2 = P(cx, top + armH, cz);
      s += line(p0, p1, 'stroke="#55595e" stroke-width="18" stroke-linecap="round"');
      [Lg + 60, top - 40].forEach(function (yy) {
        s += line(P(maxX, yy, zA), P(xA, yy, zA), 'stroke="#55595e" stroke-width="12"');
      });
      s += line(p1, p2, 'stroke="#55595e" stroke-width="14" stroke-linecap="round"');
      var pc = P(cx, top + armH * 0.42, cz), rr = Math.min(spanX, spanZ) * 0.2;
      s += line(p2, [pc[0], pc[1] - rr * 0.25], 'stroke="#3a3d41" stroke-width="5" stroke-dasharray="14 6"');
      s += '<path d="M' + f(pc[0] - rr * 0.6) + ',' + f(pc[1] - rr * 0.05) + 'Q' + f(pc[0]) + ',' + f(pc[1] - rr * 0.55) + ' ' + f(pc[0] + rr * 0.6) + ',' + f(pc[1] - rr * 0.05) + '" fill="none" stroke="#3a3d41" stroke-width="5"/>';
      s += '<path d="M' + f(pc[0] - rr) + ',' + f(pc[1]) + 'C' + f(pc[0] - rr) + ',' + f(pc[1] + rr * 1.25) + ' ' + f(pc[0] + rr) + ',' + f(pc[1] + rr * 1.25) + ' ' + f(pc[0] + rr) + ',' + f(pc[1]) + 'Z" fill="#26282b"/>';
      s += '<ellipse cx="' + f(pc[0]) + '" cy="' + f(pc[1]) + '" rx="' + f(rr) + '" ry="' + f(rr * 0.22) + '" fill="#3c4045" stroke="#55595e" stroke-width="4"/>';
      s += '<path d="M' + f(pc[0] - rr * 0.7) + ',' + f(pc[1] + rr * 0.3) + 'Q' + f(pc[0] - rr * 0.55) + ',' + f(pc[1] + rr * 0.75) + ' ' + f(pc[0] - rr * 0.15) + ',' + f(pc[1] + rr * 0.88) + '" fill="none" stroke="#fff" stroke-opacity=".18" stroke-width="6" stroke-linecap="round"/>';
    }

    return { viewBox: vb, defs: defs, body: s };
  }

  /**
   * o: { model, finish, legCm, text, fit, fontKey, pattern, decoratedCount, extras:{}, layout, id, fontCss, title }
   */
  function render(o) {
    o.id = o.id || 'fp';
    o.fontFamily = "'fp-" + (o.fontKey || 'russo') + "'";
    o.lid = o.extras && o.extras.lid;
    var r = renderPrism(o);
    var style = o.fontCss ? '<style>' + o.fontCss + '</style>' : '';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' + r.viewBox.map(f).join(' ') + '" role="img" aria-label="' + esc(o.title || o.model.name) + '">' +
      style + '<defs>' + r.defs + '</defs>' + r.body + '</svg>';
  }

  /* ---------- Кръгли форми (шоурум за обекти) ---------- */
  var TH0 = Math.atan(KX); // ъгъл на допирните (контурни) точки на вертикален цилиндър в тази проекция
  function circ(cx, cz, r, y, a0, a1, n) {
    var out = [];
    n = n || 48;
    for (var i = 0; i <= n; i++) {
      var a = a0 + (a1 - a0) * i / n;
      out.push(P(cx + r * Math.cos(a), y, cz + r * Math.sin(a)));
    }
    return out;
  }
  var FRONT = [TH0 + Math.PI, TH0 + 2 * Math.PI], BACK = [TH0, TH0 + Math.PI];
  function poly(points, attrs) { return '<polygon points="' + pts(points) + '" ' + attrs + '/>'; }
  function cylSide(r, y0, y1, attrs) {
    return poly(circ(0, 0, r, y1, FRONT[0], FRONT[1]).concat(circ(0, 0, r, y0, FRONT[0], FRONT[1]).reverse()), attrs);
  }
  function ring(ro, ri, y, range, attrs) {
    return poly(circ(0, 0, ro, y, range[0], range[1]).concat(circ(0, 0, ri, y, range[0], range[1]).reverse()), attrs);
  }
  function roundDefs(id, pal, R) {
    var d = makeDefs(id, pal);
    d += '<linearGradient id="' + id + 'body" gradientUnits="userSpaceOnUse" x1="' + f(-R * 1.1) + '" y1="0" x2="' + f(R * 1.1) + '" y2="0">' +
      pal.front.concat([pal.side[0], pal.side[1]]).map(function (c, i, a) { return '<stop offset="' + f(i / (a.length - 1)) + '" stop-color="' + c + '"/>'; }).join('') + '</linearGradient>';
    d += '<linearGradient id="' + id + 'wood" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' + WOOD[0] + '"/><stop offset="1" stop-color="' + WOOD[1] + '"/></linearGradient>';
    return d;
  }
  /** Отвор с огън отгоре на кръгъл съд: вътрешност, задна половина на ръба, пламъци, предна половина */
  function roundTop(id, pal, r, top, fh) {
    var ri = r - 28, s = '';
    s += poly(circ(0, 0, ri, top, 0, 2 * Math.PI, 64), 'fill="url(#' + id + 'inBack)"');
    s += poly(circ(0, 0, ri * 0.8, top, 0, 2 * Math.PI, 48), 'fill="url(#' + id + 'inGlow)" class="fp-glow"');
    s += ring(r, ri, top, BACK, 'fill="' + pal.rim + '" fill-opacity=".8"');
    s += flamesMarkup(0, P(0, top, -ri * 0.35)[1], r * 0.95, fh, id);
    s += ring(r, ri, top, FRONT, 'fill="' + pal.rim + '"');
    return s;
  }

  function renderBowl(o) {
    var pal = PALETTE[o.finish] || PALETTE.inox, id = o.id, R = o.d * 10 / 2;
    var Hp = 250, Hb = R * 0.6, top = Hp + Hb, fh = R * 0.95, s = '';
    s += '<ellipse cx="0" cy="8" rx="' + f(R * 0.75) + '" ry="' + f(R * KY + 30) + '" fill="url(#' + id + 'shadow)"/>';
    // крак и основа
    s += cylSide(R * 0.42, 0, 26, 'fill="url(#' + id + 'side)"');
    s += poly(circ(0, 0, R * 0.42, 26, 0, 2 * Math.PI), 'fill="url(#' + id + 'top)"');
    s += cylSide(R * 0.2, 26, Hp + 30, 'fill="url(#' + id + 'body)"');
    // купа: долна обвивка на нивата + задната дъга на ръба
    var levels = [];
    for (var i = 0; i <= 16; i++) {
      var t = i / 16;
      levels.push({ y: Hp + t * Hb, r: R * Math.max(0.3, Math.sqrt(1 - (1 - t) * (1 - t))) });
    }
    var env = [];
    for (var k = 0; k <= 48; k++) {
      var a = FRONT[0] + (FRONT[1] - FRONT[0]) * k / 48, best = null;
      levels.forEach(function (lv) {
        var p = P(lv.r * Math.cos(a), lv.y, lv.r * Math.sin(a));
        if (!best || p[1] > best[1]) best = p;
      });
      env.push(best);
    }
    s += poly(env.concat(circ(0, 0, R, top, BACK[0], BACK[1])), 'fill="url(#' + id + 'body)"');
    // отблясък
    s += '<path d="M' + f(-R * 0.62) + ',' + f(-top + R * 0.05) + 'Q' + f(-R * 0.55) + ',' + f(-Hp - Hb * 0.25) + ' ' + f(-R * 0.18) + ',' + f(-Hp + 10) + '" fill="none" stroke="#fff" stroke-opacity=".22" stroke-width="' + f(R * 0.06) + '" stroke-linecap="round"/>';
    s += roundTop(id, pal, R, top, fh);
    var vx = -R * 1.12 - 30;
    return { viewBox: [vx, -(top + fh) - R * KY - 40, -vx * 2, top + fh + R * KY * 2 + 90], defs: roundDefs(id, pal, R), body: s };
  }

  function renderRingBench(o) {
    var pal = PALETTE[o.finish] || PALETTE.corten, id = o.id, Ro = o.d * 10 / 2, Ri = Ro - 330, Hb = 450, Rp = 430, Hpit = 450, s = '';
    s += '<ellipse cx="0" cy="8" rx="' + f(Ro * 1.15) + '" ry="' + f(Ro * KY + 60) + '" fill="url(#' + id + 'shadow)"/>';
    // каменна настилка вътре в пръстена
    s += poly(circ(0, 0, Ri, 0, 0, 2 * Math.PI, 72), 'fill="#4a433d"');
    s += poly(circ(0, 0, Ri * 0.72, 0, 0, 2 * Math.PI, 72), 'fill="none" stroke="#5a524b" stroke-width="10"');
    // задната половина на пейката: вътрешна стена и седалка
    s += poly(circ(0, 0, Ri, Hb, BACK[0], BACK[1]).concat(circ(0, 0, Ri, 0, BACK[0], BACK[1]).reverse()), 'fill="url(#' + id + 'sideL)"');
    s += ring(Ro, Ri, Hb, BACK, 'fill="url(#' + id + 'wood)"');
    // огнище в центъра с изрязани прорези, осветени отвътре
    s += cylSide(Rp, 0, Hpit, 'fill="url(#' + id + 'body)"');
    for (var k = 1; k <= 9; k++) {
      var a = FRONT[0] + (FRONT[1] - FRONT[0]) * (k / 10), da = 0.05, y0 = Hpit * 0.5, y1 = Hpit * 0.88;
      var q = [P(Rp * Math.cos(a - da), y1, Rp * Math.sin(a - da)), P(Rp * Math.cos(a + da), y1, Rp * Math.sin(a + da)),
        P(Rp * Math.cos(a + da), y0, Rp * Math.sin(a + da)), P(Rp * Math.cos(a - da), y0, Rp * Math.sin(a - da))];
      s += poly(q, 'fill="url(#' + id + 'glow)" class="fp-glow"');
    }
    s += roundTop(id, pal, Rp, Hpit, Rp * 1.7);
    // предната половина на пейката
    s += cylSide(Ro, 0, Hb, 'fill="url(#' + id + 'body)"');
    s += ring(Ro, Ri, Hb, FRONT, 'fill="url(#' + id + 'wood)"');
    s += poly(circ(0, 0, Ro, Hb, FRONT[0], FRONT[1]), 'fill="none" stroke="#5e3a1c" stroke-width="5"');
    var vx = -Ro * 1.1 - 40, topY = -(Hpit + Rp * 1.7) - Rp * KY - 40;
    return { viewBox: [vx, Math.min(topY, -Hb - Ro * KY - 40), -vx * 2, -Math.min(topY, -Hb - Ro * KY - 40) + Ro * KY + 80], defs: roundDefs(id, pal, Ro), body: s };
  }

  /** Модели за обекти: bowl, ring, table, column */
  function renderShowcase(item, o) {
    o = o || {};
    var id = o.id || ('sc' + item.code), r;
    var base = { id: id, finish: item.finish, layout: o.layout, fonts: o.fonts, fontKey: o.fontKey || 'russo', fontFamily: "'fp-" + (o.fontKey || 'russo') + "'" };
    if (item.kind === 'bowl') r = renderBowl({ id: id, finish: item.finish, d: item.d });
    else if (item.kind === 'ring') r = renderRingBench({ id: id, finish: item.finish, d: item.d });
    else {
      var m = { shape: 'box', w: item.w, d: item.dd, h: item.h, name: item.name };
      var po = Object.assign(base, { model: m, legCm: 0, extras: {}, decoratedCount: 2, text: o.text || '', fit: o.fit || null, pattern: o.pattern || null });
      if (item.kind === 'table') po.table = { r: Math.min(item.w, item.dd) * 10 * 0.36 };
      r = renderPrism(po);
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' + r.viewBox.map(f).join(' ') + '" role="img" aria-label="' + esc(item.name) + '">' +
      '<defs>' + r.defs + '</defs>' + r.body + '</svg>';
  }

  global.FPRender = { render: render, renderShowcase: renderShowcase, patterns: PATTERNS, KX: KX, KY: KY };
})(window);
