// Modern Navigation JS Scaffold
(function() {
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('.submenu-toggle');
    if (!btn) {
      if (!e.target.closest('.nav__menu')) {
        document.querySelectorAll('.nav-item.open').forEach(li => {
          li.classList.remove('open');
          const b = li.querySelector('.submenu-toggle');
          if (b) b.setAttribute('aria-expanded', 'false');
        });
      }
      return;
    }
    const li = btn.closest('.nav-item');
    const isOpen = li.classList.toggle('open');
    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    const link = li.querySelector('[role="menuitem"]');
    if (link) link.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    e.preventDefault();
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.nav-item.open').forEach(li => {
        li.classList.remove('open');
        const b = li.querySelector('.submenu-toggle');
        if (b) b.setAttribute('aria-expanded', 'false');
      });
    }
  });
  if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    document.querySelectorAll('.nav-item.has-dropdown').forEach(li => {
      let hoverTimer;
      li.addEventListener('mouseenter', () => {
        hoverTimer = setTimeout(() => li.classList.add('open'), 120);
      });
      li.addEventListener('mouseleave', () => {
        clearTimeout(hoverTimer);
        li.classList.remove('open');
      });
    });
  }
})();