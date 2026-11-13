// Item details sheet for the guest page, loaded on demand (PLAN.md §15.2.2):
// the page fetches this small file in the background after it has loaded,
// so it is normally ready at the first tap; the service worker keeps it.
window.mS = function (li, D) {
  var w = window, d = document, dl = d.getElementById('s'), body = d.getElementById('sb'), T = D.t, x = D.c[li.dataset.i] || {};
  var el = function (t, c, s) { var e = d.createElement(t); if (c) e.className = c; if (s != null) e.textContent = s; return e; };
  var add = function (e) { body.appendChild(e); return e; };
  var row = function (a, b, so) { var r = el('div', 'ro'); r.appendChild(el('span', '', a)); r.appendChild(el('span', so ? 'mu' : '', so ? T.so : b)); return r; };
  body.textContent = '';
  // Choices and the sticky "Add to my order" bar; made once, so they look right before anything is in the list.
  if (!d.getElementById('sst')) {
    var st = d.head.appendChild(el('style'));
    st.id = 'sst';
    st.textContent = '.oc{cursor:pointer;align-items:center;min-height:2.75rem}.oc input{flex:none;width:1.25rem;height:1.25rem;margin:0;accent-color:var(--a)}.oc span:nth-child(2){flex:1}.sf{position:sticky;bottom:0;z-index:1;margin:1.25rem -1rem -1.5rem;padding:.75rem 1rem calc(.75rem + env(safe-area-inset-bottom));background:var(--bg);border-top:1px solid var(--ln)}.sf .mu{margin:0 0 .5rem;text-align:center}.sf .mu:empty{display:none}.oa{display:flex;align-items:center;justify-content:center;width:100%;min-height:3.25rem;padding:.5rem 1.25rem;border:0;border-radius:var(--br,99px);background:var(--a);color:var(--af);font:inherit;font-size:1rem;font-weight:700;box-shadow:0 2px 8px #0002;cursor:pointer}.oa:active{transform:scale(.98)}';
  }
  // Photos: the main one, then up to 4 more to swipe through (scroll snap).
  var pics = x.i ? [x.i].concat(x.g || []) : [], n = pics.length;
  if (n) {
    var gw = add(el('div', 'gw')), gl = gw.appendChild(el('div', 'gl'));
    pics.forEach(function (src, k) { var im = el('img'); im.src = src; im.alt = ''; im.width = 1280; im.height = 853; if (k) im.loading = 'lazy'; gl.appendChild(im); });
    if (n > 1) {
      var gc = gw.appendChild(el('span', 'gc', '1 / ' + n)), at = function () { return Math.round(Math.abs(gl.scrollLeft) / gl.offsetWidth); };
      gl.tabIndex = 0;
      gl.setAttribute('role', 'group');
      gl.setAttribute('aria-label', T.ph.replace(':count', n));
      gl.onscroll = function () { gc.textContent = at() + 1 + ' / ' + n; };
      // Arrows for mouse users (hidden on touch screens by the CSS).
      [-1, 1].forEach(function (s) {
        var b = gw.appendChild(el('button', 'ga' + (s > 0 ? ' gn' : ''), s > 0 ? '\u203A' : '\u2039'));
        b.type = 'button';
        b.setAttribute('aria-label', s > 0 ? T.nx : T.pv);
        b.onclick = function () { gl.scrollTo({ left: (at() + s) * gl.offsetWidth * (getComputedStyle(gl).direction == 'rtl' ? -1 : 1) }); };
      });
    }
  }
  add(el('h3', '', li.querySelector('.nm').textContent));
  var pr = li.querySelector('.pr');
  if (pr) add(el('div', 'pr', pr.textContent));
  if (x.d) add(el('p', '', x.d));
  // "My order": with the list on, sizes and add-ons become choices and an Add button follows.
  var o = D.o && D.o[li.dataset.i], can = o && li.querySelector('.ad'), pick = { v: 0, a: {} }, upd = function () {};
  var choice = function (type, name, text, price, on, f, off) {
    var r = el('label', 'ro oc'), i = el('input');
    i.type = type; i.name = name; i.checked = on; i.disabled = !!off; i.onchange = f;
    r.appendChild(i); r.appendChild(el('span', '', text)); r.appendChild(el('span', off ? 'mu' : '', off ? T.so : price));
    return r;
  };
  if (x.v) {
    add(el('h4', '', T.sz));
    x.v.forEach(function (v, k) { add(can ? choice('radio', 'sz', v[0], v[1], !k, function () { pick.v = k; upd(); }) : row(v[0], v[1])); });
  }
  (x.a || []).forEach(function (g, gi) {
    add(el('h4', '', g[0]));
    var h = g[2] ? (g[1] === g[2] ? T.ex.replace(':count', g[2]) : g[1] ? T.ch.replace(':min', g[1]).replace(':max', g[2]) : T.up.replace(':max', g[2])) : '';
    if (h) add(el('div', 'mu', h));
    var box = [];
    g[3].forEach(function (op, oi) {
      if (!can) return add(row(op[0], op[1], op[2]));
      var r = add(choice('checkbox', 'a' + gi, op[0], op[1], false, function () {
        var on = r.firstChild.checked, n = 0;
        if (on) pick.a[gi + ':' + oi] = 1; else delete pick.a[gi + ':' + oi];
        box.forEach(function (b) { n += b.firstChild.checked; });
        // At the group's maximum, the other options can't be ticked.
        box.forEach(function (b) { if (!b.firstChild.checked) b.firstChild.disabled = b.dataset.so == 1 || (g[2] && n >= g[2]); });
        upd();
      }, op[2]));
      r.dataset.so = op[2];
      box.push(r);
    });
  });
  if (x.al) { add(el('h4', '', T.al)); add(el('div', '', x.al.join(', '))); }
  var m = [];
  if (x.k) m.push(T.kc.replace(':count', x.k));
  if (x.p) m.push(T.pm.replace(':count', x.p));
  if (m.length) add(el('p', 'mu', m.join(' · ')));
  if (can) {
    var msg = el('p', 'mu'), btn = el('button', 'oa');
    msg.setAttribute('role', 'status');
    btn.type = 'button';
    upd = function () { btn.textContent = D.t.o.add + ' · ' + w.mC.fmt(w.mC.price(+li.dataset.i, pick)); };
    btn.onclick = function () {
      var short = (o[2] || []).filter(function (g, gi) { var n = 0; for (var k in pick.a) n += k.split(':')[0] == gi; return n < g[0]; })[0];
      if (short) { msg.textContent = D.t.o.pick.replace(':count', short[0]); return; }
      w.mC.add(+li.dataset.i, pick, li.querySelector('.nm').textContent, x);
      dl.close();
    };
    var sf = add(el('div', 'sf'));
    sf.appendChild(msg); sf.appendChild(btn); upd();
  }
  if (dl.showModal) dl.showModal(); else dl.setAttribute('open', '');
  // Insights: count that this dish's details were opened (not in the admin preview).
  if (!D.p && navigator.sendBeacon) navigator.sendBeacon('/api/v1/beacon', JSON.stringify({ t: D.id, b: D.b, i: +li.dataset.i }));
};

