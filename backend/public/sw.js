// Guest menu service worker (PLAN.md §15.2.6).
// Menu pages: show the saved copy at once, fetch a fresh one in the background
// (stale-while-revalidate), so repeat visits are instant and work offline.
// Photos: cache-first; their URLs never change.
var PAGES = 'menu-pages-v1', MEDIA = 'menu-media-v1', MAX_MEDIA = 300;

self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (k) { return k !== PAGES && k !== MEDIA; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

function trim(cache) {
  cache.keys().then(function (keys) {
    for (var i = 0; i < keys.length - MAX_MEDIA; i++) cache.delete(keys[i]);
  });
}

self.addEventListener('fetch', function (e) {
  var r = e.request, u = new URL(r.url);
  if (r.method !== 'GET' || u.origin !== location.origin) return;

  if (u.pathname.indexOf('/media/') === 0) {
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
        var net = fetch(r).then(function (res) {
          if (res.ok) c.put(u.pathname, res.clone());
          return res;
        });
        if (hit) { e.waitUntil(net.catch(function () {})); return hit; }
        return net;
      });
    }));
  }
});
