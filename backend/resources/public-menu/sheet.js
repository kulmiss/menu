// Item details sheet for the guest page, loaded on demand (PLAN.md §15.2.2):
// the page fetches this small file in the background after it has loaded,
// so it is normally ready at the first tap; the service worker keeps it.
window.mS = function (li, D) {
  var d = document, dl = d.getElementById('s'), body = d.getElementById('sb'), T = D.t, x = D.c[li.dataset.i] || {};
  var el = function (t, c, s) { var e = d.createElement(t); if (c) e.className = c; if (s != null) e.textContent = s; return e; };
  var add = function (e) { body.appendChild(e); return e; };
  var row = function (a, b, so) { var r = el('div', 'ro'); r.appendChild(el('span', '', a)); r.appendChild(el('span', so ? 'mu' : '', so ? T.so : b)); return r; };
  body.textContent = '';
  if (x.i) { var im = el('img'); im.src = x.i; im.alt = ''; im.width = 1280; im.height = 853; add(im); }
  add(el('h3', '', li.querySelector('.nm').textContent));
  var pr = li.querySelector('.pr');
  if (pr) add(el('div', 'pr', pr.textContent));
  if (x.d) add(el('p', '', x.d));
  if (x.v) { add(el('h4', '', T.sz)); x.v.forEach(function (v) { add(row(v[0], v[1])); }); }
  (x.a || []).forEach(function (g) {
    add(el('h4', '', g[0]));
    var h = g[2] ? (g[1] === g[2] ? T.ex.replace(':count', g[2]) : g[1] ? T.ch.replace(':min', g[1]).replace(':max', g[2]) : T.up.replace(':max', g[2])) : '';
    if (h) add(el('div', 'mu', h));
    g[3].forEach(function (o) { add(row(o[0], o[1], o[2])); });
  });
  if (x.al) { add(el('h4', '', T.al)); add(el('div', '', x.al.join(', '))); }
  var m = [];
  if (x.k) m.push(T.kc.replace(':count', x.k));
  if (x.p) m.push(T.pm.replace(':count', x.p));
  if (m.length) add(el('p', 'mu', m.join(' · ')));
  if (dl.showModal) dl.showModal(); else dl.setAttribute('open', '');
};
