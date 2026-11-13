// Guest menu service worker (PLAN.md §15.2.6).
// Menu pages: show the saved copy at once, fetch a fresh one in the background
// (stale-while-revalidate), so repeat visits are instant and work offline. When the
// fresh copy differs (the restaurant changed something), the open page is told and
// reloads from it at once — guests never keep looking at old prices.
// Photos and the on-demand item sheet script: cache-first; their URLs never change.
var PAGES = 'menu-pages-v1', MEDIA = 'menu-media-v1', MAX_MEDIA = 300;

self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (k) { return k !== PAGES && k !== MEDIA; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

// The menu changed (prices, dishes, anything) when the new copy differs from the saved one.
function changed(a, b) {
  var x = a.headers.get('ETag'), y = b.headers.get('ETag');
  if (x && y) return Promise.resolve(x !== y);
  return Promise.all([a.text(), b.text()]).then(function (t) { return t[0] !== t[1]; });
}

// Tell the open menu page(s) at this address; they reload from the fresh copy.
// The change time is also kept, because the page may not listen yet: once loaded
// it asks (a message with the time it was opened) and gets the answer then.
var changedAt = {};
function tell(path) {
  changedAt[path] = Date.now();
  return self.clients.matchAll({ type: 'window' }).then(function (list) {
    list.forEach(function (w) { if (new URL(w.url).pathname === path) w.postMessage('mu'); });
  });
}

function trim(cache) {
  cache.keys().then(function (keys) {
    for (var i = 0; i < keys.length - MAX_MEDIA; i++) cache.delete(keys[i]);
  });
}

self.addEventListener('message', function (e) {
  var src = e.source, path = src && src.url && new URL(src.url).pathname;
  if (typeof e.data === 'number' && changedAt[path] >= e.data) src.postMessage('mu');
});

self.addEventListener('fetch', function (e) {
  var r = e.request, u = new URL(r.url);
  if (r.method !== 'GET' || u.origin !== location.origin) return;

  if (u.pathname.indexOf('/media/') === 0 || u.pathname.indexOf('/menu-assets/') === 0) {
    e.respondWith(caches.open(MEDIA).then(function (c) {
      return c.match(r).then(function (hit) {
        return hit || fetch(r).then(function (res) {
          if (res.ok) { c.put(r, res.clone()); trim(c); }
          return res;
        });
      });
    }));
    return;
  }

  // Guest pages are single-segment paths; the admin, API and files are left alone.
  if (r.mode === 'navigate' && /^\/[a-z0-9-]+$/.test(u.pathname) && !/^\/(admin|platform|auth|api|q)$/.test(u.pathname)) {
    e.respondWith(caches.open(PAGES).then(function (c) {
      return c.match(r, { ignoreSearch: true }).then(function (hit) {
        var old = hit && hit.clone();
        var net = fetch(r).then(function (res) {
          if (res.ok) {
            var fresh = res.clone();
            // Saved first, then the open page is told, so its reload gets the new copy.
            c.put(u.pathname, res.clone()).then(function () { return old && changed(old, fresh); })
              .then(function (yes) { if (yes) tell(u.pathname); });
          }
          return res;
        });
        if (hit) { e.waitUntil(net.catch(function () {})); return hit; }
        return net;
      });
    }));
  }
});
