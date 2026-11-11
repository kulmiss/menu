// Guest menu page script, inlined (PLAN.md §15.2.2). Budget: < 5 KB;
// rarely needed parts load on demand (sheet.js).
// Nothing heavy runs before the first paint: one delegated click handler now,
// everything else on the next idle moment.
(function () {
  var d = document, w = window, D, seen = {};
  var id = function (x) { return d.getElementById(x); };
  var on = function (e, n, f, o) { e.addEventListener(n, f, o); };
  var dl = id('s');
  var $ = function (s, r) { return (r || d).querySelectorAll(s); };
  var data = function () { return D || (D = w.mD = JSON.parse(id('d').textContent)); }; // w.mD: shared with the on-demand file
  var later = w.requestIdleCallback || function (f) { setTimeout(f, 1); };

  // Item details: photo prefetch on hover/touch; the sheet itself is a small
  // file loaded on demand (sheet.js), fetched in the background after load.
  var wait, tag;
  function big(id) { var x = data().c[id]; if (x && x.i && !seen[id]) { seen[id] = 1; new Image().src = x.i; } }
  function load() {
    if (tag) return;
    tag = d.createElement('script');
    tag.src = data().s;
    tag.onload = function () { if (wait) run(wait[0], wait[1]); wait = null; };
    d.head.appendChild(tag);
  }
  // Calls a part of the on-demand file (mS sheet, mH share, …), loading it first if needed.
  function run(f, a) { if (w['m' + f]) w['m' + f](a, data()); else { wait = [f, a]; load(); } }
  on(d, 'click', function (e) {
    var b = e.target.closest && e.target.closest('.it,.ib,[data-x]');
    if (b) b.dataset.x ? run(b.dataset.x, b) : b.hash ? id('info').open = 1 : run('S', b.parentNode); // the info button opens "Info & opening hours"
    else if (e.target.id === 'sx' || e.target === dl) dl.close();
  });
  ['pointerover', 'touchstart'].forEach(function (n) {
    on(d, n, function (e) { var b = e.target.closest && e.target.closest('.it'); if (b) big(b.parentNode.dataset.i); }, { passive: true });
  });

  later(function () {
    data();
    // Menus served at set times, and the open / closed pill (restaurant's time zone).
    try {
      var n = mhNow(D.z), T = D.t;
      $('.mn[data-s]').forEach(function (m) { m.classList.toggle('off', !mhIn(JSON.parse(m.dataset.s), n)); });
      // Promotions: shown only between their start and end (local time).
      $('[data-w]').forEach(function (e) { var p = JSON.parse(e.dataset.w), k = n.d + ' ' + n.t; e.hidden = !!(p[0] && k < p[0] || p[1] && k >= p[1]); });
      var st = mhState(D.h || [], D.x || [], n), pill = id('st');
      if (st && pill) {
        pill.textContent = st[0] === 2 ? T.ct : st[0] === 1 ? T.on.replace(':time', st[1]) : st[1] == null ? T.cl
          : T.cl + ' · ' + (st[2] === 0 ? T.oa : st[2] === 1 ? T.ot : T.od.replace(':day', T.dn[(n.w + st[2]) % 7])).replace(':time', st[1]);
        pill.className = 'st' + (st[0] === 1 ? ' op' : '');
        pill.hidden = false;
      }
    } catch (e) {}

    // Search and tag filters.
    var q = id('q'), tag = '', items = $('li[data-i]');
    function filter() {
      var s = q.value.trim().toLowerCase(), any = 0;
      items.forEach(function (li) {
        if (li._t == null) li._t = li.textContent.toLowerCase();
        var ok = (!s || li._t.indexOf(s) > -1) && (!tag || (' ' + li.dataset.t + ' ').indexOf(' ' + tag + ' ') > -1);
        li.hidden = !ok; any |= ok;
      });
      $('section').forEach(function (sec) { sec.hidden = !sec.querySelector('li:not([hidden])'); });
      id('nr').hidden = !!any;
    }
    on(q, 'input', filter);
    $('[data-tag]').forEach(function (b) {
      on(b, 'click', function () {
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

    function off() { id('of').hidden = navigator.onLine; }
    on(w, 'online', off); on(w, 'offline', off); off();
  });

  // A QR scan arrives as #q=CODE: remember it for the beacon, then tidy the address.
  var scan = w.mQ = (location.hash.match(/^#q=([A-Z0-9]{8})$/) || [])[1];
  if (scan && history.replaceState) history.replaceState(null, '', location.pathname + location.search);

  // Service worker and the visit beacon after load.
  on(w, 'load', function () {
    if (data().p) return; // admin preview: no service worker, no visit counted
    later(function () {
      load();
      if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(function () {});
      if (navigator.sendBeacon) navigator.sendBeacon('/api/v1/beacon', JSON.stringify({ t: D.id, b: D.b, q: scan || null }));
    });
  });
})();
