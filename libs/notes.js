/* Pinnwand: Klebezettel im Fenster der Visualisierung (mode "overlay", geladen von der Browser-Erweiterung
 * „Visu-Erweiterung für Symcon“ über den Hook k=agent.js) und als Brett in der Kachel (mode "tile").
 *
 * Die Visu ist eine Flutter-Fläche; eigene Elemente am body liegen immer darüber und ihre Zeiger- und
 * Tastenereignisse erreichen die Visu nicht (gemessen 10.11.2026). Lesen und Schreiben über den Hook:
 * k=poll (Stand), k=save (POST). Text kommt nur als textContent ins Dokument, Handschrift als Zahlen in SVG-Pfade.
 */
(function () {
  'use strict';
  var C = /*STICKY_CONFIG*/null;
  var w = window, d = document;
  if (!C || (C.mode === 'overlay' && w.top !== w)) return;
  var reg = w.__stickyNotes || (w.__stickyNotes = {});
  if (reg[C.owner]) return;
  var api = reg[C.owner] = {};

  var OVERLAY = C.mode === 'overlay';
  var COLORS = { yellow: ['#fff48f', '#ffe95c'], pink: ['#ffc4dd', '#ff9fc6'], green: ['#d0f5a9', '#b4ea7e'], blue: ['#bfe6ff', '#96d2fb'] };
  var COLOR_KEYS = Object.keys(COLORS);
  var PENS = ['#1f2933', '#d7263d', '#1b5fd1'];
  var MIN_PX = 90, MAX_STROKES = 400, MAX_POINTS = 2000;
  var L = function (text) { return (C.labels && C.labels[text]) || text; };
  var clamp = function (v, lo, hi) { return Math.min(hi, Math.max(lo, v)); };
  var store = function (key, value) {
    try { if (value === undefined) return w.localStorage.getItem(C.owner + '.' + key); w.localStorage.setItem(C.owner + '.' + key, value); } catch (e) { /* ohne Speicher */ }
    return null;
  };

  var notes = [], seq = -1, els = {}, active = null, mode = '', pen = 0, gesture = null;
  var dirty = {}, timers = {}, collapsed = OVERLAY && store('collapsed') === '1';
  var lastColor = COLORS[store('color')] ? store('color') : 'yellow';

  // ── Aufbau ──
  var css = '.sn-root{font-family:Poppins,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;-webkit-tap-highlight-color:transparent}'
    + '.sn-root *{box-sizing:border-box}'
    + '.sn-overlay{position:fixed;inset:0;z-index:2147483000;pointer-events:none}'
    + '.sn-tile{position:fixed;inset:0;overflow-y:auto;display:flex;flex-wrap:wrap;align-content:flex-start;gap:12px;scrollbar-width:none}'
    + '.sn-note{position:absolute;pointer-events:auto;touch-action:none;user-select:none;-webkit-user-select:none;color:#1f2933;cursor:grab;'
    + 'border-radius:2px 2px 12px 2px;box-shadow:0 8px 18px rgba(0,0,0,.28),0 1px 3px rgba(0,0,0,.22)}'
    + '.sn-tile .sn-note{position:relative;flex:0 0 auto;width:min(150px,calc(50% - 6px));aspect-ratio:1;cursor:pointer}'
    + '.sn-tile .sn-note.sn-active{position:fixed;width:auto;aspect-ratio:auto;z-index:5;transform:none!important}'
    // Eingeklappt liegen die Zettel als kleiner Stapel auf dem Stapel-Knopf; der Knopf darüber bleibt die Tippfläche
    + '.sn-collapsed .sn-note{pointer-events:none;box-shadow:0 2px 5px rgba(0,0,0,.3);border-radius:1px 1px 5px 1px}'
    + '.sn-fly .sn-note{transition-delay:var(--sn-d,0ms);transition:left .5s cubic-bezier(.3,.9,.3,1),top .5s cubic-bezier(.3,.9,.3,1),width .5s cubic-bezier(.3,.9,.3,1),height .5s cubic-bezier(.3,.9,.3,1),transform .5s,border-radius .5s,box-shadow .5s}'
    + '.sn-pile .sn-toggle{background:none;box-shadow:none;opacity:1}.sn-pile .sn-toggle svg{visibility:hidden}'
    + '.sn-body{position:absolute;inset:0;overflow:hidden;container-type:size;border-radius:inherit}'
    + '.sn-body::before{content:"";position:absolute;left:0;right:0;top:0;height:13%;background:rgba(0,0,0,.045)}'
    + '.sn-text,.sn-edit{position:absolute;left:7cqw;right:7cqw;top:15cqh;bottom:6cqh;margin:0;padding:0;border:0;background:none;outline:none;resize:none;'
    + 'font-family:"Bradley Hand","Segoe Print","Marker Felt","Comic Sans MS",cursive;font-size:max(11px,11.5cqh);line-height:1.2;color:inherit;'
    + 'white-space:pre-wrap;overflow-wrap:anywhere;overflow:hidden}'
    + '.sn-edit{user-select:text;-webkit-user-select:text;overflow-y:auto;touch-action:auto}'
    + '.sn-ink{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}'
    + '.sn-ink path{fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-width:max(1.5px,1.5cqh);vector-effect:non-scaling-stroke}'
    + '.sn-draw{cursor:crosshair}'
    + '.sn-tools{position:absolute;left:50%;bottom:calc(100% + 8px);transform:translateX(-50%);display:none;gap:2px;padding:4px;border-radius:24px;'
    + 'background:rgba(28,28,30,.94);box-shadow:0 6px 16px rgba(0,0,0,.35);white-space:nowrap;z-index:3}'
    + '.sn-active .sn-tools{display:flex}.sn-below .sn-tools{bottom:auto;top:calc(100% + 8px)}'
    + '.sn-tile .sn-tools,.sn-tile .sn-below .sn-tools{top:auto;bottom:10px}'
    + '.sn-b{width:38px;height:38px;border-radius:50%;border:0;padding:0;background:none;color:#fff;font:600 14px/1 inherit;display:flex;align-items:center;justify-content:center;cursor:pointer;touch-action:manipulation}'
    + '.sn-b svg{width:19px;height:19px}.sn-b.sn-on{background:rgba(255,255,255,.24)}.sn-b.sn-arm{background:#d7263d}'
    + '.sn-b i{display:block;width:16px;height:16px;border-radius:50%;border:2px solid rgba(255,255,255,.55)}.sn-b.sn-on i{border-color:#fff}'
    + '.sn-only-draw{display:none}.sn-draw .sn-only-draw{display:flex}'
    + '.sn-grip{position:absolute;right:-8px;bottom:-8px;width:34px;height:34px;display:none;cursor:nwse-resize;touch-action:none;z-index:2}'
    + '.sn-grip::after{content:"";position:absolute;right:12px;bottom:12px;width:12px;height:12px;border-right:3px solid rgba(0,0,0,.4);border-bottom:3px solid rgba(0,0,0,.4);border-radius:0 0 4px 0}'
    + '.sn-overlay .sn-active .sn-grip{display:block}'
    + '.sn-pad{position:absolute;display:flex;gap:8px;padding:12px;pointer-events:auto;z-index:2147483001}.sn-tile .sn-pad{position:fixed;z-index:4}'
    + '.sn-c0{left:0;top:0}.sn-c1{right:0;top:0}.sn-c2{left:0;bottom:0}.sn-c3{right:0;bottom:0}'
    + '.sn-p{position:relative;width:42px;height:42px;border:0;padding:0;border-radius:3px 3px 11px 3px;background:#ffe95c;color:#1f2933;display:flex;align-items:center;justify-content:center;'
    + 'box-shadow:0 4px 10px rgba(0,0,0,.3);cursor:pointer;touch-action:manipulation;opacity:.92}.sn-p svg{width:20px;height:20px}'
    + '.sn-p b{position:absolute;right:-5px;top:-5px;min-width:18px;height:18px;padding:0 4px;border-radius:9px;background:#1f2933;color:#fff;font:600 11px/18px inherit;text-align:center}'
    + '.sn-p b:empty{display:none}.sn-p:active,.sn-b:active{transform:scale(.93)}'
    + '.sn-hint{position:absolute;left:50%;bottom:64px;transform:translateX(-50%);padding:8px 14px;border-radius:12px;background:rgba(28,28,30,.94);color:#fff;font-size:14px;pointer-events:none;white-space:nowrap}'
    + '.sn-none{display:none;width:100%;padding-top:24px;text-align:center;opacity:.6}.sn-tile.sn-empty .sn-none{display:block}';
  var style = d.createElement('style');
  style.textContent = css;
  d.head.appendChild(style);

  function h(tag, cls, parent) { var e = d.createElement(tag); if (cls) e.className = cls; if (parent) parent.appendChild(e); return e; }
  // Feste Zeichenketten aus dieser Datei, keine Daten
  function icon(paths) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + paths + '</svg>'; }
  var ICONS = {
    plus: icon('<path d="M12 5v14M5 12h14"/>'), stack: icon('<path d="M12 4l9 5-9 5-9-5z"/><path d="M3 14l9 5 9-5"/>'),
    pen: icon('<path d="M4 20l1-4L16 5l3 3L8 19z"/>'), undo: icon('<path d="M9 7l-5 5 5 5"/><path d="M4 12h10a5 5 0 010 10h-2"/>'),
    trash: icon('<path d="M5 7h14M10 7V4h4v3M7 7l1 13h8l1-13"/>'), done: icon('<path d="M5 12l5 5 9-10"/>')
  };
  function button(parent, cls, label, html, onClick) {
    var b = h('button', cls, parent);
    b.type = 'button';
    b.title = label;
    b.setAttribute('aria-label', label);
    // html ist immer eine feste Zeichenkette aus dieser Datei (Symbol, "Aa", Farbpunkt), nie Zettelinhalt
    if (html) b.innerHTML = html;
    b.addEventListener('pointerdown', function (ev) { ev.stopPropagation(); });
    b.addEventListener('click', function (ev) { ev.stopPropagation(); onClick(b); });
    return b;
  }

  var root = h('div', 'sn-root ' + (OVERLAY ? 'sn-overlay' : 'sn-tile'), d.body);
  root.setAttribute('data-owner', C.owner);
  var margin = { top: 0, side: 0, bottom: 0 };
  if (!OVERLAY) {
    var q = new URLSearchParams(w.location.search), px = function (name, fallback) { var v = parseInt(q.get(name) || '', 10); return isNaN(v) ? fallback : v; };
    margin = { top: px('margintop', 36), side: px('marginside', 12), bottom: px('marginbottom', 12) };
    root.style.padding = margin.top + 'px ' + margin.side + 'px ' + (margin.bottom + 56) + 'px';
    h('div', 'sn-none', root).textContent = L('No notes');
  }
  var pad = h('div', 'sn-pad sn-c' + (OVERLAY ? C.corner : 3), root), count = null;
  button(pad, 'sn-p', L('New note'), ICONS.plus, addNote);
  if (OVERLAY) {
    var toggle = button(pad, 'sn-p sn-toggle', L('Show or hide notes'), ICONS.stack, function () {
      if (active) deactivate();
      collapsed = !collapsed;
      store('collapsed', collapsed ? '1' : '0');
      fly();
      render();
    });
    count = h('b', '', toggle);
  }

  // ── Zettel zeichnen ──
  function find(id) { for (var i = 0; i < notes.length; i++) if (notes[i].id === id) return notes[i]; return null; }
  function maxZ() { return notes.reduce(function (m, n) { return Math.max(m, n.z || 0); }, 0); }

  function build(id) {
    var el = h('div', 'sn-note', root);
    el.setAttribute('data-id', id);
    el._body = h('div', 'sn-body', el);
    el._text = h('div', 'sn-text', el._body);
    el._ink = d.createElementNS('http://www.w3.org/2000/svg', 'svg');
    el._ink.setAttribute('class', 'sn-ink'); el._ink.setAttribute('viewBox', '0 0 1000 1000'); el._ink.setAttribute('preserveAspectRatio', 'none');
    el._body.appendChild(el._ink);

    var tools = h('div', 'sn-tools', el);
    tools.addEventListener('pointerdown', function (ev) { ev.stopPropagation(); });
    el._type = button(tools, 'sn-b', L('Type'), 'Aa', function () { setMode('text'); });
    el._drawB = button(tools, 'sn-b', L('Write by hand'), ICONS.pen, function () { setMode('draw'); });
    el._pens = PENS.map(function (color, i) {
      var b = button(tools, 'sn-b sn-only-draw', L('Pen color'), '<i></i>', function () { pen = i; render(); });
      b.firstChild.style.background = color;
      return b;
    });
    button(tools, 'sn-b sn-only-draw', L('Undo last stroke'), ICONS.undo, function () {
      var n = find(id);
      if (n && n.strokes.length) { n.strokes.pop(); touch(n); render(); }
    });
    el._colorB = button(tools, 'sn-b', L('Note color'), '<i></i>', function () {
      var n = find(id);
      if (!n) return;
      n.color = COLOR_KEYS[(COLOR_KEYS.indexOf(n.color) + 1) % COLOR_KEYS.length];
      lastColor = n.color;
      store('color', lastColor);
      touch(n);
      render();
    });
    button(tools, 'sn-b', L('Tear off'), ICONS.trash, function (b) {
      // Zweimal tippen: ein Zettel mit Handschrift lässt sich nicht zurückholen
      if (b.classList.contains('sn-arm')) return removeNote(id);
      b.classList.add('sn-arm');
      b.title = L('Tear off?');
      w.setTimeout(function () { b.classList.remove('sn-arm'); b.title = L('Tear off'); }, 3000);
    });
    button(tools, 'sn-b', L('Done'), ICONS.done, deactivate);

    var grip = h('div', 'sn-grip', el);
    grip.addEventListener('pointerdown', function (ev) { startResize(ev, el, id); });
    el._body.addEventListener('pointerdown', function (ev) {
      if (ev.button) return;
      if (active === id && mode === 'text') return; // das Textfeld bedient sich selbst
      ev.preventDefault();
      ev.stopPropagation();
      if (active === id && mode === 'draw') startStroke(ev, el, id); else startDrag(ev, el, id);
    });
    return el;
  }

  function geometry(n) {
    var W = w.innerWidth, H = w.innerHeight, m = Math.min(W, H);
    var pw = Math.min(W - 8, Math.max(MIN_PX, n.w * m)), ph = Math.min(H - 8, Math.max(MIN_PX, n.h * m));
    return { W: W, H: H, m: m, pw: pw, ph: ph, left: clamp(n.x * W - pw / 2, 4, W - pw - 4), top: clamp(n.y * H - ph / 2, 4, H - ph - 4) };
  }

  /** Für einen Moment gleiten die Zettel an ihren neuen Platz (Stapel oder Brett), statt zu springen. */
  var flyTimer = 0;
  function fly() {
    root.classList.add('sn-fly');
    w.clearTimeout(flyTimer);
    flyTimer = w.setTimeout(function () { root.classList.remove('sn-fly'); }, 900);
  }

  function paint(el, n, rank) {
    var isActive = active === n.id, pair = COLORS[n.color] || COLORS.yellow;
    el.style.background = 'linear-gradient(180deg,' + pair[0] + ',' + pair[1] + ')';
    el.classList.toggle('sn-active', isActive);
    el.classList.toggle('sn-draw', isActive && mode === 'draw');
    el._type.classList.toggle('sn-on', isActive && mode === 'text');
    el._drawB.classList.toggle('sn-on', isActive && mode === 'draw');
    el._pens.forEach(function (b, i) { b.classList.toggle('sn-on', i === pen); });
    el._colorB.firstChild.style.background = COLORS[COLOR_KEYS[(COLOR_KEYS.indexOf(n.color) + 1) % COLOR_KEYS.length]][1];
    if (el._text.textContent !== n.text) el._text.textContent = n.text;
    paintInk(el, n);
    if (OVERLAY && collapsed) {
      // Stapel: jeder Zettel klein auf dem Stapel-Knopf, leicht verdreht und versetzt
      var b = toggle.getBoundingClientRect();
      el.style.left = b.left + ((rank * 3) % 5) - 2 + 'px'; el.style.top = b.top + ((rank * 7) % 5) - 2 + 'px';
      el.style.width = b.width + 'px'; el.style.height = b.height + 'px';
      el.style.zIndex = String(rank + 1);
      el.style.transform = 'rotate(' + (((rank * 37) % 15) - 7) + 'deg)';
      el.style.setProperty('--sn-d', Math.min(rank, 12) * 25 + 'ms');
      el.classList.remove('sn-below');
    } else if (OVERLAY) {
      var g = geometry(n);
      el.style.setProperty('--sn-d', Math.min(rank, 12) * 25 + 'ms');
      el.style.left = g.left + 'px'; el.style.top = g.top + 'px';
      el.style.width = g.pw + 'px'; el.style.height = g.ph + 'px';
      el.style.zIndex = String(rank + 1);
      // Beim Bearbeiten gerade: so stimmen Zeiger und Linien überein
      el.style.transform = isActive ? '' : 'rotate(' + (n.rot || 0) + 'deg)';
      el.classList.toggle('sn-below', g.top < 60);
    } else {
      el.style.order = String(-rank);
      el.style.transform = isActive ? '' : 'rotate(' + (n.rot || 0) / 2 + 'deg)';
      el.style.left = el.style.right = isActive ? margin.side + 'px' : '';
      el.style.top = isActive ? margin.top + 'px' : '';
      el.style.bottom = isActive ? margin.bottom + 'px' : '';
    }
  }

  function paintInk(el, n) {
    var key = n.strokes.length + ':' + n.strokes.reduce(function (sum, s) { return sum + s.p.length; }, 0) + ':' + n.strokes.map(function (s) { return s.c; }).join('');
    if (el._inkKey === key) return;
    el._inkKey = key;
    while (el._ink.firstChild) el._ink.removeChild(el._ink.firstChild);
    n.strokes.forEach(function (s) {
      var p = s.p, path = 'M' + (+p[0]) + ' ' + (+p[1]);
      // Ein einzelner Punkt wird ein Tupfer
      if (p.length === 2) path += 'l0.1 0';
      for (var i = 2; i + 1 < p.length; i += 2) path += 'L' + (+p[i]) + ' ' + (+p[i + 1]);
      var node = d.createElementNS('http://www.w3.org/2000/svg', 'path');
      node.setAttribute('d', path);
      node.setAttribute('stroke', PENS[s.c] || PENS[0]);
      el._ink.appendChild(node);
    });
  }

  function render() {
    var seen = {};
    notes.slice().sort(function (a, b) { return (a.z || 0) - (b.z || 0); }).forEach(function (n, rank) {
      seen[n.id] = true;
      paint(els[n.id] || (els[n.id] = build(n.id)), n, rank);
    });
    Object.keys(els).forEach(function (id) {
      if (seen[id]) return;
      els[id].remove();
      delete els[id];
      if (active === id) { active = null; mode = ''; }
    });
    root.classList.toggle('sn-collapsed', collapsed);
    root.classList.toggle('sn-pile', collapsed && notes.length > 0);
    root.classList.toggle('sn-empty', !notes.length);
    if (count) count.textContent = notes.length ? String(notes.length) : '';
  }

  // ── Bedienen ──
  function follow(target, ev, onMove, onEnd) {
    try { target.setPointerCapture(ev.pointerId); } catch (e) { /* ohne Bindung */ }
    var id = ev.pointerId;
    function move(e) { if (e.pointerId === id) onMove(e); }
    function end(e) {
      if (e.pointerId !== id) return;
      target.removeEventListener('pointermove', move);
      target.removeEventListener('pointerup', end);
      target.removeEventListener('pointercancel', end);
      gesture = null;
      onEnd(e);
    }
    target.addEventListener('pointermove', move);
    target.addEventListener('pointerup', end);
    target.addEventListener('pointercancel', end);
  }

  // Ziehen und Größe ändern fassen nur den einen Zettel an, einmal je Bild: beim Ziehen allein seine transform
  // (läuft ohne neues Layout), nicht das ganze Brett. Sonst ruckelt es über der Flutter-Fläche der Visu.
  function startDrag(ev, el, id) {
    var n = find(id), g = geometry(n), sx = ev.clientX, sy = ev.clientY, moved = false, frame = 0;
    var left = g.left, top = g.top, rot = active === id ? 0 : (n.rot || 0);
    gesture = id;
    follow(el._body, ev, function (e) {
      var dx = e.clientX - sx, dy = e.clientY - sy;
      if (!OVERLAY || (!moved && Math.abs(dx) + Math.abs(dy) < 7)) return;
      if (!moved) { moved = true; el.style.willChange = 'transform'; }
      left = clamp(g.left + dx, 4, g.W - g.pw - 4);
      top = clamp(g.top + dy, 4, g.H - g.ph - 4);
      if (frame) return;
      frame = w.requestAnimationFrame(function () {
        frame = 0;
        el.style.transform = 'translate3d(' + (left - g.left) + 'px,' + (top - g.top) + 'px,0) rotate(' + rot + 'deg)';
      });
    }, function () {
      w.cancelAnimationFrame(frame);
      el.style.willChange = '';
      if (!moved) { if (active !== id) activate(id); return; }
      n.x = clamp((left + g.pw / 2) / g.W, 0, 1);
      n.y = clamp((top + g.ph / 2) / g.H, 0, 1);
      touch(n);
      render();
    });
  }

  function startResize(ev, el, id) {
    if (ev.button) return;
    ev.preventDefault();
    ev.stopPropagation();
    var n = find(id), g = geometry(n), sx = ev.clientX, sy = ev.clientY, pw = g.pw, ph = g.ph, frame = 0;
    gesture = id;
    follow(ev.currentTarget, ev, function (e) {
      pw = clamp(g.pw + e.clientX - sx, MIN_PX, g.W - g.left - 4);
      ph = clamp(g.ph + e.clientY - sy, MIN_PX, g.H - g.top - 4);
      if (frame) return;
      frame = w.requestAnimationFrame(function () {
        frame = 0;
        el.style.width = pw + 'px';
        el.style.height = ph + 'px';
      });
    }, function () {
      w.cancelAnimationFrame(frame);
      n.w = clamp(pw / g.m, 0.08, 0.9);
      n.h = clamp(ph / g.m, 0.08, 0.9);
      // Die obere linke Ecke bleibt stehen
      n.x = clamp((g.left + pw / 2) / g.W, 0, 1);
      n.y = clamp((g.top + ph / 2) / g.H, 0, 1);
      touch(n);
      render();
    });
  }

  function startStroke(ev, el, id) {
    var n = find(id);
    if (!n || n.strokes.length >= MAX_STROKES) return;
    var rect = el._body.getBoundingClientRect();
    var point = function (e) {
      return [Math.round(clamp((e.clientX - rect.left) / rect.width, 0, 1) * 1000), Math.round(clamp((e.clientY - rect.top) / rect.height, 0, 1) * 1000)];
    };
    var first = point(ev), stroke = { c: pen, p: first.slice() };
    n.strokes.push(stroke);
    gesture = id;
    paintInk(el, n);
    // Beim Schreiben wächst nur die eine Linie, die anderen bleiben unberührt
    var line = el._ink.lastChild, path = line.getAttribute('d');
    follow(el._body, ev, function (e) {
      var p = point(e), len = stroke.p.length;
      if (len >= MAX_POINTS || Math.abs(p[0] - stroke.p[len - 2]) + Math.abs(p[1] - stroke.p[len - 1]) < 5) return;
      stroke.p.push(p[0], p[1]);
      line.setAttribute('d', path += 'L' + p[0] + ' ' + p[1]);
    }, function () { el._inkKey = ''; touch(n); render(); });
  }

  function activate(id) {
    if (active && active !== id) deactivate();
    var n = find(id);
    if (!n) return;
    active = id;
    mode = '';
    if ((n.z || 0) < maxZ()) { n.z = maxZ() + 1; touch(n); }
    render();
  }

  function closeEditor() {
    var el = els[active];
    if (!el || !el._ta) return;
    el._ta.remove(); el._ta = null; el._text.style.display = '';
  }

  function setMode(next) {
    var el = els[active], n = find(active);
    if (!el || !n) return;
    closeEditor();
    mode = mode === next ? '' : next;
    if (mode === 'text') {
      var ta = el._ta = h('textarea', 'sn-edit', el._body);
      ta.maxLength = 600;
      ta.value = n.text;
      el._text.style.display = 'none';
      ta.addEventListener('input', function () {
        var current = find(active);
        if (current) { current.text = ta.value; touch(current); }
      });
      ta.addEventListener('pointerdown', function (ev) { ev.stopPropagation(); });
      w.setTimeout(function () { ta.focus(); }, 0);
    }
    render();
  }

  function deactivate() {
    var id = active, n = find(id);
    if (!id) return;
    closeEditor();
    active = null;
    mode = '';
    // Ein leerer Zettel bleibt nicht kleben
    if (n && !n.text.trim() && !n.strokes.length) removeNote(id); else render();
  }

  function addNote() {
    if (notes.length >= C.max) return hint(L('The board is full'));
    if (active) deactivate();
    if (collapsed) fly();
    collapsed = false;
    store('collapsed', '0');
    var r = Math.random, bytes = new Uint8Array(4), id = '';
    w.crypto.getRandomValues(bytes);
    for (var i = 0; i < 4; i++) id += ('0' + bytes[i].toString(16)).slice(-2);
    notes.push({ id: id, text: '', color: lastColor, x: 0.5 + (r() - 0.5) * 0.2, y: 0.45 + (r() - 0.5) * 0.2, w: 0.26, h: 0.26,
      rot: Math.round((r() * 6 - 3) * 10) / 10, z: maxZ() + 1, strokes: [] });
    activate(id);
  }

  function removeNote(id) {
    notes = notes.filter(function (n) { return n.id !== id; });
    w.clearTimeout(timers[id]);
    delete dirty[id];
    if (active === id) { active = null; mode = ''; }
    render();
    post({ op: 'del', id: id }).then(apply, function () { /* der nächste Abgleich holt es nach */ });
  }

  function hint(text) {
    var el = h('div', 'sn-hint', root);
    el.textContent = text;
    w.setTimeout(function () { el.remove(); }, 2500);
  }

  // Tipp neben den aktiven Zettel: fertig
  w.addEventListener('pointerdown', function (ev) {
    if (active && els[active] && !els[active].contains(ev.target)) deactivate();
  }, true);
  if (OVERLAY) w.addEventListener('resize', render);

  // ── Abgleich mit dem Modul ──
  function post(body) {
    return w.fetch(C.hook + '&k=save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
  }

  function touch(n) {
    dirty[n.id] = (dirty[n.id] || 0) + 1;
    w.clearTimeout(timers[n.id]);
    timers[n.id] = w.setTimeout(function () { save(n.id); }, 500);
  }

  function save(id) {
    var n = find(id), mark = dirty[id];
    if (!n) return;
    post({ op: 'put', note: { id: n.id, text: n.text, color: n.color, x: n.x, y: n.y, w: n.w, h: n.h, rot: n.rot, z: n.z, strokes: n.strokes } }).then(function (state) {
      if (dirty[id] === mark) delete dirty[id];
      if (state && state.error) hint(state.error);
      apply(state);
    }, function () {
      timers[id] = w.setTimeout(function () { save(id); }, 3000);
    });
  }

  /** Stand des Moduls übernehmen; was hier gerade bearbeitet wird oder noch nicht gespeichert ist, bleibt. */
  function apply(state) {
    if (!state || typeof state.seq !== 'number' || state.seq < seq || !Array.isArray(state.notes)) return;
    seq = state.seq;
    var mine = {};
    notes.forEach(function (n) { mine[n.id] = n; });
    var keep = function (id) { return (dirty[id] || gesture === id || (active === id && mode)) && mine[id]; };
    var next = state.notes.map(function (n) { return keep(n.id) ? mine[n.id] : n; });
    notes.forEach(function (n) {
      if ((dirty[n.id] || active === n.id) && !next.some(function (m) { return m.id === n.id; })) next.push(n);
    });
    notes = next;
    // Mitten in einer Geste nicht neu zeichnen: das setzte die transform des gezogenen Zettels zurück; das Ende der Geste zeichnet
    if (!gesture) render();
  }

  if (OVERLAY) {
    w.setInterval(function () {
      if (d.hidden) return;
      w.fetch(C.hook + '&k=poll&since=' + seq, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; }).then(apply, function () { /* nächster Versuch */ });
    }, C.pollMs);
  } else {
    // Die Kachel bekommt Änderungen vom Modul geschickt
    w.handleMessage = function (data) {
      var msg;
      try { msg = typeof data === 'string' ? JSON.parse(data) : data; } catch (e) { return; }
      if (msg && msg.type === 'reload') w.location.reload();
      if (msg && msg.type === 'notes') apply(msg);
    };
  }

  api.apply = apply;
  api.notes = function () { return notes; };
  apply(C.state);
  render();
})();
