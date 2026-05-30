(function(){
  document.documentElement.classList.add('wat-switcher-ready');

  function eachNode(nodes, callback) {
    Array.prototype.forEach.call(nodes || [], callback);
  }

  function matches(el, selector) {
    var fn = el && (el.matches || el.msMatchesSelector || el.webkitMatchesSelector);
    return fn ? fn.call(el, selector) : false;
  }

  function closest(el, selector) {
    if (!el) return null;
    if (el.closest) return el.closest(selector);
    while (el && el.nodeType === 1) {
      if (matches(el, selector)) return el;
      el = el.parentNode;
    }
    return null;
  }

  function menuFor(nav) {
    return nav ? nav.querySelector('.wat-switcher-menu') : null;
  }

  function menuLinks(nav) {
    return nav ? nav.querySelectorAll('.wat-switcher-menu a[role="menuitem"]') : [];
  }

  function setMenuItemTabIndexes(nav, activeIndex) {
    var links = menuLinks(nav);
    eachNode(links, function(link, index){
      link.setAttribute('tabindex', index === activeIndex ? '0' : '-1');
    });
  }

  function closeDropdown(nav, focusToggle) {
    if (!nav) return;
    nav.classList.remove('is-open');
    var menu = menuFor(nav);
    if (menu) menu.setAttribute('hidden', 'hidden');
    setMenuItemTabIndexes(nav, -1);
    var btn = nav.querySelector('.wat-switcher-toggle');
    if (btn) {
      btn.setAttribute('aria-expanded', 'false');
      if (focusToggle) btn.focus();
    }
  }

  function openDropdown(nav, focusIndex) {
    if (!nav) return;
    nav.classList.add('is-open');
    var menu = menuFor(nav);
    if (menu) menu.removeAttribute('hidden');
    var btn = nav.querySelector('.wat-switcher-toggle');
    if (btn) btn.setAttribute('aria-expanded', 'true');
    var links = menuLinks(nav);
    if (links.length) {
      var index = typeof focusIndex === 'number' ? focusIndex : 0;
      if (index < 0) index = links.length - 1;
      if (index >= links.length) index = 0;
      setMenuItemTabIndexes(nav, index);
      if (typeof focusIndex === 'number') links[index].focus();
    }
  }

  document.addEventListener('click', function(event){
    var toggle = closest(event.target, '.wat-switcher-dropdown .wat-switcher-toggle');

    eachNode(document.querySelectorAll('.wat-switcher-dropdown.is-open'), function(nav){
      if (!toggle || !nav.contains(toggle)) {
        closeDropdown(nav, false);
      }
    });

    if (!toggle) return;

    var nav = closest(toggle, '.wat-switcher-dropdown');
    if (!nav) return;

    if (nav.classList.contains('is-open')) {
      closeDropdown(nav, false);
    } else {
      openDropdown(nav, null);
    }
  });

  document.addEventListener('focusin', function(event){
    eachNode(document.querySelectorAll('.wat-switcher-dropdown.is-open'), function(nav){
      if (!nav.contains(event.target)) {
        closeDropdown(nav, false);
      }
    });
  });

  document.addEventListener('keydown', function(event){
    var key = event.key || event.keyCode;
    if (key === 'Escape' || key === 'Esc' || key === 27) {
      eachNode(document.querySelectorAll('.wat-switcher-dropdown.is-open'), function(nav){
        closeDropdown(nav, true);
      });
      return;
    }

    var active = document.activeElement;
    var toggle = closest(active, '.wat-switcher-dropdown .wat-switcher-toggle');
    var nav = closest(active, '.wat-switcher-dropdown');
    if (!nav) return;

    var links = menuLinks(nav);
    if (!links.length) return;

    if (toggle && (key === 'Enter' || key === ' ' || key === 'Spacebar' || key === 13 || key === 32)) {
      event.preventDefault();
      if (nav.classList.contains('is-open')) {
        closeDropdown(nav, false);
      } else {
        openDropdown(nav, 0);
      }
      return;
    }

    if (key === 'Home' || key === 36) {
      event.preventDefault();
      if (!nav.classList.contains('is-open')) openDropdown(nav, 0);
      setMenuItemTabIndexes(nav, 0);
      links[0].focus();
      return;
    }

    if (key === 'End' || key === 35) {
      event.preventDefault();
      if (!nav.classList.contains('is-open')) openDropdown(nav, links.length - 1);
      setMenuItemTabIndexes(nav, links.length - 1);
      links[links.length - 1].focus();
      return;
    }

    if (key !== 'ArrowDown' && key !== 'ArrowUp' && key !== 40 && key !== 38) {
      return;
    }

    event.preventDefault();
    if (!nav.classList.contains('is-open')) {
      openDropdown(nav, key === 'ArrowUp' || key === 38 ? links.length - 1 : 0);
      return;
    }

    var current = Array.prototype.indexOf.call(links, document.activeElement);
    var next = key === 'ArrowUp' || key === 38 ? current - 1 : current + 1;
    if (next < 0) next = links.length - 1;
    if (next >= links.length) next = 0;
    setMenuItemTabIndexes(nav, next);
    links[next].focus();
  });
})();
