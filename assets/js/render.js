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

  /**
   * Перфорация: кръгли отвори в 3 размера (шахматна мрежа), които не застъпват надписа.
   * excl = {x0, y0, x1, y1} – зоната на надписа (с отстъп); density: dense | sparse
   */
  function perfPath(x0, y0, w, h, density, excl) {
    var sp = density === 'sparse' ? 46 : 27, r0 = density === 'sparse' ? 8 : 5.5, sizes = [0.6, 1, 1.4], d = '';
    var rows = Math.floor(h / (sp * 0.87)), cols = Math.floor(w / sp);
    var offX = x0 + (w - (cols - 1) * sp) / 2, offY = y0 + (h - (rows - 1) * sp * 0.87) / 2;
    for (var j = 0; j < rows; j++) {
      for (var i = 0; i < cols - (j % 2); i++) {
        var cxp = offX + i * sp + (j % 2 ? sp / 2 : 0), cyp = offY + j * sp * 0.87;
        var r = r0 * sizes[(i * 7 + j * 13) % 3];
        if (cxp - r < x0 || cxp + r > x0 + w || cyp - r < y0 || cyp + r > y0 + h) continue;
        if (excl && cxp + r > excl.x0 && cxp - r < excl.x1 && cyp + r > excl.y0 && cyp - r < excl.y1) continue;
        d += 'M' + f(cxp - r) + ',' + f(cyp) + 'a' + f(r) + ',' + f(r) + ' 0 1,0 ' + f(2 * r) + ',0a' + f(r) + ',' + f(r) + ' 0 1,0 ' + f(-2 * r) + ',0Z';
      }
    }
    return d;
  }

  function wallDecor(W, H, o, showText) {
    var L = o.layout, holes = '', bridges = '', edge = L.edge_mm || 25;
    if (o.pattern === 'perf') {
      // зоната на надписа (дори когато надписът не се рисува на скосена стена – физически го има)
      var excl = null, ft = o.fit;
      if (o.text && ft && ft.lines) {
        var tot = ft.lines.length * ft.line_h + (ft.lines.length - 1) * ft.gap, t0 = (ft.band[0] + ft.band[1]) / 2 - tot / 2;
        var mw = Math.max.apply(null, ft.widths_mm), clr = Math.max(14, ft.cap_mm * 0.35);
        excl = { x0: (W - mw) / 2 - clr, x1: (W + mw) / 2 + clr, y0: t0 - clr, y1: t0 + tot + clr };
      }
      // при постамента перфорацията е само над отвора за дърва
      var perfH = o.model && o.model.shape === 'pedestal' ? H * 0.52 - edge : H - 2 * edge;
      holes += '<path d="' + perfPath(edge, edge, W - 2 * edge, perfH, o.perfDensity, excl) + '"/>';
    } else if (o.pattern && PATTERNS[o.pattern]) {
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

  /** shapePts: по желание – контур на стената в локални координати (напр. трапец); иначе правоъгълник W×H */
  function wallMarkup(id, W, H, transform, fill, o, decorate, pal, showText, shapePts) {
    var dec = decorate ? wallDecor(W, H, o, showText !== false) : { holes: '', bridges: '' };
    var body = function (attrs) {
      return shapePts ? '<polygon points="' + pts(shapePts) + '" ' + attrs + '/>' : '<rect width="' + f(W) + '" height="' + f(H) + '" ' + attrs + '/>';
    };
    var s = '<g transform="' + transform + '">';
    var masked = '';
    if (dec.holes) {
      s += '<mask id="' + id + '" maskUnits="userSpaceOnUse" x="0" y="0" width="' + f(W) + '" height="' + f(H) + '">' +
        '<rect width="' + f(W) + '" height="' + f(H) + '" fill="#fff"/>' +
        '<g fill="#000">' + dec.holes + '</g><g fill="#fff">' + dec.bridges + '</g></mask>';
      s += body('fill="url(#' + o.id + 'glow)" class="fp-glow"');
      masked = ' mask="url(#' + id + ')"';
    }
    s += body('fill="' + fill + '"' + masked);
    if (pal.brush) s += body('fill="url(#' + o.id + 'brush)"' + masked);
    if (dec.holes) {
      // светлина, която "прелива" около отворите
      s += '<g fill="#ff8a2a" opacity="' + (o.lid ? 0.3 : 0.42) + '" filter="url(#' + o.id + 'halo)">' + dec.holes + '</g>';
    }
    s += body('fill="none" stroke="' + pal.edge + '" stroke-width="2"');
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
    if (m.shape === 'octagon') {
      // плоска стена отпред; 8 стени, обиколени обратно на часовника
      var Ro = W / 2;
      return [247.5, 292.5, 337.5, 22.5, 67.5, 112.5, 157.5, 202.5].map(function (a) {
        var r = a * Math.PI / 180;
        return [Ro * Math.cos(r), Ro * Math.sin(r)];
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
    // жар, месо, тухли (скари и мангали)
    d += '<radialGradient id="' + id + 'coal" cx=".45" cy=".4" r=".65"><stop offset="0" stop-color="#ffe9a0"/><stop offset=".35" stop-color="#ff9a2a"/><stop offset=".75" stop-color="#d4400c"/><stop offset="1" stop-color="#6e1c06"/></radialGradient>';
    d += '<radialGradient id="' + id + 'coalDark" cx=".45" cy=".4" r=".65"><stop offset="0" stop-color="#6b3420"/><stop offset=".7" stop-color="#3a1a10"/><stop offset="1" stop-color="#1c0d08"/></radialGradient>';
    d += '<linearGradient id="' + id + 'meat" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#b5652e"/><stop offset=".55" stop-color="#8a3f19"/><stop offset="1" stop-color="#5a2610"/></linearGradient>';
    d += '<linearGradient id="' + id + 'meatV" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#c47a3c"/><stop offset=".5" stop-color="#9a4c1f"/><stop offset="1" stop-color="#5e2a10"/></linearGradient>';
    d += '<pattern id="' + id + 'brick" patternUnits="userSpaceOnUse" width="240" height="130"><path d="M0 1H240M0 66H240M120 1V66M0 66V130M240 66V130" stroke="#d9cfc2" stroke-width="8" fill="none"/></pattern>';
    d += '<pattern id="' + id + 'brickS" patternUnits="userSpaceOnUse" width="100" height="130" patternTransform="skewY(' + f(-Math.atan(KY / KX) * 180 / Math.PI) + ')"><path d="M0 1H100M0 66H100M50 1V66M0 66V130" stroke="#b9ad9f" stroke-width="7" fill="none"/></pattern>';
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
    if (m.shape === 'hex' || m.shape === 'octagon') {
      return (m.shape === 'hex' ? [0, 2, 4] : [0, 2, 4, 6]).map(function (i) {
        var p = outline[i];
        return [p[0] + (cx - p[0]) * 0.14, p[1] + (cz - p[1]) * 0.14];
      });
    }
    var xs = outline.map(function (p) { return p[0]; }), zs = outline.map(function (p) { return p[1]; });
    var x0 = minOf(xs) + 27, x1 = maxOf(xs) - 27, z0 = minOf(zs) + 27, z1 = maxOf(zs) - 27;
    return [[x0, z0], [x1, z0], [x1, z1], [x0, z1]];
  }

  /** between: рисунка между задните и предните крачета (напр. рафт с дърва) */
  function legsMarkup(spots, legMM, pal, between) {
    if (!legMM) return '';
    var h = 13, out = '', inserted = !between;
    var zMid = (Math.min.apply(null, spots.map(function (p) { return p[1]; })) + Math.max.apply(null, spots.map(function (p) { return p[1]; }))) / 2;
    if (legMM > 300) {
      // напречни тръби при високите крачета
      var y = legMM * 0.22;
      spots.forEach(function (a, i) {
        var b = spots[(i + 1) % spots.length], pa = P(a[0], y, a[1]), pb = P(b[0], y, b[1]);
        out += '<line x1="' + f(pa[0]) + '" y1="' + f(pa[1]) + '" x2="' + f(pb[0]) + '" y2="' + f(pb[1]) + '" stroke="' + (Math.abs(a[1] - b[1]) < 1 ? pal.leg[0] : pal.leg[1]) + '" stroke-width="16"/>';
      });
    }
    spots.slice().sort(function (a, b) { return b[1] - a[1]; }).forEach(function (sp) {
      if (!inserted && sp[1] <= zMid) { out += between; inserted = true; }
      var x = sp[0] - h, z = sp[1] - h, s = 2 * h;
      out += '<polygon points="' + pts([P(x + s, legMM, z), P(x + s, legMM, z + s), P(x + s, 0, z + s), P(x + s, 0, z)]) + '" fill="' + pal.leg[1] + '"/>';
      out += '<polygon points="' + pts([P(x, legMM, z), P(x + s, legMM, z), P(x + s, 0, z), P(x, 0, z)]) + '" fill="' + pal.leg[0] + '"/>';
    });
    if (!inserted) out += between;
    return out;
  }

  /** Рафт с дърва между крачетата (поставка за дърва) */
  function logRackMarkup(spots, legMM, pal) {
    var xs = spots.map(function (p) { return p[0]; }), zs = spots.map(function (p) { return p[1]; });
    var x0 = minOf(xs), x1 = maxOf(xs), z0 = minOf(zs), z1 = maxOf(zs), y = legMM * 0.28, s = '';
    s += '<polygon points="' + pts([P(x0, y, z0), P(x1, y, z0), P(x1, y, z1), P(x0, y, z1)]) + '" fill="' + pal.leg[1] + '"/>';
    var r = Math.min(55, (z1 - z0) * 0.16), n = Math.max(3, Math.floor((x1 - x0) / (r * 2.3)));
    for (var row = 0; row < 2; row++) {
      for (var i = 0; i < n - row; i++) {
        var lx = x0 + (x1 - x0) * (i + 0.5 + row * 0.5) / n, c = P(lx, y + r + row * r * 1.7, z0 + 10);
        s += '<circle cx="' + f(c[0]) + '" cy="' + f(c[1]) + '" r="' + f(r) + '" fill="#8a5a33" stroke="#5c3a1e" stroke-width="4"/>';
        s += '<circle cx="' + f(c[0]) + '" cy="' + f(c[1]) + '" r="' + f(r * 0.45) + '" fill="none" stroke="#b07a45" stroke-width="3"/>';
      }
    }
    return s;
  }

  /** Плот отстрани: dir = 1 вдясно от maxX, -1 вляво от minX */
  function sideTableMarkup(id, pal, xEdge, dir, T, top, H, cz, minZ, maxZ) {
    var y = top - 2, tt = 18, xa = dir > 0 ? xEdge : xEdge - T, xb = dir > 0 ? xEdge + T : xEdge, s = '';
    s += line(P(xEdge, top - Math.min(140, H * 0.8), cz), P(xEdge + dir * T * 0.75, y - tt, cz), 'stroke="' + pal.leg[1] + '" stroke-width="10"');
    s += '<polygon points="' + pts([P(xa, y, minZ), P(xb, y, minZ), P(xb, y, maxZ), P(xa, y, maxZ)]) + '" fill="url(#' + id + 'top)"/>';
    s += '<polygon points="' + pts([P(xa, y, minZ), P(xb, y, minZ), P(xb, y - tt, minZ), P(xa, y - tt, minZ)]) + '" fill="url(#' + id + 'front)"/>';
    if (dir > 0) s += '<polygon points="' + pts([P(xb, y, minZ), P(xb, y, maxZ), P(xb, y - tt, maxZ), P(xb, y - tt, minZ)]) + '" fill="url(#' + id + 'side)"/>';
    return s;
  }

  function line(a, b, attrs) {
    return '<line x1="' + f(a[0]) + '" y1="' + f(a[1]) + '" x2="' + f(b[0]) + '" y2="' + f(b[1]) + '" ' + attrs + '/>';
  }

  /* ---------- Скари и мангали (етап 4): жар, прорези за шишове, шишове, втора скара, ръжен ---------- */
  var GRILLS = ['grill', 'tabletop', 'suitcase', 'bbq', 'cheverme', 'insert'];
  var SLOT_PITCH = 65; // разстояние между прорезите за шишове, мм

  /** Детерминирано „случайно“ число 0..1 (един и същ изглед при всяко прерисуване) */
  function rnd(i, j) { var x = Math.sin(i * 127.1 + j * 311.7) * 43758.5453; return x - Math.floor(x); }

  /** Легло от жар в отвора на съда (на ниво y), изрязано по отвора */
  function embersMarkup(id, minX, maxX, minZ, maxZ, y, clip) {
    var spanX = maxX - minX, spanZ = maxZ - minZ, s = '<g clip-path="url(#' + clip + ')">';
    s += poly([P(minX, y, minZ), P(maxX, y, minZ), P(maxX, y, maxZ), P(minX, y, maxZ)], 'fill="#2a140c"');
    var gc = P((minX + maxX) / 2, y, (minZ + maxZ) / 2);
    s += '<ellipse class="fp-glow" cx="' + f(gc[0]) + '" cy="' + f(gc[1]) + '" rx="' + f(spanX * 0.55) + '" ry="' + f(spanZ * KY * 1.1 + 20) + '" fill="url(#' + id + 'inGlow)"/>';
    var r0 = Math.max(9, Math.min(24, Math.min(spanX, spanZ) * 0.06));
    var cols = Math.max(4, Math.round(spanX / (r0 * 1.9))), rows = Math.max(2, Math.round(spanZ / (r0 * 1.9)));
    for (var j = rows - 1; j >= 0; j--) {
      for (var i = 0; i < cols; i++) {
        var k = rnd(i, j), x = minX + (i + 0.5 + (j % 2) * 0.35 - 0.17) * spanX / cols, z = minZ + (j + 0.5) * spanZ / rows;
        var c = P(x, y + r0 * 0.4, z), r = r0 * (0.7 + k * 0.6), hot = rnd(j, i) > 0.22;
        s += '<ellipse cx="' + f(c[0]) + '" cy="' + f(c[1]) + '" rx="' + f(r) + '" ry="' + f(r * 0.68) + '" fill="url(#' + id + (hot ? 'coal' : 'coalDark') + ')"' + (hot ? ' class="fp-glow"' : '') + '/>';
      }
    }
    return s + '</g>';
  }

  /** x-позиции на прорезите по горния ръб */
  function slotXs(minX, maxX) {
    var n = Math.max(2, Math.floor((maxX - minX - 50) / SLOT_PITCH) + 1), x0 = minX + (maxX - minX - (n - 1) * SLOT_PITCH) / 2, out = [];
    for (var i = 0; i < n; i++) out.push(x0 + i * SLOT_PITCH);
    return out;
  }
  /** Прорези (U-образни) по горния ръб на стена при z, 16 мм надолу от ръба */
  function slotsMarkup(xs, z, top, op) {
    var s = '';
    xs.forEach(function (x) {
      s += poly([P(x - 6, top + 1, z), P(x + 6, top + 1, z), P(x + 6, top - 15, z), P(x - 6, top - 15, z)], 'fill="#1d1e20"' + (op ? ' fill-opacity="' + op + '"' : ''));
    });
    return s;
  }
  /** n шиша в прорезите, с месо и дръжка-халка отпред */
  function skewersMarkup(id, xs, n, zFront, zBack, top) {
    if (!xs.length || !n) return '';
    var pick = [], m = Math.min(n, xs.length);
    for (var i = 0; i < m; i++) pick.push(xs[Math.round(i * (xs.length - 1) / Math.max(1, m - 1))]);
    if (m === 1) pick = [xs[Math.floor(xs.length / 2)]];
    var span = zBack - zFront, s = '', y = top + 4;
    pick.forEach(function (x, si) {
      s += line(P(x, y, zBack + 18), P(x, y, zFront - 70), 'stroke="#c3c8cd" stroke-width="5" stroke-linecap="round"');
      var pieces = Math.max(3, Math.min(6, Math.round(span / 70)));
      for (var p = pieces - 1; p >= 0; p--) {
        var z = zFront + span * (0.16 + 0.68 * p / Math.max(1, pieces - 1)), c = P(x, y + 4, z), k = rnd(si, p);
        s += '<rect x="' + f(c[0] - 17) + '" y="' + f(c[1] - 15) + '" width="34" height="27" rx="9" fill="url(#' + id + 'meat)" transform="rotate(' + f(k * 30 - 15) + ' ' + f(c[0]) + ' ' + f(c[1]) + ')"/>';
        if (k > 0.55) s += '<rect x="' + f(c[0] - 12) + '" y="' + f(c[1] - 8) + '" width="24" height="10" rx="4" fill="#3a8a3a" opacity=".85"/>';
      }
      var hc = P(x, y, zFront - 82);
      s += '<circle cx="' + f(hc[0]) + '" cy="' + f(hc[1]) + '" r="13" fill="none" stroke="#aeb4ba" stroke-width="5"/>';
    });
    return s;
  }
  /** Втора скара на ниво: повдигната над задната половина */
  function grate2Markup(minX, maxX, minZ, maxZ, top) {
    var spanX = maxX - minX, spanZ = maxZ - minZ, x0 = minX + spanX * 0.08, x1 = maxX - spanX * 0.08, z0 = minZ + spanZ * 0.52, z1 = maxZ - 14;
    var y = top + Math.min(170, Math.max(110, spanZ * 0.45)), s = '';
    [[x0, z1], [x1, z1], [x0, z0], [x1, z0]].forEach(function (p) { s += line(P(p[0], top, p[1]), P(p[0], y, p[1]), 'stroke="#9aa0a7" stroke-width="7"'); });
    for (var i = 1; i <= 5; i++) {
      var z = z0 + (z1 - z0) * i / 6;
      s += line(P(x0, y, z), P(x1, y, z), 'stroke="#c9ced3" stroke-width="4" stroke-linecap="round"');
    }
    return s + poly([P(x0, y, z0), P(x1, y, z0), P(x1, y, z1), P(x0, y, z1)], 'fill="none" stroke="#aeb4ba" stroke-width="6"');
  }
  /** Ръжен и лопатка, облегнати отстрани (dir = 1 вдясно, -1 вляво) */
  function toolsMarkup(xEdge, dir, z, top) {
    var s = '', h = Math.max(380, top + 140);
    var a = P(xEdge + dir * 70, 0, z), b = P(xEdge + dir * 12, h, z + 40);
    s += line(a, b, 'stroke="#55595e" stroke-width="9" stroke-linecap="round"');
    s += '<path d="M' + f(a[0]) + ',' + f(a[1]) + 'q' + f(dir * 22) + ',-4 ' + f(dir * 26) + ',-26" fill="none" stroke="#55595e" stroke-width="9" stroke-linecap="round"/>';
    s += line(b, P(xEdge + dir * 8, h + 50, z + 40), 'stroke="#7a4b26" stroke-width="16" stroke-linecap="round"');
    var c = P(xEdge + dir * 120, 60, z + 10), d = P(xEdge + dir * 40, h * 0.92, z + 50);
    s += line(c, d, 'stroke="#6a6e73" stroke-width="8" stroke-linecap="round"');
    s += '<polygon points="' + pts([[c[0] - 34, c[1] + 6], [c[0] + 34, c[1] + 6], [c[0] + 26, c[1] - 52], [c[0] - 26, c[1] - 52]]) + '" fill="#4b4f54" stroke="#2f3236" stroke-width="3"/>';
    s += line(d, [d[0], d[1] - 46], 'stroke="#7a4b26" stroke-width="14" stroke-linecap="round"');
    return s;
  }
  /** Отворен капак на панти: вертикално зад отвора; вътрешната му страна (W × D) гледа към нас */
  function openLidMarkup(o, id, pal, minX, maxX, maxZ, top, D, lidT, decorate) {
    var s = '', W = maxX - minX, y1 = top + D;
    s += poly([P(maxX, top, maxZ), P(maxX, top, maxZ + lidT), P(maxX, y1, maxZ + lidT), P(maxX, y1, maxZ)], 'fill="url(#' + id + 'side)" stroke="' + pal.edge + '" stroke-width="2"');
    s += poly([P(minX, y1, maxZ), P(maxX, y1, maxZ), P(maxX, y1, maxZ + lidT), P(minX, y1, maxZ + lidT)], 'fill="url(#' + id + 'top)"');
    var tr = 'matrix(1,0,0,1,' + f(sx(minX, maxZ)) + ',' + f(-y1 - maxZ * KY) + ')';
    s += wallMarkup(id + 'lid', W, D, tr, 'url(#' + id + 'front)', o, decorate, pal, true);
    [minX + W * 0.18, maxX - W * 0.18].forEach(function (x) { s += poly([P(x - 22, top + 14, maxZ), P(x + 22, top + 14, maxZ), P(x + 22, top - 10, maxZ), P(x - 22, top - 10, maxZ)], 'fill="#3b3f44"'); });
    s += line(P(minX, y1, maxZ), P(maxX, y1, maxZ), 'stroke="' + pal.rim + '" stroke-width="4"');
    return s;
  }
  /** Дръжка над горния ръб на отворения капак */
  function lidHandleMarkup(cx, y, z, w) {
    var a = P(cx - w / 2, y, z), b = P(cx + w / 2, y, z);
    return line(a, [a[0], a[1] - 34], 'stroke="#3b3f44" stroke-width="9"') + line(b, [b[0], b[1] - 34], 'stroke="#3b3f44" stroke-width="9"') +
      line([a[0] - 6, a[1] - 34], [b[0] + 6, b[1] - 34], 'stroke="#2b2c2f" stroke-width="15" stroke-linecap="round"');
  }
  /** Сгъваеми крака (кръст) в двата края на мангала куфар */
  function foldLegsMarkup(minX, maxX, minZ, maxZ, Lg, pal) {
    var s = '';
    [minX + 45, maxX - 45].forEach(function (x) {
      s += line(P(x, 0, maxZ - 15), P(x, Lg, minZ + 15), 'stroke="' + pal.leg[1] + '" stroke-width="12" stroke-linecap="round"');
      s += line(P(x, 0, minZ + 15), P(x, Lg, maxZ - 15), 'stroke="' + pal.leg[0] + '" stroke-width="12" stroke-linecap="round"');
      var c = P(x, Lg / 2, (minZ + maxZ) / 2);
      s += '<circle cx="' + f(c[0]) + '" cy="' + f(c[1]) + '" r="7" fill="#3b3f44"/>';
    });
    return s;
  }
  /** Тухлена зидария: лице, обърнато към нас (front) или надясно (side) */
  function brickFace(id, points, side) {
    return poly(points, 'fill="' + (side ? '#7d3b28' : '#a5533a') + '"') + poly(points, 'fill="url(#' + id + (side ? 'brickS' : 'brick') + ')"');
  }
  /** Тухлен блок: дясно лице, плот и предно лице */
  function brickBox(id, x0, x1, y0, y1, z0, z1, topFill) {
    return brickFace(id, [P(x1, y0, z0), P(x1, y0, z1), P(x1, y1, z1), P(x1, y1, z0)], true) +
      poly([P(x0, y1, z0), P(x1, y1, z0), P(x1, y1, z1), P(x0, y1, z1)], 'fill="' + (topFill || '#b8b0a6') + '"') +
      brickFace(id, [P(x0, y0, z0), P(x1, y0, z0), P(x1, y1, z0), P(x0, y1, z0)], false);
  }
  /** Стойка за чеверме: стълб с нива (полукръгли прорези) и стъпка */
  function chevermeStand(id, x, cz, levels, pal) {
    var s = '', yTop = levels[levels.length - 1] + 70;
    s += poly([P(x - 20, 14, cz - 220), P(x + 20, 14, cz - 220), P(x + 20, 14, cz + 220), P(x - 20, 14, cz + 220)], 'fill="' + pal.leg[0] + '"');
    s += poly([P(x + 20, 0, cz - 220), P(x + 20, 0, cz + 220), P(x + 20, 14, cz + 220), P(x + 20, 14, cz - 220)], 'fill="' + pal.leg[1] + '"');
    s += poly([P(x - 22, 0, cz), P(x + 22, 0, cz), P(x + 22, yTop, cz), P(x - 22, yTop, cz)], 'fill="url(#' + id + 'front)" stroke="' + pal.edge + '" stroke-width="2"');
    levels.forEach(function (y) {
      var c = P(x, y, cz);
      s += '<path d="M' + f(c[0] - 13) + ',' + f(c[1] - 2) + 'a13,13 0 0,0 26,0Z" fill="#1d1e20"/>';
    });
    return s;
  }
  /** Месо на шиша (агне): удължено тяло между стойките */
  function spitMeatMarkup(id, x0, x1, y, cz, r) {
    var a = P(x0, y, cz), b = P(x1, y, cz), s = '', L = b[0] - a[0], cy = a[1];
    // предни и задни крака, вързани по шиша
    s += '<path d="M' + f(a[0] + L * 0.1) + ',' + f(cy - r * 0.2) + 'L' + f(a[0] - L * 0.08) + ',' + f(cy - r * 0.05) + 'L' + f(a[0] - L * 0.08) + ',' + f(cy + r * 0.25) + 'L' + f(a[0] + L * 0.12) + ',' + f(cy + r * 0.45) + 'Z" fill="#8a4019"/>';
    s += '<path d="M' + f(b[0] - L * 0.1) + ',' + f(cy - r * 0.25) + 'L' + f(b[0] + L * 0.09) + ',' + f(cy - r * 0.1) + 'L' + f(b[0] + L * 0.09) + ',' + f(cy + r * 0.2) + 'L' + f(b[0] - L * 0.12) + ',' + f(cy + r * 0.5) + 'Z" fill="#8a4019"/>';
    // тяло: по-широко в средата, стеснено към краищата
    s += '<path d="M' + f(a[0]) + ',' + f(cy) + 'C' + f(a[0]) + ',' + f(cy - r * 0.95) + ' ' + f(a[0] + L * 0.35) + ',' + f(cy - r * 1.15) + ' ' + f(a[0] + L * 0.55) + ',' + f(cy - r) +
      'C' + f(a[0] + L * 0.8) + ',' + f(cy - r * 0.9) + ' ' + f(b[0]) + ',' + f(cy - r * 0.6) + ' ' + f(b[0]) + ',' + f(cy) +
      'C' + f(b[0]) + ',' + f(cy + r * 0.7) + ' ' + f(a[0] + L * 0.7) + ',' + f(cy + r * 1.05) + ' ' + f(a[0] + L * 0.45) + ',' + f(cy + r) +
      'C' + f(a[0] + L * 0.2) + ',' + f(cy + r * 0.95) + ' ' + f(a[0]) + ',' + f(cy + r * 0.7) + ' ' + f(a[0]) + ',' + f(cy) + 'Z" fill="url(#' + id + 'meatV)"/>';
    for (var i = 1; i <= 4; i++) {
      var x = a[0] + L * i / 5;
      s += line([x, cy - r * 0.8], [x, cy + r * 0.8], 'stroke="#e9dcc4" stroke-width="3" stroke-opacity=".55"');
    }
    s += '<ellipse cx="' + f((a[0] + b[0]) / 2) + '" cy="' + f(a[1] - r * 0.45) + '" rx="' + f((b[0] - a[0]) * 0.36) + '" ry="' + f(r * 0.18) + '" fill="#fff" fill-opacity=".14"/>';
    return s;
  }

  /* ---------- Общ рендер за огнища и мангали (призма по контура) ---------- */
  function renderPrism(o) {
    var m = o.model, pal = PALETTE[o.finish] || PALETTE.inox, id = o.id;
    var H = m.h * 10, Lg = (o.legCm || 0) * 10, shape = m.shape;
    // вградени крака / основа според формата: настолен – ниски крачета; куфар – сгъваеми крака; чеверме – корито на крачета; вложка – върху зидария
    if (shape === 'tabletop') Lg = 35; else if (shape === 'suitcase') Lg = 280; else if (shape === 'cheverme') Lg = 150; else if (shape === 'insert') Lg = 450;
    var top = Lg + H;
    var outline = outlineFor(m), n = outline.length;
    var xs = outline.map(function (p) { return p[0]; }), zs = outline.map(function (p) { return p[1]; });
    var minX = minOf(xs), maxX = maxOf(xs), minZ = minOf(zs), maxZ = maxOf(zs);
    var cx = (minX + maxX) / 2, cz = (minZ + maxZ) / 2, spanX = maxX - minX, spanZ = maxZ - minZ;
    var ex = o.extras || {}, isGrill = GRILLS.indexOf(shape) >= 0;
    // капак: при барбекюто и куфара е отворен (зад отвора), при мангала – затворен
    var lidOpen = shape === 'suitcase' || (shape === 'bbq' && !!ex.lid), closedLid = !!ex.lid && !lidOpen;
    var textOnLid = shape === 'suitcase' || (shape === 'bbq' && o.textPlace === 'lid'), lidT = shape === 'bbq' ? H * 0.6 : 25;
    var chevLevels = shape === 'cheverme' ? [top + 260, top + 390, top + 520, top + 650] : null;
    // скарите показват 4 шиша (или избрания комплект), освен ако има скара отгоре
    var nSkew = !isGrill || ex.grate ? 0 : (typeof ex.skewers === 'number' ? ex.skewers : (shape === 'cheverme' ? 0 : 4));
    var decorate = !!(o.text && o.fit) || !!o.pattern;
    var fireH = isGrill ? Math.min(140, H * 0.9) : Math.min(spanX, spanZ) * 0.72;
    var armH = spanX * 0.95;
    var round = m.shape === 'hex' || m.shape === 'octagon';
    var tableW = ex.side_table && !round ? Math.min(320, spanX * 0.55) : 0;
    var tableL = ex.side_table_left && !round ? Math.min(320, spanX * 0.55) : 0;
    var wgH = ex.wind_guard ? Math.min(spanX, spanZ) * 0.4 : 0;
    var topPoly = outline.map(function (p) { return P(p[0], top, p[1]); });

    var toolsH = ex.tools ? Math.max(380, top + 140) + 60 - top : 0;
    var up = Math.max(fireH, ex.arm ? armH + 20 : 0, closedLid ? 60 : 0, wgH + 20, lidOpen ? spanZ + 90 : 0, chevLevels ? 760 : 0, ex.grate2 ? 210 : 0, nSkew ? 40 : 0, toolsH);
    var sxs = outline.map(function (p) { return sx(p[0], p[1]); });
    var bagW = ex.bag ? spanX * 1.05 : 0, bagH = Math.max(H * 1.6 + 40, spanX * 0.45);
    var foldW = shape === 'suitcase' ? Math.min(spanX * 0.42, 300) : 0, brick = shape === 'insert' ? 175 : 0;
    var toolsL = ex.tools && tableW ? 170 : 0, toolsR = ex.tools && !tableW ? 170 : 0;
    var postL = chevLevels ? 100 : 0, postR = chevLevels ? 160 + (ex.motor ? 170 : 0) : 0;
    var vx = minOf(sxs) - 50 - (bagW ? bagW + 40 : 0) - tableL - (foldW ? foldW + 70 : 0) - toolsL - postL - brick, vy = -(top + up) - maxZ * KY - 20;
    var vb = [vx, vy, maxOf(sxs) + (ex.arm ? 70 : 0) + tableW + (ex.wheels ? 60 : 0) + toolsR + postR + brick + (lidOpen ? lidT * KX : 0) + 40 - vx, -minZ * KY + 50 - vy];

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
    // зидано барбекю (вложка): задна стена, лява колона и основа; дясната колона се рисува след вложката
    if (brick) {
      s += brickBox(id, minX - 170, maxX + 170, 0, top + 280, maxZ + 40, maxZ + 160);
      s += brickBox(id, minX - 170, minX - 10, 0, top + 120, minZ - 30, maxZ + 40);
      s += brickBox(id, minX - 10, maxX + 10, 0, Lg, minZ - 30, maxZ + 40, '#9b948b');
    }
    // стойка за чеверме – лявата стойка е зад коритото
    if (chevLevels) s += chevermeStand(id, minX - 60, cz, chevLevels, pal);
    // отворен капак (барбекю, куфар) – зад отвора, преди вътрешността
    if (lidOpen) s += openLidMarkup(o, id, pal, minX, maxX, maxZ, top, spanZ, lidT, decorate && textOnLid);
    // крачета (рафтът за дърва е между задните и предните)
    var spots = legSpots(m, outline, cx, cz);
    if (shape === 'suitcase') s += foldLegsMarkup(minX, maxX, minZ, maxZ, Lg, pal);
    else if (!brick) s += legsMarkup(spots, Lg, pal, ex.log_rack && Lg > 300 ? logRackMarkup(spots, Lg, pal) : '');
    // колела: в долния край на десните крачета
    if (ex.wheels && Lg) {
      spots.filter(function (p) { return p[0] > cx; }).sort(function (a, b) { return b[1] - a[1]; }).forEach(function (p) {
        var c = P(p[0] + 22, 62, p[1]);
        s += '<circle cx="' + f(c[0]) + '" cy="' + f(c[1]) + '" r="62" fill="#26282b" stroke="#111" stroke-width="5"/><circle cx="' + f(c[0]) + '" cy="' + f(c[1]) + '" r="20" fill="#9aa0a7"/>';
      });
    }
    // плот вляво (стои зад предната стена – рисува се преди стените)
    if (tableL) s += sideTableMarkup(id, pal, minX, -1, tableL, top, H, cz, minZ, maxZ);

    // стени: видими са тези, чийто ръб върви надясно на екрана
    var walls = outline.map(function (a, i) {
      var b = outline[(i + 1) % n];
      return { i: i, a: a, b: b, len: Math.hypot(b[0] - a[0], b[1] - a[1]), dz: b[1] - a[1], visible: sx(b[0], b[1]) - sx(a[0], a[1]) > 1e-6 };
    });
    // ветробран: панели над ръба на задните стени (+ дясната при правоъгълните)
    function guardPanel(w) {
      return '<polygon points="' + pts([P(w.a[0], top, w.a[1]), P(w.b[0], top, w.b[1]), P(w.b[0], top + wgH, w.b[1]), P(w.a[0], top + wgH, w.a[1])]) + '" fill="' + faceFill(id, w.dz) + '" stroke="' + pal.edge + '" stroke-width="2"/>';
    }
    var guardFront = '';

    if (!o.table) {
    // вътрешност: през отвора се виждат вътрешните страни на задните стени
    s += '<g clip-path="url(#' + id + 'open)">';
    s += '<polygon points="' + pts(topPoly) + '" fill="' + pal.inLeft + '"/>';
    walls.forEach(function (w) {
      if (w.visible) return;
      var poly = [P(w.a[0], top, w.a[1]), P(w.b[0], top, w.b[1]), P(w.b[0], Lg, w.b[1]), P(w.a[0], Lg, w.a[1])];
      s += '<polygon points="' + pts(poly) + '" fill="' + (w.dz < -1e-6 ? pal.inLeft : 'url(#' + id + 'inBack)') + '"/>';
    });
    if (wgH) s += '</g>' + walls.filter(function (w) { return !w.visible; }).map(guardPanel).join('') + '<g clip-path="url(#' + id + 'open)">';
    if (wgH && !round) guardFront = walls.filter(function (w) { return w.visible && w.dz > 1e-6; }).map(guardPanel).join('');
    if (!closedLid && !isGrill) {
      var gc = P(cx, top, minZ + spanZ * 0.3);
      s += '<ellipse class="fp-glow" cx="' + f(gc[0]) + '" cy="' + f(gc[1]) + '" rx="' + f(spanX * 0.62) + '" ry="' + f(spanZ * KY * 1.4) + '" fill="url(#' + id + 'inGlow)"/>';
    }
    s += '</g>';
    // скари: жар вместо високи пламъци
    if (isGrill && !closedLid) s += embersMarkup(id, minX + 8, maxX - 8, minZ + 8, maxZ - 8, Lg + H * 0.45, id + 'open');

    // скара за печене
    if (ex.grate && !closedLid) {
      var g = '', rods = Math.max(5, Math.round(spanZ / 45));
      for (var r = 1; r <= rods; r++) {
        var z = minZ + spanZ * r / (rods + 1);
        g += line(P(minX, top + 3, z), P(maxX, top + 3, z), '');
      }
      s += '<g clip-path="url(#' + id + 'open)" stroke="#c9ced3" stroke-width="5" stroke-linecap="round">' + g + '</g>';
      s += '<polygon points="' + pts(outline.map(function (p) { return P(p[0] + (cx - p[0]) * 0.02, top + 3, p[1] + (cz - p[1]) * 0.02); })) + '" fill="none" stroke="#aeb4ba" stroke-width="7"/>';
    }

    // плоча за печене: дебела плоча върху дясната половина на отвора
    if (ex.griddle && !closedLid) {
      var gx0 = cx + 6, gx1 = maxX - 8, gz0 = minZ + 8, gz1 = maxZ - 8, gy = top + 6;
      s += '<g clip-path="url(#' + id + 'open)"><polygon points="' + pts([P(gx0, gy, gz0), P(gx1, gy, gz0), P(gx1, gy, gz1), P(gx0, gy, gz1)]) + '" fill="#2f3033" stroke="#55585d" stroke-width="4"/>' +
        line(P(gx0 + 20, gy, gz0 + 25), P(gx1 - 20, gy, gz0 + 25), 'stroke="#45474b" stroke-width="3"') + '</g>';
    }

    // огън (при плоча за печене – в свободната лява половина)
    if (!closedLid) {
      var fcx = ex.griddle ? cx - spanX * 0.22 : cx;
      var fc = P(fcx, top, cz), frontRimY = P(0, top, minZ)[1];
      if (isGrill) {
        // ниски езици над жарта
        s += '<g opacity="' + (ex.grate ? '.6' : '.8') + '">' + flamesMarkup(fc[0], frontRimY + 30, spanX * (ex.griddle ? 0.3 : 0.5), fireH * (ex.grate ? 0.25 : 0.55) + (frontRimY - fc[1]) + 30, id) + '</g>';
      } else {
        s += flamesMarkup(fc[0], frontRimY + 30, spanX * (ex.griddle ? 0.36 : 0.62), fireH + (frontRimY - fc[1]) + 30, id);
      }
    }
    // прорези за шишове по задния ръб
    if (isGrill) s += slotsMarkup(slotXs(minX, maxX), maxZ, top, 0.8);
    } // !o.table

    // видими стени с надпис и орнамент
    var nDec = o.decoratedCount || 1;
    walls.forEach(function (w) {
      if (!w.visible) return;
      var tr = 'matrix(' + ((sx(w.b[0], w.b[1]) - sx(w.a[0], w.a[1])) / w.len).toFixed(4) + ',' + (-w.dz * KY / w.len).toFixed(4) + ',0,1,' +
        f(sx(w.a[0], w.a[1])) + ',' + f(-top - w.a[1] * KY) + ')';
      // На скосените стени надписът не се изрязва в изгледа (би бил нечетлив) – вместо него кратка бележка
      var skewed = Math.abs(w.dz) > 1e-6, dec = decorate && !textOnLid && w.i < nDec;
      s += wallMarkup(id + 'm' + w.i, w.len, H, tr, faceFill(id, w.dz), o, dec, pal, !skewed);
      if (skewed && dec && o.text && o.fit && o.fit.band) {
        var a = (sx(w.b[0], w.b[1]) - sx(w.a[0], w.a[1])) / w.len, bb = -w.dz * KY / w.len, u = w.len / 2;
        var cxp = sx(w.a[0], w.a[1]) + a * u, cyp = -top - w.a[1] * KY + bb * u + (o.fit.band[0] + o.fit.band[1]) / 2;
        // размер по видимата (проектираната) ширина на стената: най-дългият ред е ~8 em
        var fs = Math.min(Math.abs(a) * w.len * 0.8 / 8.2, 30);
        s += '<text class="fp-caption" x="' + f(cxp) + '" y="' + f(cyp) + '" text-anchor="middle" font-family="system-ui, Segoe UI, Arial, sans-serif" font-size="' + f(fs) + '" fill="' + (pal.caption || 'rgba(40,46,52,.7)') + '">' +
          '<tspan x="' + f(cxp) + '" dy="-0.9em">надписът</tspan><tspan x="' + f(cxp) + '" dy="1.2em">се повтаря</tspan><tspan x="' + f(cxp) + '" dy="1.2em">на тази страна</tspan></text>';
      }
      // пепелник: чекмедже в долната част на предната стена
      if (ex.ash_pan && !skewed && w.i === 0 && !brick) {
        s += '<g transform="' + tr + '"><rect x="' + f(w.len * 0.3) + '" y="' + f(H * 0.78) + '" width="' + f(w.len * 0.4) + '" height="' + f(H * 0.17) + '" fill="#2b2c2f" stroke="#55585d" stroke-width="3"/>' +
          '<rect x="' + f(w.len * 0.44) + '" y="' + f(H * 0.83) + '" width="' + f(w.len * 0.12) + '" height="' + f(Math.max(8, H * 0.03)) + '" rx="4" fill="#9aa0a7"/></g>';
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
    s += guardFront;

    // скари: прорези по предния ръб, шишове, втора скара
    if (isGrill) {
      var sxsSlots = slotXs(minX, maxX);
      s += slotsMarkup(sxsSlots, minZ, top);
      if (nSkew) s += skewersMarkup(id, ex.griddle ? sxsSlots.filter(function (x) { return x < cx - 20; }) : sxsSlots, nSkew, minZ, maxZ, top);
    }
    if (ex.grate2) s += grate2Markup(minX, maxX, minZ, maxZ, top);
    // стойка за чеверме: дясна стойка, шиш с месо, манивела или мотор
    if (chevLevels) {
      var ySpit = chevLevels[1], xr = maxX + 60;
      s += chevermeStand(id, xr, cz, chevLevels, pal);
      s += line(P(minX - 95, ySpit, cz), P(xr + 70, ySpit, cz), 'stroke="#c3c8cd" stroke-width="10" stroke-linecap="round"');
      s += spitMeatMarkup(id, minX + spanX * 0.08, maxX - spanX * 0.08, ySpit, cz, Math.min(150, spanZ * 0.34));
      var ce = P(xr + 70, ySpit, cz);
      if (ex.motor) {
        s += '<rect x="' + f(ce[0]) + '" y="' + f(ce[1] - 55) + '" width="150" height="110" rx="10" fill="#2b2c2f" stroke="#55585d" stroke-width="4"/>' +
          '<circle cx="' + f(ce[0] + 75) + '" cy="' + f(ce[1]) + '" r="22" fill="#3b3f44" stroke="#9aa0a7" stroke-width="3"/>' +
          '<path d="M' + f(ce[0] + 120) + ',' + f(ce[1] + 55) + 'q20,60 -10,' + f(P(0, 0, cz)[1] - ce[1] - 70) + '" fill="none" stroke="#1d1e20" stroke-width="5"/>';
      } else {
        s += line(ce, [ce[0], ce[1] + 70], 'stroke="#9aa0a7" stroke-width="9" stroke-linecap="round"') + line([ce[0], ce[1] + 70], [ce[0] + 34, ce[1] + 70], 'stroke="#7a4b26" stroke-width="16" stroke-linecap="round"');
      }
    }
    // вложка: дясната колона закрива дясната стена на тавата; пепелникът е чекмедже в зидарията под тавата
    if (brick) {
      s += brickBox(id, maxX + 10, maxX + 170, 0, top + 120, minZ - 30, maxZ + 40);
      if (ex.ash_pan) {
        var ax0 = minX + spanX * 0.25, ax1 = maxX - spanX * 0.25, ay0 = Lg * 0.55, ay1 = Lg * 0.85, zA = minZ - 30;
        s += poly([P(ax0, ay0, zA), P(ax1, ay0, zA), P(ax1, ay1, zA), P(ax0, ay1, zA)], 'fill="#2b2c2f" stroke="#55585d" stroke-width="4"');
        var am = P((ax0 + ax1) / 2, (ay0 + ay1) / 2, zA);
        s += '<rect x="' + f(am[0] - 40) + '" y="' + f(am[1] - 6) + '" width="80" height="12" rx="5" fill="#9aa0a7"/>';
      }
    }
    // барбекю: дръжка и термометър на отворения капак; куфар: дръжка за носене
    if (lidOpen) {
      s += lidHandleMarkup(cx, top + spanZ, maxZ + lidT, Math.min(220, spanX * 0.4));
      if (ex.thermometer) {
        var tcO = P(cx + spanX * 0.36, top + spanZ * 0.86, maxZ);
        s += '<circle cx="' + f(tcO[0]) + '" cy="' + f(tcO[1]) + '" r="24" fill="#f4f1ec" stroke="#3b3f44" stroke-width="5"/>' +
          line(tcO, [tcO[0] + 12, tcO[1] - 12], 'stroke="#c0392b" stroke-width="3" stroke-linecap="round"') + '<circle cx="' + f(tcO[0]) + '" cy="' + f(tcO[1]) + '" r="3" fill="#3b3f44"/>';
      }
    }
    // ръжен и лопатка – отстрани (вляво, ако вдясно има плот)
    if (ex.tools) s += tableW ? toolsMarkup(minX - tableL, -1, minZ + 30, top) : toolsMarkup(maxX + (brick ? 170 : chevLevels ? 120 : 0), 1, minZ + 30, top);
    // мангал куфар: малък втори изглед „сгънат“ (затворен, изправен, с дръжка; надписът е на капака)
    if (foldW) {
      var scF = foldW / spanX, fh2 = spanZ * scF, tF = (H + lidT) * scF;
      var fxF = minOf(sxs) - 60 - foldW, fyF = -(top + spanZ * 0.75) - maxZ * KY;
      s += '<polygon points="' + pts([[fxF + foldW, fyF], [fxF + foldW + tF * KX, fyF - tF * KY], [fxF + foldW + tF * KX, fyF - tF * KY + fh2], [fxF + foldW, fyF + fh2]]) + '" fill="url(#' + id + 'side)"/>';
      s += '<polygon points="' + pts([[fxF, fyF], [fxF + foldW, fyF], [fxF + foldW + tF * KX, fyF - tF * KY], [fxF + tF * KX, fyF - tF * KY]]) + '" fill="url(#' + id + 'top)"/>';
      s += wallMarkup(id + 'fold', spanX, spanZ, 'matrix(' + scF.toFixed(4) + ',0,0,' + scF.toFixed(4) + ',' + f(fxF) + ',' + f(fyF) + ')', 'url(#' + id + 'front)', o, decorate, pal, true);
      var hcx = fxF + foldW / 2 + tF * KX / 2, hcy = fyF - tF * KY / 2;
      s += '<path d="M' + f(hcx - foldW * 0.16) + ',' + f(hcy) + 'v' + f(-foldW * 0.08) + 'h' + f(foldW * 0.32) + 'v' + f(foldW * 0.08) + '" fill="none" stroke="#2b2c2f" stroke-width="' + f(Math.max(5, foldW * 0.035)) + '" stroke-linejoin="round"/>';
      s += '<text class="fp-caption" x="' + f(fxF + foldW / 2) + '" y="' + f(fyF + fh2 + foldW * 0.16) + '" text-anchor="middle" font-family="system-ui, Segoe UI, Arial, sans-serif" font-size="' + f(Math.max(34, foldW * 0.2)) + '" fill="#b9b2a8">сгънат</text>';
    }

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

    // капак (затворен – мангалите)
    if (closedLid) {
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
      // термометър на капака
      if (ex.thermometer) {
        var tc = P(cx, yl - t / 2, minZ - 14);
        s += '<circle cx="' + f(tc[0]) + '" cy="' + f(tc[1]) + '" r="22" fill="#f4f1ec" stroke="#3b3f44" stroke-width="5"/>' +
          line(tc, [tc[0] + 11, tc[1] - 11], 'stroke="#c0392b" stroke-width="3" stroke-linecap="round"') + '<circle cx="' + f(tc[0]) + '" cy="' + f(tc[1]) + '" r="3" fill="#3b3f44"/>';
      }
    }

    // страничен плот вдясно
    if (tableW) s += sideTableMarkup(id, pal, maxX, 1, tableW, top, H, cz, minZ, maxZ);

    // куки за прибори: под предния ръб на десния плот или на дясната стена
    if (ex.hooks) {
      for (var hk = 0; hk < 3; hk++) {
        var hx = tableW ? maxX + tableW * (0.3 + hk * 0.2) : maxX + 4, hz = tableW ? minZ : minZ + spanZ * (0.25 + hk * 0.22);
        var hy = tableW ? top - 22 : top - 25, h0 = P(hx, hy, hz), h1b = P(hx, hy - 50, hz);
        s += line(h0, h1b, 'stroke="#8b9096" stroke-width="5" stroke-linecap="round"') +
          '<path d="M' + f(h1b[0]) + ',' + f(h1b[1]) + 'q0,16 -12,14" fill="none" stroke="#8b9096" stroke-width="5" stroke-linecap="round"/>';
      }
    }

    // рамо за котле
    if (ex.arm) {
      var zA = round ? 0 : minZ + spanZ * 0.78, xA = maxX + 28;
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
    var SHAPES = { trapezoid: renderTrapezoid, pedestal: renderPedestal, cross: renderCross, ringbowl: renderRingBowl, plancha: renderPlancha, plancha_sq: renderPlancha };
    var r = (SHAPES[o.model.shape] || renderPrism)(o);
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

  /** Тяло на кръгла купа (център 0,0): долна обвивка на нивата + задната дъга на ръба */
  function bowlBody(id, R, yBottom, depth, minFrac) {
    var levels = [];
    for (var i = 0; i <= 16; i++) {
      var t = i / 16;
      levels.push({ y: yBottom + t * depth, r: R * Math.max(minFrac, Math.sqrt(1 - (1 - t) * (1 - t))) });
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
    return poly(env.concat(circ(0, 0, R, yBottom + depth, BACK[0], BACK[1])), 'fill="url(#' + id + 'body)"');
  }

  /** Скара/плоча върху кръгъл отвор (център 0,0) */
  function roundCooking(id, ri, top, ex) {
    var s = '', clip = id + 'rc';
    if (!ex.grate && !ex.griddle) return '';
    s += '<clipPath id="' + clip + '"><polygon points="' + pts(circ(0, 0, ri, top + 4, 0, 2 * Math.PI, 48)) + '"/></clipPath><g clip-path="url(#' + clip + ')">';
    if (ex.griddle) s += '<polygon points="' + pts([P(4, top + 6, -ri), P(ri, top + 6, -ri), P(ri, top + 6, ri), P(4, top + 6, ri)]) + '" fill="#2f3033" stroke="#55585d" stroke-width="4"/>';
    if (ex.grate) {
      for (var i = 1; i <= 7; i++) {
        var z = -ri + 2 * ri * i / 8;
        s += line(P(-ri, top + 8, z), P(ri, top + 8, z), 'stroke="#c9ced3" stroke-width="5" stroke-linecap="round"');
      }
    }
    return s + '</g>';
  }

  /* ---------- Огнище с плоча за печене (етап 4): основа, огън в средата, широка плоча около него (център 0,0) ---------- */
  var PL = { base: 0.62, hole: 0.45, chord: 0.7 }; // огледало на PLANCHA_* в inc/geometry.php
  /** Храна върху плочата: пържоли и зеленчуци в предната дясна част на пръстена */
  function plateFood(id, rMid, y) {
    var s = '';
    [[-70, 1], [-35, 1], [-115, 0], [-10, 0], [-150, 1]].forEach(function (a, i) {
      var ang = a[0] * Math.PI / 180, c = P(rMid * Math.cos(ang), y, rMid * Math.sin(ang));
      if (a[1]) {
        s += '<ellipse cx="' + f(c[0]) + '" cy="' + f(c[1]) + '" rx="56" ry="24" fill="url(#' + id + 'meat)" transform="rotate(' + (i * 17 - 10) + ' ' + f(c[0]) + ' ' + f(c[1]) + ')"/>';
        s += line([c[0] - 30, c[1] - 8], [c[0] + 14, c[1] + 12], 'stroke="#3a1608" stroke-width="5" stroke-opacity=".7"') + line([c[0] - 8, c[1] - 14], [c[0] + 34, c[1] + 6], 'stroke="#3a1608" stroke-width="5" stroke-opacity=".7"');
      } else {
        s += '<ellipse cx="' + f(c[0]) + '" cy="' + f(c[1]) + '" rx="30" ry="14" fill="#5f9e3a" stroke="#3f6e24" stroke-width="4"/><ellipse cx="' + f(c[0]) + '" cy="' + f(c[1]) + '" rx="16" ry="7" fill="#cfe59a"/>';
      }
    });
    return s;
  }
  function renderPlancha(o) {
    var m = o.model, pal = PALETTE[o.finish] || PALETTE.black, id = o.id, ex = o.extras || {}, sq = m.shape === 'plancha_sq';
    var D = m.w * 10, Ht = m.h * 10, Rp = D / 2, Rh = PL.hole * D / 2, hb = PL.base * D / 2, t = Math.max(14, (o.plateMm || 6) * 2);
    var yb = Ht - t, defs = roundDefs(id, pal, hb), s = '', decorate = !!(o.text && o.fit) || !!o.pattern;
    defs += '<linearGradient id="' + id + 'plate" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#55565b"/><stop offset=".45" stop-color="#36373b"/><stop offset="1" stop-color="#1f2023"/></linearGradient>';
    defs += '<clipPath id="' + id + 'hole"><polygon points="' + pts(circ(0, 0, Rh, Ht, 0, 2 * Math.PI, 48)) + '"/></clipPath>';
    var ext = sq ? Rp * (1 + KX) : Rp * Math.sqrt(1 + KX * KX);
    s += '<ellipse cx="0" cy="10" rx="' + f(ext * 0.95) + '" ry="' + f(Rp * KY + 40) + '" fill="url(#' + id + 'shadow)"/>';
    // основа с надписа
    if (sq) {
      s += wallMarkup(id + 'm1', 2 * hb, yb, 'matrix(' + KX.toFixed(4) + ',' + (-KY).toFixed(4) + ',0,1,' + f(sx(hb, -hb)) + ',' + f(-yb + hb * KY) + ')', 'url(#' + id + 'side)', o, false, pal, false);
      s += wallMarkup(id + 'm0', 2 * hb, yb, 'matrix(1,0,0,1,' + f(sx(-hb, -hb)) + ',' + f(-yb + hb * KY) + ')', 'url(#' + id + 'front)', o, decorate, pal, true);
      s += line(P(hb, yb, -hb), P(hb, 0, -hb), 'stroke="' + pal.rim + '" stroke-opacity=".6" stroke-width="2.5"');
    } else {
      s += cylSide(hb, 0, yb, 'fill="url(#' + id + 'body)"');
      if (decorate) {
        var cw = 2 * hb * PL.chord, flat = Object.assign({}, pal, { edge: 'none', brush: false });
        defs += '<linearGradient id="' + id + 'bodyL" gradientUnits="userSpaceOnUse" x1="' + f(-hb * 1.1 + cw / 2) + '" y1="0" x2="' + f(hb * 1.1 + cw / 2) + '" y2="0">' +
          pal.front.concat([pal.side[0], pal.side[1]]).map(function (c, i, a) { return '<stop offset="' + f(i / (a.length - 1)) + '" stop-color="' + c + '"/>'; }).join('') + '</linearGradient>';
        defs += '<clipPath id="' + id + 'base">' + cylSide(hb, 0, yb, '') + '</clipPath>';
        s += '<g clip-path="url(#' + id + 'base)">' + wallMarkup(id + 'm0', cw, yb, 'matrix(1,0,0,1,' + f(-cw / 2) + ',' + f(-yb + hb * KY / Math.sqrt(1 + KX * KX)) + ')', 'url(#' + id + 'bodyL)', o, true, flat, true) + '</g>';
      }
    }
    // плоча: дебелина (видима според избора 6/8/10 мм) и горна повърхност с отвор
    var outer, plateTop;
    if (sq) {
      s += poly([P(-Rp, Ht, -Rp), P(Rp, Ht, -Rp), P(Rp, yb, -Rp), P(-Rp, yb, -Rp)], 'fill="#26272a"');
      s += poly([P(Rp, Ht, -Rp), P(Rp, Ht, Rp), P(Rp, yb, Rp), P(Rp, yb, -Rp)], 'fill="#1c1d1f"');
      outer = [P(-Rp, Ht, -Rp), P(Rp, Ht, -Rp), P(Rp, Ht, Rp), P(-Rp, Ht, Rp)];
    } else {
      s += cylSide(Rp, yb, Ht, 'fill="#26272a"');
      outer = circ(0, 0, Rp, Ht, 0, 2 * Math.PI, 72);
    }
    var hole = circ(0, 0, Rh, Ht, 0, 2 * Math.PI, 48);
    plateTop = 'M' + outer.map(function (p) { return f(p[0]) + ',' + f(p[1]); }).join('L') + 'ZM' + hole.map(function (p) { return f(p[0]) + ',' + f(p[1]); }).join('L') + 'Z';
    s += '<path d="' + plateTop + '" fill="url(#' + id + 'plate)" fill-rule="evenodd" stroke="#5c5d62" stroke-width="3"/>';
    s += '<path d="' + plateTop + '" fill="none" stroke="#fff" stroke-opacity=".08" stroke-width="18" fill-rule="evenodd"/>';
    // отвор: вътрешна стена, жар, огън
    var dep = Math.min(220, Ht * 0.3);
    s += '<g clip-path="url(#' + id + 'hole)">' + poly(circ(0, 0, Rh, Ht, 0, 2 * Math.PI, 48), 'fill="url(#' + id + 'inBack)"') +
      poly(circ(0, 0, Rh, Ht, BACK[0], BACK[1]).concat(circ(0, 0, Rh, Ht - dep, BACK[0], BACK[1]).reverse()), 'fill="url(#' + id + 'inBack)"') + '</g>';
    s += embersMarkup(id, -Rh, Rh, -Rh, Rh, Ht - dep, id + 'hole');
    s += flamesMarkup(0, P(0, Ht, -Rh * 0.3)[1], Rh * 1.3, Rh * 1.15, id);
    s += poly(hole, 'fill="none" stroke="#6a6b70" stroke-width="5"');
    if (ex.grate) s += roundCooking(id, Rh, Ht, { grate: true });
    s += plateFood(id, (Rp + Rh) / 2 + (sq ? 20 : 0), Ht + 6);
    // куки под ръба на плочата вдясно
    if (ex.hooks) {
      for (var k = 0; k < 3; k++) {
        var hz = -Rp * 0.5 + k * Rp * 0.4, hx = sq ? Rp : Math.sqrt(Math.max(0, Rp * Rp - hz * hz)) * 0.98;
        var h0 = P(hx, yb, hz), h1 = P(hx, yb - 60, hz);
        s += line(h0, h1, 'stroke="#8b9096" stroke-width="5" stroke-linecap="round"') + '<path d="M' + f(h1[0]) + ',' + f(h1[1]) + 'q0,16 -12,14" fill="none" stroke="#8b9096" stroke-width="5" stroke-linecap="round"/>';
      }
    }
    if (ex.tools) s += toolsMarkup(sq ? Rp : Rp * 0.98, 1, -Rp * 0.35, Ht);
    var vx = -ext - 60, vy = -(Ht + Rh * 1.25) - Rp * KY - 50;
    return { viewBox: [vx, vy, 2 * ext + 120 + (ex.tools ? 190 : 0), (sq ? hb * KY : hb * KY) + 70 - vy], defs: defs, body: s };
  }

  /* ---------- Трапецовидно огнище: квадратно дъно, стени наклонени навън ---------- */
  function renderTrapezoid(o) {
    var m = o.model, pal = PALETTE[o.finish] || PALETTE.inox, id = o.id, ex = o.extras || {};
    var W = m.w * 10, H = m.h * 10, Lg = (o.legCm || 0) * 10, top = Lg + H, t = 0.22 * H;
    var defs = makeDefs(id, pal), s = '';
    var topO = [[-t, -t], [W + t, -t], [W + t, W + t], [-t, W + t]], topPoly = topO.map(function (p) { return P(p[0], top, p[1]); });
    defs += '<clipPath id="' + id + 'open"><polygon points="' + pts(topPoly) + '"/></clipPath>';
    var fireH = W * 0.72, up = Math.max(fireH, ex.lid ? 60 : 0);
    var sc = P(W / 2, 0, W / 2);
    s += '<ellipse cx="' + f(sc[0]) + '" cy="' + f(sc[1] + 8) + '" rx="' + f(W * 0.85) + '" ry="' + f(W * KY + 50) + '" fill="url(#' + id + 'shadow)"/>';
    s += legsMarkup([[27, 27], [W - 27, 27], [W - 27, W - 27], [27, W - 27]], Lg, pal, '');
    // вътрешност
    s += '<g clip-path="url(#' + id + 'open)"><polygon points="' + pts(topPoly) + '" fill="' + pal.inLeft + '"/>' +
      '<polygon points="' + pts([P(-t, top, W + t), P(W + t, top, W + t), P(W, Lg, W), P(0, Lg, W)]) + '" fill="url(#' + id + 'inBack)"/>' +
      '<polygon points="' + pts([P(-t, top, -t), P(-t, top, W + t), P(0, Lg, W), P(0, Lg, 0)]) + '" fill="' + pal.inLeft + '"/>';
    if (!ex.lid) s += '<ellipse class="fp-glow" cx="' + f(P(W / 2, top, W * 0.3)[0]) + '" cy="' + f(P(W / 2, top, W * 0.3)[1]) + '" rx="' + f(W * 0.7) + '" ry="' + f(W * KY * 1.4) + '" fill="url(#' + id + 'inGlow)"/>';
    if (ex.grate && !ex.lid) {
      for (var i = 1; i <= 8; i++) {
        var z = -t + (W + 2 * t) * i / 9;
        s += line(P(-t, top + 3, z), P(W + t, top + 3, z), 'stroke="#c9ced3" stroke-width="5" stroke-linecap="round"');
      }
    }
    if (ex.griddle && !ex.lid) s += '<polygon points="' + pts([P(W / 2 + 6, top + 6, -t + 8), P(W + t - 8, top + 6, -t + 8), P(W + t - 8, top + 6, W + t - 8), P(W / 2 + 6, top + 6, W + t - 8)]) + '" fill="#2f3033" stroke="#55585d" stroke-width="4"/>';
    s += '</g>';
    if (!ex.lid) {
      var fcx = ex.griddle ? W * 0.28 : W / 2, fc = P(fcx, top, W / 2), frontRimY = P(0, top, -t)[1];
      s += flamesMarkup(fc[0], frontRimY + 30, W * (ex.griddle ? 0.4 : 0.7), fireH + (frontRimY - fc[1]) + 30, id);
    }
    // предна наклонена стена (с надпис): локални координати u = 0..W+2t, v = 0..H
    var tr = 'matrix(1,0,' + (t * KX / H).toFixed(4) + ',' + (1 - t * KY / H).toFixed(4) + ',' + f(-t - t * KX) + ',' + f(-top + t * KY) + ')';
    var decorate = !!(o.text && o.fit) || !!o.pattern;
    s += wallMarkup(id + 'm0', W + 2 * t, H, tr, 'url(#' + id + 'front)', o, decorate, pal, true, [[0, 0], [W + 2 * t, 0], [W + t, H], [t, H]]);
    // дясна наклонена стена
    s += '<polygon points="' + pts([P(W + t, top, -t), P(W + t, top, W + t), P(W, Lg, W), P(W, Lg, 0)]) + '" fill="url(#' + id + 'side)" stroke="' + pal.edge + '" stroke-width="2"/>';
    // ръбове
    s += '<polyline points="' + pts([P(W + t, top, W + t), P(-t, top, W + t), P(-t, top, -t)]) + '" fill="none" stroke="' + pal.rim + '" stroke-opacity=".55" stroke-width="3"/>';
    s += '<polyline points="' + pts([P(-t, top, -t), P(W + t, top, -t), P(W + t, top, W + t)]) + '" fill="none" stroke="' + pal.rim + '" stroke-width="5" stroke-linejoin="round"/>';
    s += line(P(W + t, top, -t), P(W, Lg, 0), 'stroke="' + pal.rim + '" stroke-opacity=".7" stroke-width="2.5"');
    if (ex.lid) {
      var yl = top + 4, k = 10;
      s += '<polygon points="' + pts([P(-t - k, yl, -t - k), P(W + t + k, yl, -t - k), P(W + t + k, yl, W + t + k), P(-t - k, yl, W + t + k)]) + '" fill="url(#' + id + 'top)"/>';
      s += '<polygon points="' + pts([P(-t - k, yl, -t - k), P(W + t + k, yl, -t - k), P(W + t + k, yl - 26, -t - k), P(-t - k, yl - 26, -t - k)]) + '" fill="url(#' + id + 'front)"/>';
      var h1 = P(W / 2 - 70, yl, W / 2), h2 = P(W / 2 + 70, yl, W / 2);
      s += '<path d="M' + f(h1[0]) + ',' + f(h1[1]) + 'C' + f(h1[0]) + ',' + f(h1[1] - 55) + ' ' + f(h2[0]) + ',' + f(h2[1] - 55) + ' ' + f(h2[0]) + ',' + f(h2[1]) + '" fill="none" stroke="#3b3f44" stroke-width="12" stroke-linecap="round"/>';
    }
    var vx = sx(-t, -t) - 60, vy = -(top + up) - (W + t) * KY - 20;
    return { viewBox: [vx, vy, sx(W + t, W + t) + 60 - vx, 60 - vy], defs: defs, body: s };
  }

  /* ---------- Огнище на постамент: кутия с отвор за дърва и плитка купа отгоре (център 0,0) ---------- */
  function renderPedestal(o) {
    var m = o.model, pal = PALETTE[o.finish] || PALETTE.inox, id = o.id, ex = o.extras || {};
    var W = m.w * 10, H = m.h * 10, hw = W / 2, R = 0.45 * W, Hb = 0.16 * W, yb = H + 18, top = yb + Hb, fh = R * 1.05;
    var defs = roundDefs(id, pal, R), s = '';
    s += '<ellipse cx="' + f(P(0, 0, 0)[0]) + '" cy="8" rx="' + f(W * 0.8) + '" ry="' + f(hw * KY * 2 + 40) + '" fill="url(#' + id + 'shadow)"/>';
    // стени: предна (с надпис над отвора) и дясна
    var decorate = !!(o.text && o.fit) || !!o.pattern;
    s += wallMarkup(id + 'm0', W, H, 'matrix(1,0,0,1,' + f(-hw - hw * KX) + ',' + f(-H + hw * KY) + ')', 'url(#' + id + 'front)', o, decorate, pal, true);
    s += wallMarkup(id + 'm1', W, H, 'matrix(' + KX.toFixed(4) + ',' + (-KY).toFixed(4) + ',0,1,' + f(hw - hw * KX) + ',' + f(-H + hw * KY) + ')', 'url(#' + id + 'side)', o, false, pal, false);
    // отвор за дърва с цепеници (в долната част на предната стена)
    var ox0 = W * 0.14, ow = W * 0.72, oy0 = H * 0.56, oh = H * 0.38, lr = Math.min(oh * 0.2, ow * 0.09);
    var logs = '<rect x="' + f(ox0) + '" y="' + f(oy0) + '" width="' + f(ow) + '" height="' + f(oh) + '" fill="#141210"/>';
    for (var row = 0; row < 3; row++) {
      for (var i = 0; i < Math.floor(ow / (lr * 2.2)) - (row % 2); i++) {
        var lx = ox0 + lr * 1.15 + i * lr * 2.2 + (row % 2 ? lr * 1.1 : 0), ly = oy0 + oh - lr * 1.05 - row * lr * 1.85;
        logs += '<circle cx="' + f(lx) + '" cy="' + f(ly) + '" r="' + f(lr) + '" fill="#8a5a33" stroke="#5c3a1e" stroke-width="3"/><circle cx="' + f(lx) + '" cy="' + f(ly) + '" r="' + f(lr * 0.45) + '" fill="none" stroke="#b07a45" stroke-width="2"/>';
      }
    }
    s += '<g transform="matrix(1,0,0,1,' + f(-hw - hw * KX) + ',' + f(-H + hw * KY) + ')">' + logs +
      '<rect x="' + f(ox0) + '" y="' + f(oy0) + '" width="' + f(ow) + '" height="' + f(oh) + '" fill="none" stroke="' + pal.edge + '" stroke-width="3"/></g>';
    // плот
    s += '<polygon points="' + pts([P(-hw, H, -hw), P(hw, H, -hw), P(hw, H, hw), P(-hw, H, hw)]) + '" fill="url(#' + id + 'top)" stroke="' + pal.rim + '" stroke-width="3"/>';
    // купа
    s += cylSide(R * 0.5, H, yb, 'fill="url(#' + id + 'side)"');
    s += bowlBody(id, R, yb, Hb, 0.55);
    s += roundTop(id, pal, R, top, fh);
    s += roundCooking(id, R - 28, top, ex);
    var vx = sx(-hw, -hw) - 60;
    var vy = Math.min(-(top + fh) - R * KY - 30, -H - hw * KY - 40);
    return { viewBox: [vx, vy, sx(hw, hw) + 60 - vx, hw * KY + 60 - vy], defs: defs, body: s };
  }

  /* ---------- Кръстато сглобяемо огнище: две плочи на прорези (център 0,0) ---------- */
  function renderCross(o) {
    var m = o.model, pal = PALETTE[o.finish] || PALETTE.inox, id = o.id, ex = o.extras || {};
    var W = m.w * 10, H = m.h * 10, hw = W / 2, fh = H * 1.6;
    var defs = makeDefs(id, pal), s = '';
    s += '<ellipse cx="0" cy="8" rx="' + f(W * 0.7) + '" ry="' + f(hw * KY + 50) + '" fill="url(#' + id + 'shadow)"/>';
    // задната половина на напречната плоча (стената x = 0, z = 0..hw)
    s += '<polygon points="' + pts([P(0, H, 0), P(0, H, hw), P(0, 0, hw), P(0, 0, 0)]) + '" fill="url(#' + id + 'side)" stroke="' + pal.edge + '" stroke-width="2"/>';
    // огън в центъра
    var g = P(0, 70, 0);
    s += '<ellipse class="fp-glow" cx="' + f(g[0]) + '" cy="' + f(g[1]) + '" rx="' + f(W * 0.3) + '" ry="' + f(W * 0.05) + '" fill="url(#' + id + 'inGlow)"/>';
    s += flamesMarkup(g[0], g[1] + 10, W * 0.5, fh, id);
    // предна плоча: дясна половина с надписа (предната половина на напречната плоча закрива лявата), лява – гладка
    var decorate = !!(o.text && o.fit) || !!o.pattern;
    s += wallMarkup(id + 'm1', hw, H, 'matrix(1,0,0,1,' + f(-hw) + ',' + f(-H) + ')', 'url(#' + id + 'front)', o, false, pal, false);
    s += wallMarkup(id + 'm0', hw, H, 'matrix(1,0,0,1,0,' + f(-H) + ')', 'url(#' + id + 'front)', o, decorate, pal, true);
    // предната половина на напречната плоча (стената x = 0, z = -hw..0)
    s += '<polygon points="' + pts([P(0, H, -hw), P(0, H, 0), P(0, 0, 0), P(0, 0, -hw)]) + '" fill="url(#' + id + 'side)" stroke="' + pal.edge + '" stroke-width="2"/>';
    s += line(P(0, H, -hw), P(0, H, 0), 'stroke="' + pal.rim + '" stroke-width="4"') + line(P(-hw, H, 0), P(hw, H, 0), 'stroke="' + pal.rim + '" stroke-width="4"');
    if (ex.grate) {
      var gs = hw * 0.75, yy = H + 4;
      s += '<polygon points="' + pts([P(-gs, yy, -gs), P(gs, yy, -gs), P(gs, yy, gs), P(-gs, yy, gs)]) + '" fill="none" stroke="#aeb4ba" stroke-width="7"/>';
      for (var i = 1; i <= 7; i++) {
        var z = -gs + 2 * gs * i / 8;
        s += line(P(-gs, yy, z), P(gs, yy, z), 'stroke="#c9ced3" stroke-width="5" stroke-linecap="round"');
      }
    }
    var vx = sx(-hw, 0) - 60, vy = -(Math.max(H, fh + 30)) - hw * KY - 40;
    return { viewBox: [vx, vy, sx(hw, hw) + 60 - vx, hw * KY + 60 - vy], defs: defs, body: s };
  }

  /* ---------- Кръгла купа с пръстен (купата от шоурума върху пръстен с надпис) ---------- */
  function renderRingBowl(o) {
    var m = o.model, pal = PALETTE[o.finish] || PALETTE.inox, id = o.id, ex = o.extras || {};
    var D = m.w * 10, Ht = m.h * 10, R = D / 2, rr = 0.36 * D, Hr = 0.55 * Ht, Hb = Ht - Hr, fh = R * 0.95, s = '';
    var defs = roundDefs(id, pal, R);
    s += '<ellipse cx="0" cy="8" rx="' + f(R * 0.95) + '" ry="' + f(R * KY + 40) + '" fill="url(#' + id + 'shadow)"/>';
    // пръстен (цилиндър) с плосък участък отпред за надписа
    s += cylSide(rr, 0, Hr, 'fill="url(#' + id + 'body)"');
    var cw = 2 * rr * 0.62, decorate = !!(o.text && o.fit) || !!o.pattern;
    if (decorate) {
      // плосък участък с надписа: същият градиент като цилиндъра (изместен с cw/2) и изрязан по контура на пръстена
      var flat = Object.assign({}, pal, { edge: 'none', brush: false });
      defs += '<linearGradient id="' + id + 'bodyL" gradientUnits="userSpaceOnUse" x1="' + f(-R * 1.1 + cw / 2) + '" y1="0" x2="' + f(R * 1.1 + cw / 2) + '" y2="0">' +
        pal.front.concat([pal.side[0], pal.side[1]]).map(function (c, i, a) { return '<stop offset="' + f(i / (a.length - 1)) + '" stop-color="' + c + '"/>'; }).join('') + '</linearGradient>';
      defs += '<clipPath id="' + id + 'ring">' + cylSide(rr, 0, Hr, '') + '</clipPath>';
      s += '<g clip-path="url(#' + id + 'ring)">' + wallMarkup(id + 'm0', cw, Hr, 'matrix(1,0,0,1,' + f(-cw / 2) + ',' + f(-Hr + rr * KY / Math.sqrt(1 + KX * KX)) + ')', 'url(#' + id + 'bodyL)', o, true, flat, true) + '</g>';
    }
    s += poly(circ(0, 0, rr, Hr, FRONT[0], FRONT[1]), 'fill="none" stroke="' + pal.rim + '" stroke-opacity=".6" stroke-width="3"');
    // купа върху пръстена
    s += bowlBody(id, R, Hr, Hb, rr / R);
    s += roundTop(id, pal, R, Ht, fh);
    s += roundCooking(id, R - 28, Ht, ex);
    var vx = -R * 1.12 - 30;
    return { viewBox: [vx, -(Ht + fh) - R * KY - 40, -vx * 2, Ht + fh + R * KY * 2 + 90], defs: defs, body: s };
  }

  function renderBowl(o) {
    var pal = PALETTE[o.finish] || PALETTE.inox, id = o.id, R = o.d * 10 / 2;
    var Hp = 250, Hb = R * 0.6, top = Hp + Hb, fh = R * 0.95, s = '';
    s += '<ellipse cx="0" cy="8" rx="' + f(R * 0.75) + '" ry="' + f(R * KY + 30) + '" fill="url(#' + id + 'shadow)"/>';
    // крак и основа
    s += cylSide(R * 0.42, 0, 26, 'fill="url(#' + id + 'side)"');
    s += poly(circ(0, 0, R * 0.42, 26, 0, 2 * Math.PI), 'fill="url(#' + id + 'top)"');
    s += cylSide(R * 0.2, 26, Hp + 30, 'fill="url(#' + id + 'body)"');
    s += bowlBody(id, R, Hp, Hb, 0.3);
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

  global.FPRender = { render: render, renderShowcase: renderShowcase, patterns: PATTERNS, perf: perfPath, KX: KX, KY: KY };
})(window);
