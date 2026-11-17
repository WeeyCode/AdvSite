/* ============================================================
   Wesley Martins Jardim — Advocacia
   Scripts do site público. Arquivo externo: a CSP não permite
   scripts inline ('script-src self').
   ============================================================ */
(function () {
  'use strict';

  /* ── Navegação ── */
  var nav = document.getElementById('navbar');
  var toggle = document.getElementById('navToggle');
  var links = document.getElementById('navLinks');
  var scrollTop = document.getElementById('scrollTop');

  function aoRolar() {
    var y = window.scrollY || window.pageYOffset;
    if (nav) nav.classList.toggle('scrolled', y > 40);
    if (scrollTop) scrollTop.classList.toggle('show', y > 700);
  }
  window.addEventListener('scroll', aoRolar, { passive: true });
  aoRolar();

  if (toggle && links) {
    toggle.addEventListener('click', function () {
      var aberto = links.classList.toggle('active');
      toggle.setAttribute('aria-expanded', aberto ? 'true' : 'false');
      var i = toggle.querySelector('i');
      if (i) {
        i.classList.toggle('fa-bars', !aberto);
        i.classList.toggle('fa-xmark', aberto);
      }
    });
    links.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        links.classList.remove('active');
        toggle.setAttribute('aria-expanded', 'false');
        var i = toggle.querySelector('i');
        if (i) { i.classList.add('fa-bars'); i.classList.remove('fa-xmark'); }
      });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && links.classList.contains('active')) {
        links.classList.remove('active');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ── Revelar ao rolar ── */
  var alvos = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    var obs = new IntersectionObserver(function (entradas) {
      entradas.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('visible');
          obs.unobserve(en.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
    alvos.forEach(function (el) { obs.observe(el); });
  } else {
    alvos.forEach(function (el) { el.classList.add('visible'); });
  }

  /* ── Aviso de cookies (LGPD) ── */
  var banner = document.getElementById('cookieBanner');
  function decidirCookies(valor) {
    try { localStorage.setItem('wmj_cookies', valor); } catch (e) {}
    if (banner) banner.classList.remove('show');
  }
  var aceitar = document.getElementById('ckAccept');
  var recusar = document.getElementById('ckReject');
  if (aceitar) aceitar.addEventListener('click', function () { decidirCookies('aceito'); });
  if (recusar) recusar.addEventListener('click', function () { decidirCookies('recusado'); });

  var escolha = null;
  try { escolha = localStorage.getItem('wmj_cookies'); } catch (e) {}
  if (!escolha && banner) {
    window.setTimeout(function () { banner.classList.add('show'); }, 2200);
  }
})();
