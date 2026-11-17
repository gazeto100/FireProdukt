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

  var state = { type: null, model: null, custom: null, opts: {}, extras: [], text: '', qty: 1 };
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
  /** Моделът с размерите по избор (ако са зададени) – той се рисува, проверява и смята */
  function effModel() {
    var m = model(), f = formOf(m);
    if (!state.custom || !f || !f.custom) return m;
    return Object.assign({}, m, { w: state.custom.w, d: state.custom.d, h: state.custom.h, sheet_area_m2: null, custom: true });
  }
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
    var m = model(), mo = modelOptions(m), fm = formOf(m);
    // форма само по мярка: размерът по избор е задължителен
    if (fm && fm.custom_only && fm.custom && !state.custom) state.custom = G.clampCustom(fm.custom, { w: m.w, d: m.d, h: m.h }, m.shape).dims;
    CAT.groups.forEach(function (g) {
      var list = mo[g.code] || [];
      if (g.multi) {
        // включените в предишния модел екстри не се пренасят като избор на клиента
        var prevInc = state.autoInc || [];
        state.extras = state.extras.filter(function (c) { return prevInc.indexOf(c) < 0 && list.some(function (x) { return x.opt.code === c; }); });
        // включените в модела екстри (напр. капакът на барбекюто) са винаги избрани
        state.autoInc = [];
        list.forEach(function (x) {
          if (!x.mo.default) return;
          state.autoInc.push(x.opt.code);
          if (state.extras.indexOf(x.opt.code) < 0) state.extras.push(x.opt.code);
        });
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
    var m = Object.assign({}, effModel(), { text_place: renderParam('text_place', 'text_place', '') });
    var lines = G.textLines(state.text, L);
    var text = lines.join('\n');
    var fontKey = renderParam('font', 'font', 'russo');
    var sides = renderParam('sides', 'sides', 1);
    var pattern = renderParam('ornament', 'pattern', null);
    var walls = G.decoratedWalls(m, sides);
    var minMm = G.minLetterFor(parseFloat(renderParam('material', 'thickness_mm', 2)) || 2, SET);
    var res = { m: m, text: text, lines: lines, fontKey: fontKey, sides: sides, pattern: pattern, walls: walls, fit: null, error: '', minMm: minMm };
    var hasOrn = !!pattern && pattern !== 'perf'; // перфорацията е около надписа и не заема отделна зона
    if (text) {
      // единственото правило, което спира надписа, е минималната височина на буквата
      var chk = G.checkText(lines, fontKey, walls, hasOrn, minMm, FONTS, L, m.shape);
      res.fit = chk.fit;
      res.error = chk.error || '';
      res.errorType = chk.kind === 'ok' ? '' : chk.kind;
      res.split = chk.split;
      if (chk.kind === 'small') res.betterFont = bestFont(m, lines, walls, hasOrn, fontKey);
    }
    res.perfDensity = renderParam('ornament', 'density', 'dense');
    var sel = selected(m);
    var isDec = !!text || !!pattern;
    res.decoratedCount = isDec ? walls.length : 0;
    var fm = formOf(m);
    res.price = m.custom ? G.calcPriceCustom(m, fm.custom, sel, walls.length, isDec, state.qty, SET) : G.calcPrice(m, sel, walls.length, isDec, state.qty);
    res.weight = G.calcWeight(m, sel);
    // бележки за офертата: палет (за всеки модел над прага) и две части (размер по избор извън листа)
    res.pallet = res.weight.kg > SET.pallet_kg;
    res.sheet = m.custom ? G.sheetFit(m, SET, fm.construction) : { fits: true, part: '' };
    if (m.project) { res.price = null; res.pallet = false; }
    res.segments = G.benchSegments(m, SET);
    res.legCm = renderParam('legs', 'leg_cm', 0);
    res.finish = renderParam('material', 'finish', 'inox');
    return res;
  }

  /** Шрифтът (от позволените за модела), с който буквите стават най-високи */
  function bestFont(m, text, walls, hasOrn, current) {
    var best = null, bestMm = -1, curMm = -1;
    (modelOptions(m).font || []).forEach(function (x) {
      var fit = G.textFit(text, x.opt.render.font, walls, hasOrn, FONTS, L, m.shape);
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
      list.forEach(function (x) { if (x.opt.code === c) ex[x.opt.render.extra || c] = x.opt.render.count || true; });
    });
    var o = {
      model: r.m, finish: r.finish, legCm: r.legCm, text: r.error && !r.fit ? '' : r.text, fit: r.fit, fontKey: r.fontKey, fonts: FONTS,
      pattern: r.pattern, perfDensity: r.perfDensity, decoratedCount: r.walls.length, extras: ex, layout: L, id: 'pv', title: r.m.name,
      textPlace: r.m.text_place, plateMm: parseFloat(renderParam('plate', 'plate_mm', 0)) || 0, segments: r.segments
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

  function ornamentIcon(render) {
    var pattern = render.pattern;
    if (!pattern) return '<span class="sw sw-none"></span>';
    var d = pattern === 'perf' ? window.FPRender.perf(0, 0, 400, 160, render.density) : window.FPRender.patterns[pattern](4, 6, 92, 28);
    return '<svg class="sw sw-orn" viewBox="' + (pattern === 'perf' ? '0 0 400 160' : '0 0 100 40') + '" aria-hidden="true"><path d="' + d + '"/></svg>';
  }

  /* ---------- форми и размери ---------- */
  function formOf(m) { return CAT.forms.filter(function (f) { return f.code === m.form; })[0]; }
  function formsOfType(t) { return CAT.forms.filter(function (f) { return f.type === t; }); }
  function sizeText(m) {
    var round = G.isRound(m.shape);
    return round ? 'Ø' + num(m.w) + ' × ' + num(m.h) + ' см' : num(m.w) + ' × ' + num(m.d) + ' × ' + num(m.h) + ' см';
  }
  /** „от 40 до 60 см“ / „Ø60 см“ – по най-голямата страна */
  function formRange(f) {
    var ms = f.models.map(function (c) { return CAT.models.filter(function (m) { return m.code === c; })[0]; }).filter(Boolean);
    var ws = ms.map(function (m) { return m.w; }), mn = Math.min.apply(null, ws), mx = Math.max.apply(null, ws);
    if (f.custom_only && f.custom) return 'по мярка, ' + num(f.custom.w_min) + '–' + num(f.custom.w_max) + ' см';
    var pre = G.isRound(f.shape) ? 'Ø' : '';
    return mn === mx ? pre + num(mn) + ' см' : 'от ' + pre + num(mn) + ' до ' + pre + num(mx) + ' см';
  }

  /* ---------- правила между екстрите (от данните) ---------- */
  function optByCode(group, code) {
    for (var id in CAT.options) { if (CAT.options[id].group === group && CAT.options[id].code === code) return CAT.options[id]; }
    return null;
  }
  function isSelected(o) {
    return groupsByCode[o.group] && groupsByCode[o.group].multi ? state.extras.indexOf(o.code) >= 0 : state.opts[o.group] === o.code;
  }
  /** Моделът предлага ли опцията (изискване към опция, която моделът няма, не важи – напр. поставка за дърва без крачета) */
  function offered(o) {
    var m = model();
    return !!m && m.options.some(function (mo) { return mo.id === o.id; });
  }
  /** Вид „по проект“ (обекти): цена по оферта, последната стъпка е проектната форма */
  function isProject() { var m = model(); return !!(m && m.project); }
  /** Неизпълнено изискване на опцията (напр. термометър без капак) -> целевата опция, иначе null */
  function missingRequirement(o) {
    var miss = null;
    (CAT.rules || []).forEach(function (r) {
      if (r.option === o.id && r.kind === 'requires' && CAT.options[r.target] && offered(CAT.options[r.target]) && !isSelected(CAT.options[r.target])) miss = CAT.options[r.target];
    });
    return miss;
  }
  /** Опциите, които се изключват с дадената (в двете посоки) */
  function excludedBy(o) {
    var out = [];
    (CAT.rules || []).forEach(function (r) {
      if (r.kind !== 'excludes') return;
      if (r.option === o.id && CAT.options[r.target]) out.push(CAT.options[r.target]);
      if (r.target === o.id && CAT.options[r.option]) out.push(CAT.options[r.option]);
    });
    return out;
  }
  /** Махa избрани екстри, чието изискване вече не е изпълнено; връща имената им */
  function dropUnmet() {
    var dropped = [], changed = true;
    while (changed) {
      changed = false;
      state.extras.slice().forEach(function (c) {
        var o = optByCode('extras', c);
        if (o && missingRequirement(o)) { state.extras = state.extras.filter(function (x) { return x !== c; }); dropped.push(o.name); changed = true; }
      });
    }
    return dropped;
  }
  /** Полета за размер по избор: плъзгач + числово поле за всяко измерение според формата */
  function dimKeys(lim) {
    var cur = model();
    if (lim.mode !== 'wdh' && cur && !G.isRound(cur.shape)) return [['w', 'Ширина и дълбочина'], ['h', 'Височина']];
    if (lim.mode === 'dh') return [['w', 'Диаметър'], ['h', 'Височина']];
    if (lim.mode === 'wh') return [['w', 'Ширина и дълбочина'], ['h', 'Височина']];
    return [['w', 'Ширина'], ['d', 'Дълбочина'], ['h', 'Височина']];
  }
  function customFields(lim) {
    var h = '<div class="custom-size" id="customBox">';
    dimKeys(lim).forEach(function (k) {
      var key = k[0], v = state.custom[key];
      h += '<div class="dim-row"><label for="num-' + key + '">' + esc(k[1]) + '</label>' +
        '<input type="range" id="rng-' + key + '" data-dim="' + key + '" min="' + lim[key + '_min'] + '" max="' + lim[key + '_max'] + '" step="' + lim.step + '" value="' + v + '" aria-label="' + esc(k[1]) + '">' +
        '<span class="dim-num"><input type="number" id="num-' + key + '" data-dim="' + key + '" min="' + lim[key + '_min'] + '" max="' + lim[key + '_max'] + '" step="' + lim.step + '" value="' + v + '" inputmode="numeric"> см</span></div>';
    });
    h += '<p class="custom-msg" id="customMsg" role="status" hidden></p>';
    h += '<p class="step-hint">Граници: ' + dimKeys(lim).map(function (k) { return k[1].toLowerCase() + ' ' + num(lim[k[0] + '_min']) + '–' + num(lim[k[0] + '_max']) + ' см'; }).join(', ') +
      '; стъпка ' + num(lim.step) + ' см. Изгледът и надписът се преизчисляват веднага.</p></div>';
    return h;
  }
  function syncDims() {
    ['w', 'd', 'h'].forEach(function (k) {
      var r = $('#rng-' + k), n = $('#num-' + k);
      if (r) r.value = state.custom[k];
      if (n) n.value = state.custom[k];
    });
  }
  function customMsg(msgs) {
    var el = $('#customMsg');
    if (!el) return;
    el.hidden = !msgs.length;
    el.textContent = msgs.join(' ');
  }

  var SUBGROUPS = [['cook', 'За печене'], ['comfort', 'За удобство'], ['carry', 'За пренасяне'], ['', 'Други']];

  function build() {
    var m = model(), mo = modelOptions(m), html = '';

    // 1. Вид
    if (CAT.types.length) {
      html += stepHtml('st-type', 'Вид', '<div class="opts opts-2 opts-types">' + CAT.types.map(function (t) {
        return optCard('type', t.code, state.type === t.code, t.name, t.hint, 'opt-big');
      }).join('') + '</div>');
    }
    // 2. Форма (карти с рисунка) и 3. Размер (ред от бутони)
    var curForm = formOf(m);
    html += stepHtml('st-form', 'Форма', '<div class="opts opts-models">' + formsOfType(state.type).map(function (f) {
      var thumb = '<span class="thumb" data-thumb="' + esc(f.models[0]) + '"></span>';
      return optCard('form', f.code, curForm && curForm.code === f.code, f.name, formRange(f), 'opt-model', thumb);
    }).join('') + '</div>');
    var sizes = (curForm ? curForm.models : [m.code]).map(function (c) { return CAT.models.filter(function (x) { return x.code === c; })[0]; }).filter(Boolean);
    var lim = curForm && curForm.custom;
    var sizeBody = curForm && curForm.custom_only ? '<p class="step-hint">Изработва се по мярка – задайте размерите на мястото за вграждане.</p>' : '<div class="sizes">' + sizes.map(function (x) {
      return '<label class="size-btn"><input type="radio" name="model" value="' + esc(x.code) + '"' + (!state.custom && state.model === x.code ? ' checked' : '') + '>' +
        '<span><b>' + esc(x.size_label || x.name) + '</b><small>' + esc(sizeText(x)) + '</small></span></label>';
    }).join('') + '</div>';
    if (lim && curForm.custom_only) {
      sizeBody += customFields(lim);
    } else if (lim) {
      sizeBody += '<button type="button" class="link-btn custom-toggle" id="customToggle" aria-expanded="' + (state.custom ? 'true' : 'false') + '">' +
        (state.custom ? 'Обратно към стандартните размери' : 'Размер по избор') + '</button>';
      if (state.custom) sizeBody += customFields(lim);
    }
    html += stepHtml('st-size', 'Размер', sizeBody);
    // 3. Материал
    if (mo.material) {
      html += stepHtml('st-material', groupsByCode.material.name, '<div class="opts">' + mo.material.map(function (x) {
        return optCard('material', x.opt.code, state.opts.material === x.opt.code, x.opt.name, x.opt.hint, '', '<span class="sw sw-' + esc(x.opt.render.finish || 'inox') + '"></span>');
      }).join('') + '</div>');
    }
    // плоча за печене (огнище с плоча)
    if (mo.plate) {
      html += stepHtml('st-plate', groupsByCode.plate.name, '<div class="opts opts-3">' + mo.plate.map(function (x) {
        return optCard('plate', x.opt.code, state.opts.plate === x.opt.code, x.opt.name, x.opt.hint, 'opt-compact');
      }).join('') + '</div>', groupsByCode.plate.hint);
    }
    // 4. Крачета (само ако има избор – при барбекюто и скарата на стойка стойката е част от модела)
    if (mo.legs && mo.legs.length > 1) {
      html += stepHtml('st-legs', groupsByCode.legs.name, '<div class="opts opts-3">' + mo.legs.map(function (x) {
        return optCard('legs', x.opt.code, state.opts.legs === x.opt.code, x.opt.name, x.opt.hint, 'opt-compact');
      }).join('') + '</div>');
    }
    // 5. Надпис
    var body = '<div class="field"><label for="txt">' + (m.project ? 'Име на обекта' : 'Вашият надпис') + ' <span class="muted">(по желание, на един или два реда)</span></label>' +
      '<textarea id="txt" rows="2" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="' + (m.project ? 'напр. ХОТЕЛ ЕЛЕНА' : 'напр. СЕМЕЙСТВО ИВАНОВИ') + '">' + esc(state.text) + '</textarea>' +
      '<p class="txt-status" id="txtStatus" role="status" aria-live="polite"></p>' +
      '<div class="txt-actions"><button type="button" class="btn btn-sm btn-ghost" id="splitBtn" hidden>Раздели на два реда</button>' +
      '<button type="button" class="btn btn-sm btn-ghost" id="narrowBtn" hidden>Пробвай с по-тесния шрифт</button></div>' +
      (m.project ? '<p class="step-hint txt-logo-note">Ако имате лого, прикачете го в последната стъпка. В прегледа се показва името, а логото се поставя във финалния проект.</p>' : '') + '</div>';
    if (mo.font) {
      body += '<p class="sub">Шрифт</p><div class="opts opts-3 fonts">' + mo.font.map(function (x) {
        return '<label class="opt opt-font"><input type="radio" name="font" value="' + esc(x.opt.code) + '"' + (state.opts.font === x.opt.code ? ' checked' : '') +
          '><span class="opt-in"><span class="font-sample" style="font-family:\'fp-' + esc(x.opt.render.font) + '\'">Огън</span><small>' + esc(x.opt.name) + '</small></span></label>';
      }).join('') + '</div>';
    }
    if (mo.text_place && mo.text_place.length > 1) {
      body += '<p class="sub">' + esc(groupsByCode.text_place.name) + '</p><div class="opts opts-2">' + mo.text_place.map(function (x) {
        return optCard('text_place', x.opt.code, state.opts.text_place === x.opt.code, x.opt.name, x.opt.hint, 'opt-compact');
      }).join('') + '</div>';
    }
    if (mo.sides && mo.sides.length > 1) {
      body += '<p class="sub">' + esc(groupsByCode.sides.name) + '</p><div class="opts opts-3">' + mo.sides.map(function (x) {
        var label = x.opt.render.sides === 'all' ? x.opt.name + ' (' + G.modelWalls(m).length + ')' : x.opt.name;
        return optCard('sides', x.opt.code, state.opts.sides === x.opt.code, label, '', 'opt-compact');
      }).join('') + '</div><p class="step-hint">' + esc(groupsByCode.sides.hint || '') + '</p>';
    }
    html += stepHtml('st-text', 'Надпис', body, 'Надписът се побира автоматично. Нов ред – с Enter.');
    // 6. Орнамент
    if (mo.ornament && mo.ornament.length > 1) {
      html += stepHtml('st-ornament', groupsByCode.ornament.name, '<div class="opts opts-orn">' + mo.ornament.map(function (x) {
        return optCard('ornament', x.opt.code, state.opts.ornament === x.opt.code, x.opt.name, '', 'opt-orn', ornamentIcon(x.opt.render));
      }).join('') + '</div>');
    }
    // 7. Екстри – групирани; недостъпните (неизпълнено изискване) са изключени с обяснение
    if (mo.extras) {
      var ebody = '';
      SUBGROUPS.forEach(function (sg) {
        var list = mo.extras.filter(function (x) { return (x.opt.subgroup || '') === sg[0]; });
        if (!list.length) return;
        ebody += '<p class="sub">' + esc(sg[1]) + '</p><div class="opts">' + list.map(function (x) {
          var miss = missingRequirement(x.opt), incl = x.mo.default, hint = incl ? 'Включено в модела' : miss ? 'Само с „' + miss.name + '“' : x.opt.hint;
          return optCard('extras', x.opt.code, incl || state.extras.indexOf(x.opt.code) >= 0, x.opt.name, hint, 'opt-check' + (miss ? ' opt-off' : '') + (incl ? ' opt-incl' : ''), '<span class="tick"></span>', 'checkbox')
            .replace('type="checkbox"', 'type="checkbox"' + (miss || incl ? ' disabled' : ''));
        }).join('') + '</div>';
      });
      ebody += '<p class="extras-note" id="extrasNote" role="status" hidden></p>';
      html += stepHtml('st-extras', groupsByCode.extras.name, ebody, groupsByCode.extras.hint);
    }

    stepsEl.innerHTML = html;
    $('#st-send').hidden = !!m.project;
    $('#st-project').hidden = !m.project;
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
      // материалът по подразбиране на модела (новите огнища са черна стомана)
      var mat = (modelOptions(m).material || []).filter(function (x) { return x.mo.default; })[0];
      var legs = (modelOptions(m).legs || []).filter(function (x) { return x.mo.default; })[0];
      var inc = {};
      (modelOptions(m).extras || []).forEach(function (x) { if (x.mo.default) inc[x.opt.render.extra || x.opt.code] = true; });
      el.innerHTML = window.FPRender.render({ model: m, finish: mat ? mat.opt.render.finish : 'inox', legCm: legs ? legs.opt.render.leg_cm : 0, layout: L, id: 'th' + m.id, extras: inc, pattern: null, decoratedCount: 1 });
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
      var dims = sizeText(m) + (m.custom ? ' <span class="muted">(по избор)</span>' : '');
      if (r.legCm) dims += ' <span class="muted">(+' + num(r.legCm) + ' см крачета)</span>';
      var spec = '<span>' + dims + '</span><span>≈ ' + num(r.weight.kg) + ' кг</span>';
      if (r.decoratedCount > 1) {
        spec += '<span>' + r.decoratedCount + ' декорирани страни' + (r.text && r.fit ? ' · надписът се повтаря на страничните' : '') + '</span>';
      }
      specsEl.innerHTML = spec;
      // бележки под изгледа: палет и изработка от две части
      var flags = [];
      if (m.project) flags.push('Доставката и монтажът се уточняват в офертата.');
      else if (r.pallet) flags.push('Доставката е с палет, цената се уточнява в офертата.');
      if (!r.sheet.fits) flags.push('Този размер се изработва от две части' + (m.project ? '.' : ', цената се уточнява в офертата.'));
      if (r.segments) flags.push('Пейката се изработва от ' + r.segments + ' сегмента. Седалката е от дърво и се уточнява по проект.');
      var fl = $('#cfgFlags');
      if (fl) { fl.hidden = !flags.length; fl.innerHTML = flags.map(function (t) { return '<span>' + esc(t) + '</span>'; }).join(''); }
      var priceTxt = r.price ? (r.price.min === r.price.max ? money(r.price.min) : money(r.price.min) + ' – ' + money(r.price.max)) + ' ' + SET.currency : '';
      $$('[data-price]').forEach(function (el) {
        // вид „по проект“: цената никога не се показва (каталогът дори не я съдържа)
        el.hidden = !r.price && !m.project;
        if (m.project) el.innerHTML = '<b>Цена по оферта</b>';
        else if (r.price) el.innerHTML = 'Ориентировъчно: <b>' + priceTxt + '</b>' + (state.qty > 1 ? ' <span class="muted">за ' + state.qty + ' бр.</span>' : '');
      });
      $('#barModel').textContent = m.custom ? (formOf(m) ? formOf(m).name : m.name) + ' · по избор' : m.name;
      $('#barInfo').textContent = m.project ? 'Цена по оферта' : r.price ? priceTxt : '≈ ' + num(r.weight.kg) + ' кг';
      updateTextUi(r);
      save();
    });
  }

  function updateText() { update(); }

  function updateTextUi(r) {
    var input = $('#txt');
    if (!input) return;
    // един ред със състоянието: зелено, когато е наред; червено с причината и какво да се направи
    var st = $('#txtStatus');
    if (r.error) {
      st.textContent = r.error;
      st.className = 'txt-status bad';
    } else if (r.fit) {
      st.textContent = 'Височина на буквите ≈ ' + Math.round(r.fit.letter_mm) + ' мм' + (r.fit.lines.length > 1 ? ' (на два реда)' : '');
      st.className = 'txt-status ok';
    } else {
      st.textContent = 'Минимална височина на буквите за този материал: ' + num(r.minMm) + ' мм.';
      st.className = 'txt-status';
    }
    var sb = $('#splitBtn');
    sb.hidden = !r.split;
    if (r.split) sb.setAttribute('data-split', JSON.stringify(r.split));
    var nb = $('#narrowBtn');
    nb.hidden = !r.betterFont;
    if (r.betterFont) {
      nb.setAttribute('data-font', r.betterFont.code);
      nb.textContent = 'Пробвай с по-тесния шрифт (' + r.betterFont.name + ', ≈ ' + Math.round(r.betterFont.mm) + ' мм)';
    }
    input.classList.toggle('invalid', !!r.error);
    input.setAttribute('aria-invalid', r.error ? 'true' : 'false');
    var sendBtn = $('#sendBtn');
    if (sendBtn) sendBtn.disabled = !!r.error || sending;
    var projBtn = $('#projBtn');
    if (projBtn) projBtn.disabled = !!r.error || pSending;
    var formErr = $('#textBlock');
    if (formErr) formErr.hidden = !r.error;
    var formErrP = $('#textBlockP');
    if (formErrP) formErrP.hidden = !r.error;
    // образците на шрифтовете показват надписа на клиента
    $$('.font-sample').forEach(function (s) {
      var t = r.text ? G.chars(r.lines[0]).slice(0, 8).join('') : 'Огън';
      if (s.textContent !== t) s.textContent = t;
    });
  }

  /* ---------- събития ---------- */
  stepsEl.addEventListener('change', function (e) {
    var t = e.target, name = t.name;
    var notes = [];
    if (name === 'type') {
      state.type = t.value;
      var ff = formsOfType(state.type)[0];
      if (ff) state.model = ff.models[0];
      state.custom = null;
      normalizeState(); build(); track('model_select');
      return;
    } else if (name === 'form') {
      var fm = CAT.forms.filter(function (x) { return x.code === t.value; })[0];
      if (fm) state.model = fm.models[0];
      state.custom = null;
      normalizeState(); build(); track('model_select');
      return;
    } else if (name === 'model') {
      state.model = t.value;
      state.custom = null;
      normalizeState(); build(); track('model_select');
      return;
    } else if (name === 'extras') {
      if (t.checked) {
        if (state.extras.indexOf(t.value) < 0) state.extras.push(t.value);
        // изключващите се екстри се махат автоматично
        var o = optByCode('extras', t.value);
        excludedBy(o).forEach(function (x) {
          if (x.group === 'extras' && state.extras.indexOf(x.code) >= 0) {
            state.extras = state.extras.filter(function (c) { return c !== x.code; });
            notes.push('„' + x.name + '“ е махнат – не може заедно с „' + o.name + '“.');
          }
        });
      } else {
        state.extras = state.extras.filter(function (c) { return c !== t.value; });
      }
    } else if (groupsByCode[name]) {
      state.opts[name] = t.value;
    }
    // екстри, чието изискване вече не е изпълнено (напр. термометър без капак)
    dropUnmet().forEach(function (n) { notes.push('„' + n + '“ е махнат – изисква опция, която вече не е избрана.'); });
    if ((CAT.rules || []).length && (name === 'extras' || name === 'legs')) {
      build();
    } else {
      update();
    }
    var note = $('#extrasNote');
    if (note) { note.hidden = !notes.length; note.textContent = notes.join(' '); }
  });

  /* ---------- размер по избор ---------- */
  stepsEl.addEventListener('click', function (e) {
    if (!e.target.closest('#customToggle')) return;
    var m = model(), f = formOf(m);
    if (state.custom) {
      state.custom = null;
    } else if (f && f.custom) {
      state.custom = G.clampCustom(f.custom, { w: m.w, d: m.d, h: m.h }, m.shape).dims;
    }
    build();
    var tg = $('#customToggle');
    if (tg) tg.focus({ preventScroll: true });
  });
  stepsEl.addEventListener('input', function (e) {
    var k = e.target.getAttribute && e.target.getAttribute('data-dim');
    if (!k || e.target.type !== 'range' || !state.custom) return;
    var f = formOf(model());
    state.custom[k] = parseFloat(e.target.value);
    var c = G.clampCustom(f.custom, state.custom, model().shape);   // отношение височина/ширина
    state.custom = c.dims;
    syncDims();
    customMsg(c.messages);
    update();
  });
  stepsEl.addEventListener('change', function (e) {
    var k = e.target.getAttribute && e.target.getAttribute('data-dim');
    if (!k || e.target.type !== 'number' || !state.custom) return;
    var f = formOf(model()), raw = {};
    raw.w = state.custom.w; raw.d = state.custom.d; raw.h = state.custom.h;
    raw[k] = e.target.value === '' ? NaN : e.target.value;
    var c = G.clampCustom(f.custom, raw, model().shape);
    state.custom = c.dims;
    e.target.value = state.custom[k];
    syncDims();
    customMsg(c.messages);
    update();
  });

  stepsEl.addEventListener('click', function (e) {
    var sp = e.target.closest('#splitBtn');
    if (sp && !sp.hidden) {
      // разделяне при интервала, най-близък до средата
      var parts = JSON.parse(sp.getAttribute('data-split') || 'null');
      if (parts) {
        state.text = parts.join('\n');
        $('#txt').value = state.text;
        update();
      }
      return;
    }
    var b = e.target.closest('#narrowBtn');
    if (!b || b.hidden) return;
    var code = b.getAttribute('data-font'), radio = stepsEl.querySelector('input[name="font"][value="' + code + '"]');
    if (radio) { radio.checked = true; state.opts.font = code; update(); }
  });

  var textTimer = 0;
  stepsEl.addEventListener('input', function (e) {
    if (e.target.id !== 'txt') return;
    // най-много два реда: трети ред се слива с втория
    var v = e.target.value, parts = v.split('\n');
    if (parts.length > 2) {
      v = parts[0] + '\n' + parts.slice(1).join(' ');
      var pos = e.target.selectionStart;
      e.target.value = v;
      e.target.setSelectionRange(Math.min(pos, v.length), Math.min(pos, v.length));
    }
    state.text = v;
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
        state.custom = s.custom && typeof s.custom === 'object' ? s.custom : null;
        return true;
      }
    } catch (e) { /* няма достъп */ }
    return false;
  }

  /* ---------- запитване ---------- */
  var form = $('#inqForm');
  var qtyInput = $('#qty');
  var sending = false; // бутонът стои заключен, докато тече заявката
  var pSending = false; // същото за проектната форма

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
        config: { model: state.model, custom: state.custom, options: Object.assign({}, state.opts, { extras: state.extras }), text: state.text, quantity: state.qty },
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
        var te = $('#txtStatus'); te.textContent = errsS.text; te.className = 'txt-status bad';
      }
      msg.textContent = (data && data.message) || 'Запитването не беше изпратено. Проверете полетата и опитайте отново.';
      msg.hidden = false;
    }).catch(function () {
      unlock();
      msg.textContent = 'Няма връзка със сървъра. Проверете интернета и опитайте отново.';
      msg.hidden = false;
    });
  });

  /* ---------- проектна форма (вид „по проект“): multipart към api/project.php с конфигурацията и прегледа ---------- */
  var pform = $('#projForm');
  var P_FIELDS = ['object', 'city', 'quantity', 'deadline', 'contact_name', 'phone', 'email', 'note'], STORE_PFORM = 'fp_pform_v1', pTimer = 0;
  pform.addEventListener('input', function (e) {
    if (P_FIELDS.indexOf(e.target.name) < 0) return;
    clearTimeout(pTimer);
    pTimer = setTimeout(function () {
      var d = {};
      P_FIELDS.forEach(function (n) { d[n] = String(pform.elements[n].value || '').slice(0, 3000); });
      storeSet(STORE_PFORM, d);
    }, 300);
  });
  (function restoreP() {
    var d = storeGet(STORE_PFORM);
    if (!d) return;
    P_FIELDS.forEach(function (n) { if (typeof d[n] === 'string' && d[n] !== '') pform.elements[n].value = d[n]; });
  })();
  function pError(name, msg) {
    var el = pform.querySelector('[data-err="' + name + '"]'), input = pform.elements[name];
    if (el) { el.textContent = msg || ''; el.hidden = !msg; }
    if (input && input.classList) { input.classList.toggle('invalid', !!msg); input.setAttribute('aria-invalid', msg ? 'true' : 'false'); }
  }
  var pLogo = pform.elements.logo, pLogoInfo = $('#logoInfo');
  function checkLogo() {
    var fl = pLogo.files && pLogo.files[0];
    pLogoInfo.textContent = '';
    if (!fl) { pError('logo', ''); return ''; }
    var ext = (fl.name.split('.').pop() || '').toLowerCase(), err = '';
    if (['png', 'jpg', 'jpeg', 'svg', 'pdf'].indexOf(ext) < 0) err = 'Позволени са само PNG, JPG, SVG и PDF.';
    else if (fl.size > CFG.maxBytes) err = 'Файлът е ' + (fl.size / 1048576).toFixed(1).replace('.', ',') + ' MB. Максимумът е 5 MB.';
    pError('logo', err);
    if (!err) pLogoInfo.textContent = fl.name + ' · ' + (fl.size >= 1048576 ? (fl.size / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(fl.size / 1024)) + ' KB');
    return err;
  }
  pLogo.addEventListener('change', checkLogo);
  function validateP() {
    var e = {}, v = function (n) { return String(pform.elements[n].value || '').trim(); };
    if (v('object').length < 2) e.object = 'Напишете кой е обектът.';
    var q = parseInt(v('quantity'), 10);
    if (!(q >= 1 && q <= 500)) e.quantity = 'Брой между 1 и 500.';
    if (v('contact_name').length < 2) e.contact_name = 'Въведете име.';
    var digits = v('phone').replace(/\D/g, '');
    if (!v('phone')) e.phone = 'Въведете телефон.';
    else if (!/^[\d\s+()\-\/]+$/.test(v('phone')) || digits.length < 8 || digits.length > 15) e.phone = 'Проверете телефона – напр. 0888 123 456.';
    if (!v('email')) e.email = 'Въведете имейл.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v('email'))) e.email = 'Невалиден имейл.';
    if (!pform.elements.consent.checked) e.consent = 'Нужно е съгласие, за да се свържем с вас.';
    ['object', 'quantity', 'contact_name', 'phone', 'email', 'consent'].forEach(function (n) { pError(n, e[n]); });
    var le = checkLogo();
    if (le) e.logo = le;
    return e;
  }
  function pPost(fd) {
    return fetch(CFG.projectUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (res) { return res.json().catch(function () { return { ok: false, message: 'Сървърът върна неочакван отговор.' }; }); });
  }
  /** При изтекла сесия: нов токен и още един опит със същите данни (и същия файл) */
  function pSend(fd, retried) {
    return pPost(fd).then(function (data) {
      if (data && data.code === 'csrf' && !retried) {
        return postJson(CFG.tokenUrl, {}).then(function (t) {
          if (!t || !t.csrf) return data;
          CFG.csrf = t.csrf;
          fd.set('csrf', t.csrf);
          return pSend(fd, true);
        }, function () { return data; });
      }
      return data;
    });
  }
  pform.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (pSending) return;
    var r = compute(), errs = validateP();
    if (r.error) { scrollToStep($('#st-text')); return; }
    if (Object.keys(errs).length) {
      var first = pform.querySelector('.invalid');
      if (first) first.focus();
      return;
    }
    var btn = $('#projBtn'), msg = $('#projMsg');
    pSending = true;
    btn.disabled = true;
    btn.classList.add('loading');
    msg.hidden = true;
    function unlock() { pSending = false; btn.classList.remove('loading'); btn.disabled = !!compute().error; }
    fontCss(r.fontKey).then(function (css) {
      var svg = window.FPRender.render(renderOpts(r, { id: 'sn', fontCss: r.text ? css : '' }));
      var fd = new FormData(pform);
      var qty = parseInt(pform.elements.quantity.value, 10) || 1;
      fd.set('csrf', CFG.csrf);
      fd.set('config', JSON.stringify({ model: state.model, custom: state.custom, options: Object.assign({}, state.opts, { extras: state.extras }), text: state.text, quantity: qty }));
      fd.set('svg', svg);
      fd.set('src', JSON.stringify(src()));
      return pSend(fd, false);
    }).then(function (data) {
      if (data && data.ok) {
        storeClear();
        try { localStorage.removeItem(STORE_PFORM); } catch (e) { /* */ }
        window.location.href = data.redirect;
        return;
      }
      unlock();
      var es = (data && data.errors) || {};
      Object.keys(es).forEach(function (k) { pError(k, es[k]); });
      if (es.text) { var te = $('#txtStatus'); te.textContent = es.text; te.className = 'txt-status bad'; }
      msg.textContent = (data && data.message) || 'Запитването не беше изпратено. Проверете полетата и опитайте отново.';
      msg.hidden = false;
    }).catch(function () {
      unlock();
      msg.textContent = 'Няма връзка със сървъра. Данните ви са запазени – опитайте отново.';
      msg.hidden = false;
    });
  });

  /* ---------- долна лента на телефон ---------- */
  var bar = $('#bar'), stage = $('.cfg-stage');
  function finalStep() { return isProject() ? $('#st-project') : $('#st-send'); }
  function syncBar() {
    bar.classList.toggle('hide', finalStep().getBoundingClientRect().top < window.innerHeight * 0.75);
  }
  window.addEventListener('scroll', syncBar, { passive: true });
  window.addEventListener('resize', syncBar);
  syncBar();
  $('#barBtn').addEventListener('click', function () { scrollToStep(finalStep()); });

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
  var qs = new URLSearchParams(location.search), qModel = qs.get('model'), qForm = qs.get('form');
  // ?form=код (карта от началната страница) – първият размер на формата, ако текущият модел не е от нея
  if (qForm && !qModel) {
    var qf = CAT.forms.filter(function (x) { return x.code === qForm; })[0];
    if (qf && qf.models.indexOf(state.model) < 0) qModel = qf.models[0];
  }
  if (qModel && CAT.models.some(function (m) { return m.code === qModel; }) && qModel !== state.model) {
    state.model = qModel;
    state.custom = null;
    state.opts = {};
    state.extras = [];
  }
  // параметрите важат само веднъж: при презареждане да не изтриват избора на клиента
  if ((qs.get('model') || qForm) && window.history && history.replaceState) {
    var u = new URL(location.href);
    u.searchParams.delete('model');
    u.searchParams.delete('form');
    history.replaceState(null, '', u.pathname + u.search + u.hash);
  }
  if (!model()) state.model = CAT.models[0].code;
  if (state.custom) {
    var sf = formOf(model());
    state.custom = sf && sf.custom ? G.clampCustom(sf.custom, state.custom, model().shape).dims : null;
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