// "Share menu": the phone's own share sheet (WhatsApp, Telegram, SMS, …);
// elsewhere a small sheet with the link, "Copy link", WhatsApp and Telegram.
// Shares the menu address with the branch, never the table (?table=, #q=).
window.mH = function (b, D) {
  var d = document, n = navigator, T = D.t, u = location.origin + location.pathname, t = d.title, e = encodeURIComponent;
  if (n.share) { n.share({ title: t, url: u }).catch(function () {}); return; }
  var dl = d.getElementById('s'), body = d.getElementById('sb');
  var el = function (g, c, s) { var x = d.createElement(g); if (c) x.className = c; if (s != null) x.textContent = s; return x; };
  body.textContent = '';
  body.appendChild(el('h3', '', T.sh));
  var f = body.appendChild(el('input', 'su'));
  f.readOnly = true; f.value = u; f.setAttribute('aria-label', T.sl);
  var l = body.appendChild(el('div', 'sl')), st = el('p', 'mu');
  st.setAttribute('role', 'status');
  var c = l.appendChild(el('button', '', T.cp));
  c.type = 'button';
  c.onclick = function () {
    var ok = function () { st.textContent = T.cd; };
    if (n.clipboard) n.clipboard.writeText(u).then(ok, function () { f.select(); });
    else { f.select(); if (d.execCommand('copy')) ok(); }
  };
  [['WhatsApp', 'https://wa.me/?text=' + e(t + ' ' + u)], ['Telegram', 'https://t.me/share/url?url=' + e(u) + '&text=' + e(t)]].forEach(function (s) {
    var a = l.appendChild(el('a', '', s[0])); a.href = s[1]; a.target = '_blank'; a.rel = 'noopener';
  });
  body.appendChild(st);
  if (dl.showModal) dl.showModal(); else dl.setAttribute('open', '');
};

