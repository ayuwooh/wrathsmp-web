/* =========================================================
   WrathSMP — site behaviour
   Change the server details in CONFIG below.
   ========================================================= */
const CONFIG = {
  ip: '187.75.120.140',
  port: 25565,
  maxPlayers: 20,
  version: '26.3',
  dynmap: 'http://187.75.120.140:8123'
};

(() => {
  'use strict';

  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* -------------------------------------------------------
     Server details
     ------------------------------------------------------- */
  $$('[data-ip]').forEach((el) => {
    el.textContent = CONFIG.ip;
  });

  $$('[data-dynmap-link]').forEach((el) => {
    el.href = CONFIG.dynmap;
  });

  $$('[data-year]').forEach((el) => {
    el.textContent = new Date().getFullYear();
  });

  /* -------------------------------------------------------
     Copy IP
     ------------------------------------------------------- */
  const toast = $('[data-toast]');
  const toastText = $('[data-toast-text]');
  let toastTimer;

  function showToast(message) {
    if (!toast || !toastText) return;
    toastText.textContent = message;
    toast.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2400);
  }

  async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      try {
        await navigator.clipboard.writeText(text);
        return true;
      } catch (error) {
        /* fall through to the legacy path */
      }
    }

    try {
      const input = document.createElement('textarea');
      input.value = text;
      input.setAttribute('readonly', '');
      input.style.position = 'fixed';
      input.style.opacity = '0';
      document.body.appendChild(input);
      input.select();
      const ok = document.execCommand('copy');
      document.body.removeChild(input);
      return ok;
    } catch (error) {
      return false;
    }
  }

  $$('[data-copy-ip]').forEach((button) => {
    button.addEventListener('click', async () => {
      const ok = await copyText(CONFIG.ip);
      showToast(ok ? `IP copiado: ${CONFIG.ip}` : `Copie manualmente: ${CONFIG.ip}`);
    });
  });

  /* -------------------------------------------------------
     Header, mobile menu, back-to-top
     ------------------------------------------------------- */
  const header = $('[data-header]');
  const menuToggle = $('[data-menu-toggle]');
  const nav = $('[data-nav]');
  const backToTop = $('[data-back-to-top]');

  function closeMenu() {
    if (!menuToggle || !nav) return;
    menuToggle.setAttribute('aria-expanded', 'false');
    menuToggle.setAttribute('aria-label', 'Abrir menu');
    nav.classList.remove('is-open');
    header?.classList.remove('is-menu-open');
    document.body.classList.remove('nav-open');
  }

  menuToggle?.addEventListener('click', () => {
    const open = menuToggle.getAttribute('aria-expanded') !== 'true';
    menuToggle.setAttribute('aria-expanded', String(open));
    menuToggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
    nav?.classList.toggle('is-open', open);
    header?.classList.toggle('is-menu-open', open);
    document.body.classList.toggle('nav-open', open);
  });

  nav?.addEventListener('click', (event) => {
    if (event.target.closest('a')) closeMenu();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeMenu();
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 1020) closeMenu();
  });

  backToTop?.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
  });

  let scrollTicking = false;
  function onScroll() {
    if (scrollTicking) return;
    scrollTicking = true;

    requestAnimationFrame(() => {
      const y = window.scrollY;
      header?.classList.toggle('is-scrolled', y > 18);
      backToTop?.classList.toggle('is-visible', y > 720);
      updateToc(y);
      scrollTicking = false;
    });
  }

  window.addEventListener('scroll', onScroll, { passive: true });

  /* -------------------------------------------------------
     Scroll reveals
     ------------------------------------------------------- */
  const revealItems = $$('.reveal');

  if (reduceMotion || !('IntersectionObserver' in window)) {
    revealItems.forEach((item) => item.classList.add('is-in'));
  } else {
    const revealObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('is-in');
          revealObserver.unobserve(entry.target);
        });
      },
      { rootMargin: '0px 0px -8% 0px', threshold: 0.08 }
    );

    revealItems.forEach((item) => revealObserver.observe(item));
  }

  /* -------------------------------------------------------
     Documentation table of contents
     ------------------------------------------------------- */
  const tocLinks = $$('.toc a[href^="#"]');
  const docSections = $$('.doc-section[id]');

  function updateToc(scrollY) {
    if (!tocLinks.length || !docSections.length) return;

    let current = docSections[0].id;

    docSections.forEach((section) => {
      if (section.classList.contains('is-hidden')) return;
      if (scrollY >= section.offsetTop - 180) current = section.id;
    });

    tocLinks.forEach((link) => {
      const target = link.getAttribute('href')?.slice(1);
      const active = target === current;
      link.classList.toggle('is-active', active);
      if (active) {
        link.setAttribute('aria-current', 'location');
      } else {
        link.removeAttribute('aria-current');
      }
    });
  }

  /* -------------------------------------------------------
     Feature search
     ------------------------------------------------------- */
  const searchInput = $('#featureSearch');
  const searchCount = $('#searchCount');
  const emptyState = $('#emptyState');
  const searchItems = $$('[data-search-item]');
  const searchSections = $$('[data-doc-section]');

  const normalize = (value) =>
    value
      .toString()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();

  function runSearch() {
    if (!searchInput) return;

    const query = normalize(searchInput.value);
    let visible = 0;

    searchItems.forEach((item) => {
      const match = !query || normalize(item.textContent).includes(query);
      item.hidden = !match;
      if (match) visible += 1;
    });

    searchSections.forEach((section) => {
      const hasVisible = $$('[data-search-item]', section).some((item) => !item.hidden);
      section.classList.toggle('is-hidden', Boolean(query) && !hasVisible);
    });

    if (searchCount) {
      searchCount.classList.toggle('is-visible', Boolean(query));
      searchCount.innerHTML = query
        ? `<b>${visible}</b> ${visible === 1 ? 'resultado' : 'resultados'} para “${escapeHtml(searchInput.value.trim())}”`
        : '';
    }

    emptyState?.classList.toggle('is-visible', Boolean(query) && visible === 0);
    onScroll();
  }

  function escapeHtml(value) {
    return value.replace(/[&<>"']/g, (character) => {
      const entities = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
      return entities[character];
    });
  }

  searchInput?.addEventListener('input', runSearch);

  document.addEventListener('keydown', (event) => {
    const tag = document.activeElement?.tagName;
    const typing = tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement?.isContentEditable;

    if (event.key === '/' && !typing && searchInput) {
      event.preventDefault();
      searchInput.focus();
      searchInput.select();
    }
  });

  /* -------------------------------------------------------
     Live server status
     ------------------------------------------------------- */
  function setStatus(label, state) {
    $$('[data-status-label]').forEach((el) => {
      el.textContent = label;
    });

    $$('[data-status-dot]').forEach((dot) => {
      dot.classList.remove('is-offline', 'is-unknown');
      if (state === 'offline') dot.classList.add('is-offline');
      if (state === 'unknown') dot.classList.add('is-unknown');
    });
  }

  function setPlayers(online, max) {
    $$('[data-players]').forEach((el) => {
      el.textContent = online;
    });

    $$('[data-max-players]').forEach((el) => {
      el.textContent = max;
    });
  }

  async function loadStatus() {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 8000);

    try {
      const response = await fetch(`https://api.mcsrvstat.us/3/${CONFIG.ip}`, {
        signal: controller.signal,
        headers: { Accept: 'application/json' }
      });

      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const data = await response.json();

      if (data.online) {
        setStatus('Servidor online', 'online');
        setPlayers(
          Number.isFinite(data.players?.online) ? data.players.online : '—',
          Number.isFinite(data.players?.max) ? data.players.max : CONFIG.maxPlayers
        );
      } else {
        setStatus('Servidor offline', 'offline');
        setPlayers('—', CONFIG.maxPlayers);
      }
    } catch (error) {
      setStatus('Status indisponível', 'unknown');
      setPlayers('—', CONFIG.maxPlayers);
    } finally {
      clearTimeout(timer);
    }
  }

  loadStatus();
  onScroll();
})();
