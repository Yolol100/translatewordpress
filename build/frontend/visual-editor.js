(function (wp, cfg) {
  'use strict';

  cfg = cfg || {};
  var element = wp && wp.element ? wp.element : null;
  var apiFetch = wp && wp.apiFetch ? wp.apiFetch : null;
  var i18n = wp && wp.i18n ? wp.i18n : null;
  var __ = i18n && i18n.__ ? function (text) { return i18n.__(text, 'webactueel-translate-language-dropdowns'); } : function (text) { return text; };

  if (!element || !apiFetch) {
    document.documentElement.classList.add('wat-visual-editor-active');
    var fallback = document.createElement('div');
    fallback.className = 'wat-visual-editor-notice';
    fallback.setAttribute('role', 'alert');
    fallback.textContent = __('Vertaalmodus kon niet laden: WordPress React dependencies ontbreken.');
    document.body.appendChild(fallback);
    return;
  }

  var el = element.createElement;
  var Fragment = element.Fragment;
  var useEffect = element.useEffect;
  var useRef = element.useRef;
  var useState = element.useState;
  var protectedSelectors = Array.isArray(cfg.protectedSelectors) ? cfg.protectedSelectors : [];
  var maxSegments = parseInt(cfg.maxSegments || 300, 10);
  var segmentHandlers = new WeakMap();

  if (apiFetch.createNonceMiddleware && cfg.nonce) {
    apiFetch.use(apiFetch.createNonceMiddleware(cfg.nonce));
  }

  function cleanText(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
  }

  function isProtected(node) {
    if (!node || !node.parentElement) {
      return true;
    }
    if (node.closest && node.closest('#wat-visual-editor-react-root, .wat-visual-editor-bar, .wat-visual-editor-sidebar, .wat-visual-editor-notice, .wat-visual-editor-segment')) {
      return true;
    }
    for (var i = 0; i < protectedSelectors.length; i += 1) {
      try {
        if (node.parentElement.closest(protectedSelectors[i])) {
          return true;
        }
      } catch (error) {}
    }
    return false;
  }

  function isGoodText(value) {
    var text = cleanText(value);
    if (text.length < 2 || text.length > 300) {
      return false;
    }
    return !/^[\d\s.,:;!?\'"()\-–—€$%]+$/.test(text);
  }

  function selectorFor(elementNode) {
    if (!elementNode || !elementNode.tagName) {
      return '';
    }
    if (elementNode.id) {
      return '#' + elementNode.id;
    }
    var parts = [];
    var current = elementNode;
    while (current && current.nodeType === 1 && parts.length < 4 && current !== document.body) {
      var part = current.tagName.toLowerCase();
      if (current.className && typeof current.className === 'string') {
        var cls = current.className.split(/\s+/).filter(function (item) {
          return item && item.indexOf('wat-visual') !== 0;
        }).slice(0, 2).join('.');
        if (cls) {
          part += '.' + cls;
        }
      }
      parts.unshift(part);
      current = current.parentElement;
    }
    return parts.join(' > ');
  }

  function getFocusable(container) {
    if (!container) {
      return [];
    }
    return Array.prototype.filter.call(
      container.querySelectorAll('a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'),
      function (node) { return !!(node.offsetWidth || node.offsetHeight || node.getClientRects().length); }
    );
  }

  function clearSegmentMarkers() {
    document.querySelectorAll('.wat-visual-editor-segment, .wat-visual-editor-selected').forEach(function (node) {
      node.classList.remove('wat-visual-editor-segment', 'wat-visual-editor-selected');
      var handler = segmentHandlers.get(node);
      if (handler) {
        node.removeEventListener('click', handler.click);
        node.removeEventListener('keydown', handler.keydown);
        segmentHandlers.delete(node);
      }
      if (node.dataset) {
        delete node.dataset.watVisualSegment;
        delete node.dataset.watOriginal;
      }
      if (node.getAttribute('tabindex') === '0' && node.dataset.watHadTabindex !== '1') {
        node.removeAttribute('tabindex');
      }
      node.removeAttribute('role');
      node.removeAttribute('aria-label');
      delete node.dataset.watHadTabindex;
    });
  }

  function scanSegments(onSelect) {
    var selectors = 'h1,h2,h3,h4,h5,h6,p,li,a,button,label,figcaption,span,div.wp-block-button__link,.elementor-heading-title,.elementor-button-text,.elementor-widget-text-editor,.et_pb_text,.et_pb_button,.vc_btn3,.wpb_text_column,.fl-rich-text,.brxe-text,.ct-text-block';
    var nodes = Array.prototype.slice.call(document.querySelectorAll(selectors));
    var count = 0;

    nodes.some(function (node) {
      if (count >= maxSegments) {
        return true;
      }
      if (!node || node.dataset.watVisualSegment === '1' || node.children.length > 3 || isProtected(node)) {
        return false;
      }
      var text = cleanText(node.textContent);
      if (!isGoodText(text)) {
        return false;
      }
      node.dataset.watVisualSegment = '1';
      node.dataset.watOriginal = text;
      node.dataset.watHadTabindex = node.hasAttribute('tabindex') ? '1' : '0';
      node.classList.add('wat-visual-editor-segment');
      node.setAttribute('role', 'button');
      node.setAttribute('aria-label', __('Vertaal dit tekstsegment') + ': ' + text.slice(0, 90));
      if (!node.hasAttribute('tabindex')) {
        node.setAttribute('tabindex', '0');
      }
      var activate = function (event) {
        event.preventDefault();
        event.stopPropagation();
        document.querySelectorAll('.wat-visual-editor-selected').forEach(function (selected) {
          selected.classList.remove('wat-visual-editor-selected');
        });
        node.classList.add('wat-visual-editor-selected');
        onSelect(node);
      };
      var keydown = function (event) {
        var key = event.key || event.keyCode;
        if (key === 'Enter' || key === ' ' || key === 13 || key === 32) {
          activate(event);
        }
      };
      segmentHandlers.set(node, { click: activate, keydown: keydown });
      node.addEventListener('click', activate);
      node.addEventListener('keydown', keydown);
      count += 1;
      return false;
    });

    return count;
  }

  function leaveEditor() {
    var url = new URL(window.location.href);
    url.searchParams.delete('wat_visual_editor');
    window.location.href = url.toString();
  }

  function Notice(props) {
    if (!props.message) {
      return null;
    }
    return el('div', {
      className: 'wat-visual-editor-notice',
      role: props.type === 'error' ? 'alert' : 'status',
      'aria-live': props.type === 'error' ? 'assertive' : 'polite',
      'aria-atomic': 'true',
      'data-type': props.type || 'info'
    }, props.message);
  }

  function EditorBar(props) {
    return el('div', { className: 'wat-visual-editor-bar', role: 'region', 'aria-label': __('Vertaalmodus') },
      el('strong', null, __('Webactueel Translate vertaalmodus')),
      el('span', null, props.count > 0 ? props.count + ' ' + __('vertaalbare tekstsegmenten gevonden.') : __('Klik op zichtbare tekst om in context te vertalen.')),
      el('button', { type: 'button', onClick: leaveEditor }, __('Sluiten'))
    );
  }

  function Sidebar(props) {
    var sidebarRef = useRef(null);
    var translationRef = useRef(null);
    var languages = Array.isArray(cfg.languages) && cfg.languages.length ? cfg.languages : [{ code: cfg.language || '', label: String(cfg.language || '').toUpperCase() }];
    var initialLanguage = cfg.language || (languages[0] && languages[0].code) || '';
    var original = props.target ? (props.target.dataset.watOriginal || cleanText(props.target.textContent)) : '';
    var [language, setLanguage] = useState(initialLanguage);
    var [translation, setTranslation] = useState(props.target ? cleanText(props.target.textContent) : '');
    var [saving, setSaving] = useState(false);
    var [loading, setLoading] = useState(false);
    var [meta, setMeta] = useState({ status: '', origin: '', memory: null });

    useEffect(function () {
      setTranslation(props.target ? cleanText(props.target.textContent) : '');
      setLanguage(initialLanguage);
      setMeta({ status: '', origin: '', memory: null });
    }, [props.target]);

    useEffect(function () {
      if (!props.target || !cfg.restUrl || !language) {
        return;
      }
      var cancelled = false;
      setLoading(true);
      apiFetch({
        url: cfg.restUrl + '?original=' + encodeURIComponent(original) + '&language=' + encodeURIComponent(language),
        method: 'GET'
      }).then(function (response) {
        if (cancelled) {
          return;
        }
        setMeta({
          status: response && response.status ? response.status : '',
          origin: response && response.origin ? response.origin : '',
          memory: response && response.memory ? response.memory : null
        });
        if (response && response.translation) {
          setTranslation(response.translation);
        }
      }).catch(function () {
        if (!cancelled) {
          setMeta({ status: '', origin: '', memory: null });
        }
      }).finally(function () {
        if (!cancelled) {
          setLoading(false);
        }
      });
      return function () { cancelled = true; };
    }, [props.target, language]);

    useEffect(function () {
      if (translationRef.current) {
        translationRef.current.focus();
      }
    }, [props.target]);

    useEffect(function () {
      function onKeyDown(event) {
        var key = event.key || event.keyCode;
        if (key === 'Escape' || key === 'Esc' || key === 27) {
          event.preventDefault();
          props.onClose();
          return;
        }
        if ((event.ctrlKey || event.metaKey) && (key === 'Enter' || key === 13)) {
          event.preventDefault();
          save();
          return;
        }
        if (key !== 'Tab' && key !== 9) {
          return;
        }
        var focusable = getFocusable(sidebarRef.current);
        if (!focusable.length) {
          return;
        }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }

      document.addEventListener('keydown', onKeyDown);
      return function () { document.removeEventListener('keydown', onKeyDown); };
    }, [props.target, translation, language, saving]);

    function save() {
      if (!props.target || !cfg.restUrl || !cfg.nonce || saving) {
        props.onNotice(__('Opslaan is niet beschikbaar.'), 'error');
        return;
      }
      if (!translation || !language) {
        props.onNotice(__('Kies een taal en vul een vertaling in.'), 'error');
        return;
      }
      setSaving(true);
      apiFetch({
        url: cfg.restUrl,
        method: 'POST',
        data: {
          original: original,
          translation: translation,
          language: language,
          selector: selectorFor(props.target),
          url: window.location.href
        }
      }).then(function (response) {
        // Keep the live preview conservative: only replace text for simple elements.
        // Complex elements may contain spans/icons/emphasis and should not be flattened in the editor DOM.
        if (props.target && props.target.children && props.target.children.length === 0) {
          props.target.textContent = translation;
        }
        setMeta({ status: response && response.status ? response.status : '', origin: 'manual', memory: null });
        props.onNotice(response && response.message ? response.message : __('Vertaling opgeslagen.'), 'success');
        props.onClose();
      }).catch(function (error) {
        props.onNotice(error && error.message ? error.message : __('Opslaan mislukt.'), 'error');
      }).finally(function () {
        setSaving(false);
      });
    }

    if (!props.target) {
      return null;
    }

    return el('aside', {
      ref: sidebarRef,
      className: 'wat-visual-editor-sidebar is-open',
      role: 'dialog',
      'aria-modal': 'true',
      'aria-labelledby': 'wat-visual-editor-title',
      'aria-describedby': 'wat-visual-editor-help'
    },
      el('button', { type: 'button', className: 'wat-visual-editor-close', 'aria-label': __('Sluiten'), onClick: props.onClose }, '×'),
      el('div', { className: 'wat-visual-editor-header' },
        el('span', { className: 'wat-visual-editor-kicker' }, __('Vertaalmodus')),
        el('h2', { id: 'wat-visual-editor-title' }, __('Tekst vertalen')),
        el('p', { id: 'wat-visual-editor-help', className: 'wat-visual-editor-help' }, __('Klik op tekst in de pagina en werk de vertaling direct in context bij.')),
        el('p', { className: 'wat-visual-editor-status', role: 'status', 'aria-live': 'polite' }, loading ? __('Bestaande vertaling zoeken...') : (meta.status ? __('Status') + ': ' + meta.status + (meta.origin ? ' · ' + __('Bron') + ': ' + meta.origin : '') : __('Nieuwe visuele vertaling')))
      ),
      el('div', { className: 'wat-visual-editor-body' },
        meta.memory ? el('div', { className: 'wat-visual-editor-memory', role: 'note' },
          el('strong', null, __('Translation Memory-match gevonden')),
          el('span', null, ' ' + String(meta.memory.score || 100) + '%'),
          el('button', { type: 'button', onClick: function () { setTranslation(meta.memory.translation || ''); } }, __('Gebruik voorstel'))
        ) : null,
        el('div', { className: 'wat-visual-field wat-visual-field--compact' },
          el('label', { htmlFor: 'wat-visual-language' }, __('Doeltaal')),
          el('select', {
            id: 'wat-visual-language',
            className: 'wat-visual-language',
            value: language,
            onChange: function (event) { setLanguage(event.target.value); }
          }, languages.map(function (item) {
            var code = String(item.code || '');
            return el('option', { key: code, value: code }, String(item.label || code.toUpperCase()));
          }))
        ),
        el('div', { className: 'wat-visual-field' },
          el('label', { htmlFor: 'wat-visual-original' }, __('Originele tekst')),
          el('textarea', { id: 'wat-visual-original', className: 'wat-visual-original', readOnly: true, value: original })
        ),
        el('div', { className: 'wat-visual-field' },
          el('label', { htmlFor: 'wat-visual-translation' }, __('Vertaling')),
          el('textarea', {
            ref: translationRef,
            id: 'wat-visual-translation',
            className: 'wat-visual-translation',
            value: translation,
            onChange: function (event) { setTranslation(event.target.value); }
          })
        ),
        el('p', { className: 'wat-visual-editor-small' }, __('Sneltoets: Ctrl/Cmd + Enter slaat op. Formulieren, checkout en technische elementen blijven beschermd.'))
      ),
      el('div', { className: 'wat-visual-editor-footer' },
        el('button', { type: 'button', className: 'wat-visual-save', disabled: saving || loading, onClick: save }, saving ? __('Opslaan...') : __('Vertaling opslaan'))
      )
    );
  }

  function App() {
    var [target, setTarget] = useState(null);
    var [lastFocus, setLastFocus] = useState(null);
    var [count, setCount] = useState(0);
    var [notice, setNotice] = useState({ message: '', type: 'info' });
    var noticeTimer = useRef(null);

    function showNotice(message, type) {
      setNotice({ message: message, type: type || 'info' });
      window.clearTimeout(noticeTimer.current);
      noticeTimer.current = window.setTimeout(function () {
        setNotice({ message: '', type: 'info' });
      }, 3500);
    }

    function selectTarget(node) {
      setLastFocus(document.activeElement);
      setTarget(node);
    }

    function closeSidebar() {
      if (target) {
        target.classList.remove('wat-visual-editor-selected');
      }
      setTarget(null);
      if (lastFocus && typeof lastFocus.focus === 'function') {
        lastFocus.focus();
      }
      setLastFocus(null);
    }

    useEffect(function () {
      document.documentElement.classList.add('wat-visual-editor-active');
      var total = scanSegments(selectTarget);
      setCount(total);
      showNotice(total + ' ' + __('vertaalbare tekstsegmenten gevonden.'), 'info');
      return function () {
        document.documentElement.classList.remove('wat-visual-editor-active');
        clearSegmentMarkers();
        window.clearTimeout(noticeTimer.current);
      };
    }, []);

    return el(Fragment, null,
      el(EditorBar, { count: count }),
      el(Sidebar, { target: target, onClose: closeSidebar, onNotice: showNotice }),
      el(Notice, { message: notice.message, type: notice.type })
    );
  }

  function mount() {
    var root = document.createElement('div');
    root.id = 'wat-visual-editor-react-root';
    document.body.appendChild(root);
    if (typeof element.createRoot === 'function') {
      element.createRoot(root).render(el(App));
      return;
    }
    element.render(el(App), root);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
}(window.wp || {}, window.watVisualEditor || {}));
