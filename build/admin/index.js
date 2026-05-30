(function (wp, cfg) {
  var __ = wp && wp.i18n && wp.i18n.__ ? function (text) { return wp.i18n.__(text, 'webactueel-translate-language-dropdowns'); } : function (text) { return text; };

  function renderAdminError(target, message) {
    if (!target) return;
    while (target.firstChild) {
      target.removeChild(target.firstChild);
    }
    var notice = document.createElement('div');
    notice.className = 'notice notice-error';
    var paragraph = document.createElement('p');
    paragraph.textContent = message;
    notice.appendChild(paragraph);
    target.appendChild(notice);
  }
  if (!wp || !wp.element || !wp.components || !wp.apiFetch) {
    var missingRoot = document.getElementById("webactueel-translate-admin-root") || document.getElementById("webactueel-translate-language-dropdowns-admin-root");
    renderAdminError(missingRoot, __('Translate admin kon niet laden: WordPress admin JavaScript dependencies ontbreken. Controleer of wp-element, wp-components en wp-api-fetch niet door Asset CleanUp/cache/optimalisatie zijn uitgeschakeld.', 'webactueel-translate-language-dropdowns'));
    return;
  }

  var el = wp.element.createElement;
  var useState = wp.element.useState;
  var useEffect = wp.element.useEffect;
  var components = wp.components;
  var c = {};

  function stripComponentProps(props, keys) {
    var safeProps = Object.assign({}, props || {});
    (keys || []).forEach(function (key) {
      delete safeProps[key];
    });
    return safeProps;
  }

  function watCompatComponent(tagName, fallbackClassName, blockedProps) {
    return function WatCompatComponent(props) {
      var safeProps = stripComponentProps(props, blockedProps || []);
      var children = safeProps.children;
      delete safeProps.children;
      safeProps.className = [fallbackClassName, safeProps.className].filter(Boolean).join(' ');
      return el(tagName, safeProps, children);
    };
  }

  function WatFallbackButton(props) {
    var safeProps = stripComponentProps(props, ['isPrimary', 'isSecondary', 'isTertiary', 'isDestructive', 'variant', 'isBusy', 'icon', 'label', 'showTooltip']);
    var children = safeProps.children || props.label || '';
    delete safeProps.children;
    safeProps.type = safeProps.type || 'button';
    safeProps.className = ['button', props.isPrimary || props.variant === 'primary' ? 'button-primary' : '', safeProps.className].filter(Boolean).join(' ');
    return el('button', safeProps, children);
  }

  function WatFallbackNotice(props) {
    var status = props && props.status ? String(props.status) : 'info';
    return el('div', { className: ['notice', 'notice-' + status, props && props.className].filter(Boolean).join(' ') },
      el('p', null, props && props.children ? props.children : '')
    );
  }

  function fallbackControlIds(props, prefix) {
    var base = props && props.id ? String(props.id) : prefix + '-' + Math.random().toString(36).slice(2);
    return {
      control: base,
      help: base + '-help'
    };
  }

  function WatFallbackTextControl(props) {
    var ids = fallbackControlIds(props, 'wat-fallback-text');
    var safeProps = stripComponentProps(props, ['label', 'help', 'hideLabelFromVision']);
    var label = props && props.label ? el('label', { htmlFor: ids.control, className: props.hideLabelFromVision ? 'screen-reader-text' : undefined }, props.label) : null;
    var help = props && props.help ? el('p', { id: ids.help, className: 'description' }, props.help) : null;
    safeProps.type = safeProps.type || 'text';
    safeProps.id = ids.control;
    if (props && props.help && !safeProps['aria-describedby']) {
      safeProps['aria-describedby'] = ids.help;
    }
    safeProps.onChange = props && props.onChange ? function (event) { props.onChange(event.target.value); } : undefined;
    return el('div', { className: 'components-base-control wat-fallback-control' }, label, el('input', safeProps), help);
  }

  function WatFallbackTextareaControl(props) {
    var ids = fallbackControlIds(props, 'wat-fallback-textarea');
    var safeProps = stripComponentProps(props, ['label', 'help', 'hideLabelFromVision']);
    var label = props && props.label ? el('label', { htmlFor: ids.control, className: props.hideLabelFromVision ? 'screen-reader-text' : undefined }, props.label) : null;
    var help = props && props.help ? el('p', { id: ids.help, className: 'description' }, props.help) : null;
    safeProps.id = ids.control;
    if (props && props.help && !safeProps['aria-describedby']) {
      safeProps['aria-describedby'] = ids.help;
    }
    safeProps.onChange = props && props.onChange ? function (event) { props.onChange(event.target.value); } : undefined;
    return el('div', { className: 'components-base-control wat-fallback-control' }, label, el('textarea', safeProps), help);
  }

  function WatFallbackSelectControl(props) {
    var ids = fallbackControlIds(props, 'wat-fallback-select');
    var safeProps = stripComponentProps(props, ['label', 'help', 'options', 'hideLabelFromVision']);
    var label = props && props.label ? el('label', { htmlFor: ids.control, className: props.hideLabelFromVision ? 'screen-reader-text' : undefined }, props.label) : null;
    var help = props && props.help ? el('p', { id: ids.help, className: 'description' }, props.help) : null;
    safeProps.id = ids.control;
    if (props && props.help && !safeProps['aria-describedby']) {
      safeProps['aria-describedby'] = ids.help;
    }
    safeProps.onChange = props && props.onChange ? function (event) { props.onChange(event.target.value); } : undefined;
    return el('div', { className: 'components-base-control wat-fallback-control' }, label, el('select', safeProps, (props.options || []).map(function (option) {
      return el('option', { key: option.value, value: option.value, disabled: option.disabled || false }, option.label);
    })), help);
  }

  function WatFallbackToggleControl(props) {
    var checked = !!(props && props.checked);
    return el('label', { className: ['components-toggle-control wat-fallback-toggle', props && props.className].filter(Boolean).join(' ') },
      el('input', { type: 'checkbox', checked: checked, disabled: props && props.disabled, onChange: props && props.onChange ? function (event) { props.onChange(event.target.checked); } : undefined }),
      el('span', null, props && props.label ? props.label : '')
    );
  }

  function WatFallbackTabPanel(props) {
    var initial = props && props.initialTabName ? props.initialTabName : ((props.tabs && props.tabs[0] && props.tabs[0].name) || '');
    var active = useState(initial);
    var tabs = props.tabs || [];
    var selected = tabs.filter(function (tab) { return tab.name === active[0]; })[0] || tabs[0] || { name: active[0], title: active[0] };
    function select(name) {
      active[1](name);
      if (props.onSelect) {
        props.onSelect(name);
      }
    }
    return el('div', { className: props.className || 'wat-fallback-tabs' },
      el('div', { className: 'components-tab-panel__tabs' }, tabs.map(function (tab) {
        return el('button', { type: 'button', key: tab.name, className: tab.name === selected.name ? (props.activeClass || 'is-active') : '', onClick: function () { select(tab.name); } }, tab.title || tab.name);
      })),
      typeof props.children === 'function' ? props.children(selected) : null
    );
  }

  c.Card = components.Card || watCompatComponent('div', 'components-card');
  c.CardBody = components.CardBody || watCompatComponent('div', 'components-card__body');
  c.CardHeader = components.CardHeader || watCompatComponent('div', 'components-card__header');
  c.Panel = components.Panel || watCompatComponent('div', 'components-panel');
  c.PanelBody = components.PanelBody || watCompatComponent('div', 'components-panel__body');
  c.Button = components.Button || WatFallbackButton;
  c.Notice = components.Notice || WatFallbackNotice;
  c.Spinner = components.Spinner || watCompatComponent('span', 'spinner is-active');
  c.TextControl = components.TextControl || WatFallbackTextControl;
  c.TextareaControl = components.TextareaControl || WatFallbackTextareaControl;
  c.SelectControl = components.SelectControl || WatFallbackSelectControl;
  c.ToggleControl = components.ToggleControl || WatFallbackToggleControl;
  c.TabPanel = components.TabPanel || WatFallbackTabPanel;

  var apiFetch = wp.apiFetch;
  var rootPath = '/webactueel-translate-language-dropdowns/v1';

  function watModalFocusable(modal) {
    return Array.prototype.filter.call(
      modal.querySelectorAll('a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'),
      function (node) { return !!(node.offsetWidth || node.offsetHeight || node.getClientRects().length); }
    );
  }

  document.addEventListener('keydown', function (event) {
    var modal = document.querySelector('.wat-custom-modal[role="dialog"]');
    if (!modal) { return; }
    var key = event.key || event.keyCode;
    if (key === 'Escape' || key === 'Esc' || key === 27) {
      var close = modal.querySelector('.wat-custom-modal-close');
      if (close) {
        event.preventDefault();
        close.click();
      }
      return;
    }
    if (key !== 'Tab' && key !== 9) { return; }
    var focusable = watModalFocusable(modal);
    if (!focusable.length) { return; }
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  document.addEventListener('focusin', function (event) {
    var modal = document.querySelector('.wat-custom-modal[role="dialog"]');
    if (!modal || modal.contains(event.target)) { return; }
    var focusable = watModalFocusable(modal);
    if (focusable.length) { focusable[0].focus(); }
  });

  if (apiFetch && typeof apiFetch.use === 'function' && typeof apiFetch.createNonceMiddleware === 'function' && cfg.nonce) {
    apiFetch.use(apiFetch.createNonceMiddleware(cfg.nonce));
  }

  function api(path, options) {
    return apiFetch(Object.assign({}, options || {}, { path: rootPath + path }));
  }

  function upload(path, file) {
    var form = new window.FormData();
    form.append('file', file);
    return apiFetch({ path: rootPath + path, method: 'POST', body: form });
  }

  function bool(v) {
    return v === true || v === 1 || v === '1';
  }

  function safe(v) {
    return v === undefined || v === null || v === '' ? '—' : String(v);
  }

  function num(v) {
    return parseInt(v || 0, 10).toLocaleString('nl-NL');
  }

  function formatDateLabel(value) {
    if (!value) {
      return __('Nog niet uitgevoerd', 'webactueel-translate-language-dropdowns');
    }
    if (typeof value === 'number') {
      var numericDate = new Date(value > 100000000000 ? value : value * 1000);
      return isNaN(numericDate.getTime()) ? __('Nog niet uitgevoerd', 'webactueel-translate-language-dropdowns') : numericDate.toLocaleString('nl-NL');
    }
    if (typeof value === 'string') {
      var date = new Date(value);
      return isNaN(date.getTime()) ? safe(value) : date.toLocaleString('nl-NL');
    }
    return __('Nog niet uitgevoerd', 'webactueel-translate-language-dropdowns');
  }

  function formatScanLabel(value) {
    if (!value) {
      return __('Nog niet uitgevoerd', 'webactueel-translate-language-dropdowns');
    }
    if (typeof value === 'string' || typeof value === 'number') {
      return formatDateLabel(value);
    }
    if (typeof value === 'object') {
      if (value.label) {
        return safe(value.label);
      }
      if (value.completed_at) {
        return formatDateLabel(value.completed_at);
      }
      if (value.updated_at) {
        return formatDateLabel(value.updated_at);
      }
      if (value.created_at) {
        return formatDateLabel(value.created_at);
      }
      if (value.total_strings !== undefined || value.found_strings !== undefined) {
        return num(value.total_strings !== undefined ? value.total_strings : value.found_strings) + ' ' + __('teksten gevonden', 'webactueel-translate-language-dropdowns');
      }
      if (value.status) {
        return safe(value.status);
      }
    }
    return __('Nog niet uitgevoerd', 'webactueel-translate-language-dropdowns');
  }

  function cls() {
    return Array.prototype.slice.call(arguments).filter(Boolean).join(' ');
  }

  function useFetch(path, deps, enabled) {
    var st = useState({ loading: enabled === false ? false : true, error: '', data: null });
    var refreshTick = useState(0);
    var effectDeps = (deps || []).concat([refreshTick[0]]);

    useEffect(function () {
      if (enabled === false || !path) {
        st[1](function (prev) { return Object.assign({}, prev, { loading: false }); });
        return;
      }
      var alive = true;
      st[1](function (prev) { return { loading: true, error: '', data: prev.data }; });
      api(path)
        .then(function (data) {
          if (alive) {
            st[1]({ loading: false, error: '', data: data });
          }
        })
        .catch(function (e) {
          if (alive) {
            st[1]({ loading: false, error: e.message || __('Er ging iets mis.', 'webactueel-translate-language-dropdowns'), data: null });
          }
        });

      return function () {
        alive = false;
      };
    }, effectDeps);

    return Object.assign({}, st[0], {
      patchData: function (fn) {
        st[1](function (prev) { return { loading: false, error: '', data: fn(prev.data) }; });
      },
      refresh: function () {
        refreshTick[1](refreshTick[0] + 1);
      }
    });
  }

  function useToasts() {
    var st = useState([]);

    function add(message, type) {
      var id = Date.now() + Math.random();
      st[1](function (items) {
        return items.concat([{ id: id, message: message, type: type || 'success' }]).slice(-3);
      });
      window.setTimeout(function () {
        st[1](function (items) {
          return items.filter(function (n) {
            return n.id !== id;
          });
        });
      }, 3600);
    }

    function remove(id) {
      st[1](function (items) {
        return items.filter(function (n) {
          return n.id !== id;
        });
      });
    }

    return { items: st[0], add: add, remove: remove };
  }

  function Toasts(p) {
    return el(
      'div',
      { className: 'wat-toast-stack', 'aria-live': 'polite' },
      p.items.map(function (n) {
        return el(
          'div',
          { key: n.id, className: cls('wat-toast', 'is-' + n.type) },
          el('span', null, n.message),
          el(
            'button',
            {
              type: 'button',
              onClick: function () {
                p.remove(n.id);
              },
              'aria-label': __('Sluiten', 'webactueel-translate-language-dropdowns')
            },
            '×'
          )
        );
      })
    );
  }

  function useTabState(tabs) {
    function valid(v) {
      return tabs.some(function (t) { return t.name === v; }) ? v : 'dashboard';
    }

    function read() {
      return valid(
        new URLSearchParams(window.location.search).get('wat_tab') ||
        window.localStorage.getItem('wat_tab') ||
        cfg.currentTab ||
        'dashboard'
      );
    }

    var st = useState(read());

    useEffect(function () {
      function pop() {
        st[1](read());
      }
      window.addEventListener('popstate', pop);
      return function () {
        window.removeEventListener('popstate', pop);
      };
    }, []);

    function set(name) {
      name = valid(name);
      st[1](name);
      window.localStorage.setItem('wat_tab', name);
      var url = new URL(window.location.href);
      url.searchParams.set('wat_tab', name);
      window.history.pushState({}, '', url.toString());
      try {
        window.dispatchEvent(new CustomEvent('wat-tab-change', { detail: { tab: name } }));
      } catch (e) {}
    }

    return [st[0], set];
  }

  function useDebouncedValue(value, delay) {
    var debounced = useState(value);
    useEffect(function () {
      var timer = window.setTimeout(function () { debounced[1](value); }, delay || 300);
      return function () { window.clearTimeout(timer); };
    }, [value, delay]);
    return debounced[0];
  }

  function Badge(p) {
    return el('span', { className: cls('wat-badge', 'is-' + (p.tone || 'info')) }, p.children);
  }

  function Card(p) {
    return el(
      c.Card,
      { className: cls('wat-card', p.draggable ? 'is-draggable' : '', p.className || '') },
      p.title
        ? el(
            c.CardHeader,
            null,
            el(
              'div',
              { className: 'wat-card-head' },
              p.draggable ? el('span', { className: 'wat-drag-handle', title: __('Versleep', 'webactueel-translate-language-dropdowns') }, '⋮⋮') : null,
              el(
                'div',
                null,
                el('strong', null, p.title),
                p.desc ? el('p', null, p.desc) : null
              ),
              p.action || null
            )
          )
        : null,
      el(c.CardBody, { size: 'large', className: 'wat-card-body' }, p.children),
      p.footer ? el('div', { className: 'wat-card-footer' }, p.footer) : null
    );
  }

  function Empty(p) {
    return el(
      'div',
      { className: 'wat-empty' },
      el('strong', null, p.title || __('Nog niets gevonden', 'webactueel-translate-language-dropdowns')),
      p.text ? el('p', null, p.text) : null,
      p.action || null
    );
  }

  function Table(p) {
    return el(
      'div',
      { className: 'wat-table-wrap' },
      el(
        'table',
        { className: 'widefat striped wat-table' },
        el(
          'thead',
          null,
          el(
            'tr',
            null,
            p.head.map(function (h) {
              return el('th', { key: h }, h);
            })
          )
        ),
        el(
          'tbody',
          null,
          p.rows && p.rows.length
            ? p.rows
            : el(
                'tr',
                null,
                el(
                  'td',
                  { colSpan: p.head.length },
                  el(Empty, {
                    title: p.emptyTitle,
                    text: p.emptyText,
                    action: p.emptyAction
                  })
                )
              )
        )
      )
    );
  }

  function Panel(p) {
    return el(
      c.Panel,
      { className: 'wat-soft-panel' },
      el(c.PanelBody, { title: p.title, initialOpen: !!p.open, opened: p.open !== undefined ? !!p.open : undefined }, p.children)
    );
  }

  function Row(p) {
    return el('div', { className: 'wat-form-row' }, p.children);
  }

  function HeroBanner(p) {
    return el('div', { className: 'wat-native-hero' },
      el('div', { className: 'wat-native-hero__content' },
        p.eyebrow ? el('span', { className: 'wat-eyebrow' }, p.eyebrow) : null,
        el('h2', null, p.title),
        p.text ? el('p', null, p.text) : null,
        p.actions ? el('div', { className: 'wat-hero-actions' }, p.actions) : null
      ),
      p.status ? el('div', { className: 'wat-hero-status' }, p.status) : null
    );
  }

  function StatCard(p) {
    return el('div', { className: 'wat-stat-card' },
      el('span', { className: 'wat-stat-label' }, p.label),
      el('strong', null, p.value),
      p.hint ? el('small', null, p.hint) : null
    );
  }

  function ActionCard(p) {
    return el('button', { type: 'button', className: 'wat-action-card', onClick: p.onClick },
      el('span', { className: 'wat-action-icon', 'aria-hidden': 'true' }, p.icon || '→'),
      el('span', { className: 'wat-action-card__content' },
        el('strong', null, p.title),
        el('span', null, p.text)
      ),
      el('span', { className: 'wat-action-card__arrow', 'aria-hidden': 'true' }, '›')
    );
  }

  function DashboardScreen(p) {
    var d = p.dashboard || {};
    var activeLanguages = parseInt(d.activeLanguages || 0, 10);
    var totalStrings = parseInt(d.totalStrings || 0, 10);
    var missingTranslations = parseInt(d.missingTranslations || 0, 10);
    var translatedCount = Math.max(0, totalStrings - missingTranslations);
    var progress = totalStrings > 0 ? Math.max(0, Math.min(100, Math.round((translatedCount / totalStrings) * 100))) : 0;
    var needsLanguage = activeLanguages < 2;
    var needsScan = !needsLanguage && totalStrings < 1;
    var needsTranslation = !needsLanguage && !needsScan && missingTranslations > 0;
    var nextTitle = needsLanguage
      ? __('Voeg een taal toe', 'webactueel-translate-language-dropdowns')
      : needsScan
        ? __('Scan je website', 'webactueel-translate-language-dropdowns')
        : needsTranslation
          ? __('Rond open vertalingen af', 'webactueel-translate-language-dropdowns')
          : __('Controleer je website in context', 'webactueel-translate-language-dropdowns');
    var nextText = needsLanguage
      ? __('Kies eerst de talen die je bezoekers moeten kunnen gebruiken.', 'webactueel-translate-language-dropdowns')
      : needsScan
        ? __('Laat de plugin zichtbare teksten vinden voordat je gaat vertalen.', 'webactueel-translate-language-dropdowns')
        : needsTranslation
          ? __('Open de vertalingen en werk de belangrijkste teksten bij.', 'webactueel-translate-language-dropdowns')
          : __('Open de visual editor om je vertalingen op de pagina te controleren.', 'webactueel-translate-language-dropdowns');
    var nextAction = needsLanguage || needsScan ? 'settings' : needsTranslation ? 'translate' : 'visual-editor-link';

    function openNext() {
      if (nextAction === 'visual-editor-link') {
        window.location.href = cfg.visualEditorUrl || (cfg.siteUrl ? cfg.siteUrl + '?wat_visual_editor=1' : window.location.origin + '/?wat_visual_editor=1');
        return;
      }
      p.setTab(nextAction);
    }


    return el('div', { className: 'wat-page wat-dashboard-screen wat-calm-dashboard' },
      el(HeroBanner, {
        title: __('Maak je website meertalig', 'webactueel-translate-language-dropdowns'),
        text: __('Begin met talen, scan je content en vertaal stap voor stap.', 'webactueel-translate-language-dropdowns'),
        actions: [
          el(c.Button, { key: 'setup', variant: 'primary', onClick: function () { p.setTab('settings'); } }, needsLanguage ? __('Start setup', 'webactueel-translate-language-dropdowns') : __('Instellingen openen', 'webactueel-translate-language-dropdowns')),
          el(c.Button, { key: 'visual', variant: 'secondary', href: cfg.visualEditorUrl || (cfg.siteUrl ? cfg.siteUrl + '?wat_visual_editor=1' : window.location.origin + '/?wat_visual_editor=1') }, __('Visuele editor openen', 'webactueel-translate-language-dropdowns'))
        ],
        status: el(Badge, { tone: needsLanguage || needsScan || needsTranslation ? 'warning' : 'success' }, needsLanguage || needsScan || needsTranslation ? __('Actie nodig', 'webactueel-translate-language-dropdowns') : __('Klaar voor gebruik', 'webactueel-translate-language-dropdowns'))
      }),
      el('div', { className: 'wat-dashboard-progress', role: 'group', 'aria-label': __('Vertaalvoortgang', 'webactueel-translate-language-dropdowns') },
        el('div', { className: 'wat-dashboard-progress__label' },
          el('strong', null, __('Vertaalvoortgang', 'webactueel-translate-language-dropdowns')),
          el('span', null, String(progress) + '%')
        ),
        el('div', { className: 'wat-dashboard-progress__track', 'aria-hidden': 'true' },
          el('span', { style: { width: String(progress) + '%' } })
        )
      ),
      el('div', { className: 'wat-stat-grid wat-stat-grid--calm wat-stat-grid--customer' },
        el(StatCard, { label: __('Talen', 'webactueel-translate-language-dropdowns'), value: activeLanguages > 1 ? __('Ingesteld', 'webactueel-translate-language-dropdowns') : __('Nog instellen', 'webactueel-translate-language-dropdowns') }),
        el(StatCard, { label: __('Vertalingen', 'webactueel-translate-language-dropdowns'), value: totalStrings > 0 ? (missingTranslations > 0 ? __('Aandacht nodig', 'webactueel-translate-language-dropdowns') : __('Bijgewerkt', 'webactueel-translate-language-dropdowns')) : __('Nog niet gestart', 'webactueel-translate-language-dropdowns') }),
        el(StatCard, { label: __('Websitescan', 'webactueel-translate-language-dropdowns'), value: totalStrings > 0 ? __('Uitgevoerd', 'webactueel-translate-language-dropdowns') : __('Nog niet uitgevoerd', 'webactueel-translate-language-dropdowns') }),
        el(StatCard, { label: __('Publicatie', 'webactueel-translate-language-dropdowns'), value: activeLanguages > 1 && totalStrings > 0 && missingTranslations < 1 && d.settings && bool(d.settings.hreflang_enabled) ? __('Klaar', 'webactueel-translate-language-dropdowns') : __('Nakijken', 'webactueel-translate-language-dropdowns') })
      ),
      el(Card, { title: __('Volgende stap', 'webactueel-translate-language-dropdowns'), className: 'wat-next-action-card wat-next-action-card--calm' },
        el('div', { className: 'wat-next-action' },
          el('div', null, el('h3', null, nextTitle), el('p', null, nextText)),
          el(c.Button, { variant: 'primary', onClick: openNext }, __('Ga verder', 'webactueel-translate-language-dropdowns'))
        )
      ),
      el('div', { className: 'wat-action-grid wat-action-grid--calm' },
        el(ActionCard, { icon: '1', title: __('Website scannen', 'webactueel-translate-language-dropdowns'), text: __('Open vertalingen en start een scan.', 'webactueel-translate-language-dropdowns'), onClick: function () { p.setTab('translate'); } }),
        el(ActionCard, { icon: '2', title: __('Visuele editor', 'webactueel-translate-language-dropdowns'), text: __('Vertaal direct op de pagina.', 'webactueel-translate-language-dropdowns'), onClick: function () { window.location.href = cfg.visualEditorUrl || (cfg.siteUrl ? cfg.siteUrl + '?wat_visual_editor=1' : window.location.origin + '/?wat_visual_editor=1'); } }),
        el(ActionCard, { icon: '3', title: __('CSV & back-up', 'webactueel-translate-language-dropdowns'), text: __('Exporteer, importeer of herstel vertalingen.', 'webactueel-translate-language-dropdowns'), onClick: function () { p.setTab('tools'); } }),
        el(ActionCard, { icon: '4', title: __('Instellingen', 'webactueel-translate-language-dropdowns'), text: __('Pas talen en gedrag aan.', 'webactueel-translate-language-dropdowns'), onClick: function () { p.setTab('settings'); } })
      )
    );
  }

  function VisualEditorScreen(p) {
    var d = p.dashboard || {};
    var settings = d.settings || {};
    var activeLanguages = parseInt(d.activeLanguages || 0, 10);
    var totalStrings = parseInt(d.totalStrings || 0, 10);
    var missingTranslations = parseInt(d.missingTranslations || 0, 10);
    var needsLanguages = activeLanguages < 2;
    var needsScan = !needsLanguages && totalStrings < 1;
    var canUseEditor = !needsLanguages && !needsScan;
    var reviewRequired = bool(settings.translator_review_required) || bool(settings.ai_review_required);
    var editorUrl = cfg.visualEditorUrl || (cfg.siteUrl ? cfg.siteUrl + '?wat_visual_editor=1' : window.location.origin + '/?wat_visual_editor=1');
    var nextLabel = needsLanguages
      ? __('Talen instellen', 'webactueel-translate-language-dropdowns')
      : needsScan
        ? __('Scan starten', 'webactueel-translate-language-dropdowns')
        : __('Visuele editor openen', 'webactueel-translate-language-dropdowns');

    function nextAction() {
      if (needsLanguages) {
        p.setTab('settings');
        return;
      }
      if (needsScan) {
        p.setTab('translate');
        return;
      }
      window.location.href = editorUrl;
    }

    return el('div', { className: 'wat-page wat-visual-editor-screen wat-visual-editor-screen--compact' },
      el('section', { className: 'wat-visual-editor-compact' },
        el('div', { className: 'wat-visual-editor-compact__content' },
          el('h2', null, __('Visuele editor', 'webactueel-translate-language-dropdowns')),
          el('p', null, canUseEditor
            ? __('Open de visuele editor om vertalingen direct op de pagina te controleren.', 'webactueel-translate-language-dropdowns')
            : __('Rond eerst de taalinstellingen en scan af voordat je de visuele editor opent.', 'webactueel-translate-language-dropdowns')
          ),
          el('ul', { className: 'wat-simple-list wat-simple-list--inline' },
            el('li', null, __('Talen', 'webactueel-translate-language-dropdowns') + ': ' + (activeLanguages > 0 ? num(activeLanguages) : '0')),
            el('li', null, __('Gevonden teksten', 'webactueel-translate-language-dropdowns') + ': ' + (totalStrings > 0 ? num(totalStrings) : '0')),
            el('li', null, __('Open vertalingen', 'webactueel-translate-language-dropdowns') + ': ' + (missingTranslations > 0 ? num(missingTranslations) : '0')),
            el('li', null, __('Review', 'webactueel-translate-language-dropdowns') + ': ' + (reviewRequired ? __('aan', 'webactueel-translate-language-dropdowns') : __('uit', 'webactueel-translate-language-dropdowns')))
          )
        ),
        el('div', { className: 'wat-visual-editor-compact__actions' },
          el(c.Button, { variant: 'primary', onClick: nextAction }, nextLabel),
          el(c.Button, { variant: 'secondary', onClick: function () { p.setTab('translate'); } }, __('Naar vertalingen', 'webactueel-translate-language-dropdowns'))
        )
      )
    );
  }

  function SeoScreen(p) {
    var d = p.dashboard || {};
    return el('div', { className: 'wat-page' },
      el(HeroBanner, { eyebrow: __('Meertalige SEO', 'webactueel-translate-language-dropdowns'), title: __('Controleer SEO voordat je live gaat', 'webactueel-translate-language-dropdowns'), text: __('Houd hreflang, meta titles, descriptions en URL’s overzichtelijk per taal.', 'webactueel-translate-language-dropdowns'), actions: [el(c.Button, { key: 'settings', variant: 'primary', onClick: function () { p.setTab('settings'); } }, __('SEO-instellingen bekijken', 'webactueel-translate-language-dropdowns'))], status: el(Badge, { tone: d.hreflangEnabled === false ? 'warning' : 'success' }, d.hreflangEnabled === false ? __('Controle nodig', 'webactueel-translate-language-dropdowns') : __('Hreflang klaar', 'webactueel-translate-language-dropdowns')) }),
      el('div', { className: 'wat-settings-grid' },
        el(Card, { title: __('Hreflang status', 'webactueel-translate-language-dropdowns') }, el('p', null, __('Hreflang helpt zoekmachines de juiste taalversie te kiezen.', 'webactueel-translate-language-dropdowns')), el(Badge, { tone: 'success' }, __('Beschikbaar', 'webactueel-translate-language-dropdowns'))),
        el(Card, { title: __('Meta titles & descriptions', 'webactueel-translate-language-dropdowns') }, el('p', null, __('De plugin heeft een veilige basis voor per-taal SEO metadata. Controleer dit in staging met je SEO-plugin.', 'webactueel-translate-language-dropdowns'))),
        el(Card, { title: __('URL slugs en sitemaps', 'webactueel-translate-language-dropdowns') }, el('p', null, __('Per-taal URL-slugs, canonicals en de meertalige sitemap zijn beschikbaar. Sitemap: /?wat_language_sitemap=1.', 'webactueel-translate-language-dropdowns')))
      )
    );
  }


  function HealthCheckList(p) {
    var health = p.health || {};
    var checks = health.checks || [];
    if (!checks.length) {
      return el(Empty, { title: __('Nog geen systeemcontrole geladen', 'webactueel-translate-language-dropdowns'), text: __('Open deze tab opnieuw of ververs de pagina.', 'webactueel-translate-language-dropdowns') });
    }
    return el('div', { className: 'wat-health-list' }, checks.map(function (check, index) {
      var tone = check.status === 'fail' ? 'danger' : (check.status === 'warn' ? 'warning' : (check.status === 'pass' ? 'success' : 'info'));
      return el('div', { key: check.label || index, className: 'wat-health-item wat-health-item--' + (check.status || 'info') },
        el(Badge, { tone: tone }, String(check.status || 'info').toUpperCase()),
        el('div', null,
          el('strong', null, check.label || '—'),
          el('p', null, check.detail || '')
        )
      );
    }));
  }

  function StatusScreen(p) {
    var h = p.health || {};
    var logs = p.logs || [];
    var checks = h.checks || [];
    var problems = checks.filter(function (check) {
      return check.status === 'fail' || check.status === 'warn';
    });
    var hasProblems = problems.length > 0 || h.ok === false;
    var statusTone = hasProblems ? 'warning' : 'success';
    var statusTitle = hasProblems
      ? __('Er is iets dat aandacht vraagt', 'webactueel-translate-language-dropdowns')
      : __('Alles werkt goed', 'webactueel-translate-language-dropdowns');
    var statusText = hasProblems
      ? __('Los eerst de punten hieronder op. De overige technische informatie staat bewust ingeklapt.', 'webactueel-translate-language-dropdowns')
      : __('De plugin heeft geen actie nodig. Technische controles blijven beschikbaar, maar staan uit beeld zolang alles goed is.', 'webactueel-translate-language-dropdowns');
    var generated = h.generated_at ? __('Laatst gecontroleerd: ', 'webactueel-translate-language-dropdowns') + h.generated_at : '';

    function renderProblemList() {
      if (!problems.length) {
        return el('div', { className: 'wat-simple-success', role: 'status' },
          el('span', { className: 'wat-simple-success__icon', 'aria-hidden': 'true' }, '✓'),
          el('div', null,
            el('strong', null, __('Geen actie nodig', 'webactueel-translate-language-dropdowns')),
            el('p', null, __('Je kunt dit scherm sluiten. Gebruik technische details alleen bij support of stagingcontrole.', 'webactueel-translate-language-dropdowns'))
          )
        );
      }
      return el('ul', { className: 'wat-system-issue-list' }, problems.map(function (check, index) {
        var tone = check.status === 'fail' ? 'danger' : 'warning';
        return el('li', { key: check.label || index, className: 'wat-system-issue wat-system-issue--' + check.status },
          el(Badge, { tone: tone }, check.status === 'fail' ? __('Fout', 'webactueel-translate-language-dropdowns') : __('Let op', 'webactueel-translate-language-dropdowns')),
          el('div', null,
            el('strong', null, check.label || __('Controle', 'webactueel-translate-language-dropdowns')),
            check.detail ? el('p', null, check.detail) : null
          )
        );
      }));
    }

    function renderLatestLog() {
      if (!logs.length) {
        return null;
      }
      return el('div', { className: 'wat-latest-log wat-latest-log--inside-details' },
        el('strong', null, __('Laatste melding', 'webactueel-translate-language-dropdowns')),
        el('p', null, (logs[0].level || 'info') + ': ' + (logs[0].message || '—'))
      );
    }

    return el('div', { className: 'wat-page wat-health-screen wat-health-screen--simple' },
      el('section', { className: cls('wat-system-summary', hasProblems ? 'has-warning' : 'is-healthy') },
        el('div', { className: 'wat-system-summary__icon', 'aria-hidden': 'true' }, hasProblems ? '!' : '✓'),
        el('div', { className: 'wat-system-summary__content' },
          el('span', { className: 'wat-eyebrow' }, __('Systeemcontrole', 'webactueel-translate-language-dropdowns')),
          el('h2', null, statusTitle),
          el('p', null, statusText),
          generated ? el('small', null, generated) : null
        ),
        el('div', { className: 'wat-system-summary__status' },
          el(Badge, { tone: statusTone }, hasProblems ? __('Aandacht nodig', 'webactueel-translate-language-dropdowns') : __('Gezond', 'webactueel-translate-language-dropdowns'))
        )
      ),
      el(Card, { title: hasProblems ? __('Nu oplossen', 'webactueel-translate-language-dropdowns') : __('Status', 'webactueel-translate-language-dropdowns'), className: 'wat-system-main-card' },
        renderProblemList(),
        el('details', { className: 'wat-system-details' },
          el('summary', null, __('Technische details tonen', 'webactueel-translate-language-dropdowns')),
          renderLatestLog(),
          el(HealthCheckList, { health: h })
        )
      ),
      el('p', { className: 'wat-muted wat-system-note' }, __('Voor livegang blijven SEO, cache, WooCommerce, mobiel, toetsenbord en screenreader onderdeel van de staging-test.', 'webactueel-translate-language-dropdowns'))
    );
  }

  function Translate(p) {
    var languageFilter = useState(window.localStorage.getItem('wat_translate_language') || '');
    var sourceFilter = useState('');
    var debouncedSearch = useDebouncedValue(p.search, 300);
    var strings = useFetch('/strings?page=1&per_page=300&search=' + encodeURIComponent(debouncedSearch) + '&status=' + encodeURIComponent(p.status) + '&language=' + encodeURIComponent(languageFilter[0]) + '&source_type=' + encodeURIComponent(sourceFilter[0]), [p.tick, debouncedSearch, p.status, languageFilter[0], sourceFilter[0]], !p.csvOnly);
    var langs = useFetch('/languages', [p.tick]);
    var tr = useState({ language_code: 'en', translated_text: '', status: 'published' });
    var scan = useState(null);
    var busy = useState(false);
    var csvOpen = useState(!!p.csvOnly);
    var csvLanguages = useState([]);
    var csvMode = useState('all');
    var csvReady = useState(false);
    var file = useState(null);
    var fileInputKey = useState(0);
    var preview = useState(null);
    var edit = useState(null);
    var editTranslations = useState({});
    var editLoading = useState(false);
    var saveBusy = useState(false);
    var applyMemory = useState(true);

    function activeLanguages() {
      var list = (langs.data || []).filter(function (l) { return bool(l.is_active); });
      return list.length ? list : (langs.data || []);
    }

    function translationLanguages() {
      var active = activeLanguages();
      var nonDefault = active.filter(function (l) { return !bool(l.is_default); });
      return nonDefault.length ? nonDefault : active;
    }

    function languageLabel(code) {
      var found = (langs.data || []).filter(function (l) { return l.code === code; })[0];
      return found ? (found.native_name || found.name || code) + ' (' + code + ')' : code;
    }

    function defaultTranslationLanguage() {
      var list = translationLanguages();
      return list[0] && list[0].code ? list[0].code : 'en';
    }

    useEffect(function () {
      if (!csvReady[0] && langs.data) {
        var defaults = translationLanguages().map(function (l) { return l.code; }).filter(Boolean);
        csvLanguages[1](defaults);
        if (!languageFilter[0] && defaults[0]) {
          languageFilter[1](defaults[0]);
          window.localStorage.setItem('wat_translate_language', defaults[0]);
        }
        csvReady[1](true);
      }
    }, [langs.data]);

    function startScan() {
      busy[1](true);
      p.toast.add(__('Scan gestart. Elementor, ACF en postmeta worden meegenomen.', 'webactueel-translate-language-dropdowns'));
      api('/scan/start', { method: 'POST', data: { type: 'full' } })
        .then(function (job) {
          scan[1](job);
          function runNext(currentJob) {
            return api('/scan/run-batch/' + currentJob.id, { method: 'POST', data: { batch_size: 50 } })
              .then(function (nextJob) {
                scan[1](nextJob);
                if (nextJob && nextJob.status && nextJob.status !== 'completed' && nextJob.status !== 'failed' && nextJob.status !== 'stopped' && nextJob.status !== 'paused') {
                  return runNext(nextJob);
                }
                return nextJob;
              });
          }
          return runNext(job);
        })
        .then(function (job) {
          scan[1](job);
          p.toast.add((job.found_strings || 0) + __(' teksten gevonden.', 'webactueel-translate-language-dropdowns'));
          refreshLight();
        })
        .catch(function (e) {
          p.toast.add(e.message || __('Scan mislukt.', 'webactueel-translate-language-dropdowns'), 'error');
        })
        .finally(function () {
          busy[1](false);
        });
    }

    function previewCsv() {
      if (!file[0]) {
        p.toast.add(__('Kies eerst een CSV-bestand.', 'webactueel-translate-language-dropdowns'), 'warning');
        return;
      }
      upload('/csv/preview', file[0])
        .then(function (r) {
          preview[1](r);
          p.toast.add(__('CSV preview gemaakt.', 'webactueel-translate-language-dropdowns'));
        })
        .catch(function (e) {
          p.toast.add(e.message || __('CSV preview mislukt.', 'webactueel-translate-language-dropdowns'), 'error');
        });
    }

    function importCsv() {
      if (!preview[0] || !preview[0].preview_token) {
        p.toast.add(__('Maak eerst een geldige CSV preview.', 'webactueel-translate-language-dropdowns'), 'warning');
        return;
      }
      api('/csv/import', { method: 'POST', data: { preview_token: preview[0].preview_token, languages: csvLanguages[0] } })
        .then(function (r) {
          var skipped = r && r.skipped ? ' (' + num(r.skipped) + __(' regels overgeslagen door taalselectie)', 'webactueel-translate-language-dropdowns') : '';
          var truncated = r && r.truncated ? __(' Import is gestopt op de maximale rijlimiet.', 'webactueel-translate-language-dropdowns') : '';
          p.toast.add(__('CSV import voltooid.', 'webactueel-translate-language-dropdowns') + skipped + truncated, r && r.truncated ? 'warning' : 'success');
          preview[1](null);
          file[1](null);
          fileInputKey[1](fileInputKey[0] + 1);
        })
        .catch(function (e) {
          p.toast.add(e.message || __('Import mislukt.', 'webactueel-translate-language-dropdowns'), 'error');
        });
    }

    function selectEditLanguage(code, map) {
      code = code || defaultTranslationLanguage();
      map = map || editTranslations[0] || {};
      var existing = map[code] || {};
      tr[1]({
        language_code: code,
        translated_text: existing.translated_text || '',
        status: existing.status || 'published'
      });
      if (code) {
        languageFilter[1](code);
        window.localStorage.setItem('wat_translate_language', code);
      }
    }

    function openEdit(s) {
      var code = defaultTranslationLanguage();
      edit[1](s);
      editTranslations[1]({});
      tr[1]({ language_code: code, translated_text: '', status: 'published' });
      editLoading[1](true);
      api('/strings/' + s.id + '/translations')
        .then(function (map) {
          map = map || {};
          editTranslations[1](map);
          selectEditLanguage(code, map);
        })
        .catch(function (e) {
          p.toast.add(e.message || __('Vertalingen laden mislukt.', 'webactueel-translate-language-dropdowns'), 'error');
        })
        .finally(function () {
          editLoading[1](false);
        });
    }

    function patchSavedTranslation(rowId, langCode, text) {
      if (!strings.patchData) { return; }
      strings.patchData(function (data) {
        if (!data || !data.items) { return data; }
        return Object.assign({}, data, {
          items: data.items.map(function (item) {
            if (item.id !== rowId) { return item; }
            return Object.assign({}, item, {
              status: text ? 'published' : item.status,
              effective_status: text ? 'published' : item.effective_status,
              translation_summary: text ? (langCode + ': ' + text) : item.translation_summary
            });
          })
        });
      });
    }

    function saveTranslation() {
      if (!edit[0] || !tr[0].language_code) {
        p.toast.add(__('Kies eerst een taal.', 'webactueel-translate-language-dropdowns'), 'warning');
        return;
      }
      if (saveBusy[0]) { return; }
      saveBusy[1](true);
      api('/strings/' + edit[0].id, { method: 'POST', data: Object.assign({}, tr[0], { apply_memory: applyMemory[0] }) })
        .then(function (r) {
          patchSavedTranslation(edit[0].id, tr[0].language_code, tr[0].translated_text);
          p.toast.add(__('Vertaling opgeslagen.', 'webactueel-translate-language-dropdowns') + (r && r.memory_applied ? ' ' + __('Vertaalgeheugen toegepast op', 'webactueel-translate-language-dropdowns') + ' ' + num(r.memory_applied) + ' ' + __('extra tekst(en).', 'webactueel-translate-language-dropdowns') : ''));
          edit[1](null);
          editTranslations[1]({});
          if (p.refreshDashboard) { p.refreshDashboard(); }
        })
        .catch(function (e) {
          p.toast.add(e.message || __('Opslaan mislukt.', 'webactueel-translate-language-dropdowns'), 'error');
        })
        .finally(function () { saveBusy[1](false); });
    }

    function saveAndNext() {
      var list = (strings.data && strings.data.items) || [];
      var current = edit[0];
      var index = current ? list.findIndex(function (item) { return item.id === current.id; }) : -1;
      if (index < 0 || index >= list.length - 1) {
        saveTranslation();
        return;
      }
      var next = list[index + 1];
      if (!edit[0] || !tr[0].language_code || saveBusy[0]) {
        saveTranslation();
        return;
      }
      saveBusy[1](true);
      api('/strings/' + edit[0].id, { method: 'POST', data: Object.assign({}, tr[0], { apply_memory: applyMemory[0] }) })
        .then(function (r) {
          patchSavedTranslation(edit[0].id, tr[0].language_code, tr[0].translated_text);
          p.toast.add(__('Opgeslagen. Volgende tekst geopend.', 'webactueel-translate-language-dropdowns') + (r && r.memory_applied ? ' ' + __('Geheugen:', 'webactueel-translate-language-dropdowns') + ' ' + num(r.memory_applied) + '.' : ''));
          openEdit(next);
          if (p.refreshDashboard) { p.refreshDashboard(); }
        })
        .catch(function (e) {
          p.toast.add(e.message || __('Opslaan mislukt.', 'webactueel-translate-language-dropdowns'), 'error');
        })
        .finally(function () { saveBusy[1](false); });
    }


    function toggleCsvLanguage(code) {
      csvLanguages[1](function (items) {
        return items.indexOf(code) >= 0 ? items.filter(function (item) { return item !== code; }) : items.concat([code]);
      });
    }

    function exportUrl() {
      var url = cfg.exportUrl;
      var joiner = url.indexOf('?') >= 0 ? '&' : '?';
      if (csvLanguages[0].length) {
        url += joiner + 'languages=' + encodeURIComponent(csvLanguages[0].join(','));
        joiner = '&';
      }
      if (csvMode[0] === 'missing') {
        url += joiner + 'mode=missing';
      }
      return url;
    }

    var languageChoices = translationLanguages();
    var languageOptions = languageChoices.map(function (l) {
      return { label: (l.native_name || l.name || l.code) + ' (' + l.code + ')', value: l.code };
    });
    if (!languageOptions.length) {
      languageOptions = [{ label: __('English (en)', 'webactueel-translate-language-dropdowns'), value: 'en' }];
    }

    var data = strings.data || {};
    var sourceOptions = [{ label: __('Alle bronnen', 'webactueel-translate-language-dropdowns'), value: '' }].concat(Array.from(new Set((data.items || []).map(function (s) { return s.source_type || ''; }).filter(Boolean))).map(function (v) { return { label: v, value: v }; }));
    function statusLabel(status) {
      var labels = {
        new: __('Ontbreekt', 'webactueel-translate-language-dropdowns'),
        draft: __('Concept', 'webactueel-translate-language-dropdowns'),
        needs_review: __('Review nodig', 'webactueel-translate-language-dropdowns'),
        reviewed: __('Goedgekeurd', 'webactueel-translate-language-dropdowns'),
        published: __('Gepubliceerd', 'webactueel-translate-language-dropdowns'),
        ignored: __('Genegeerd', 'webactueel-translate-language-dropdowns')
      };
      return labels[status] || safe(status);
    }

    var rows = (data.items || []).map(function (s) {
      var rowStatus = s.effective_status || s.status;
      return el(
        'tr',
        { key: s.id },
        el('td', null, safe(s.original_text).slice(0, 130)),
        el('td', null, s.translation_summary ? safe(s.translation_summary) : __('Nog niet vertaald', 'webactueel-translate-language-dropdowns')),
        el('td', null, s.translation_summary ? safe(s.translation_summary).split(',').map(function (part) { return part.split(':')[0]; }).join(', ') : '—'),
        el('td', null, el(Badge, { tone: rowStatus === 'new' ? 'warning' : 'success' }, statusLabel(rowStatus))),
        el(
          'td',
          null,
          el(c.Button, {
            variant: 'secondary',
            onClick: function () { openEdit(s); }
          }, __('Bewerken', 'webactueel-translate-language-dropdowns'))
        )
      );
    });

    return el(
      'div',
      { className: 'wat-page' },
      !p.csvOnly && edit[0] ? el('div', { className: 'wat-custom-modal-overlay', role: 'presentation' },
        el('div', { className: 'wat-custom-modal wat-custom-modal--translation', role: 'dialog', tabIndex: -1, 'aria-modal': 'true', 'aria-labelledby': 'wat-translation-modal-title' },
          el('div', { className: 'wat-custom-modal-header' },
            el('h2', { id: 'wat-translation-modal-title' }, __('Vertaling bewerken', 'webactueel-translate-language-dropdowns')),
            el('button', { type: 'button', className: 'wat-custom-modal-close', 'aria-label': __('Sluiten', 'webactueel-translate-language-dropdowns'), onClick: function () { edit[1](null); editTranslations[1]({}); } }, '×')
          ),
          el('div', { className: 'wat-custom-modal-body' },
            el('div', { className: 'wat-modal-layout' },
              el('div', { className: 'wat-modal-section' },
                el('div', { className: 'wat-modal-label' }, __('Originele tekst', 'webactueel-translate-language-dropdowns')),
                el('div', { className: 'wat-readonly' }, safe(edit[0].original_text)),
                applyMemory[0] ? el('div', { className: 'wat-memory-note' },
                  el('strong', null, __('Automatisch toepassen actief', 'webactueel-translate-language-dropdowns')),
                  el('p', null, __('Als exact dezelfde originele tekst ergens anders voorkomt, wordt deze vertaling automatisch overgenomen waar nog geen vertaling staat.', 'webactueel-translate-language-dropdowns'))
                ) : null
              ),
              el('div', { className: 'wat-modal-grid wat-modal-grid--single' },
                el('div', { className: 'wat-modal-field' },
                  el(c.SelectControl, {
                    label: __('Vertalen naar', 'webactueel-translate-language-dropdowns'),
                    value: tr[0].language_code,
                    options: languageOptions,
                    onChange: function (v) { selectEditLanguage(v); }
                  }),
                  editLoading[0] ? el('p', { className: 'wat-muted' }, __('Bestaande vertaling laden…', 'webactueel-translate-language-dropdowns')) : null
                ),
                el('div', { className: 'wat-modal-field' },
                  el(c.SelectControl, {
                    label: __('Status', 'webactueel-translate-language-dropdowns'),
                    value: tr[0].status,
                    options: [
                      { label: __('Concept', 'webactueel-translate-language-dropdowns'), value: 'draft' },
                      { label: __('Review nodig', 'webactueel-translate-language-dropdowns'), value: 'needs_review' },
                      { label: __('Goedgekeurd', 'webactueel-translate-language-dropdowns'), value: 'reviewed' },
                      { label: __('Gepubliceerd', 'webactueel-translate-language-dropdowns'), value: 'published' },
                      { label: __('Negeren', 'webactueel-translate-language-dropdowns'), value: 'ignored' }
                    ],
                    onChange: function (v) { tr[1](Object.assign({}, tr[0], { status: v })); }
                  })
                ),
                el('div', { className: 'wat-modal-field' },
                  el(c.TextareaControl, {
                    label: __('Vertaling voor ', 'webactueel-translate-language-dropdowns') + languageLabel(tr[0].language_code),
                    rows: 8,
                    value: tr[0].translated_text,
                    onChange: function (v) { tr[1](Object.assign({}, tr[0], { translated_text: v })); }
                  })
                ),
                el('div', { className: 'wat-modal-field wat-modal-field--compact' },
                  el(c.ToggleControl, {
                    label: __('Vertaalgeheugen gebruiken', 'webactueel-translate-language-dropdowns'),
                    help: __('Past exact dezelfde originele tekst automatisch toe op andere plekken waar nog geen vertaling staat.', 'webactueel-translate-language-dropdowns'),
                    checked: applyMemory[0],
                    onChange: applyMemory[1]
                  })
                )
              )
            )
          ),
          el('div', { className: 'wat-custom-modal-footer' },
            el(c.Button, { variant: 'secondary', onClick: function () { edit[1](null); editTranslations[1]({}); } }, __('Annuleren', 'webactueel-translate-language-dropdowns')),
            el(c.Button, { variant: 'secondary', isBusy: saveBusy[0], disabled: saveBusy[0], onClick: saveAndNext }, saveBusy[0] ? __('Opslaan…', 'webactueel-translate-language-dropdowns') : __('Opslaan en volgende', 'webactueel-translate-language-dropdowns')),
            el(c.Button, { variant: 'primary', isBusy: saveBusy[0], disabled: saveBusy[0], onClick: saveTranslation }, saveBusy[0] ? __('Opslaan…', 'webactueel-translate-language-dropdowns') : __('Vertaling opslaan', 'webactueel-translate-language-dropdowns'))
          )
        )
      ) : null,
      !p.csvOnly && scan[0] ? el(c.Notice, { status: 'success', isDismissible: true }, __('Scan verwerkt. Je kunt nu vertalingen controleren.', 'webactueel-translate-language-dropdowns')) : null,
      !p.csvOnly ? el(Card, {
        title: __('Gevonden teksten', 'webactueel-translate-language-dropdowns'),
        className: 'wat-results-card',
        action: el(c.Button, { variant: 'secondary', href: cfg.visualEditorUrl || (cfg.siteUrl ? cfg.siteUrl + '?wat_visual_editor=1' : window.location.origin + '/?wat_visual_editor=1') }, __('Visuele editor openen', 'webactueel-translate-language-dropdowns'))
      },
        el('div', { className: 'wat-results-meta' },
          el('strong', null, __('Teksten gevonden', 'webactueel-translate-language-dropdowns')),
          (p.dashboard.missingTranslations || 0) > 0 ? el('span', { className: 'wat-muted' }, __('Er zijn nog vertalingen open', 'webactueel-translate-language-dropdowns')) : null
        ),
        el('div', { className: 'wat-filter-row wat-filter-row--extended' },
          el(c.TextControl, { label: __('Zoeken', 'webactueel-translate-language-dropdowns'), value: p.search, onChange: p.setSearch, placeholder: __('Zoek tekst of bron…', 'webactueel-translate-language-dropdowns') }),
          el(c.SelectControl, {
            label: __('Taal', 'webactueel-translate-language-dropdowns'),
            value: languageFilter[0],
            options: [{ label: __('Alle talen', 'webactueel-translate-language-dropdowns'), value: '' }].concat(languageOptions),
            onChange: function (v) { languageFilter[1](v); window.localStorage.setItem('wat_translate_language', v || ''); }
          }),
          el(c.SelectControl, {
            label: __('Status', 'webactueel-translate-language-dropdowns'),
            value: p.status,
            options: [
              { label: __('Alle statussen', 'webactueel-translate-language-dropdowns'), value: '' },
              { label: __('Ontbreekt', 'webactueel-translate-language-dropdowns'), value: 'new' },
              { label: __('Concept', 'webactueel-translate-language-dropdowns'), value: 'draft' },
              { label: __('Review nodig', 'webactueel-translate-language-dropdowns'), value: 'needs_review' },
              { label: __('Goedgekeurd', 'webactueel-translate-language-dropdowns'), value: 'reviewed' },
              { label: __('Gepubliceerd', 'webactueel-translate-language-dropdowns'), value: 'published' },
              { label: __('Negeren', 'webactueel-translate-language-dropdowns'), value: 'ignored' }
            ],
            onChange: p.setStatus
          }),
          el(c.SelectControl, { label: __('Bron', 'webactueel-translate-language-dropdowns'), value: sourceFilter[0], options: sourceOptions, onChange: sourceFilter[1] })
        ),
        strings.loading ? el(c.Spinner) : el('div', { className: 'wat-results-scroll' },
          el(Table, {
            head: [__('Origineel', 'webactueel-translate-language-dropdowns'), __('Vertaling', 'webactueel-translate-language-dropdowns'), __('Taal', 'webactueel-translate-language-dropdowns'), __('Status', 'webactueel-translate-language-dropdowns'), __('Actie', 'webactueel-translate-language-dropdowns')],
            rows: rows,
            emptyTitle: __('Nog geen teksten gevonden', 'webactueel-translate-language-dropdowns'),
            emptyText: __('Gebruik de scanfunctie om vertaalbare teksten op je website te vinden.', 'webactueel-translate-language-dropdowns')
          })
        )
      ) : null,
      p.csvOnly ? el('div', { className: 'wat-csv-simple wat-csv-simple--polished wat-csv-standalone' },
          el('div', { className: 'wat-csv-box wat-csv-box--export' },
            el('h3', null, __('Talen kiezen en exporteren', 'webactueel-translate-language-dropdowns')),
            el('p', null, __('Kies één taal of meerdere talen voor export en import.', 'webactueel-translate-language-dropdowns')),
            langs.loading ? el(c.Spinner) : el('div', { className: 'wat-language-checklist' },
              languageChoices.map(function (l) {
                return el('label', { key: l.code, className: 'wat-language-check' },
                  el('input', { type: 'checkbox', checked: csvLanguages[0].indexOf(l.code) >= 0, onChange: function () { toggleCsvLanguage(l.code); } }),
                  el('span', null, (l.native_name || l.name || l.code) + ' (' + l.code + ')')
                );
              })
            ),
            el(c.SelectControl, {
              label: __('Exporttype', 'webactueel-translate-language-dropdowns'),
              value: csvMode[0],
              options: [
                { label: __('Alle teksten', 'webactueel-translate-language-dropdowns'), value: 'all' },
                { label: __('Alleen ontbrekende vertalingen', 'webactueel-translate-language-dropdowns'), value: 'missing' }
              ],
              onChange: csvMode[1]
            }),
            el('p', { className: 'wat-muted' }, csvLanguages[0].length ? __('Download CSV voor: ', 'webactueel-translate-language-dropdowns') + csvLanguages[0].map(languageLabel).join(', ') + (csvMode[0] === 'missing' ? ' · alleen ontbrekend.' : '.') : __('Download CSV zonder taalfilter.', 'webactueel-translate-language-dropdowns')),
            el('div', { className: 'wat-inline-actions wat-inline-actions--export' },
              el(c.Button, { variant: 'secondary', onClick: function () { csvLanguages[1](languageChoices.map(function (l) { return l.code; }).filter(Boolean)); } }, __('Alles selecteren', 'webactueel-translate-language-dropdowns')),
              el(c.Button, { variant: 'tertiary', onClick: function () { csvLanguages[1]([]); } }, __('Alles wissen', 'webactueel-translate-language-dropdowns')),
              el(c.Button, { variant: 'primary', className: 'wat-button-auto', href: exportUrl() }, __('CSV downloaden', 'webactueel-translate-language-dropdowns'))
            )
          ),
          el('div', { className: 'wat-csv-box wat-csv-box--import' },
            el('h3', null, __('Importeren', 'webactueel-translate-language-dropdowns')),
            el('p', null, csvLanguages[0].length ? __('Alleen regels voor de geselecteerde taal/talen worden geïmporteerd.', 'webactueel-translate-language-dropdowns') : __('Alle geldige talen uit de CSV worden geïmporteerd.', 'webactueel-translate-language-dropdowns')),
            el('div', { className: 'wat-file-picker' },
              el('input', {
                key: fileInputKey[0],
                type: 'file',
                accept: '.csv,text/csv',
                onChange: function (e) {
                  file[1](e.target.files[0] || null);
                  preview[1](null);
                }
              })
            ),
            el('div', { className: 'wat-inline-actions wat-inline-actions-csv' },
              el(c.Button, { variant: 'secondary', className: 'wat-button-auto', disabled: !file[0], onClick: previewCsv }, __('Preview maken', 'webactueel-translate-language-dropdowns')),
              el(c.Button, { variant: 'primary', className: 'wat-button-auto', disabled: !(preview[0] && preview[0].preview_token), onClick: importCsv }, __('Importeren', 'webactueel-translate-language-dropdowns'))
            ),
            preview[0] ? el('div', { className: 'wat-preview-summary' },
              el('strong', null, __('Preview klaar', 'webactueel-translate-language-dropdowns')),
              el('p', null, num((preview[0].stats && preview[0].stats.valid) || preview[0].valid || 0) + ' geldige regels, ' + num((preview[0].errors || []).length) + __(' fouten.', 'webactueel-translate-language-dropdowns'))
            ) : null
          )
      ) : null
    );
  }

  function switcherPreviewText(language, layout) {
    var code = String(language.code || '').toUpperCase();
    var name = language.name || code;
    var flag = language.flag || '';

    if (layout === 'flags') {
      return flag || code;
    }
    if (layout === 'code') {
      return code;
    }
    if (layout === 'flags_name') {
      return (flag ? flag + ' ' : '') + name;
    }
    if (layout === 'flag_code') {
      return (flag ? flag + ' ' : '') + code;
    }
    if (layout === 'name_code') {
      return name + ' (' + code + ')';
    }
    if (layout === 'flags_name_code') {
      return (flag ? flag + ' ' : '') + name + ' (' + code + ')';
    }
    return name;
  }

  function switcherFlagChip(language) {
    var code = String(language.code || '').toLowerCase();
    var rawFlag = String(language.flag || '').toLowerCase();
    var flagClass = /^[a-z]{2}$/i.test(rawFlag) ? rawFlag : code;

    return el('span', {
      className: cls('wat-flag-chip', 'wat-flag-chip--' + code, 'wat-flag-chip--' + flagClass),
      'aria-hidden': 'true'
    });
  }

  function switcherPreviewContent(language, layout) {
    var code = String(language.code || '').toUpperCase();
    var name = language.name || code;
    var pieces = [];
    var useFlag = layout === 'flags' || layout === 'flags_name' || layout === 'flag_code' || layout === 'flags_name_code' || layout === 'dropdown';

    if (useFlag) {
      pieces.push(switcherFlagChip(language));
    }

    if (layout === 'flags') {
      if (!useFlag) {
        pieces.push(code);
      }
      return pieces;
    }
    if (layout === 'code') {
      pieces.push(el('span', { className: 'wat-switcher-preview-code' }, code));
      return pieces;
    }
    if (layout === 'flags_name') {
      pieces.push(el('span', { className: 'wat-switcher-preview-text' }, name));
      return pieces;
    }
    if (layout === 'flag_code') {
      pieces.push(el('span', { className: 'wat-switcher-preview-code' }, code));
      return pieces;
    }
    if (layout === 'name_code') {
      pieces.push(el('span', { className: 'wat-switcher-preview-text' }, name + ' (' + code + ')'));
      return pieces;
    }
    if (layout === 'flags_name_code') {
      pieces.push(el('span', { className: 'wat-switcher-preview-text' }, name + ' (' + code + ')'));
      return pieces;
    }
    pieces.push(el('span', { className: 'wat-switcher-preview-text' }, name));
    return pieces;
  }

  function SwitcherPreview(p) {
    var sample = [
      { code: 'nl', name: 'Nederlands', flag: '🇳🇱' },
      { code: 'en', name: 'English', flag: '🇬🇧' },
      { code: 'de', name: 'Deutsch', flag: '🇩🇪' }
    ];
    var layout = p.layout || 'dropdown';
    var style = p.style || 'light';
    var floating = bool(p.floating);
    var positionLabels = {
      'bottom-right': __('rechtsonder', 'webactueel-translate-language-dropdowns'),
      'bottom-left': __('linksonder', 'webactueel-translate-language-dropdowns'),
      'top-right': __('rechtsboven', 'webactueel-translate-language-dropdowns'),
      'top-left': __('linksboven', 'webactueel-translate-language-dropdowns')
    };
    var active = sample[0];

    if (layout === 'dropdown') {
      return el('div', { className: cls('wat-switcher-preview', 'is-' + style, 'is-dropdown') },
        el('div', { className: 'wat-switcher-preview-inner' },
          el('div', { className: 'wat-switcher-preview-control' },
            el('span', { className: 'wat-switcher-preview-value' }, switcherPreviewContent(active, layout)),
            el('span', { className: 'wat-switcher-preview-chevron', 'aria-hidden': 'true' }, '▾')
          )
        ),
        floating ? el('p', { className: 'wat-switcher-preview-note' }, __('Floating voorbeeld: ', 'webactueel-translate-language-dropdowns') + (positionLabels[p.position] || __('rechtsonder', 'webactueel-translate-language-dropdowns')) + '.') : null
      );
    }

    return el('div', { className: cls('wat-switcher-preview', 'is-' + style, 'is-inline') },
      el('div', { className: 'wat-switcher-preview-inner' },
        el('div', { className: cls('wat-switcher-preview-list', 'layout-' + layout) },
          sample.map(function (language, index) {
            return el('span', {
              key: language.code,
              className: cls('wat-switcher-preview-pill', index === 0 ? 'is-active' : '')
            }, switcherPreviewContent(language, layout));
          })
        )
      ),
      floating ? el('p', { className: 'wat-switcher-preview-note' }, __('Floating voorbeeld: ', 'webactueel-translate-language-dropdowns') + (positionLabels[p.position] || __('rechtsonder', 'webactueel-translate-language-dropdowns')) + '.') : null
    );
  }

  function Languages(p) {
    var langs = useFetch('/languages', [p.tick]);
    var setSt = useState(null);
    var snippetMode = useState('shortcode');
    var modal = useState(false);
    var form = useState({ code: '', locale: '', name: '', native_name: '', flag: '', is_active: true, is_default: false, is_rtl: false });
    var picker = useState({ field: '', query: '' });
    var languagePresetList = [
      { code: 'af', locale: 'af', name: 'Afrikaans', native_name: 'Afrikaans', flag: 'za', country: 'Zuid-Afrika' },
      { code: 'sq', locale: 'sq_AL', name: 'Albanian', native_name: 'Shqip', flag: 'al', country: 'Albanie' },
      { code: 'ar', locale: 'ar_DZ', name: 'Arabic', native_name: 'العربية', flag: 'dz', country: 'Algerije', is_rtl: true },
      { code: 'ar', locale: 'ar_BH', name: 'Arabic', native_name: 'العربية', flag: 'bh', country: 'Bahrein', is_rtl: true },
      { code: 'ar', locale: 'ar_EG', name: 'Arabic', native_name: 'العربية', flag: 'eg', country: 'Egypte', is_rtl: true },
      { code: 'ar', locale: 'ar_IQ', name: 'Arabic', native_name: 'العربية', flag: 'iq', country: 'Irak', is_rtl: true },
      { code: 'ar', locale: 'ar_JO', name: 'Arabic', native_name: 'العربية', flag: 'jo', country: 'Jordanie', is_rtl: true },
      { code: 'ar', locale: 'ar_KW', name: 'Arabic', native_name: 'العربية', flag: 'kw', country: 'Koeweit', is_rtl: true },
      { code: 'ar', locale: 'ar_LB', name: 'Arabic', native_name: 'العربية', flag: 'lb', country: 'Libanon', is_rtl: true },
      { code: 'ar', locale: 'ar_LY', name: 'Arabic', native_name: 'العربية', flag: 'ly', country: 'Libie', is_rtl: true },
      { code: 'ar', locale: 'ar_MA', name: 'Arabic', native_name: 'العربية', flag: 'ma', country: 'Marokko', is_rtl: true },
      { code: 'ar', locale: 'ar_OM', name: 'Arabic', native_name: 'العربية', flag: 'om', country: 'Oman', is_rtl: true },
      { code: 'ar', locale: 'ar_QA', name: 'Arabic', native_name: 'العربية', flag: 'qa', country: 'Qatar', is_rtl: true },
      { code: 'ar', locale: 'ar_SA', name: 'Arabic', native_name: 'العربية', flag: 'sa', country: 'Saoedi-Arabie', is_rtl: true },
      { code: 'ar', locale: 'ar_SD', name: 'Arabic', native_name: 'العربية', flag: 'sd', country: 'Soedan', is_rtl: true },
      { code: 'ar', locale: 'ar_SY', name: 'Arabic', native_name: 'العربية', flag: 'sy', country: 'Syrie', is_rtl: true },
      { code: 'ar', locale: 'ar_TN', name: 'Arabic', native_name: 'العربية', flag: 'tn', country: 'Tunesie', is_rtl: true },
      { code: 'ar', locale: 'ar_AE', name: 'Arabic', native_name: 'العربية', flag: 'ae', country: 'Verenigde Arabische Emiraten', is_rtl: true },
      { code: 'ar', locale: 'ar_YE', name: 'Arabic', native_name: 'العربية', flag: 'ye', country: 'Jemen', is_rtl: true },
      { code: 'hy', locale: 'hy_AM', name: 'Armenian', native_name: 'Հայերեն', flag: 'am', country: 'Armenie' },
      { code: 'az', locale: 'az_AZ', name: 'Azerbaijani', native_name: 'Azərbaycanca', flag: 'az', country: 'Azerbeidzjan' },
      { code: 'eu', locale: 'eu_ES', name: 'Basque', native_name: 'Euskara', flag: 'es', country: 'Baskenland' },
      { code: 'be', locale: 'be_BY', name: 'Belarusian', native_name: 'Беларуская', flag: 'by', country: 'Belarus' },
      { code: 'bn', locale: 'bn_BD', name: 'Bengali', native_name: 'বাংলা', flag: 'bd', country: 'Bangladesh' },
      { code: 'bn', locale: 'bn_IN', name: 'Bengali', native_name: 'বাংলা', flag: 'in', country: 'India Bengali' },
      { code: 'bs', locale: 'bs_BA', name: 'Bosnian', native_name: 'Bosanski', flag: 'ba', country: 'Bosnie en Herzegovina' },
      { code: 'bg', locale: 'bg_BG', name: 'Bulgarian', native_name: 'Български', flag: 'bg', country: 'Bulgarije' },
      { code: 'my', locale: 'my_MM', name: 'Burmese', native_name: 'မြန်မာ', flag: 'mm', country: 'Myanmar' },
      { code: 'ca', locale: 'ca_ES', name: 'Catalan', native_name: 'Catala', flag: 'es', country: 'Catalonie' },
      { code: 'zh', locale: 'zh_CN', name: 'Chinese Simplified', native_name: '简体中文', flag: 'cn', country: 'China' },
      { code: 'zh', locale: 'zh_HK', name: 'Chinese Traditional', native_name: '繁體中文', flag: 'hk', country: 'Hongkong' },
      { code: 'zh', locale: 'zh_TW', name: 'Chinese Traditional', native_name: '繁體中文', flag: 'tw', country: 'Taiwan' },
      { code: 'hr', locale: 'hr_HR', name: 'Croatian', native_name: 'Hrvatski', flag: 'hr', country: 'Kroatie' },
      { code: 'cs', locale: 'cs_CZ', name: 'Czech', native_name: 'Cestina', flag: 'cz', country: 'Tsjechie' },
      { code: 'da', locale: 'da_DK', name: 'Danish', native_name: 'Dansk', flag: 'dk', country: 'Denemarken' },
      { code: 'nl', locale: 'nl_AW', name: 'Dutch', native_name: 'Nederlands', flag: 'aw', country: 'Aruba' },
      { code: 'nl', locale: 'nl_BE', name: 'Dutch Belgium', native_name: 'Nederlands', flag: 'be', country: 'Belgie' },
      { code: 'nl', locale: 'nl_CW', name: 'Dutch', native_name: 'Nederlands', flag: 'cw', country: 'Curacao' },
      { code: 'nl', locale: 'nl_NL', name: 'Dutch', native_name: 'Nederlands', flag: 'nl', country: 'Nederland' },
      { code: 'nl', locale: 'nl_SR', name: 'Dutch', native_name: 'Nederlands', flag: 'sr', country: 'Suriname' },
      { code: 'en', locale: 'en_AU', name: 'English Australia', native_name: 'English', flag: 'au', country: 'Australie' },
      { code: 'en', locale: 'en_BZ', name: 'English Belize', native_name: 'English', flag: 'bz', country: 'Belize' },
      { code: 'en', locale: 'en_CA', name: 'English Canada', native_name: 'English', flag: 'ca', country: 'Canada' },
      { code: 'en', locale: 'en_IE', name: 'English Ireland', native_name: 'English', flag: 'ie', country: 'Ierland' },
      { code: 'en', locale: 'en_JM', name: 'English Jamaica', native_name: 'English', flag: 'jm', country: 'Jamaica' },
      { code: 'en', locale: 'en_NZ', name: 'English New Zealand', native_name: 'English', flag: 'nz', country: 'Nieuw-Zeeland' },
      { code: 'en', locale: 'en_PH', name: 'English Philippines', native_name: 'English', flag: 'ph', country: 'Filipijnen' },
      { code: 'en', locale: 'en_SG', name: 'English Singapore', native_name: 'English', flag: 'sg', country: 'Singapore' },
      { code: 'en', locale: 'en_ZA', name: 'English South Africa', native_name: 'English', flag: 'za', country: 'Zuid-Afrika Engels' },
      { code: 'en', locale: 'en_GB', name: 'English UK', native_name: 'English', flag: 'gb', country: 'Verenigd Koninkrijk' },
      { code: 'en', locale: 'en_US', name: 'English US', native_name: 'English', flag: 'us', country: 'Verenigde Staten' },
      { code: 'et', locale: 'et_EE', name: 'Estonian', native_name: 'Eesti', flag: 'ee', country: 'Estland' },
      { code: 'fi', locale: 'fi_FI', name: 'Finnish', native_name: 'Suomi', flag: 'fi', country: 'Finland' },
      { code: 'fr', locale: 'fr_BE', name: 'French Belgium', native_name: 'Francais', flag: 'be', country: 'Belgie Frans' },
      { code: 'fr', locale: 'fr_CA', name: 'French Canada', native_name: 'Francais', flag: 'ca', country: 'Canada Frans' },
      { code: 'fr', locale: 'fr_FR', name: 'French', native_name: 'Francais', flag: 'fr', country: 'Frankrijk' },
      { code: 'fr', locale: 'fr_LU', name: 'French Luxembourg', native_name: 'Francais', flag: 'lu', country: 'Luxemburg Frans' },
      { code: 'fr', locale: 'fr_MC', name: 'French Monaco', native_name: 'Francais', flag: 'mc', country: 'Monaco' },
      { code: 'fr', locale: 'fr_SN', name: 'French Senegal', native_name: 'Francais', flag: 'sn', country: 'Senegal' },
      { code: 'fr', locale: 'fr_CH', name: 'French Switzerland', native_name: 'Francais', flag: 'ch', country: 'Zwitserland Frans' },
      { code: 'ka', locale: 'ka_GE', name: 'Georgian', native_name: 'ქართული', flag: 'ge', country: 'Georgie' },
      { code: 'de', locale: 'de_AT', name: 'German Austria', native_name: 'Deutsch', flag: 'at', country: 'Oostenrijk' },
      { code: 'de', locale: 'de_DE', name: 'German', native_name: 'Deutsch', flag: 'de', country: 'Duitsland' },
      { code: 'de', locale: 'de_LI', name: 'German Liechtenstein', native_name: 'Deutsch', flag: 'li', country: 'Liechtenstein' },
      { code: 'de', locale: 'de_LU', name: 'German Luxembourg', native_name: 'Deutsch', flag: 'lu', country: 'Luxemburg Duits' },
      { code: 'de', locale: 'de_CH', name: 'German Switzerland', native_name: 'Deutsch', flag: 'ch', country: 'Zwitserland' },
      { code: 'el', locale: 'el_GR', name: 'Greek', native_name: 'Ελληνικα', flag: 'gr', country: 'Griekenland' },
      { code: 'el', locale: 'el_CY', name: 'Greek Cyprus', native_name: 'Ελληνικα', flag: 'cy', country: 'Cyprus' },
      { code: 'gu', locale: 'gu_IN', name: 'Gujarati', native_name: 'ગુજરાતી', flag: 'in', country: 'India Gujarati' },
      { code: 'he', locale: 'he_IL', name: 'Hebrew', native_name: 'עברית', flag: 'il', country: 'Israel', is_rtl: true },
      { code: 'hi', locale: 'hi_IN', name: 'Hindi', native_name: 'हिन्दी', flag: 'in', country: 'India' },
      { code: 'hu', locale: 'hu_HU', name: 'Hungarian', native_name: 'Magyar', flag: 'hu', country: 'Hongarije' },
      { code: 'is', locale: 'is_IS', name: 'Icelandic', native_name: 'Islenska', flag: 'is', country: 'IJsland' },
      { code: 'id', locale: 'id_ID', name: 'Indonesian', native_name: 'Bahasa Indonesia', flag: 'id', country: 'Indonesie' },
      { code: 'ga', locale: 'ga_IE', name: 'Irish', native_name: 'Gaeilge', flag: 'ie', country: 'Iers' },
      { code: 'it', locale: 'it_IT', name: 'Italian', native_name: 'Italiano', flag: 'it', country: 'Italie' },
      { code: 'it', locale: 'it_CH', name: 'Italian Switzerland', native_name: 'Italiano', flag: 'ch', country: 'Zwitserland Italiaans' },
      { code: 'ja', locale: 'ja', name: 'Japanese', native_name: '日本語', flag: 'jp', country: 'Japan' },
      { code: 'kn', locale: 'kn_IN', name: 'Kannada', native_name: 'ಕನ್ನಡ', flag: 'in', country: 'India Kannada' },
      { code: 'kk', locale: 'kk_KZ', name: 'Kazakh', native_name: 'Қазақша', flag: 'kz', country: 'Kazachstan' },
      { code: 'km', locale: 'km_KH', name: 'Khmer', native_name: 'ខ្មែរ', flag: 'kh', country: 'Cambodja' },
      { code: 'ko', locale: 'ko_KR', name: 'Korean', native_name: '한국어', flag: 'kr', country: 'Zuid-Korea' },
      { code: 'ky', locale: 'ky_KG', name: 'Kyrgyz', native_name: 'Кыргызча', flag: 'kg', country: 'Kirgizie' },
      { code: 'lo', locale: 'lo_LA', name: 'Lao', native_name: 'ລາວ', flag: 'la', country: 'Laos' },
      { code: 'lv', locale: 'lv_LV', name: 'Latvian', native_name: 'Latviesu', flag: 'lv', country: 'Letland' },
      { code: 'lt', locale: 'lt_LT', name: 'Lithuanian', native_name: 'Lietuviu', flag: 'lt', country: 'Litouwen' },
      { code: 'mk', locale: 'mk_MK', name: 'Macedonian', native_name: 'Македонски', flag: 'mk', country: 'Noord-Macedonie' },
      { code: 'ms', locale: 'ms_MY', name: 'Malay', native_name: 'Bahasa Melayu', flag: 'my', country: 'Maleisie' },
      { code: 'ml', locale: 'ml_IN', name: 'Malayalam', native_name: 'മലയാളം', flag: 'in', country: 'India Malayalam' },
      { code: 'mt', locale: 'mt_MT', name: 'Maltese', native_name: 'Malti', flag: 'mt', country: 'Malta' },
      { code: 'mr', locale: 'mr_IN', name: 'Marathi', native_name: 'मराठी', flag: 'in', country: 'India Marathi' },
      { code: 'mn', locale: 'mn_MN', name: 'Mongolian', native_name: 'Монгол', flag: 'mn', country: 'Mongolie' },
      { code: 'ne', locale: 'ne_NP', name: 'Nepali', native_name: 'नेपाली', flag: 'np', country: 'Nepal' },
      { code: 'nb', locale: 'nb_NO', name: 'Norwegian Bokmal', native_name: 'Norsk bokmal', flag: 'no', country: 'Noorwegen' },
      { code: 'nn', locale: 'nn_NO', name: 'Norwegian Nynorsk', native_name: 'Norsk nynorsk', flag: 'no', country: 'Noorwegen Nynorsk' },
      { code: 'fa', locale: 'fa_IR', name: 'Persian', native_name: 'فارسی', flag: 'ir', country: 'Iran', is_rtl: true },
      { code: 'pl', locale: 'pl_PL', name: 'Polish', native_name: 'Polski', flag: 'pl', country: 'Polen' },
      { code: 'pt', locale: 'pt_AO', name: 'Portuguese Angola', native_name: 'Portugues', flag: 'ao', country: 'Angola' },
      { code: 'pt', locale: 'pt_BR', name: 'Portuguese Brazil', native_name: 'Portugues do Brasil', flag: 'br', country: 'Brazilie' },
      { code: 'pt', locale: 'pt_MZ', name: 'Portuguese Mozambique', native_name: 'Portugues', flag: 'mz', country: 'Mozambique' },
      { code: 'pt', locale: 'pt_PT', name: 'Portuguese', native_name: 'Portugues', flag: 'pt', country: 'Portugal' },
      { code: 'pa', locale: 'pa_IN', name: 'Punjabi', native_name: 'ਪੰਜਾਬੀ', flag: 'in', country: 'India Punjabi' },
      { code: 'ro', locale: 'ro_MD', name: 'Romanian Moldova', native_name: 'Romana', flag: 'md', country: 'Moldavie' },
      { code: 'ro', locale: 'ro_RO', name: 'Romanian', native_name: 'Romana', flag: 'ro', country: 'Roemenie' },
      { code: 'ru', locale: 'ru_RU', name: 'Russian', native_name: 'Русский', flag: 'ru', country: 'Rusland' },
      { code: 'ru', locale: 'ru_BY', name: 'Russian Belarus', native_name: 'Русский', flag: 'by', country: 'Belarus Russisch' },
      { code: 'ru', locale: 'ru_KZ', name: 'Russian Kazakhstan', native_name: 'Русский', flag: 'kz', country: 'Kazachstan Russisch' },
      { code: 'sr', locale: 'sr_RS', name: 'Serbian', native_name: 'Српски', flag: 'rs', country: 'Servie' },
      { code: 'si', locale: 'si_LK', name: 'Sinhala', native_name: 'සිංහල', flag: 'lk', country: 'Sri Lanka' },
      { code: 'sk', locale: 'sk_SK', name: 'Slovak', native_name: 'Slovencina', flag: 'sk', country: 'Slowakije' },
      { code: 'sl', locale: 'sl_SI', name: 'Slovenian', native_name: 'Slovenscina', flag: 'si', country: 'Slovenie' },
      { code: 'es', locale: 'es_AR', name: 'Spanish Argentina', native_name: 'Espanol', flag: 'ar', country: 'Argentinie' },
      { code: 'es', locale: 'es_BO', name: 'Spanish Bolivia', native_name: 'Espanol', flag: 'bo', country: 'Bolivia' },
      { code: 'es', locale: 'es_CL', name: 'Spanish Chile', native_name: 'Espanol', flag: 'cl', country: 'Chili' },
      { code: 'es', locale: 'es_CO', name: 'Spanish Colombia', native_name: 'Espanol', flag: 'co', country: 'Colombia' },
      { code: 'es', locale: 'es_CR', name: 'Spanish Costa Rica', native_name: 'Espanol', flag: 'cr', country: 'Costa Rica' },
      { code: 'es', locale: 'es_DO', name: 'Spanish Dominican Republic', native_name: 'Espanol', flag: 'do', country: 'Dominicaanse Republiek' },
      { code: 'es', locale: 'es_EC', name: 'Spanish Ecuador', native_name: 'Espanol', flag: 'ec', country: 'Ecuador' },
      { code: 'es', locale: 'es_SV', name: 'Spanish El Salvador', native_name: 'Espanol', flag: 'sv', country: 'El Salvador' },
      { code: 'es', locale: 'es_GT', name: 'Spanish Guatemala', native_name: 'Espanol', flag: 'gt', country: 'Guatemala' },
      { code: 'es', locale: 'es_HN', name: 'Spanish Honduras', native_name: 'Espanol', flag: 'hn', country: 'Honduras' },
      { code: 'es', locale: 'es_MX', name: 'Spanish Mexico', native_name: 'Espanol', flag: 'mx', country: 'Mexico' },
      { code: 'es', locale: 'es_NI', name: 'Spanish Nicaragua', native_name: 'Espanol', flag: 'ni', country: 'Nicaragua' },
      { code: 'es', locale: 'es_PA', name: 'Spanish Panama', native_name: 'Espanol', flag: 'pa', country: 'Panama' },
      { code: 'es', locale: 'es_PY', name: 'Spanish Paraguay', native_name: 'Espanol', flag: 'py', country: 'Paraguay' },
      { code: 'es', locale: 'es_PE', name: 'Spanish Peru', native_name: 'Espanol', flag: 'pe', country: 'Peru' },
      { code: 'es', locale: 'es_PR', name: 'Spanish Puerto Rico', native_name: 'Espanol', flag: 'pr', country: 'Puerto Rico' },
      { code: 'es', locale: 'es_ES', name: 'Spanish', native_name: 'Espanol', flag: 'es', country: 'Spanje' },
      { code: 'es', locale: 'es_UY', name: 'Spanish Uruguay', native_name: 'Espanol', flag: 'uy', country: 'Uruguay' },
      { code: 'es', locale: 'es_VE', name: 'Spanish Venezuela', native_name: 'Espanol', flag: 've', country: 'Venezuela' },
      { code: 'sw', locale: 'sw_KE', name: 'Swahili', native_name: 'Kiswahili', flag: 'ke', country: 'Kenia' },
      { code: 'sw', locale: 'sw_TZ', name: 'Swahili', native_name: 'Kiswahili', flag: 'tz', country: 'Tanzania' },
      { code: 'sv', locale: 'sv_SE', name: 'Swedish', native_name: 'Svenska', flag: 'se', country: 'Zweden' },
      { code: 'ta', locale: 'ta_IN', name: 'Tamil', native_name: 'தமிழ்', flag: 'in', country: 'India Tamil' },
      { code: 'ta', locale: 'ta_LK', name: 'Tamil Sri Lanka', native_name: 'தமிழ்', flag: 'lk', country: 'Sri Lanka Tamil' },
      { code: 'te', locale: 'te_IN', name: 'Telugu', native_name: 'తెలుగు', flag: 'in', country: 'India Telugu' },
      { code: 'th', locale: 'th', name: 'Thai', native_name: 'ไทย', flag: 'th', country: 'Thailand' },
      { code: 'tr', locale: 'tr_TR', name: 'Turkish', native_name: 'Turkce', flag: 'tr', country: 'Turkije' },
      { code: 'uk', locale: 'uk_UA', name: 'Ukrainian', native_name: 'Українська', flag: 'ua', country: 'Oekraine' },
      { code: 'ur', locale: 'ur_PK', name: 'Urdu', native_name: 'اردو', flag: 'pk', country: 'Pakistan', is_rtl: true },
      { code: 'uz', locale: 'uz_UZ', name: 'Uzbek', native_name: 'Ozbek', flag: 'uz', country: 'Oezbekistan' },
      { code: 'vi', locale: 'vi', name: 'Vietnamese', native_name: 'Tieng Viet', flag: 'vn', country: 'Vietnam' },
      { code: 'cy', locale: 'cy_GB', name: 'Welsh', native_name: 'Cymraeg', flag: 'gb', country: 'Wales' },
      { code: 'ps', locale: 'ps_AF', name: 'Pashto', native_name: 'پښتو', flag: 'af', country: 'Afghanistan', is_rtl: true },
      { code: 'ca', locale: 'ca_AD', name: 'Catalan Andorra', native_name: 'Catala', flag: 'ad', country: 'Andorra' },
      { code: 'en', locale: 'en_AG', name: 'English Antigua and Barbuda', native_name: 'English', flag: 'ag', country: 'Antigua en Barbuda' },
      { code: 'en', locale: 'en_BS', name: 'English Bahamas', native_name: 'English', flag: 'bs', country: 'Bahamas' },
      { code: 'en', locale: 'en_BB', name: 'English Barbados', native_name: 'English', flag: 'bb', country: 'Barbados' },
      { code: 'fr', locale: 'fr_BJ', name: 'French Benin', native_name: 'Francais', flag: 'bj', country: 'Benin' },
      { code: 'dz', locale: 'dz_BT', name: 'Dzongkha', native_name: 'Dzongkha', flag: 'bt', country: 'Bhutan' },
      { code: 'en', locale: 'en_BW', name: 'English Botswana', native_name: 'English', flag: 'bw', country: 'Botswana' },
      { code: 'fr', locale: 'fr_BF', name: 'French Burkina Faso', native_name: 'Francais', flag: 'bf', country: 'Burkina Faso' },
      { code: 'fr', locale: 'fr_BI', name: 'French Burundi', native_name: 'Francais', flag: 'bi', country: 'Burundi' },
      { code: 'fr', locale: 'fr_CM', name: 'French Cameroon', native_name: 'Francais', flag: 'cm', country: 'Kameroen' },
      { code: 'pt', locale: 'pt_CV', name: 'Portuguese Cape Verde', native_name: 'Portugues', flag: 'cv', country: 'Kaapverdie' },
      { code: 'fr', locale: 'fr_CF', name: 'French Central African Republic', native_name: 'Francais', flag: 'cf', country: 'Centraal-Afrikaanse Republiek' },
      { code: 'fr', locale: 'fr_TD', name: 'French Chad', native_name: 'Francais', flag: 'td', country: 'Tsjaad' },
      { code: 'ar', locale: 'ar_KM', name: 'Arabic Comoros', native_name: 'العربية', flag: 'km', country: 'Comoren', is_rtl: true },
      { code: 'fr', locale: 'fr_CG', name: 'French Congo', native_name: 'Francais', flag: 'cg', country: 'Congo-Brazzaville' },
      { code: 'fr', locale: 'fr_CD', name: 'French DR Congo', native_name: 'Francais', flag: 'cd', country: 'Congo-Kinshasa' },
      { code: 'es', locale: 'es_CU', name: 'Spanish Cuba', native_name: 'Espanol', flag: 'cu', country: 'Cuba' },
      { code: 'fr', locale: 'fr_DJ', name: 'French Djibouti', native_name: 'Francais', flag: 'dj', country: 'Djibouti' },
      { code: 'en', locale: 'en_DM', name: 'English Dominica', native_name: 'English', flag: 'dm', country: 'Dominica' },
      { code: 'es', locale: 'es_GQ', name: 'Spanish Equatorial Guinea', native_name: 'Espanol', flag: 'gq', country: 'Equatoriaal-Guinea' },
      { code: 'ti', locale: 'ti_ER', name: 'Tigrinya', native_name: 'Tigrinya', flag: 'er', country: 'Eritrea' },
      { code: 'am', locale: 'am_ET', name: 'Amharic', native_name: 'Amharic', flag: 'et', country: 'Ethiopie' },
      { code: 'en', locale: 'en_FJ', name: 'English Fiji', native_name: 'English', flag: 'fj', country: 'Fiji' },
      { code: 'fr', locale: 'fr_GA', name: 'French Gabon', native_name: 'Francais', flag: 'ga', country: 'Gabon' },
      { code: 'en', locale: 'en_GM', name: 'English Gambia', native_name: 'English', flag: 'gm', country: 'Gambia' },
      { code: 'en', locale: 'en_GH', name: 'English Ghana', native_name: 'English', flag: 'gh', country: 'Ghana' },
      { code: 'en', locale: 'en_GD', name: 'English Grenada', native_name: 'English', flag: 'gd', country: 'Grenada' },
      { code: 'fr', locale: 'fr_GN', name: 'French Guinea', native_name: 'Francais', flag: 'gn', country: 'Guinee' },
      { code: 'pt', locale: 'pt_GW', name: 'Portuguese Guinea-Bissau', native_name: 'Portugues', flag: 'gw', country: 'Guinee-Bissau' },
      { code: 'en', locale: 'en_GY', name: 'English Guyana', native_name: 'English', flag: 'gy', country: 'Guyana' },
      { code: 'ht', locale: 'ht_HT', name: 'Haitian Creole', native_name: 'Kreyol ayisyen', flag: 'ht', country: 'Haiti' },
      { code: 'fr', locale: 'fr_CI', name: 'French Ivory Coast', native_name: 'Francais', flag: 'ci', country: 'Ivoorkust' },
      { code: 'en', locale: 'en_LS', name: 'English Lesotho', native_name: 'English', flag: 'ls', country: 'Lesotho' },
      { code: 'en', locale: 'en_LR', name: 'English Liberia', native_name: 'English', flag: 'lr', country: 'Liberia' },
      { code: 'mg', locale: 'mg_MG', name: 'Malagasy', native_name: 'Malagasy', flag: 'mg', country: 'Madagaskar' },
      { code: 'en', locale: 'en_MW', name: 'English Malawi', native_name: 'English', flag: 'mw', country: 'Malawi' },
      { code: 'dv', locale: 'dv_MV', name: 'Dhivehi', native_name: 'Dhivehi', flag: 'mv', country: 'Maldiven', is_rtl: true },
      { code: 'fr', locale: 'fr_ML', name: 'French Mali', native_name: 'Francais', flag: 'ml', country: 'Mali' },
      { code: 'ar', locale: 'ar_MR', name: 'Arabic Mauritania', native_name: 'العربية', flag: 'mr', country: 'Mauritanie', is_rtl: true },
      { code: 'en', locale: 'en_MU', name: 'English Mauritius', native_name: 'English', flag: 'mu', country: 'Mauritius' },
      { code: 'en', locale: 'en_FM', name: 'English Micronesia', native_name: 'English', flag: 'fm', country: 'Micronesie' },
      { code: 'sr', locale: 'sr_ME', name: 'Montenegrin', native_name: 'Crnogorski', flag: 'me', country: 'Montenegro' },
      { code: 'en', locale: 'en_NA', name: 'English Namibia', native_name: 'English', flag: 'na', country: 'Namibie' },
      { code: 'en', locale: 'en_NR', name: 'English Nauru', native_name: 'English', flag: 'nr', country: 'Nauru' },
      { code: 'fr', locale: 'fr_NE', name: 'French Niger', native_name: 'Francais', flag: 'ne', country: 'Niger' },
      { code: 'en', locale: 'en_NG', name: 'English Nigeria', native_name: 'English', flag: 'ng', country: 'Nigeria' },
      { code: 'ko', locale: 'ko_KP', name: 'Korean North Korea', native_name: '한국어', flag: 'kp', country: 'Noord-Korea' },
      { code: 'en', locale: 'en_PW', name: 'English Palau', native_name: 'English', flag: 'pw', country: 'Palau' },
      { code: 'ar', locale: 'ar_PS', name: 'Arabic Palestine', native_name: 'العربية', flag: 'ps', country: 'Palestina', is_rtl: true },
      { code: 'rw', locale: 'rw_RW', name: 'Kinyarwanda', native_name: 'Kinyarwanda', flag: 'rw', country: 'Rwanda' },
      { code: 'en', locale: 'en_KN', name: 'English Saint Kitts and Nevis', native_name: 'English', flag: 'kn', country: 'Saint Kitts en Nevis' },
      { code: 'en', locale: 'en_LC', name: 'English Saint Lucia', native_name: 'English', flag: 'lc', country: 'Saint Lucia' },
      { code: 'en', locale: 'en_VC', name: 'English Saint Vincent and the Grenadines', native_name: 'English', flag: 'vc', country: 'Saint Vincent en de Grenadines' },
      { code: 'sm', locale: 'sm_WS', name: 'Samoan', native_name: 'Gagana Samoa', flag: 'ws', country: 'Samoa' },
      { code: 'it', locale: 'it_SM', name: 'Italian San Marino', native_name: 'Italiano', flag: 'sm', country: 'San Marino' },
      { code: 'pt', locale: 'pt_ST', name: 'Portuguese Sao Tome and Principe', native_name: 'Portugues', flag: 'st', country: 'Sao Tome en Principe' },
      { code: 'fr', locale: 'fr_SC', name: 'French Seychelles', native_name: 'Francais', flag: 'sc', country: 'Seychellen' },
      { code: 'en', locale: 'en_SL', name: 'English Sierra Leone', native_name: 'English', flag: 'sl', country: 'Sierra Leone' },
      { code: 'en', locale: 'en_SB', name: 'English Solomon Islands', native_name: 'English', flag: 'sb', country: 'Salomonseilanden' },
      { code: 'so', locale: 'so_SO', name: 'Somali', native_name: 'Soomaali', flag: 'so', country: 'Somalie' },
      { code: 'en', locale: 'en_SS', name: 'English South Sudan', native_name: 'English', flag: 'ss', country: 'Zuid-Soedan' },
      { code: 'tg', locale: 'tg_TJ', name: 'Tajik', native_name: 'Tojiki', flag: 'tj', country: 'Tadzjikistan' },
      { code: 'pt', locale: 'pt_TL', name: 'Portuguese Timor-Leste', native_name: 'Portugues', flag: 'tl', country: 'Oost-Timor' },
      { code: 'fr', locale: 'fr_TG', name: 'French Togo', native_name: 'Francais', flag: 'tg', country: 'Togo' },
      { code: 'to', locale: 'to_TO', name: 'Tongan', native_name: 'Lea fakatonga', flag: 'to', country: 'Tonga' },
      { code: 'en', locale: 'en_TT', name: 'English Trinidad and Tobago', native_name: 'English', flag: 'tt', country: 'Trinidad en Tobago' },
      { code: 'tk', locale: 'tk_TM', name: 'Turkmen', native_name: 'Turkmen', flag: 'tm', country: 'Turkmenistan' },
      { code: 'en', locale: 'en_TV', name: 'English Tuvalu', native_name: 'English', flag: 'tv', country: 'Tuvalu' },
      { code: 'en', locale: 'en_UG', name: 'English Uganda', native_name: 'English', flag: 'ug', country: 'Oeganda' },
      { code: 'fr', locale: 'fr_VU', name: 'French Vanuatu', native_name: 'Francais', flag: 'vu', country: 'Vanuatu' },
      { code: 'it', locale: 'it_VA', name: 'Italian Vatican City', native_name: 'Italiano', flag: 'va', country: 'Vaticaanstad' },
      { code: 'en', locale: 'en_ZM', name: 'English Zambia', native_name: 'English', flag: 'zm', country: 'Zambia' },
      { code: 'en', locale: 'en_ZW', name: 'English Zimbabwe', native_name: 'English', flag: 'zw', country: 'Zimbabwe' }
    ];
    var languagePresets = languagePresetList.reduce(function (map, item) {
      if (!map[item.code]) {
        map[item.code] = item;
      }
      map[item.locale.toLowerCase()] = item;
      map[item.flag.toLowerCase()] = item;
      return map;
    }, {});

    useEffect(function () {
      if (p.dashboard && p.dashboard.settings && !setSt[0]) {
        setSt[1](p.dashboard.settings);
      }
    }, [p.dashboard]);

    function defaultLanguageForm(code) {
      var cleanCode = String(code || '').toLowerCase().trim();
      var preset = languagePresets[cleanCode] || {};
      return {
        code: cleanCode,
        locale: preset.locale || '',
        name: preset.name || '',
        native_name: preset.native_name || '',
        flag: preset.flag || cleanCode,
        is_active: true,
        is_default: false,
        is_rtl: false
      };
    }

    function openLanguageModal() {
      form[1](defaultLanguageForm(''));
      modal[1](true);
    }

    function presetText(preset) {
      return [preset.country, preset.name, preset.native_name, preset.code, preset.locale, preset.flag].join(' ').toLowerCase();
    }

    function matchingLanguagePresets(query) {
      var needle = String(query || '').toLowerCase().trim();
      if (!needle) {
        return languagePresetList.slice(0, 24);
      }
      return languagePresetList.filter(function (preset) {
        return presetText(preset).indexOf(needle) !== -1;
      }).slice(0, 24);
    }

    function applyLanguagePreset(preset) {
      if (!preset) {
        return;
      }
      form[1](Object.assign({}, form[0], {
        code: preset.code,
        locale: preset.locale,
        flag: preset.flag,
        name: form[0].name || preset.name,
        native_name: form[0].native_name || preset.native_name,
        is_rtl: typeof preset.is_rtl === 'boolean' ? preset.is_rtl : form[0].is_rtl
      }));
      picker[1]({ field: '', query: '' });
    }

    function languagePresetOptions() {
      return [{ label: __('Kies een taal...', 'webactueel-translate-language-dropdowns'), value: '' }].concat(languagePresetList.map(function (preset) {
        return {
          label: (preset.country || preset.native_name || preset.name) + ' - ' + preset.native_name + ' (' + preset.locale + ')',
          value: preset.locale
        };
      }));
    }

    function applyPresetByLocale(locale) {
      var key = String(locale || '').toLowerCase();
      if (!key) { return; }
      var preset = languagePresetList.find(function (item) {
        return String(item.locale || '').toLowerCase() === key;
      });
      if (preset) {
        applyLanguagePreset(preset);
      }
    }

    function setLanguageField(key, value) {
      var next = Object.assign({}, form[0]);
      next[key] = value;
      if (key === 'code') {
        var cleanCode = String(value || '').toLowerCase().trim();
        var preset = languagePresets[cleanCode] || {};
        next.code = cleanCode;
        if (!next.locale) {
          next.locale = preset.locale || '';
        }
        if (!next.name) {
          next.name = preset.name || '';
        }
        if (!next.native_name) {
          next.native_name = preset.native_name || '';
        }
        if (!next.flag) {
          next.flag = preset.flag || cleanCode;
        }
      }
      form[1](next);
    }

    function SearchableLanguagePicker(props) {
      var value = String(form[0][props.field] || '');
      var active = picker[0].field === props.field;
      var query = active ? picker[0].query : value;
      var matches = matchingLanguagePresets(query);
      function handleChange(v) {
        picker[1]({ field: props.field, query: v });
        setLanguageField(props.field, v);
      }
      function clearValue() {
        setLanguageField(props.field, '');
        picker[1]({ field: props.field, query: '' });
      }
      function choosePreset(preset) {
        applyLanguagePreset(preset);
        picker[1]({ field: '', query: '' });
      }
      return el('div', { className: 'wat-language-picker' },
        el('label', { className: 'wat-language-picker__label' }, props.label),
        el('div', { className: 'wat-language-picker__input-wrap' },
          el('input', {
            type: 'text',
            className: 'wat-language-picker__input',
            value: active ? query : value,
            placeholder: props.placeholder || __('Zoek land, taal, code of locale...', 'webactueel-translate-language-dropdowns'),
            autoComplete: 'off',
            onFocus: function () { picker[1]({ field: props.field, query: value }); },
            onChange: function (e) { handleChange(e.target.value); },
            onKeyDown: function (e) {
              if (e.key === 'Escape') {
                picker[1]({ field: '', query: '' });
              }
              if (e.key === 'Enter' && active && matches.length === 1) {
                e.preventDefault();
                choosePreset(matches[0]);
              }
            },
            onBlur: function () { window.setTimeout(function () { picker[1]({ field: '', query: '' }); }, 180); }
          }),
          value ? el('button', {
            type: 'button',
            className: 'wat-language-picker__clear',
            onMouseDown: function (e) { e.preventDefault(); clearValue(); },
            'aria-label': props.label + ' ' + __('wissen', 'webactueel-translate-language-dropdowns'),
            title: __('Wissen', 'webactueel-translate-language-dropdowns')
          }, '×') : null
        ),
        active ? el('div', { className: 'wat-language-picker__menu' },
          matches.length ? matches.map(function (preset) {
            return el('button', {
              type: 'button',
              key: props.field + '-' + preset.locale,
              className: 'wat-language-picker__option',
              onMouseDown: function (e) { e.preventDefault(); choosePreset(preset); }
            },
              el('span', { className: 'wat-language-picker__country' }, preset.country),
              el('span', { className: 'wat-language-picker__meta' }, preset.code + ' · ' + preset.locale + ' · ' + preset.flag)
            );
          }) : el('div', { className: 'wat-language-picker__empty' }, __('Geen land of taal gevonden', 'webactueel-translate-language-dropdowns'))
        ) : null
      );
    }

    function setSwitcherOption(k, v) {
      var n = Object.assign({}, setSt[0]);
      n[k] = v;
      setSt[1](n);
    }

    function saveSettings() {
      api('/settings', { method: 'POST', data: setSt[0] })
        .then(function (r) {
          setSt[1](r);
          p.toast.add(__('Taalkiezer opgeslagen.', 'webactueel-translate-language-dropdowns'));
        })
        .catch(function (e) {
          p.toast.add(e.message || __('Opslaan mislukt.', 'webactueel-translate-language-dropdowns'), 'error');
        });
    }

    function saveLang() {
      var payload = Object.assign({}, form[0], {
        code: String(form[0].code || '').toLowerCase().trim(),
        locale: String(form[0].locale || '').trim().replace(/-/g, '_'),
        native_name: String(form[0].native_name || '').trim(),
        name: String(form[0].name || '').trim(),
        flag: String(form[0].flag || form[0].code || '').toLowerCase().trim()
      });
      if (!payload.code || !payload.locale || !payload.native_name) {
        p.toast.add(__('Code, locale en native naam zijn verplicht.', 'webactueel-translate-language-dropdowns'), 'error');
        return;
      }
      api('/languages', { method: 'POST', data: payload })
        .then(function (response) {
          if (!response || !response.saved) {
            throw new Error(__('Taal opslaan mislukt.', 'webactueel-translate-language-dropdowns'));
          }
          p.toast.add(__('Taal toegevoegd en opgeslagen.', 'webactueel-translate-language-dropdowns'));
          modal[1](false);
          form[1](defaultLanguageForm(''));
          if (p.refresh) p.refresh();
        })
        .catch(function (e) {
          p.toast.add(e.message || __('Taal opslaan mislukt.', 'webactueel-translate-language-dropdowns'), 'error');
        });
    }

    function copyShortcode() {
      if (window.navigator.clipboard) {
        window.navigator.clipboard.writeText(snippetMode[0] === 'theme' ? "echo do_shortcode('[webactueel_translate_switcher]');" : '[webactueel_translate_switcher]');
      }
      p.toast.add((snippetMode[0] === 'theme' ? __('Themacode', 'webactueel-translate-language-dropdowns') : __('Shortcode', 'webactueel-translate-language-dropdowns')) + ' gekopieerd.');
    }

    var rows = (langs.data || []).map(function (l) {
      return el(
        'tr',
        { key: l.id },
        el('td', null, safe(l.native_name)),
        el('td', null, el('code', null, safe(l.code))),
        el('td', null, bool(l.is_default) ? el(Badge, { tone: 'success' }, __('Standaard', 'webactueel-translate-language-dropdowns')) : bool(l.is_active) ? el(Badge, { tone: 'success' }, __('Actief', 'webactueel-translate-language-dropdowns')) : el(Badge, { tone: 'warning' }, 'Uit')),
        el('td', null,
          el(c.Button, {
            variant: 'tertiary',
            isDestructive: true,
            disabled: bool(l.is_default),
            onClick: function () {
              if (window.confirm(__('Deze taal verwijderen?', 'webactueel-translate-language-dropdowns'))) {
                api('/languages/' + l.id + '/delete', { method: 'POST' })
                  .then(function () {
                    p.toast.add(__('Taal verwijderd.', 'webactueel-translate-language-dropdowns'));
                    p.refresh();
                  })
                  .catch(function (e) {
                    p.toast.add(e.message || __('Taal verwijderen mislukt.', 'webactueel-translate-language-dropdowns'), 'error');
                  });
              }
            }
          }, __('Verwijderen', 'webactueel-translate-language-dropdowns'))
        )
      );
    });

    return el(
      'div',
      { className: 'wat-page' },
      modal[0] ? el('div', { className: 'wat-custom-modal-overlay', role: 'presentation' },
        el('div', { className: 'wat-custom-modal wat-custom-modal--language', role: 'dialog', tabIndex: -1, 'aria-modal': 'true', 'aria-labelledby': 'wat-language-modal-title' },
          el('div', { className: 'wat-custom-modal-header' },
            el('h2', { id: 'wat-language-modal-title' }, __('Taal toevoegen', 'webactueel-translate-language-dropdowns')),
            el('button', { type: 'button', className: 'wat-custom-modal-close', 'aria-label': __('Sluiten', 'webactueel-translate-language-dropdowns'), onClick: function () { modal[1](false); } }, '×')
          ),
          el('div', { className: 'wat-custom-modal-body' },
            el('div', { className: 'wat-modal-field wat-modal-field--full wat-language-preset-fallback' },
              el(c.SelectControl, {
                label: __('Snelle taalkeuze', 'webactueel-translate-language-dropdowns'),
                value: form[0].locale || '',
                options: languagePresetOptions(),
                onChange: applyPresetByLocale,
                help: __('Werkt als fallback als de zoekvelden niet direct selecteren.', 'webactueel-translate-language-dropdowns')
              })
            ),
            el('div', { className: 'wat-modal-grid' },
              el('div', { className: 'wat-modal-field' }, el(SearchableLanguagePicker, { label: __('Taalcode', 'webactueel-translate-language-dropdowns'), field: 'code', placeholder: __('Zoek bijvoorbeeld Nederland, Duitsland of en...', 'webactueel-translate-language-dropdowns') })),
              el('div', { className: 'wat-modal-field' }, el(SearchableLanguagePicker, { label: __('Locale', 'webactueel-translate-language-dropdowns'), field: 'locale', placeholder: __('Zoek land, taal of locale...', 'webactueel-translate-language-dropdowns') })),
              el('div', { className: 'wat-modal-field' }, el(c.TextControl, { label: __('Naam', 'webactueel-translate-language-dropdowns'), value: form[0].name, onChange: function (v) { setLanguageField('name', v); } })),
              el('div', { className: 'wat-modal-field' }, el(c.TextControl, { label: __('Native naam', 'webactueel-translate-language-dropdowns'), value: form[0].native_name, onChange: function (v) { setLanguageField('native_name', v); } })),
              el('div', { className: 'wat-modal-field' }, el(SearchableLanguagePicker, { label: __('Vlag/code', 'webactueel-translate-language-dropdowns'), field: 'flag', placeholder: __('Zoek land, taal of vlagcode...', 'webactueel-translate-language-dropdowns') })),
              el('div', { className: 'wat-modal-field wat-modal-toggles' },
                el(c.ToggleControl, { label: __('Actief', 'webactueel-translate-language-dropdowns'), checked: bool(form[0].is_active), onChange: function (v) { setLanguageField('is_active', v); } }),
                el(c.ToggleControl, { label: __('Standaard taal', 'webactueel-translate-language-dropdowns'), checked: bool(form[0].is_default), onChange: function (v) { setLanguageField('is_default', v); } }),
                el(c.ToggleControl, { label: __('RTL taal', 'webactueel-translate-language-dropdowns'), checked: bool(form[0].is_rtl), onChange: function (v) { setLanguageField('is_rtl', v); } })
              )
            )
          ),
          el('div', { className: 'wat-custom-modal-footer' },
            el(c.Button, { variant: 'secondary', onClick: function () { modal[1](false); } }, __('Annuleren', 'webactueel-translate-language-dropdowns')),
            el(c.Button, { variant: 'primary', onClick: saveLang }, __('Taal opslaan', 'webactueel-translate-language-dropdowns'))
          )
        )
      ) : null,
      !p.embedded ? el('div', { className: 'wat-page-header' },
        el('div', null,
          el('h2', null, __('Talen & taalkiezer', 'webactueel-translate-language-dropdowns')),
          el('p', null, __('Beheer je talen en plaats de taalkiezer op je website.', 'webactueel-translate-language-dropdowns'))
        )
      ) : null,
      el('div', { className: 'wat-two-column wat-two-column--languages' },
        el('div', { className: 'wat-column' },
          el(Card, { title: __('Talen', 'webactueel-translate-language-dropdowns'), className: 'wat-languages-card' },
            langs.loading ? el(c.Spinner) : el(Table, {
              head: [__('Taal', 'webactueel-translate-language-dropdowns'), __('Code', 'webactueel-translate-language-dropdowns'), __('Status', 'webactueel-translate-language-dropdowns'), __('Actie', 'webactueel-translate-language-dropdowns')],
              rows: rows,
              emptyTitle: __('Voeg je eerste taal toe', 'webactueel-translate-language-dropdowns'),
              emptyText: __('Kies de talen voor je website.', 'webactueel-translate-language-dropdowns')
            }),
            el('div', { className: 'wat-card-actions wat-card-actions--end' },
              el(c.Button, { variant: 'primary', onClick: openLanguageModal }, __('Taal toevoegen', 'webactueel-translate-language-dropdowns'))
            )
          ),
          el(Card, { title: __('Taalkiezer plaatsen', 'webactueel-translate-language-dropdowns') },
            el('div', { className: 'wat-segmented-control', role: 'group', 'aria-label': __('Code type', 'webactueel-translate-language-dropdowns') },
              el(c.Button, { variant: snippetMode[0] === 'shortcode' ? 'primary' : 'secondary', onClick: function () { snippetMode[1]('shortcode'); } }, __('Shortcode', 'webactueel-translate-language-dropdowns')),
              el(c.Button, { variant: snippetMode[0] === 'theme' ? 'primary' : 'secondary', onClick: function () { snippetMode[1]('theme'); } }, __('Thema/PHP', 'webactueel-translate-language-dropdowns'))
            ),
            el('div', { className: 'wat-code-block' }, snippetMode[0] === 'theme' ? "echo do_shortcode('[webactueel_translate_switcher]');" : '[webactueel_translate_switcher]'),
            el('div', { className: 'wat-copy-actions' },
              el(c.Button, { variant: 'secondary', className: 'wat-button-auto wat-copy-shortcode-button wat-details-button', onClick: copyShortcode }, snippetMode[0] === 'theme' ? __('Themacode kopiëren', 'webactueel-translate-language-dropdowns') : __('Shortcode kopiëren', 'webactueel-translate-language-dropdowns'))
            )
          )
        ),
        setSt[0] ? el('div', { className: 'wat-column wat-switcher-column wat-column--fill' },
          el(Card, { title: __('Taalkiezer uiterlijk', 'webactueel-translate-language-dropdowns'), className: 'wat-switcher-card wat-fill-card' },
            el(Row, null, el(c.SelectControl, {
              label: __('Layout', 'webactueel-translate-language-dropdowns'),
              value: setSt[0].switcher_layout,
              options: [
                { label: __('Dropdown', 'webactueel-translate-language-dropdowns'), value: 'dropdown' },
                { label: __('Knoppen', 'webactueel-translate-language-dropdowns'), value: 'inline' },
                { label: __('Vlaggen + naam', 'webactueel-translate-language-dropdowns'), value: 'flags_name' },
                { label: __('Alleen vlaggen', 'webactueel-translate-language-dropdowns'), value: 'flags' },
                { label: __('Taalcodes', 'webactueel-translate-language-dropdowns'), value: 'code' },
                { label: __('Vlag + code', 'webactueel-translate-language-dropdowns'), value: 'flag_code' },
                { label: __('Naam + code', 'webactueel-translate-language-dropdowns'), value: 'name_code' },
                { label: __('Vlag + naam + code', 'webactueel-translate-language-dropdowns'), value: 'flags_name_code' }
              ],
              onChange: function (v) { setSwitcherOption('switcher_layout', v); }
            })),
            el(Row, null, el(c.SelectControl, {
              label: __('Stijl', 'webactueel-translate-language-dropdowns'),
              value: setSt[0].switcher_style,
              options: [
                { label: __('Licht', 'webactueel-translate-language-dropdowns'), value: 'light' },
                { label: __('Donker', 'webactueel-translate-language-dropdowns'), value: 'dark' },
                { label: __('Compact', 'webactueel-translate-language-dropdowns'), value: 'compact' },
                { label: __('Omlijnd', 'webactueel-translate-language-dropdowns'), value: 'outline' },
                { label: __('Minimaal', 'webactueel-translate-language-dropdowns'), value: 'minimal' }
              ],
              onChange: function (v) { setSwitcherOption('switcher_style', v); }
            })),
            el(Row, null, el(c.ToggleControl, {
              label: __('Floating taalkiezer tonen', 'webactueel-translate-language-dropdowns'),
              checked: bool(setSt[0].switcher_floating),
              onChange: function (v) { setSwitcherOption('switcher_floating', v); }
            })),
            bool(setSt[0].switcher_floating) ? el(Row, null, el(c.SelectControl, {
              label: __('Positie', 'webactueel-translate-language-dropdowns'),
              value: setSt[0].switcher_position,
              options: [
                { label: __('Rechtsonder', 'webactueel-translate-language-dropdowns'), value: 'bottom-right' },
                { label: __('Linksonder', 'webactueel-translate-language-dropdowns'), value: 'bottom-left' },
                { label: __('Rechtsboven', 'webactueel-translate-language-dropdowns'), value: 'top-right' },
                { label: __('Linksboven', 'webactueel-translate-language-dropdowns'), value: 'top-left' }
              ],
              onChange: function (v) { setSwitcherOption('switcher_position', v); }
            })) : null,
            el(SwitcherPreview, {
              layout: setSt[0].switcher_layout,
              style: setSt[0].switcher_style,
              floating: setSt[0].switcher_floating,
              position: setSt[0].switcher_position
            }),
            el('div', { className: 'wat-card-actions' },
              el(c.Button, { variant: 'primary', className: 'wat-button-auto', onClick: saveSettings }, __('Taalkiezer opslaan', 'webactueel-translate-language-dropdowns'))
            )
          ),
        ) : el(c.Spinner)
      )
    );
  }


  function GlossaryManager(p) {
    var language = useState('');
    var items = useFetch('/glossary?language=' + encodeURIComponent(language[0] || ''), [p.tick, language[0]]);
    var langs = useFetch('/languages', [p.tick]);
    var form = useState({ source_term: '', target_term: '', language_code: '', case_sensitive: false });
    var busy = useState(false);

    function activeLanguages() {
      return ((langs.data || []).filter(function (l) { return bool(l.is_active); }).length ? (langs.data || []).filter(function (l) { return bool(l.is_active); }) : (langs.data || []));
    }

    function languageOptions() {
      var list = activeLanguages();
      return [{ label: __('Kies taal', 'webactueel-translate-language-dropdowns'), value: '' }].concat(list.map(function (l) { return { label: (l.native_name || l.name || l.code) + ' (' + l.code + ')', value: l.code }; }));
    }

    useEffect(function () {
      var list = activeLanguages().filter(function (l) { return !bool(l.is_default); });
      if (!form[0].language_code && list[0] && list[0].code) {
        form[1](Object.assign({}, form[0], { language_code: list[0].code }));
        language[1](list[0].code);
      }
    }, [langs.data]);

    function setField(k, v) {
      var next = Object.assign({}, form[0]);
      next[k] = v;
      form[1](next);
      if (k === 'language_code') { language[1](v || ''); }
    }

    function saveGlossary() {
      if (!form[0].source_term || !form[0].target_term || !form[0].language_code) {
        p.toast.add(__('Vul bronterm, vertaling en taal in.', 'webactueel-translate-language-dropdowns'), 'warning');
        return;
      }
      busy[1](true);
      api('/glossary', { method: 'POST', data: form[0] })
        .then(function () {
          p.toast.add(__('Woordenlijstterm opgeslagen.', 'webactueel-translate-language-dropdowns'));
          form[1]({ source_term: '', target_term: '', language_code: form[0].language_code, case_sensitive: false });
          items.refresh();
        })
        .catch(function (e) { p.toast.add(e.message || __('Woordenlijst opslaan mislukt.', 'webactueel-translate-language-dropdowns'), 'error'); })
        .finally(function () { busy[1](false); });
    }

    function deleteGlossary(id) {
      if (!window.confirm(__('Deze woordenlijstterm verwijderen?', 'webactueel-translate-language-dropdowns'))) { return; }
      api('/glossary/' + id + '/delete', { method: 'POST' })
        .then(function () { p.toast.add(__('Woordenlijstterm verwijderd.', 'webactueel-translate-language-dropdowns')); p.refresh(); })
        .catch(function (e) { p.toast.add(e.message || __('Verwijderen mislukt.', 'webactueel-translate-language-dropdowns'), 'error'); });
    }

    var rows = (items.data || []).map(function (g) {
      return el('tr', { key: g.id },
        el('td', null, g.source_term),
        el('td', null, g.target_term),
        el('td', null, g.language_code),
        el('td', null, el(c.Button, { variant: 'secondary', isDestructive: true, onClick: function () { deleteGlossary(g.id); } }, __('Verwijderen', 'webactueel-translate-language-dropdowns')))
      );
    });

    var glossaryCount = rows.length;
    var emptyGlossary = !items.loading && !glossaryCount;

    return el('div', { className: 'wat-glossary-layout' },
      el(Card, { className: 'wat-glossary-compose-card', title: __('Woordenlijstterm toevoegen', 'webactueel-translate-language-dropdowns') },
        el('div', { className: 'wat-glossary-form wat-glossary-form--modern' },
          el(c.TextControl, { label: __('Originele term', 'webactueel-translate-language-dropdowns'), value: form[0].source_term, onChange: function (v) { setField('source_term', v); }, placeholder: __('Bijv. offerte', 'webactueel-translate-language-dropdowns') }),
          el(c.TextControl, { label: __('Voorkeursvertaling', 'webactueel-translate-language-dropdowns'), value: form[0].target_term, onChange: function (v) { setField('target_term', v); }, placeholder: __('Bijv. quote', 'webactueel-translate-language-dropdowns') }),
          el(c.SelectControl, { label: __('Taal', 'webactueel-translate-language-dropdowns'), value: form[0].language_code, options: languageOptions(), onChange: function (v) { setField('language_code', v); } }),
          el('div', { className: 'wat-toggle-card' },
            el(c.ToggleControl, { label: __('Hoofdlettergevoelig', 'webactueel-translate-language-dropdowns'), checked: bool(form[0].case_sensitive), onChange: function (v) { setField('case_sensitive', v); } }),
            el('p', null, __('Aan betekent dat “Webshop” en “webshop” apart behandeld worden.', 'webactueel-translate-language-dropdowns'))
          ),
          el('div', { className: 'wat-card-actions wat-card-actions--end' },
            el(c.Button, { variant: 'primary', isBusy: busy[0], disabled: busy[0], className: 'wat-button-auto', onClick: saveGlossary }, busy[0] ? __('Opslaan…', 'webactueel-translate-language-dropdowns') : __('Term opslaan', 'webactueel-translate-language-dropdowns'))
          )
        )
      ),
      el(Card, { className: 'wat-glossary-list-card', title: __('Opgeslagen termen', 'webactueel-translate-language-dropdowns'), desc: glossaryCount ? glossaryCount + __(' woordenlijstterm(en) gevonden.', 'webactueel-translate-language-dropdowns') : __('Nog geen termen voor deze selectie.', 'webactueel-translate-language-dropdowns') },
        el('div', { className: 'wat-glossary-toolbar' },
          el('label', { className: 'wat-compact-field' }, el('span', { className: 'wat-field-label' }, __('Filter op taal', 'webactueel-translate-language-dropdowns')), el('select', { value: language[0], onChange: function (e) { language[1](e.target.value); } }, [{ label: __('Alle talen', 'webactueel-translate-language-dropdowns'), value: '' }].concat(languageOptions().slice(1)).map(function (option) { return el('option', { key: option.value || 'all', value: option.value }, option.label); })))
        ),
        items.loading ? el(c.Spinner) : emptyGlossary
          ? el(Empty, { title: __('Nog geen woordenlijsttermen', 'webactueel-translate-language-dropdowns'), text: __('Voeg links je eerste term toe om vertalingen consistenter te maken.', 'webactueel-translate-language-dropdowns') })
          : el(Table, { head: [__('Origineel', 'webactueel-translate-language-dropdowns'), __('Vertaling', 'webactueel-translate-language-dropdowns'), __('Taal', 'webactueel-translate-language-dropdowns'), __('Actie', 'webactueel-translate-language-dropdowns')], rows: rows, emptyTitle: __('Nog geen woordenlijsttermen', 'webactueel-translate-language-dropdowns'), emptyText: __('Voeg vaste termen toe die consequent vertaald moeten worden.', 'webactueel-translate-language-dropdowns') })
      )
    );
  }

  function Settings(p) {
    var vals = useState(null);
    var dirty = useState(false);
    var logsOpen = useState(false);
    var compatOpen = useState(false);
    var aiTestText = useState(__('Welkom op onze website', 'webactueel-translate-language-dropdowns'));
    var aiTargetLanguage = useState('en');
    var aiTestResult = useState(null);
    var aiTesting = useState(false);

    useEffect(function () {
      if (p.dashboard && p.dashboard.settings && !vals[0]) {
        vals[1](p.dashboard.settings);
      }
    }, [p.dashboard]);

    if (!vals[0]) {
      return el(c.Spinner);
    }

    function setSettingsOption(k, v) {
      var n = Object.assign({}, vals[0]);
      n[k] = v;
      vals[1](n);
      dirty[1](true);
    }

    function save() {
      api('/settings', { method: 'POST', data: vals[0] })
        .then(function (r) {
          vals[1](r);
          dirty[1](false);
          p.toast.add(__('Instellingen opgeslagen.', 'webactueel-translate-language-dropdowns'));
        })
        .catch(function (e) {
          p.toast.add(e.message || __('Opslaan mislukt.', 'webactueel-translate-language-dropdowns'), 'error');
        });
    }

    function clearCache() {
      api('/cache/clear', { method: 'POST' }).then(function () {
        p.toast.add(__('Cache geleegd.', 'webactueel-translate-language-dropdowns'));
      }).catch(function (e) {
        p.toast.add(e.message || __('Cache legen mislukt.', 'webactueel-translate-language-dropdowns'), 'error');
      });
    }

    function clearLogs() {
      if (!window.confirm(__('Alle logs wissen?', 'webactueel-translate-language-dropdowns'))) {
        return;
      }
      api('/logs/clear', { method: 'POST' }).then(function () {
        p.toast.add(__('Logs gewist.', 'webactueel-translate-language-dropdowns'));
        p.refresh();
      }).catch(function (e) {
        p.toast.add(e.message || __('Logs wissen mislukt.', 'webactueel-translate-language-dropdowns'), 'error');
      });
    }

    function runAiTest() {
      aiTesting[1](true);
      aiTestResult[1](null);
      if (!bool(vals[0].ai_enabled)) {
        aiTestResult[1]({ error: __('Schakel AI-vertaling eerst in en sla de instellingen op.', 'webactueel-translate-language-dropdowns') });
        p.toast.add(__('Schakel AI-vertaling eerst in en sla de instellingen op.', 'webactueel-translate-language-dropdowns'), 'error');
        aiTesting[1](false);
        return;
      }
      var saveFirst = dirty[0]
        ? api('/settings', { method: 'POST', data: vals[0] }).then(function (r) { vals[1](r); dirty[1](false); return r; })
        : Promise.resolve(vals[0]);
      saveFirst.then(function () {
        return api('/automation/translate', {
          method: 'POST',
          data: {
            text: aiTestText[0],
            source_language: '',
            target_language: aiTargetLanguage[0]
          }
        });
      }).then(function (r) {
        aiTestResult[1](r);
        p.toast.add(__('AI verbonden.', 'webactueel-translate-language-dropdowns'));
      }).catch(function (e) {
        aiTestResult[1]({ error: e.message || __('AI verbinden mislukt.', 'webactueel-translate-language-dropdowns') });
        p.toast.add(e.message || __('AI verbinden mislukt.', 'webactueel-translate-language-dropdowns'), 'error');
      }).finally(function () {
        aiTesting[1](false);
      });
    }

    var compat = p.dashboard.compatibility || [];
    var conflict = compat.some(function (x) { return x.type === 'multilingual'; });
    var frontendOn = bool(vals[0].frontend_enabled);
    var aiOn = bool(vals[0].ai_enabled);
    var hreflangOn = bool(vals[0].hreflang_enabled);
    var aiProvider = vals[0].ai_provider || 'openai';
    var aiModelOptions = aiProvider === 'deepl'
      ? [{ label: 'DeepL API', value: 'deepl-api' }]
      : aiProvider === 'openai_compatible'
        ? [{ label: 'gpt-4o-mini', value: 'gpt-4o-mini' }, { label: 'gpt-4.1-mini', value: 'gpt-4.1-mini' }, { label: 'Llama 3.1 70B', value: 'llama-3.1-70b-versatile' }, { label: 'Mistral Large', value: 'mistral-large-latest' }]
        : [{ label: 'gpt-4o-mini', value: 'gpt-4o-mini' }, { label: 'gpt-4o', value: 'gpt-4o' }, { label: 'gpt-4.1-mini', value: 'gpt-4.1-mini' }, { label: 'gpt-4.1', value: 'gpt-4.1' }, { label: 'gpt-4.1-nano', value: 'gpt-4.1-nano' }];

    return el(
      'div',
      { className: 'wat-page wat-settings-screen wat-settings-screen--loose' },
      el('div', { className: 'wat-settings-languages-section wat-settings-languages-section--flat' },
        el(Languages, { dashboard: p.dashboard || {}, toast: p.toast, refresh: p.refresh, tick: p.tick, embedded: true })
      ),
      el('div', { className: 'wat-settings-grid' },
        el(Card, { title: __('Websitevertaling', 'webactueel-translate-language-dropdowns'), className: 'wat-fill-card' },
          el(Row, null, el(c.ToggleControl, { label: __('Websitevertaling inschakelen', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].frontend_enabled), onChange: function (v) { setSettingsOption('frontend_enabled', v); } })),
          el(Row, null, el(c.ToggleControl, { label: __('Veilige compatibiliteitsmodus', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].safe_mode), disabled: !frontendOn, onChange: function (v) { setSettingsOption('safe_mode', v); } })),
          el(Row, null, el(c.ToggleControl, { label: __('Browser automatisch naar voorkeurstaal sturen', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].browser_redirect), disabled: !frontendOn, onChange: function (v) { setSettingsOption('browser_redirect', v); } })),
          el(Row, null, el(c.ToggleControl, { label: __('Taalkeuze onthouden met cookie', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].remember_language), disabled: !frontendOn, onChange: function (v) { setSettingsOption('remember_language', v); } })),
          el(Row, null, el(c.ToggleControl, { label: __('Media-altteksten en titels vertalen', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].media_translation_enabled), disabled: !frontendOn, onChange: function (v) { setSettingsOption('media_translation_enabled', v); } })),
          el(Row, null, el(c.ToggleControl, { label: __('WooCommerce productvelden diep vertalen', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].woocommerce_deep_translation_enabled), disabled: !frontendOn, onChange: function (v) { setSettingsOption('woocommerce_deep_translation_enabled', v); } })),
          el('p', { className: 'wat-help' }, frontendOn ? __('Veilige modus voorkomt vertaling op gevoelige flows zoals checkout, formulieren, REST en AJAX.', 'webactueel-translate-language-dropdowns') : __('Websitevertaling staat uit; afhankelijke frontend-opties zijn tijdelijk uitgeschakeld.', 'webactueel-translate-language-dropdowns'))
        ),
        el(Card, { title: __('SEO & compatibiliteit', 'webactueel-translate-language-dropdowns'), className: 'wat-fill-card' },
          conflict ? el(c.Notice, { status: 'warning', isDismissible: false }, __('Er is een andere vertaalplugin actief. Conflictbescherming staat aan.', 'webactueel-translate-language-dropdowns')) : el('p', { className: 'wat-ok-line' }, __('Geen conflicten gevonden.', 'webactueel-translate-language-dropdowns')),
          el('div', { className: 'wat-details-button-row' }, el(c.Button, { variant: 'secondary', className: 'wat-details-button', onClick: function () { compatOpen[1](!compatOpen[0]); } }, compatOpen[0] ? __('Details verbergen', 'webactueel-translate-language-dropdowns') : __('Details bekijken', 'webactueel-translate-language-dropdowns'))),
          compatOpen[0] ? el('div', { className: 'wat-detail-box' },
            el(Row, null, el(c.ToggleControl, { label: __('Hreflang inschakelen', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].hreflang_enabled), onChange: function (v) { setSettingsOption('hreflang_enabled', v); } })),
            el(Row, null, el(c.ToggleControl, { label: __('x-default inschakelen', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].x_default_enabled), disabled: !hreflangOn, onChange: function (v) { setSettingsOption('x_default_enabled', v); } })),
            el(Row, null, el(c.ToggleControl, { label: __('Canonicals per taal inschakelen', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].canonical_enabled), disabled: !hreflangOn, onChange: function (v) { setSettingsOption('canonical_enabled', v); } })),
            el(Row, null, el(c.ToggleControl, { label: __('Meertalige XML-sitemap inschakelen', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].multilingual_sitemap_enabled), disabled: !hreflangOn, onChange: function (v) { setSettingsOption('multilingual_sitemap_enabled', v); } })),
            el(c.TextareaControl, { label: __('Taal-domeinen', 'webactueel-translate-language-dropdowns'), help: __('Een per regel, formaat: en|https://example.com', 'webactueel-translate-language-dropdowns'), rows: 3, value: vals[0].language_domains || '', disabled: !hreflangOn, onChange: function (v) { setSettingsOption('language_domains', v); } }),
            el('p', { className: 'wat-help' }, __('Sitemap URL: /?wat_language_sitemap=1. Test deze op staging voordat je hem in Search Console indient.', 'webactueel-translate-language-dropdowns')),
            el('ul', { className: 'wat-simple-list' }, compat.slice(0, 8).map(function (x) {
              return el('li', { key: x.name },
                el('span', null, x.name),
                el(Badge, { tone: x.type === 'multilingual' ? 'warning' : 'success' }, x.type === 'multilingual' ? __('Aandacht', 'webactueel-translate-language-dropdowns') : __('Veilig', 'webactueel-translate-language-dropdowns'))
              );
            }))
          ) : null
        ),
        el(Card, { title: __('Cache', 'webactueel-translate-language-dropdowns'), className: 'wat-fill-card' },
          el(Row, null, el(c.ToggleControl, { label: __('Snelle vertaalcache gebruiken', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].cache_enabled), disabled: !frontendOn, onChange: function (v) { setSettingsOption('cache_enabled', v); } })),
          el('div', { className: 'wat-card-actions' },
            el(c.Button, { variant: 'primary', className: 'wat-button-auto', onClick: clearCache }, __('Cache legen', 'webactueel-translate-language-dropdowns'))
          )
        ),
        el(Card, { title: __('Probleemoplossing', 'webactueel-translate-language-dropdowns'), className: 'wat-fill-card' },
          el(Row, null, el(c.ToggleControl, { label: __('Technische logs bewaren', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].debug_logging), onChange: function (v) { setSettingsOption('debug_logging', v); } })),
          el('div', { className: 'wat-inline-actions' },
            el(c.Button, { variant: 'secondary', onClick: function () { logsOpen[1](!logsOpen[0]); } }, logsOpen[0] ? __('Logs verbergen', 'webactueel-translate-language-dropdowns') : __('Logs bekijken', 'webactueel-translate-language-dropdowns')),
            el(c.Button, { variant: 'secondary', isDestructive: true, onClick: clearLogs }, __('Logs wissen', 'webactueel-translate-language-dropdowns'))
          ),
          logsOpen[0] ? (
            p.logs && p.logs.length ? el('ul', { className: 'wat-simple-list' }, p.logs.slice(0, 10).map(function (l) {
              return el('li', { key: l.id }, el('span', null, l.message), el('span', { className: 'wat-muted' }, l.level));
            })) : el(Empty, { title: __('Nog geen logs', 'webactueel-translate-language-dropdowns'), text: __('Logs verschijnen hier na scans, imports of waarschuwingen.', 'webactueel-translate-language-dropdowns') })
          ) : null
        ),
        el(Card, { title: __('Uitsluitingen', 'webactueel-translate-language-dropdowns'), className: 'wat-fill-card wat-advanced-split-card' },
          el(c.TextareaControl, { label: __('Niet tonen op URL patronen', 'webactueel-translate-language-dropdowns'), rows: 4, value: vals[0].exclude_paths || '', onChange: function (v) { setSettingsOption('exclude_paths', v); } }),
          el(c.TextareaControl, { label: __('Niet vertalen CSS selectors', 'webactueel-translate-language-dropdowns'), rows: 4, value: vals[0].exclude_selectors || '', onChange: function (v) { setSettingsOption('exclude_selectors', v); } })
        ),
        el(Card, { title: __('AI-assistent', 'webactueel-translate-language-dropdowns'), className: 'wat-fill-card wat-ai-settings-card' },
          el(Row, null, el(c.ToggleControl, { label: __('AI-vertaling inschakelen', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].ai_enabled), onChange: function (v) { setSettingsOption('ai_enabled', v); } })),
          el('div', { className: 'wat-ai-provider-grid' },
            el(Row, null, el(c.SelectControl, { label: __('Provider', 'webactueel-translate-language-dropdowns'), value: aiProvider, disabled: !aiOn, options: [{ label: 'OpenAI', value: 'openai' }, { label: 'DeepL', value: 'deepl' }, { label: __('OpenAI-compatible API', 'webactueel-translate-language-dropdowns'), value: 'openai_compatible' }], onChange: function (v) { var n = Object.assign({}, vals[0]); n.ai_provider = v; n.ai_api_key = ''; if (v === 'deepl') { n.ai_model = 'deepl-api'; } else if (n.ai_model === 'deepl-api') { n.ai_model = 'gpt-4o-mini'; } vals[1](n); dirty[1](true); } })),
            el(Row, null, el(c.SelectControl, { label: __('Model', 'webactueel-translate-language-dropdowns'), value: vals[0].ai_model || (aiProvider === 'deepl' ? 'deepl-api' : 'gpt-4o-mini'), disabled: !aiOn, options: aiModelOptions, onChange: function (v) { setSettingsOption('ai_model', v); } }))
          ),
          el(Row, null, el(c.TextControl, { type: 'password', label: __('API-sleutel', 'webactueel-translate-language-dropdowns'), value: vals[0].ai_api_key || '', placeholder: vals[0].ai_has_api_key ? __('API-sleutel is geconfigureerd', 'webactueel-translate-language-dropdowns') : (vals[0].ai_database_key_storage_allowed ? __('Plak hier je API-sleutel', 'webactueel-translate-language-dropdowns') : __('Gebruik WAT_OPENAI_API_KEY, WAT_DEEPL_API_KEY, WAT_OPENAI_COMPATIBLE_API_KEY of het wat_ai_api_key filter', 'webactueel-translate-language-dropdowns')), disabled: !aiOn || !vals[0].ai_database_key_storage_allowed, autoComplete: 'off', onChange: function (v) { setSettingsOption('ai_api_key', v); } })),
          aiProvider === 'openai_compatible' ? el(Row, null, el(c.TextControl, { label: __('OpenAI-compatible endpoint', 'webactueel-translate-language-dropdowns'), help: __('Voor providers met een /v1/chat/completions compatible API. Gebruik HTTPS. API-sleutel via WAT_OPENAI_COMPATIBLE_API_KEY.', 'webactueel-translate-language-dropdowns'), value: vals[0].ai_custom_endpoint || '', placeholder: 'https://api.provider.com/v1', disabled: !aiOn, onChange: function (v) { setSettingsOption('ai_custom_endpoint', v); } })) : null,
          el('div', { className: 'wat-ai-provider-grid' },
            el(Row, null, el(c.SelectControl, { label: __('Toon', 'webactueel-translate-language-dropdowns'), value: vals[0].ai_tone || 'professional', disabled: !aiOn, options: [{ label: __('Professioneel', 'webactueel-translate-language-dropdowns'), value: 'professional' }, { label: __('Vriendelijk', 'webactueel-translate-language-dropdowns'), value: 'friendly' }, { label: __('Formeel', 'webactueel-translate-language-dropdowns'), value: 'formal' }, { label: __('Casual', 'webactueel-translate-language-dropdowns'), value: 'casual' }, { label: __('SEO-gericht', 'webactueel-translate-language-dropdowns'), value: 'seo' }], onChange: function (v) { setSettingsOption('ai_tone', v); } })),
            el(Row, null, el(c.SelectControl, { label: __('Formaliteit', 'webactueel-translate-language-dropdowns'), value: vals[0].ai_formality || 'default', disabled: !aiOn, options: [{ label: __('Standaard', 'webactueel-translate-language-dropdowns'), value: 'default' }, { label: __('Formeler', 'webactueel-translate-language-dropdowns'), value: 'more' }, { label: __('Informeler', 'webactueel-translate-language-dropdowns'), value: 'less' }, { label: __('Liever formeler', 'webactueel-translate-language-dropdowns'), value: 'prefer_more' }, { label: __('Liever informeler', 'webactueel-translate-language-dropdowns'), value: 'prefer_less' }], onChange: function (v) { setSettingsOption('ai_formality', v); } }))
          ),
          el(Row, null, el(c.ToggleControl, { label: __('AI-vertalingen altijd laten reviewen', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].ai_review_required), disabled: !aiOn, onChange: function (v) { setSettingsOption('ai_review_required', v); } })),
          el(Row, null, el(c.ToggleControl, { label: __('Visuele editor wijzigingen van vertalers eerst laten reviewen', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].translator_review_required), onChange: function (v) { setSettingsOption('translator_review_required', v); } })),
          el('div', { className: 'wat-ai-test-box wat-ai-connect-box' },
            el('div', { className: 'wat-ai-provider-grid' },
              el(c.TextControl, { label: __('Testtekst', 'webactueel-translate-language-dropdowns'), value: aiTestText[0], disabled: aiTesting[0], onChange: aiTestText[1] }),
              el(c.TextControl, { label: __('Doeltaalcode', 'webactueel-translate-language-dropdowns'), value: aiTargetLanguage[0], disabled: aiTesting[0], onChange: aiTargetLanguage[1] })
            ),
            el('div', { className: 'wat-inline-actions' },
              dirty[0] ? el(c.Button, { variant: 'secondary', className: 'wat-button-auto', disabled: aiTesting[0], onClick: save }, __('Instellingen opslaan', 'webactueel-translate-language-dropdowns')) : null,
              el(c.Button, { variant: 'primary', className: 'wat-button-auto', disabled: aiTesting[0], onClick: runAiTest }, aiTesting[0] ? __('Verbinden...', 'webactueel-translate-language-dropdowns') : __('Verbinden met AI', 'webactueel-translate-language-dropdowns')),
              aiProvider === 'openai' ? el(c.Button, { variant: 'secondary', href: 'https://platform.openai.com/api-keys', target: '_blank', rel: 'noopener noreferrer', className: 'wat-button-auto' }, __('OpenAI API-sleutel maken', 'webactueel-translate-language-dropdowns')) : null
            ),
            aiTestResult[0] ? el('div', { className: aiTestResult[0].error ? 'wat-ai-test-result is-error' : 'wat-ai-test-result' },
              aiTestResult[0].error ? aiTestResult[0].error : (aiTestResult[0].translated_text || __('AI is verbonden.', 'webactueel-translate-language-dropdowns'))
            ) : null
          )
        ),
        el(Card, { title: __('Prestatiemonitoring', 'webactueel-translate-language-dropdowns'), className: 'wat-fill-card' },
          el(Row, null, el(c.ToggleControl, { label: __('Lichte frontend performance snapshot bewaren', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].performance_monitoring), onChange: function (v) { setSettingsOption('performance_monitoring', v); } }))
        ),
        el(Card, { title: __('Technische instellingen', 'webactueel-translate-language-dropdowns'), className: 'wat-fill-card wat-advanced-split-card' },
          el(Row, null, el(c.TextControl, { type: 'number', label: __('Maximale HTML-grootte', 'webactueel-translate-language-dropdowns'), value: vals[0].max_buffer_size, onChange: function (v) { setSettingsOption('max_buffer_size', parseInt(v, 10) || 0); } })),
          el(Row, null, el(c.TextControl, { type: 'number', label: __('Maximaal aantal vervangingen per pagina', 'webactueel-translate-language-dropdowns'), value: vals[0].max_replacements, onChange: function (v) { setSettingsOption('max_replacements', parseInt(v, 10) || 0); } })),
          el(Row, null, el(c.TextControl, { type: 'number', label: __('Cache TTL in seconden', 'webactueel-translate-language-dropdowns'), value: vals[0].cache_ttl, onChange: function (v) { setSettingsOption('cache_ttl', parseInt(v, 10) || 0); } })),
          el(Row, null, el(c.TextControl, { type: 'number', label: __('Scan batchgrootte', 'webactueel-translate-language-dropdowns'), value: vals[0].scan_batch_size, onChange: function (v) { setSettingsOption('scan_batch_size', parseInt(v, 10) || 0); } })),
          el(Row, null, el(c.TextControl, { type: 'number', label: __('CSV previewregels', 'webactueel-translate-language-dropdowns'), value: vals[0].csv_preview_rows, onChange: function (v) { setSettingsOption('csv_preview_rows', parseInt(v, 10) || 0); } })),
          el(Row, null, el(c.TextControl, { type: 'number', label: __('CSV import maximum regels', 'webactueel-translate-language-dropdowns'), value: vals[0].csv_import_max_rows, onChange: function (v) { setSettingsOption('csv_import_max_rows', parseInt(v, 10) || 0); } })),
          el(Row, null, el(c.ToggleControl, { label: __('Conflictbescherming overslaan', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].compatibility_override), onChange: function (v) { setSettingsOption('compatibility_override', v); } })),
          el(Row, null, el(c.ToggleControl, { label: __('Hreflang forceren ondanks conflict', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].hreflang_force), disabled: !hreflangOn, onChange: function (v) { setSettingsOption('hreflang_force', v); } })),
          el(Row, null, el(c.ToggleControl, { label: __('Data verwijderen bij uninstall', 'webactueel-translate-language-dropdowns'), checked: bool(vals[0].delete_data_on_uninstall), onChange: function (v) { setSettingsOption('delete_data_on_uninstall', v); } }))
        ),
        dirty[0] ? el('div', { className: 'wat-unsaved-toast', role: 'status' },
          el('span', null, __('Je hebt niet-opgeslagen wijzigingen.', 'webactueel-translate-language-dropdowns')),
          el(c.Button, { variant: 'primary', onClick: save }, __('Opslaan', 'webactueel-translate-language-dropdowns'))
        ) : null
      )
    );
  }




  function ToolsScreen(p) {
    return el('div', { className: 'wat-page wat-tools-screen wat-tools-screen--loose wat-tools-screen--compact' },
      el(HeroBanner, { eyebrow: __('CSV & back-up', 'webactueel-translate-language-dropdowns'), title: __('Bulkwerk en herstel', 'webactueel-translate-language-dropdowns'), text: __('Gebruik deze tab voor export, import, controlebestanden en woordenlijstbeheer. Dagelijks vertalen doe je in Vertalingen.', 'webactueel-translate-language-dropdowns'), status: el(Badge, { tone: 'info' }, __('Beheerderstools', 'webactueel-translate-language-dropdowns')) }),
      el('section', { className: 'wat-tools-section wat-tools-section--csv' },
        el(Translate, { csvOnly: true, dashboard: p.dashboard || {}, toast: p.toast, tick: p.tick, refresh: p.refresh, refreshDashboard: p.refreshDashboard, search: p.search || '', setSearch: p.setSearch || function () {}, status: p.status || '', setStatus: p.setStatus || function () {} })
      ),
      el('section', { className: 'wat-tools-section wat-tools-section--glossary' },
        el(GlossaryManager, { dashboard: p.dashboard || {}, toast: p.toast, refresh: p.refresh, tick: p.tick })
      )
    );
  }

  function App() {
    var tick = useState(0);
    var dashboardTick = useState(0);
    var search = useState('');
    var status = useState('');
    var toast = useToasts();
    var tabs = [
      { name: 'dashboard', title: __('Overzicht', 'webactueel-translate-language-dropdowns') },
      { name: 'translate', title: __('Vertalingen', 'webactueel-translate-language-dropdowns') },
      { name: 'workflow', title: __('Workflow', 'webactueel-translate-language-dropdowns') },
      { name: 'visual-editor', title: __('Visuele editor', 'webactueel-translate-language-dropdowns') },
      { name: 'tools', title: __('CSV & back-up', 'webactueel-translate-language-dropdowns') },
      { name: 'settings', title: __('Instellingen', 'webactueel-translate-language-dropdowns') },
      { name: 'status', title: __('Systeemcontrole', 'webactueel-translate-language-dropdowns') }
    ];
    var tab = useTabState(tabs);
    var dash = useFetch('/dashboard', [dashboardTick[0]]);
    var logs = useFetch('/logs', [dashboardTick[0]], false);
    var health = useFetch('/health', [dashboardTick[0]]);
    var prefs = useFetch('/preferences', [tick[0]]);

    function refresh() {
      tick[1](tick[0] + 1);
      dashboardTick[1](dashboardTick[0] + 1);
    }

    function refreshDashboard() {
      dashboardTick[1](dashboardTick[0] + 1);
    }

    function render(t) {
      if (t.name === 'dashboard') {
        return el(DashboardScreen, { dashboard: dash.data || {}, preferences: prefs.data || {}, refresh: refresh, setTab: tab[1], toast: toast });
      }
      if (t.name === 'translate') {
        return el(Translate, { dashboard: dash.data || {}, toast: toast, tick: tick[0], refresh: refresh, refreshDashboard: refreshDashboard, search: search[0], setSearch: search[1], status: status[0], setStatus: status[1] });
      }
      if (t.name === 'workflow') {
        return el('div', { className: 'wat-workflow-tab-content', hidden: true, 'aria-hidden': 'true' });
      }
      if (t.name === 'visual-editor') {
        return el(VisualEditorScreen, { dashboard: dash.data || {}, setTab: tab[1], toast: toast });
      }
      if (t.name === 'tools') {
        return el(ToolsScreen, { dashboard: dash.data || {}, toast: toast, tick: tick[0], refresh: refresh, refreshDashboard: refreshDashboard, search: search[0], setSearch: search[1], status: status[0], setStatus: status[1], setTab: tab[1] });
      }
      if (t.name === 'settings') {
        return el(Settings, { dashboard: dash.data || {}, logs: [], toast: toast, refresh: refresh, tick: tick[0] });
      }
      if (t.name === 'status') {
        return el(StatusScreen, { dashboard: dash.data || {}, health: health.data || {}, logs: logs.data || [], toast: toast, refresh: refresh });
      }
      return el(DashboardScreen, { dashboard: dash.data || {}, preferences: prefs.data || {}, refresh: refresh, setTab: tab[1], toast: toast });
    }

    return el(
      'div',
      { className: 'wat-app-shell wat-native-admin' },
      dash.error ? el(c.Notice, { status: 'error', isDismissible: false }, dash.error) : null,
      el(c.TabPanel, {
        key: tab[0],
        className: 'wat-tabs wat-native-tabs',
        activeClass: 'is-active',
        tabs: tabs,
        initialTabName: tab[0],
        onSelect: tab[1]
      }, function (t) {
        return el('div', { className: 'wat-tab-content' }, render(t));
      }),
      el(Toasts, { items: toast.items, remove: toast.remove })
    );
  }

  var root = document.getElementById('webactueel-translate-admin-root') || document.getElementById('webactueel-translate-language-dropdowns-admin-root');
  if (root && window && typeof window.addEventListener === 'function') {
    window.addEventListener('error', function (event) {
      if (root.getAttribute && root.getAttribute('data-wat-admin-loaded') === '1' && !root.firstChild) {
        renderAdminError(root, __('Translate admin kon niet laden: ', 'webactueel-translate-language-dropdowns') + String(event && event.message ? event.message : 'onbekende JavaScript-fout'));
      }
    });
  }
  if (root) {
    try {
      var fallback = document.getElementById("wat-admin-fallback");
      if (fallback && fallback.parentNode) {
        fallback.parentNode.removeChild(fallback);
      }
      root.setAttribute("data-wat-admin-loaded", "1");
      if (typeof wp.element.createRoot === 'function') {
        wp.element.createRoot(root).render(el(App));
      } else if (typeof wp.element.render === 'function') {
        wp.element.render(el(App), root);
      } else {
        throw new Error('wp.element render API ontbreekt');
      }
      window.setTimeout(function () {
        if (!root.firstChild) {
          renderAdminError(root, __('Translate admin kon niet laden: de admin-app renderde geen inhoud. Controleer de browserconsole op JavaScript-fouten.', 'webactueel-translate-language-dropdowns'));
        }
      }, 250);
    } catch (e) {
      renderAdminError(root, __('Translate admin kon niet laden: ', 'webactueel-translate-language-dropdowns') + String(e && e.message ? e.message : e));
    }
  }
})(window.wp, window.WebactueelTranslate || {});
