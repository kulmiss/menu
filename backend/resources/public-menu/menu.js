// Guest menu page script, inlined (PLAN.md §15.2.2). Budget: < 5 KB.
// Nothing heavy runs before the first paint: one delegated click handler now,
// everything else on the next idle moment.
(function () {
  var d = document, w = window, D, dl = d.getElementById('s'), body = d.getElementById('sb'), seen = {};
  var $ = function (s, r) { return (r || d).querySelectorAll(s); };
  var el = function (t, c, x) { var e = d.createElement(t); if (c) e.className = c; if (x != null) e.textContent = x; return e; };
  var data = function () { return D || (D = JSON.parse(d.getElementById('d').textContent)); };
  var later = w.requestIdleCallback || function (f) { setTimeout(f, 1); };

  // Item details sheet, built from the data embedded in the page.
  function big(id) { var x = data().c[id]; if (x && x.i && !seen[id]) { seen[id] = 1; new Image().src = x.i; } }
  function row(a, b, so) { var r = el('div', 'ro'); r.appendChild(el('span', '', a)); r.appendChild(el('span', so ? 'mu' : '', so ? D.t.so : b)); return r; }
  function open(li) {
    var x = data().c[li.dataset.i] || {}, T = D.t;
    body.textContent = '';
    if (x.i) { var im = el('img'); im.src = x.i; im.alt = ''; im.width = 1280; im.height = 853; body.appendChild(im); }
    body.appendChild(el('h3', '', li.querySelector('.nm').textContent));
    var pr = li.querySelector('.pr'); if (pr) body.appendChild(el('div', 'pr', pr.textContent));
    if (x.d) body.appendChild(el('p', '', x.d));
    if (x.v) { body.appendChild(el('h4', '', T.sz)); x.v.forEach(function (v) { body.appendChild(row(v[0], v[1])); }); }
    (x.a || []).forEach(function (g) {
      body.appendChild(el('h4', '', g[0]));
      var h = g[2] ? (g[1] === g[2] ? T.ex.replace(':count', g[2]) : g[1] ? T.ch.replace(':min', g[1]).replace(':max', g[2]) : T.up.replace(':max', g[2])) : '';
      if (h) body.appendChild(el('div', 'mu', h));
      g[3].forEach(function (o) { body.appendChild(row(o[0], o[1], o[2])); });
    });
    if (x.al) { body.appendChild(el('h4', '', T.al)); body.appendChild(el('div', '', x.al.join(', '))); }
    var m = [];
    if (x.k) m.push(T.kc.replace(':count', x.k));
    if (x.p) m.push(T.pm.replace(':count', x.p));
    if (m.length) body.appendChild(el('p', 'mu', m.join(' · ')));
    if (dl.showModal) dl.showModal(); else dl.setAttribute('open', '');
  }
  d.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('.it');
    if (b) open(b.parentNode);
    else if (e.target.id === 'sx' || e.target === dl) dl.close();
  });
  ['pointerover', 'touchstart'].forEach(function (n) {
    d.addEventListener(n, function (e) { var b = e.target.closest && e.target.closest('.it'); if (b) big(b.parentNode.dataset.i); }, { passive: true });
  });

  later(function () {
    data();
    // Menus served at set times: label the ones not on now (restaurant's time zone).
    try {
      var p = {};
      new Intl.DateTimeFormat('en-GB', { timeZone: D.z, weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date()).forEach(function (x) { p[x.type] = x.value; });
      var wd = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(p.weekday), now = p.hour + ':' + p.minute;
      $('.mn[data-s]').forEach(function (m) {
        m.classList.toggle('off', !JSON.parse(m.dataset.s).some(function (s) {
          var a = s[1], b = s[2];
          if (a < b) return s[0] === wd && now >= a && now < b;
          return (s[0] === wd && now >= a) || (s[0] === (wd + 6) % 7 && now < b);
        }));
      });
    } catch (e) {}

    // Search and tag filters.
    var q = d.getElementById('q'), tag = '', items = $('li[data-i]');
    function filter() {
      var s = q.value.trim().toLowerCase(), any = 0;
      items.forEach(function (li) {
        if (li._t == null) li._t = li.textContent.toLowerCase();
        var ok = (!s || li._t.indexOf(s) > -1) && (!tag || (' ' + li.dataset.t + ' ').indexOf(' ' + tag + ' ') > -1);
        li.hidden = !ok; any |= ok;
      });
      $('section').forEach(function (sec) { sec.hidden = !sec.querySelector('li:not([hidden])'); });
      d.getElementById('nr').hidden = !!any;
    }
    q.addEventListener('input', filter);
    $('[data-tag]').forEach(function (b) {
      b.addEventListener('click', function () {
        tag = tag === b.dataset.tag ? '' : b.dataset.tag;
        $('[data-tag]').forEach(function (x) { x.setAttribute('aria-pressed', x.dataset.tag === tag); });
        filter();
      });
    });

    // Highlight the category chip for the section on screen.
    var chips = {};
    $('.cn a').forEach(function (a) { chips[a.hash.slice(1)] = a; });
    if ('IntersectionObserver' in w) {
      var io = new IntersectionObserver(function (es) {
        es.forEach(function (e) {
          var a = chips[e.target.id];
          if (a && e.isIntersecting) {
            $('.cn a.on').forEach(function (x) { x.classList.remove('on'); });
            a.classList.add('on');
            a.parentNode.scrollLeft = a.offsetLeft - a.parentNode.offsetWidth / 2 + a.offsetWidth / 2;
          }
        });
      }, { rootMargin: '-25% 0px -70% 0px' });
      $('section[id]').forEach(function (s) { io.observe(s); });
    }

    function off() { d.getElementById('of').hidden = navigator.onLine; }
    w.addEventListener('online', off); w.addEventListener('offline', off); off();
  });

  // A QR scan arrives as #q=CODE: remember it for the beacon, then tidy the address.
  var scan = (location.hash.match(/^#q=([A-Z0-9]{8})$/) || [])[1];
  if (scan && history.replaceState) history.replaceState(null, '', location.pathname + location.search);

  // Service worker and the visit beacon after load.
  w.addEventListener('load', function () {
    if (data().p) return; // admin preview: no service worker, no visit counted
    later(function () {
      if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(function () {});
      if (navigator.sendBeacon) navigator.sendBeacon('/api/v1/beacon', JSON.stringify({ t: data().id, q: scan || null }));
    });
  });
})();
