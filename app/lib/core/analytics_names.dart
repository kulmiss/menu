/// Route path → anonymous screen name: /r/12/dishes/5 → /r/:id/dishes/:id.
String screenNameOf(String path) => path.split('?').first.replaceAll(RegExp(r'/\d+'), '/:id');
