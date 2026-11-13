// Opening state for the guest page (PLAN.md §2: computed in the browser in
// the restaurant's time zone, so a cached page is never wrong for long).
// Kept free of the page so it can be unit-tested (frontend/src/public-menu).
// n = { w: weekday 0–6 (0 = Sunday), t: "HH:MM", d: "YYYY-MM-DD" }.
function mhNow(z, date) {
  var p = {};
  new Intl.DateTimeFormat('en-GB', { timeZone: z, weekday: 'short', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
    .formatToParts(date || new Date()).forEach(function (x) { p[x.type] = x.value; });
  return { w: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(p.weekday), t: p.hour + ':' + p.minute, d: p.year + '-' + p.month + '-' + p.day };
}

// The window [weekday, "HH:MM", "HH:MM"] that "now" falls in, or null.
// An end at or before the start runs past midnight into the next day.
function mhIn(list, n) {
  for (var i = 0; i < list.length; i++) {
    var s = list[i], a = s[1], b = s[2];
    if (a < b ? s[0] === n.w && n.t >= a && n.t < b : (s[0] === n.w && n.t >= a) || (s[0] === (n.w + 6) % 7 && n.t < b)) return s;
  }
  return null;
}

// null = no hours set; [2] = closed today (holiday);
// [1, "22:00"] = open until; [0, "07:00", days ahead] = closed, opens then.
function mhState(h, x, n) {
  if (!h.length) return null;
  for (var i = 0; i < x.length; i++) if (n.d >= x[i][0] && n.d <= x[i][1]) return [2];
  var s = mhIn(h, n);
  if (s) return [1, s[2]];
  for (var k = 0; k <= 7; k++) {
    var best = null;
    for (var j = 0; j < h.length; j++) {
      var r = h[j];
      if (r[0] === (n.w + k) % 7 && (k || r[1] > n.t) && (!best || r[1] < best)) best = r[1];
    }
    if (best) return [0, best, k];
  }
  return [0];
}
