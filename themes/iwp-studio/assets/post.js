/* ===========================================================================
   InstaWP blog — single-post behaviour: reading-progress bar, copy buttons,
   scroll-spy TOC, back-to-top. Shared by static preview + WP single.php.
   =========================================================================== */
(function(){
  var prefersReduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* reading-progress bar */
  var bar = document.getElementById('progress');
  var totop = document.getElementById('totop');
  function onScroll(){
    var h = document.documentElement;
    var max = h.scrollHeight - h.clientHeight;
    var pct = max > 0 ? (h.scrollTop || document.body.scrollTop) / max : 0;
    bar.style.width = (pct * 100) + '%';
    if ((h.scrollTop || document.body.scrollTop) > 600) totop.classList.add('show');
    else totop.classList.remove('show');
  }
  window.addEventListener('scroll', onScroll, {passive:true});
  onScroll();

  /* back to top */
  totop.addEventListener('click', function(){
    window.scrollTo({top:0, behavior: prefersReduce ? 'auto' : 'smooth'});
  });

  /* copy buttons */
  document.querySelectorAll('.code .copy').forEach(function(btn){
    btn.addEventListener('click', function(){
      var pre = btn.closest('.code').querySelector('pre');
      var text = pre.innerText.replace(/^\s*\$\s?/gm, '').trim();
      navigator.clipboard.writeText(text).then(function(){
        var orig = btn.innerHTML;
        btn.classList.add('done');
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5"/></svg>Copied';
        setTimeout(function(){ btn.classList.remove('done'); btn.innerHTML = orig; }, 1800);
      });
    });
  });

  /* scroll-spy TOC */
  var tocLinks = Array.prototype.slice.call(document.querySelectorAll('#toc a'));
  var map = {};
  tocLinks.forEach(function(a){ map[a.getAttribute('href').slice(1)] = a; });
  var heads = Array.prototype.slice.call(document.querySelectorAll('.prose [data-toc], #faq'));
  if ('IntersectionObserver' in window){
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if (e.isIntersecting){
          var id = e.target.id;
          tocLinks.forEach(function(a){ a.classList.remove('active'); });
          if (map[id]) map[id].classList.add('active');
        }
      });
    }, {rootMargin: '-80px 0px -70% 0px', threshold: 0});
    heads.forEach(function(h){ io.observe(h); });
  }

  /* mobile TOC toggle */
  var toc = document.getElementById('toc');
  var toggle = toc.querySelector('.toc-toggle');
  toggle.addEventListener('click', function(){
    var open = toc.classList.toggle('open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  tocLinks.forEach(function(a){
    a.addEventListener('click', function(){
      if (window.matchMedia('(max-width: 980px)').matches){
        toc.classList.remove('open');
        toggle.setAttribute('aria-expanded','false');
      }
    });
  });
})();
