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
  // With My order off (no D.o) the page gets none of it (QUESTIONS #236).
  if (D.o && !d.getElementById('sst')) {
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
      // Right-to-left (style H): "next" sits on the left and points left.
      var rtl = getComputedStyle(gl).direction == 'rtl';
      [-1, 1].forEach(function (s) {
        var b = gw.appendChild(el('button', 'ga' + (s > 0 ? ' gn' : ''), (s > 0) != rtl ? '\u203A' : '\u2039'));
        b.type = 'button';
        b.setAttribute('aria-label', s > 0 ? T.nx : T.pv);
        b.onclick = function () { gl.scrollTo({ left: (at() + s) * gl.offsetWidth * (rtl ? -1 : 1) }); };
      });
    }
  }
  add(el('h3', '', li.querySelector('.nm').textContent));
  var pr = li.querySelector('.pr');
  if (pr) add(el('div', 'pr', pr.textContent));
  if (x.d) add(el('p', '', x.d));
  // "My order": with the list on, sizes and add-ons become choices and an Add button follows.
  var o = D.o && D.o[li.dataset.i], can = o && w.mC && li.querySelector('.ad'), pick = { v: 0, a: {} }, upd = function () {};
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


// "My order" switched off for this menu (QUESTIONS #236): a list this phone saved
// earlier is never shown, and is removed, for every branch of this restaurant.
(function () {
  var w = window, D = w.mD || JSON.parse(document.getElementById('d').textContent), ls, k, i;
  if (D.o || D.p) return;
  try { ls = w.localStorage; for (i = ls.length - 1; i >= 0; i--) { k = ls.key(i); if (k && k.indexOf('mo:' + D.id + ':') === 0) ls.removeItem(k); } } catch (e) {}
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
  css.textContent = '.tt{position:fixed;inset-inline-end:.75rem;bottom:calc(.75rem + env(safe-area-inset-bottom));z-index:3;width:2.75rem;height:2.75rem;display:flex;align-items:center;justify-content:center;padding:0;border:0;border-radius:99px;background:var(--a);color:var(--af);box-shadow:0 2px 10px #0004;cursor:pointer;visibility:hidden;opacity:0;transform:translateY(.5rem);transition:opacity .2s,transform .2s,visibility .2s,bottom .2s}.tt.on{visibility:visible;opacity:1;transform:none}.hob .tt{bottom:calc(4.75rem + env(safe-area-inset-bottom))}.lk .tt{display:none}@media (prefers-reduced-motion:reduce){.tt{transition:none}}';
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
