// "My order": a list kept only on this phone (localStorage, per restaurant
// and branch). Nothing is sent to the restaurant; it is shown to the waiter.
// Loaded only when My order is on for this menu (QUESTIONS #236); with it off
// the page never downloads this file. The dish sheet (sheet.js) uses mC from here.
(function () {
  var d = document, w = window, D = w.mD || JSON.parse(d.getElementById('d').textContent);
  // The admin's live preview keeps no list: there a "+" opens the dish (without an Add button).
  if (D.o && D.p) w.mA = function (a) { w.mS(a.closest('li'), D); };
  if (!D.o || D.p) return;
  var T = D.t.o, key = 'mo:' + D.id + ':' + D.b, ls, L, flags = {}, status = d.createElement('p');
  try { ls = w.localStorage; L = JSON.parse(ls.getItem(key)) || { i: [] }; } catch (e) { L = { i: [] }; }
  var el = function (t, c, s) { var x = d.createElement(t); if (c) x.className = c; if (s != null) x.textContent = s; return x; };
  var fmt = function (m) { return D.cu + ' ' + (m / 100).toLocaleString('en-US', { minimumFractionDigits: m % 100 ? 2 : 0, maximumFractionDigits: 2 }); };
  // The dish's price now, with size and add-ons; null when it can't be ordered any more.
  var unit = function (l) {
    var o = D.o[l.i], p, ok = 1;
    if (!o || o[3]) return null;
    if (o[1].length) { var v = o[1].filter(function (x) { return x[0] === l.v; })[0]; if (!v) return null; p = v[1]; } else p = o[0];
    l.a.forEach(function (id) {
      var hit = 0;
      o[2].forEach(function (g) { g[2].forEach(function (op) { if (op[0] === id) { hit = 1; if (op[2]) ok = 0; p += op[1]; } }); });
      if (!hit) ok = 0;
    });
    return ok ? p : null;
  };
  var save = function () { try { ls.setItem(key, JSON.stringify(L)); } catch (e) {} bar(); };
  var total = function () { var t = 0, n = 0; L.i.forEach(function (l) { var p = unit(l); if (p != null) { t += p * l.q; n += l.q; } }); return [n, t]; };
  var b, ui = function () {
    // Styles and the bar are only made once the list has something in it.
    if (b) return;
    var css = el('style');
    css.textContent = '.obr{position:fixed;inset:auto .75rem .75rem;z-index:3;max-width:42rem;margin:0 auto;min-height:3.25rem;border:0;border-radius:99px;background:var(--a);color:var(--af);font:inherit;font-weight:700;box-shadow:0 4px 16px #0004;cursor:pointer}.hob{padding-bottom:5rem}.ow{display:block;width:100%;min-height:3rem;margin-top:1rem;border:0;border-radius:99px;background:var(--a);color:var(--af);font:inherit;font-weight:700;cursor:pointer}.ok{background:none;border:1px solid var(--ln);color:var(--fg);min-height:2.75rem}.ol,.wv ul{display:block;list-style:none;margin:.75rem 0 0;padding:0}.ol li,.wv li{background:none;border-radius:0;box-shadow:none;overflow:visible}.ol li{padding:.75rem 0;border-bottom:1px solid var(--ln)}.ol .r1{display:flex;justify-content:space-between;gap:.75rem;font-weight:600}.ol .r2{display:flex;align-items:center;gap:.5rem;margin-top:.5rem}.ol .r2 button{min-width:2.75rem;height:2.75rem;border-radius:99px;border:1px solid var(--ln);background:var(--card);color:var(--fg);font:inherit;font-size:1.1rem;cursor:pointer}.ol .r2 .rm{margin-inline-start:auto;padding:0 .9rem;font-size:.9rem}.ol .r2 b{min-width:1.5rem;text-align:center}.ol input{width:100%;height:2.75rem;margin-top:.5rem;padding:0 .75rem;border:1px solid var(--ln);border-radius:.6rem;background:var(--card);color:var(--fg);font:inherit}.ol .bad{color:var(--fg);font-weight:600;font-size:.85rem}.ol .x0 .r1{text-decoration:line-through;color:var(--mu)}.otl{display:flex;justify-content:space-between;font-size:1.15rem;font-weight:700;margin-top:1rem}.wv{position:fixed;inset:0;z-index:9;overflow:auto;background:#fff;color:#000;padding:1.25rem 1.25rem 2rem;font-size:1.3rem;line-height:1.35}.wv h2{font-size:2.2rem;margin:0 0 .25rem}.wv ul{margin:1rem 0}.wv li{padding:.8rem 0;border-bottom:2px solid #000}.wv b{font-size:1.5rem}.wv .wn{display:block;font-style:italic}.wv .wt{display:flex;justify-content:space-between;font-size:1.8rem;font-weight:800;margin-top:1rem}.wv button{position:sticky;top:0;float:inline-end;min-width:3rem;min-height:3rem;border:2px solid #000;border-radius:99px;background:#fff;color:#000;font:inherit;font-weight:700;padding:0 1rem;cursor:pointer}';
    d.head.appendChild(css);
    // "obr", not "ob": the footer's Other branches list is .ob (base.css).
    b = d.body.appendChild(el('button', 'obr'));
    b.type = 'button';
    b.hidden = true;
    b.onclick = function () { w.mO(); };
    status.setAttribute('role', 'status');
    status.style.cssText = 'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)';
    d.body.appendChild(status);
  };
  function bar() {
    if (!b) { if (!L.i.length) return; ui(); }
    var t = total();
    b.hidden = !L.i.length;
    d.body.classList.toggle('hob', !b.hidden);
    b.textContent = T.bar.replace(':count', t[0]).replace(':price', fmt(t[1]));
  }
  // The table from the QR code just scanned (codes are on the tables).
  if (w.mQ && D.q && D.q[w.mQ]) L.t = D.q[w.mQ];
  // Since the last visit: mark sold-out, removed or re-priced dishes.
  if (L.i.length || L.t) {
    L.i.forEach(function (l) { var p = unit(l); if (p == null) flags[l.k] = D.o[l.i] ? 'so' : 'gone'; else if (p !== l.p) { flags[l.k] = fmt(l.p); l.p = p; } });
    save();
  }

  w.mC = {
    fmt: fmt,
    // Price for a dish with the sheet's choices (pick.v = size index, pick.a = {"group:option": 1}).
    price: function (id, pick) { var l = this.line(id, pick); return unit(l) || 0; },
    line: function (id, pick) {
      var o = D.o[id], a = [];
      for (var k in pick.a) { var s = k.split(':'); a.push(o[2][s[0]][2][s[1]][0]); }
      return { i: id, v: o[1].length ? o[1][pick.v][0] : 0, a: a.sort(), q: 1, n: '' };
    },
    add: function (id, pick, name, x) {
      var l = this.line(id, pick), same, parts = [];
      if (x.v) parts.push(x.v[pick.v][0]);
      for (var k in pick.a) { var s = k.split(':'); parts.push('+ ' + x.a[s[0]][3][s[1]][0]); }
      l.k = id + '-' + l.v + '-' + l.a.join('.');
      same = L.i.filter(function (y) { return y.k === l.k; })[0];
      if (same) same.q++; else { l.nm = name; l.x = parts.join(', '); l.p = unit(l); L.i.push(l); }
      // Anonymous count for the restaurant's statistics: dish, quantity, list total. Nothing else.
      if (navigator.sendBeacon) navigator.sendBeacon('/api/v1/beacon', JSON.stringify({ t: D.id, b: D.b, c: [id, 1, total()[1]] })); // the list already holds the new dish
      save();
      if (b) status.textContent = T.added;
    }
  };
  // The + on a dish: straight in when there is nothing to choose, else the dish sheet.
  w.mA = function (a) {
    var li = a.closest('li'), o = D.o[li.dataset.i];
    if (o[1].length || o[2].some(function (g) { return g[0] > 0; })) w.mS(li, D);
    else w.mC.add(+li.dataset.i, { v: 0, a: {} }, li.querySelector('.nm').textContent, {});
  };
  // The list itself, in the same sheet as the dish details.
  w.mO = function () {
    var dl = d.getElementById('s'), body = d.getElementById('sb');
    body.textContent = '';
    body.appendChild(el('h3', '', T.title + (L.t ? ' · ' + T.table.replace(':n', L.t) : '')));
    if (!L.i.length) body.appendChild(el('p', 'mu', T.empty));
    var ul = body.appendChild(el('ul', 'ol'));
    L.i.forEach(function (l) {
      var li = ul.appendChild(el('li', unit(l) == null ? 'x0' : '')), p = unit(l), r1 = li.appendChild(el('div', 'r1'));
      r1.appendChild(el('span', '', l.nm));
      r1.appendChild(el('span', '', p == null ? '' : fmt(p * l.q)));
      if (l.x) li.appendChild(el('div', 'mu', l.x));
      var f = flags[l.k];
      if (f) li.appendChild(el('div', 'bad', f === 'so' ? T.sold_out : f === 'gone' ? T.gone : T.changed.replace(':price', f)));
      var r2 = li.appendChild(el('div', 'r2'));
      [['−', T.less, -1], [null], ['+', T.more, 1], [T.remove, T.remove, 0]].forEach(function (c) {
        if (!c[0]) return r2.appendChild(el('b', '', l.q)).setAttribute('aria-label', T.qty + ' ' + l.q);
        var x = r2.appendChild(el('button', c[2] ? '' : 'rm', c[0]));
        x.type = 'button';
        x.setAttribute('aria-label', c[1] + ': ' + l.nm);
        x.onclick = function () {
          l.q += c[2];
          if (!c[2] || l.q < 1) L.i.splice(L.i.indexOf(l), 1);
          save(); w.mO();
        };
      });
      var n = li.appendChild(el('input'));
      n.placeholder = T.note; n.setAttribute('aria-label', T.note + ': ' + l.nm); n.value = l.n || ''; n.maxLength = 120;
      n.onchange = function () { l.n = n.value.trim(); save(); };
    });
    if (L.i.length) {
      var t = body.appendChild(el('div', 'otl'));
      t.appendChild(el('span', '', T.total));
      t.appendChild(el('span', '', fmt(total()[1])));
      body.appendChild(el('p', 'mu', T.not_order));
      var wb = body.appendChild(el('button', 'ow', T.waiter));
      wb.type = 'button';
      wb.onclick = waiter;
      var cl = body.appendChild(el('button', 'ow ok', T.clear));
      cl.type = 'button';
      cl.onclick = function () {
        var box = el('div');
        box.setAttribute('role', 'alertdialog');
        box.appendChild(el('p', '', T.clear_ask));
        [[T.clear_yes, 1], [T.cancel, 0]].forEach(function (c) {
          var x = box.appendChild(el('button', 'ow' + (c[1] ? '' : ' ok'), c[0]));
          x.type = 'button';
          x.onclick = function () { if (c[1]) { L.i = []; save(); } w.mO(); };
        });
        cl.replaceWith(box);
        box.querySelector('button').focus();
      };
    }
    if (!dl.open) { if (dl.showModal) dl.showModal(); else dl.setAttribute('open', ''); }
  };
  // Full screen, black on white, large text: easy to read across the table.
  function waiter() {
    var v = d.body.appendChild(el('div', 'wv')), x = v.appendChild(el('button', '', T.close)), ul = el('ul');
    v.setAttribute('role', 'dialog');
    v.setAttribute('aria-modal', 'true');
    v.setAttribute('aria-label', T.waiter);
    if (L.t) v.appendChild(el('h2', '', T.table.replace(':n', L.t)));
    v.appendChild(el('p', '', d.querySelector('h1').textContent));
    v.appendChild(ul);
    L.i.forEach(function (l) {
      if (unit(l) == null) return;
      var li = ul.appendChild(el('li'));
      li.appendChild(el('b', '', l.q + ' × ' + l.nm));
      if (l.x) li.appendChild(el('div', '', l.x));
      if (l.n) li.appendChild(el('span', 'wn', l.n));
    });
    var t = v.appendChild(el('div', 'wt'));
    t.appendChild(el('span', '', T.total));
    t.appendChild(el('span', '', fmt(total()[1])));
    v.appendChild(el('p', '', T.not_order));
    var close = function () { v.remove(); d.removeEventListener('keydown', esc); if (w.mL) w.mL(); };
    var esc = function (e) { if (e.key === 'Escape') close(); };
    x.type = 'button';
    x.onclick = close;
    d.addEventListener('keydown', esc);
    d.getElementById('s').close();
    x.focus();
    if (navigator.sendBeacon) navigator.sendBeacon('/api/v1/beacon', JSON.stringify({ t: D.id, b: D.b, w: total()[1] }));
  }
})();
