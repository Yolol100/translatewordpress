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

  function closeDropdown(nav, focusToggle) {
    if (!nav) return;
    nav.classList.remove('is-open');
    var menu = menuFor(nav);
    if (menu) menu.setAttribute('hidden', 'hidden');
    var btn = nav.querySelector('.wat-switcher-toggle');
    if (btn) {
      btn.setAttribute('aria-expanded', 'false');
      if (focusToggle) btn.focus();
    }
  }

  function openDropdown(nav) {
    if (!nav) return;
    nav.classList.add('is-open');
    var menu = menuFor(nav);
    if (menu) menu.removeAttribute('hidden');
    var btn = nav.querySelector('.wat-switcher-toggle');
    if (btn) btn.setAttribute('aria-expanded', 'true');
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
      openDropdown(nav);
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

    var nav = closest(document.activeElement, '.wat-switcher-dropdown');
    if (!nav) return;

    var links = nav.querySelectorAll('.wat-switcher-menu a');
    if (!links.length) return;

    if (key === 'Home' || key === 36) {
      event.preventDefault();
      if (!nav.classList.contains('is-open')) openDropdown(nav);
      links[0].focus();
      return;
    }

    if (key === 'End' || key === 35) {
      event.preventDefault();
      if (!nav.classList.contains('is-open')) openDropdown(nav);
      links[links.length - 1].focus();
      return;
    }

    if (key !== 'ArrowDown' && key !== 'ArrowUp' && key !== 40 && key !== 38) {
      return;
    }

    event.preventDefault();
    if (!nav.classList.contains('is-open')) {
      openDropdown(nav);
      links[0].focus();
      return;
    }

    var current = Array.prototype.indexOf.call(links, document.activeElement);
    var next = key === 'ArrowUp' || key === 38 ? current - 1 : current + 1;
    if (next < 0) next = links.length - 1;
    if (next >= links.length) next = 0;
    links[next].focus();
  });
})();
