/* Mobile menu toggle, header border on scroll, scroll-reveal for blog cards */
(function () {
  document.documentElement.classList.add('js');
  var header = document.getElementById('dp-header');
  var btn = document.querySelector('.dp-menu-toggle');
  var nav = document.getElementById('dp-nav');
  if (btn && nav) {
    btn.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }
  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }
  var items = document.querySelectorAll('.dp-reveal');
  if (!('IntersectionObserver' in window)) {
    items.forEach(function (el) { el.classList.add('is-in'); });
    return;
  }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
    });
  }, { rootMargin: '0px 0px -8% 0px' });
  items.forEach(function (el, i) { el.style.transitionDelay = (i % 3) * 90 + 'ms'; io.observe(el); });
})();