// "My order": a list kept only on this phone (localStorage, per restaurant
// and branch). Nothing is sent to the restaurant; it is shown to the waiter.
// "Menu updated": the page reloaded itself because the restaurant changed something
// (menu.js sets sessionStorage.mu just before). A quiet line for ~3 s, read out politely.
(function () {
  var d = document, w = window, mu;
  try { mu = +sessionStorage.mu; if (!mu || Date.now() - mu > 15e3 || sessionStorage.mn == mu) return; sessionStorage.mn = mu; } catch (e) { return; }
  var D = w.mD || JSON.parse(d.getElementById('d').textContent), n = d.createElement('p');
  n.setAttribute('role', 'status');
  n.textContent = D.t.mu;
  n.style.cssText = 'position:fixed;top:.75rem;left:50%;transform:translateX(-50%);z-index:4;margin:0;padding:.45rem .9rem;border-radius:99px;background:var(--fg);color:var(--bg);font-size:.875rem;box-shadow:0 2px 10px #0003;pointer-events:none;transition:opacity .4s';
  d.body.appendChild(n);
  setTimeout(function () { n.style.opacity = 0; setTimeout(function () { n.remove(); }, 450); }, 3e3);
})();

(function () {
  var d = document, w = window, D = w.mD || JSON.parse(d.getElementById('d').textContent);
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
    css.textContent = '.ob{position:fixed;inset:auto .75rem .75rem;z-index:3;max-width:42rem;margin:0 auto;min-height:3.25rem;border:0;border-radius:99px;background:var(--a);color:var(--af);font:inherit;font-weight:700;box-shadow:0 4px 16px #0004;cursor:pointer}.hob{padding-bottom:5rem}.ow{display:block;width:100%;min-height:3rem;margin-top:1rem;border:0;border-radius:99px;background:var(--a);color:var(--af);font:inherit;font-weight:700;cursor:pointer}.ok{background:none;border:1px solid var(--ln);color:var(--fg);min-height:2.75rem}.ol,.wv ul{display:block;list-style:none;margin:.75rem 0 0;padding:0}.ol li,.wv li{background:none;border-radius:0;box-shadow:none;overflow:visible}.ol li{padding:.75rem 0;border-bottom:1px solid var(--ln)}.ol .r1{display:flex;justify-content:space-between;gap:.75rem;font-weight:600}.ol .r2{display:flex;align-items:center;gap:.5rem;margin-top:.5rem}.ol .r2 button{min-width:2.75rem;height:2.75rem;border-radius:99px;border:1px solid var(--ln);background:var(--card);color:var(--fg);font:inherit;font-size:1.1rem;cursor:pointer}.ol .r2 .rm{margin-inline-start:auto;padding:0 .9rem;font-size:.9rem}.ol .r2 b{min-width:1.5rem;text-align:center}.ol input{width:100%;height:2.75rem;margin-top:.5rem;padding:0 .75rem;border:1px solid var(--ln);border-radius:.6rem;background:var(--card);color:var(--fg);font:inherit}.ol .bad{color:var(--fg);font-weight:600;font-size:.85rem}.ol .x0 .r1{text-decoration:line-through;color:var(--mu)}.otl{display:flex;justify-content:space-between;font-size:1.15rem;font-weight:700;margin-top:1rem}.wv{position:fixed;inset:0;z-index:9;overflow:auto;background:#fff;color:#000;padding:1.25rem 1.25rem 2rem;font-size:1.3rem;line-height:1.35}.wv h2{font-size:2.2rem;margin:0 0 .25rem}.wv ul{margin:1rem 0}.wv li{padding:.8rem 0;border-bottom:2px solid #000}.wv b{font-size:1.5rem}.wv .wn{display:block;font-style:italic}.wv .wt{display:flex;justify-content:space-between;font-size:1.8rem;font-weight:800;margin-top:1rem}.wv button{position:sticky;top:0;float:right;min-width:3rem;min-height:3rem;border:2px solid #000;border-radius:99px;background:#fff;color:#000;font:inherit;font-weight:700;padding:0 1rem;cursor:pointer}';
    d.head.appendChild(css);
    b = d.body.appendChild(el('button', 'ob'));
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
      if (navigator.sendBeacon) navigator.sendBeacon('/api/v1/beacon', JSON.stringify({ t: D.id, b: D.b, c: [id, 1, total()[1] + (unit(l) || 0)] }));
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

// While a sheet (dish, share, My order) or the waiter view is open, the page
// behind it must not scroll: the body is pinned where it was and put back to
// the exact spot on close (iOS Safari ignores overflow:hidden on the body).
// Swipe down on the sheet when it's scrolled to the top closes it.
(function () {
  var d = document, w = window, dl = d.getElementById('s'), bs = d.body.style, y = 0, y0 = -1, dy = 0, css = d.createElement('style');
  css.textContent = 'dialog,.wv{overscroll-behavior:contain}.lk{position:fixed;left:0;right:0;overflow:hidden}';
  d.head.appendChild(css);
  var sync = w.mL = function () {
    var on = dl.open || !!d.querySelector('.wv'), lk = d.body.classList.contains('lk');
    if (on && !lk) { y = w.scrollY; bs.top = -y + 'px'; d.body.classList.add('lk'); }
    if (!on && lk) { d.body.classList.remove('lk'); bs.top = ''; w.scrollTo({ top: y, behavior: 'instant' }); }
  };
  var show = dl.showModal;
  if (show) dl.showModal = function () { show.call(dl); sync(); };
  dl.addEventListener('close', sync);
  dl.addEventListener('touchstart', function (e) { y0 = dl.scrollTop > 0 ? -1 : e.touches[0].clientY; dy = 0; }, { passive: true });
  dl.addEventListener('touchmove', function (e) {
    if (y0 < 0) return;
    dy = e.touches[0].clientY - y0;
    dl.style.transform = dy > 0 ? 'translateY(' + dy + 'px)' : '';
  }, { passive: true });
  dl.addEventListener('touchend', function () {
    if (y0 < 0) return;
    y0 = -1;
    dl.style.transform = '';
    if (dy > 90) dl.close();
  });
})();

// "Back to top": a small round button that shows after about 1.5 screens of
// scrolling; it sits above the My order bar and hides while a sheet is open.
(function () {
  var d = document, w = window, D = w.mD || JSON.parse(d.getElementById('d').textContent), css = d.createElement('style'), b = d.createElement('button'), on = 0;
  css.textContent = '.tt{position:fixed;inset-inline-start:.75rem;bottom:calc(.75rem + env(safe-area-inset-bottom));z-index:3;width:2.75rem;height:2.75rem;display:flex;align-items:center;justify-content:center;padding:0;border:0;border-radius:99px;background:var(--a);color:var(--af);box-shadow:0 2px 10px #0004;cursor:pointer;visibility:hidden;opacity:0;transform:translateY(.5rem);transition:opacity .2s,transform .2s,visibility .2s,bottom .2s}.tt.on{visibility:visible;opacity:1;transform:none}.hob .tt{bottom:calc(4.75rem + env(safe-area-inset-bottom))}.lk .tt{display:none}@media (prefers-reduced-motion:reduce){.tt{transition:none}}';
  d.head.appendChild(css);
  b.type = 'button';
  b.className = 'tt';
  b.setAttribute('aria-label', D.t.tp);
  b.innerHTML = '<svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>';
  b.onclick = function () { w.scrollTo({ top: 0, behavior: w.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' }); };
  d.body.appendChild(b);
  var check = function () {
    var v = w.scrollY > w.innerHeight * 1.5;
    if (v != on) b.classList.toggle('on', on = v);
  };
  w.addEventListener('scroll', check, { passive: true });
  check();
})();
