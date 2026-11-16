/* Начална страница: голям изглед и галерия, нарисувани със същия рендер като конфигуратора */
(function () {
  'use strict';
  var CAT = window.FP_CATALOG, G = window.FPGeo, R = window.FPRender;
  if (!CAT || !CAT.models.length || !R) return;
  var L = CAT.layout, FONTS = CAT.fonts, MIN = CAT.settings.min_letter_2mm;

  // Примерни надписи и орнаменти за галерията (само за показ)
  var SAMPLES = ['ИВАНОВИ', 'ВИЛА', 'ОГЪН', 'ПЕТРОВИ', 'СКАРА'];
  var PATTERNS = ['flames', 'pines', 'mountains', 'shevitsa'];

  /** Подразбиращата се стойност на група за модела (напр. материал, крачета) */
  function def(m, group, key, fallback) {
    for (var k = 0; k < m.options.length; k++) {
      var o = CAT.options[m.options[k].id];
      if (o && o.group === group && m.options[k].default) return o.render[key] !== undefined ? o.render[key] : fallback;
    }
    return fallback;
  }

  /** Екстрите, включени в модела (капакът и скарата на барбекюто и т.н.) */
  function included(m) {
    var ex = {};
    m.options.forEach(function (mo) {
      var o = CAT.options[mo.id];
      if (o && o.group === 'extras' && mo.default) ex[o.render.extra || o.code] = true;
    });
    return ex;
  }

  function scene(m, i, opts) {
    opts = opts || {};
    var simple = ['pedestal', 'cross', 'ringbowl', 'trapezoid', 'plancha', 'plancha_sq', 'insert', 'suitcase', 'bbq', 'grill', 'tabletop', 'cheverme'].indexOf(m.shape) >= 0;
    var pattern = opts.pattern !== undefined ? opts.pattern : simple ? null : PATTERNS[i % PATTERNS.length];
    var sides = opts.sides || (G.modelWalls(m).length > 1 ? 2 : 1), walls = G.decoratedWalls(m, sides), text = '', fit = null;
    var list = opts.text ? [opts.text] : SAMPLES.slice(i).concat(SAMPLES.slice(0, i));
    for (var k = 0; k < list.length; k++) {
      var f = G.textFit(list[k], 'russo', walls, !!pattern, FONTS, L, m.shape);
      if (f && f.letter_mm >= MIN) { text = list[k]; fit = f; break; }
    }
    return R.render({
      model: m, finish: opts.finish || def(m, 'material', 'finish', 'inox'), legCm: def(m, 'legs', 'leg_cm', 0),
      text: text, fit: fit, fontKey: 'russo', fonts: FONTS, pattern: pattern, decoratedCount: walls.length,
      extras: included(m), layout: L, id: (opts.id || 'g') + i, title: m.name
    });
  }

  var hero = document.getElementById('heroVisual');
  if (hero) hero.innerHTML = scene(CAT.models[0], 0, { id: 'hero', text: 'ИВАНОВИ', pattern: 'flames', finish: 'inox' });

  Array.prototype.forEach.call(document.querySelectorAll('.g-card'), function (card, i) {
    var m = CAT.models.filter(function (x) { return x.code === card.getAttribute('data-model'); })[0];
    if (m) card.querySelector('.g-img').innerHTML = scene(m, i);
  });

  // прерисуване след зареждане на шрифта, за да е точен надписът
  if (document.fonts && document.fonts.load) {
    document.fonts.load("40px 'fp-russo'", 'АБВ').then(function () {
      if (hero) hero.innerHTML = scene(CAT.models[0], 0, { id: 'hero', text: 'ИВАНОВИ', pattern: 'flames', finish: 'inox' });
    }, function () { /* */ });
  }
})();
