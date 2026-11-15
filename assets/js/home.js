/* Начална страница: голям изглед и галерия, нарисувани със същия рендер като конфигуратора */
(function () {
  'use strict';
  var CAT = window.FP_CATALOG, G = window.FPGeo, R = window.FPRender;
  if (!CAT || !CAT.models.length || !R) return;
  var L = CAT.layout, FONTS = CAT.fonts, MIN = CAT.settings.min_letter_2mm;

  // Примерни надписи и орнаменти за галерията (само за показ)
  var SAMPLES = ['ИВАНОВИ', 'ВИЛА', 'ОГЪН', 'ПЕТРОВИ', 'СКАРА'];
  var PATTERNS = ['flames', 'pines', 'mountains', 'shevitsa'];

  function scene(m, i, opts) {
    opts = opts || {};
    var pattern = opts.pattern !== undefined ? opts.pattern : PATTERNS[i % PATTERNS.length];
    var sides = opts.sides || 2, walls = G.decoratedWalls(m, sides), text = '', fit = null;
    var list = opts.text ? [opts.text] : SAMPLES.slice(i).concat(SAMPLES.slice(0, i));
    for (var k = 0; k < list.length; k++) {
      var f = G.textFit(list[k], 'russo', walls, !!pattern, FONTS, L);
      if (f && f.letter_mm >= MIN) { text = list[k]; fit = f; break; }
    }
    return R.render({
      model: m, finish: opts.finish || (i % 3 === 1 ? 'black' : 'inox'), legCm: m.shape === 'grill' ? 10 : 0,
      text: text, fit: fit, fontKey: 'russo', fonts: FONTS, pattern: pattern, decoratedCount: walls.length,
      extras: m.shape === 'grill' ? { grate: true } : {}, layout: L, id: (opts.id || 'g') + i, title: m.name
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
