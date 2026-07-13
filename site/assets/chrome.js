/* Shared nav + footer for every page. Each page has <div id="site-nav"></div>
   and <div id="site-footer"></div>; this fills them. Internal links use .html
   (the WordPress theme rewrites them to real routes on the live site).
   Edit nav/footer once here and every page updates. */
(function () {
  var NAV =
    '<header class="nav"><div class="in">' +
      '<a class="brand" href="index.html">My Site</a>' +
      '<nav class="links">' +
        '<a href="index.html">Home</a>' +
        '<a href="about.html">About</a>' +
        '<a class="btn" href="#">Get started</a>' +
      '</nav>' +
    '</div></header>';
  var FOOT =
    '<footer class="foot"><div class="in">' +
      '<span>&copy; ' + new Date().getFullYear() + ' My Site</span>' +
      '<span><a href="about.html">About</a> &middot; built with InstaStudio</span>' +
    '</div></footer>';
  var n = document.getElementById('site-nav');    if (n) n.innerHTML = NAV;
  var f = document.getElementById('site-footer');  if (f) f.innerHTML = FOOT;
})();
