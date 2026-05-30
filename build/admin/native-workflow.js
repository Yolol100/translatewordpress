(function (wp, cfg) {
  'use strict';

  if (!wp || !wp.element || !wp.components || !wp.apiFetch) {
    return;
  }

  var el = wp.element.createElement;
  var useEffect = wp.element.useEffect;
  var useState = wp.element.useState;
  var __ = wp.i18n && wp.i18n.__ ? function (text) { return wp.i18n.__(text, 'webactueel-translate-language-dropdowns'); } : function (text) { return text; };
  var sprintf = wp.i18n && wp.i18n.sprintf ? wp.i18n.sprintf : function (text) { var args = Array.prototype.slice.call(arguments, 1); var i = 0; return String(text).replace(/%d|%s/g, function () { return args[i++]; }); };
  var apiFetch = wp.apiFetch;
  var components = wp.components;
  var Button = components.Button;
  var Card = components.Card;
  var CardBody = components.CardBody;
  var CardHeader = components.CardHeader;
  var Notice = components.Notice;
  var SelectControl = components.SelectControl;
  var Spinner = components.Spinner;
  var Panel = components.Panel;
  var PanelBody = components.PanelBody;

  if (apiFetch && typeof apiFetch.use === 'function' && typeof apiFetch.createNonceMiddleware === 'function' && cfg && cfg.nonce) {
    apiFetch.use(apiFetch.createNonceMiddleware(cfg.nonce));
  }

  function cls() {
    return Array.prototype.slice.call(arguments).filter(Boolean).join(' ');
  }

  function num(value) {
    return parseInt(value || 0, 10).toLocaleString('nl-NL');
  }

  function api(path, options) {
    return apiFetch(Object.assign({}, options || {}, { path: '/webactueel-translate-language-dropdowns/v1' + path }));
  }

  function Badge(props) {
    return el('span', { className: cls('wat-native-workflow-badge', 'is-' + (props.tone || 'info')), role: props.live ? 'status' : undefined, 'aria-live': props.live ? 'polite' : undefined }, props.children);
  }

  function Metric(props) {
    return el('div', { className: 'wat-native-workflow-metric' },
      el('span', null, props.label),
      el('strong', null, props.value),
      props.help ? el('small', null, props.help) : null
    );
  }

  function EmptyState(props) {
    return el('div', { className: 'wat-native-workflow-empty' },
      el('strong', null, props.title),
      props.text ? el('p', null, props.text) : null
    );
  }

  function WarningList(props) {
    var items = props.items || [];
    if (!items.length) {
      return el(EmptyState, {
        title: __('Geen waarschuwingen', 'webactueel-translate-language-dropdowns'),
        text: __('Deze taal heeft binnen de huidige selectie geen opvallende workflowproblemen.', 'webactueel-translate-language-dropdowns')
      });
    }

    return el('ul', { className: 'wat-native-workflow-list' }, items.map(function (item, index) {
      return el('li', { key: item.code || item.normalized_hash || index },
        el('div', null,
          el('strong', null, item.message || item.source_preview || __('Waarschuwing', 'webactueel-translate-language-dropdowns')),
          item.source_preview ? el('p', null, item.source_preview) : null,
          item.context_count ? el('small', null, num(item.context_count) + ' ' + __('contexten', 'webactueel-translate-language-dropdowns') + ' · ' + num(item.translation_variants) + ' ' + __('varianten', 'webactueel-translate-language-dropdowns')) : null
        ),
        item.count ? el(Badge, { tone: 'warning' }, num(item.count)) : null
      );
    }));
  }

  function currentAdminTab() {
    try {
      return new URLSearchParams(window.location.search).get('wat_tab') || window.localStorage.getItem('wat_tab') || 'dashboard';
    } catch (e) {
      return 'dashboard';
    }
  }

  function syncWorkflowVisibility() {
    var root = document.getElementById('webactueel-translate-native-workflow-root');
    var app = document.getElementById('webactueel-translate-admin-root');
    var active = currentAdminTab() === 'workflow';
    if (root) {
      root.hidden = !active;
      root.setAttribute('data-wat-workflow-active', active ? '1' : '0');
    }
    if (app) {
      app.classList.toggle('is-workflow-tab-active', active);
    }
  }

  function pushAdminTab(tabName) {
    try {
      window.localStorage.setItem('wat_tab', tabName);
      var url = new URL(window.location.href);
      url.searchParams.set('wat_tab', tabName);
      window.history.pushState({}, '', url.toString());
      window.dispatchEvent(new CustomEvent('wat-tab-change', { detail: { tab: tabName } }));
      window.dispatchEvent(new PopStateEvent('popstate'));
    } catch (e) {
      try {
        window.dispatchEvent(new Event('popstate'));
      } catch (ignore) {}
    }
  }

  function setMainTab(tabName) {
    pushAdminTab(tabName);
    syncWorkflowVisibility();
  }

  function openAdminTab(tabName) {
    setMainTab(tabName);
  }

  function NativeWorkflowPanel() {
    var languages = useState({ loading: true, error: '', items: [] });
    var language = useState(window.localStorage.getItem('wat_workflow_language') || '');
    var quality = useState({ loading: false, error: '', data: null });
    var context = useState({ loading: false, error: '', data: null });
    var jobs = useState({ loading: true, error: '', data: null });

    useEffect(function () {
      var alive = true;
      api('/languages')
        .then(function (items) {
          if (!alive) { return; }
          items = Array.isArray(items) ? items : [];
          var active = items.filter(function (item) { return item && item.is_active && !item.is_default; });
          var fallback = active[0] || items.filter(function (item) { return item && !item.is_default; })[0] || items[0];
          languages[1]({ loading: false, error: '', items: items });
          if (!language[0] && fallback && fallback.code) {
            language[1](fallback.code);
            window.localStorage.setItem('wat_workflow_language', fallback.code);
          }
        })
        .catch(function (error) {
          if (alive) {
            languages[1]({ loading: false, error: error.message || __('Talen laden mislukt.', 'webactueel-translate-language-dropdowns'), items: [] });
          }
        });
      api('/workflow/jobs?limit=8')
        .then(function (data) {
          if (alive) {
            jobs[1]({ loading: false, error: '', data: data && data.jobs ? data.jobs : data });
          }
        })
        .catch(function (error) {
          if (alive) {
            jobs[1]({ loading: false, error: error.message || __('Workflowtaken laden mislukt.', 'webactueel-translate-language-dropdowns'), data: null });
          }
        });
      return function () { alive = false; };
    }, []);

    useEffect(function () {
      if (!language[0]) { return; }
      var alive = true;
      quality[1]({ loading: true, error: '', data: quality[0].data });
      context[1]({ loading: true, error: '', data: context[0].data });
      api('/workflow/quality?language=' + encodeURIComponent(language[0]))
        .then(function (data) {
          if (alive) {
            quality[1]({ loading: false, error: '', data: data && data.quality ? data.quality : data });
          }
        })
        .catch(function (error) {
          if (alive) {
            quality[1]({ loading: false, error: error.message || __('Quality check laden mislukt.', 'webactueel-translate-language-dropdowns'), data: null });
          }
        });
      api('/workflow/context?language=' + encodeURIComponent(language[0]) + '&limit=8')
        .then(function (data) {
          if (alive) {
            context[1]({ loading: false, error: '', data: data && data.context ? data.context : data });
          }
        })
        .catch(function (error) {
          if (alive) {
            context[1]({ loading: false, error: error.message || __('Contextcontrole laden mislukt.', 'webactueel-translate-language-dropdowns'), data: null });
          }
        });
      return function () { alive = false; };
    }, [language[0]]);

    function changeLanguage(value) {
      language[1](value);
      window.localStorage.setItem('wat_workflow_language', value);
    }

    var languageOptions = (languages[0].items || [])
      .filter(function (item) { return item && !item.is_default; })
      .map(function (item) {
        return { label: (item.native_name || item.name || item.code) + ' (' + item.code + ')', value: item.code };
      });
    if (!languageOptions.length) {
      languageOptions = [{ label: __('Geen vertaaltaal ingesteld', 'webactueel-translate-language-dropdowns'), value: '' }];
    }

    var q = quality[0].data || {};
    var c = context[0].data || {};
    var counts = q.counts || {};
    var jobData = jobs[0].data || {};
    var jobItems = jobData.items || [];
    var jobSummary = jobData.summary || {};
    var score = q.score !== undefined ? q.score : 0;
    var hasBlockingWork = (counts.missing || 0) > 0 || (counts.needs_review || 0) > 0 || (counts.outdated || 0) > 0 || (c.summary && c.summary.conflict_groups > 0);

    return el('div', { className: 'wat-native-workflow-root' },
      el(Card, { className: 'wat-native-workflow-card' },
        el(CardHeader, null,
          el('div', { className: 'wat-native-workflow-heading' },
            el('span', { className: 'wat-native-workflow-eyebrow' }, __('Workflowkwaliteit', 'webactueel-translate-language-dropdowns')),
            el('h2', null, __('Publicatiecheck', 'webactueel-translate-language-dropdowns')),
            el('p', null, __('Controleer ontbrekende vertalingen, reviewwerk en contextconflicten voordat je live gaat.', 'webactueel-translate-language-dropdowns'))
          ),
          el('div', { className: 'wat-native-workflow-header-actions' },
            el(SelectControl, { label: __('Taal', 'webactueel-translate-language-dropdowns'), hideLabelFromVision: false, __nextHasNoMarginBottom: true, __next40pxDefaultSize: true, value: language[0], options: languageOptions, onChange: changeLanguage }),
            el(Badge, { tone: hasBlockingWork ? 'warning' : 'success', live: true }, hasBlockingWork ? __('Actie nodig', 'webactueel-translate-language-dropdowns') : __('Klaar voor review', 'webactueel-translate-language-dropdowns'))
          )
        ),
        el(CardBody, null,
          languages[0].loading ? el('div', { className: 'wat-native-workflow-loading' }, el(Spinner), __('Workflow laden...', 'webactueel-translate-language-dropdowns')) : null,
          languages[0].error ? el(Notice, { status: 'error', isDismissible: false, politeness: 'assertive' }, languages[0].error) : null,
          quality[0].error ? el(Notice, { status: 'warning', isDismissible: false, politeness: 'polite' }, quality[0].error) : null,
          context[0].error ? el(Notice, { status: 'warning', isDismissible: false, politeness: 'polite' }, context[0].error) : null,
          el('div', { className: 'wat-native-workflow-grid' },
            el(Metric, { label: __('Score', 'webactueel-translate-language-dropdowns'), value: String(score) + '%', help: __('Reviewed + gepubliceerd', 'webactueel-translate-language-dropdowns') }),
            el(Metric, { label: __('Ontbreekt', 'webactueel-translate-language-dropdowns'), value: num(counts.missing), help: __('Nog te vertalen', 'webactueel-translate-language-dropdowns') }),
            el(Metric, { label: __('Review', 'webactueel-translate-language-dropdowns'), value: num(counts.needs_review), help: __('Controle nodig', 'webactueel-translate-language-dropdowns') }),
            el(Metric, { label: __('Verouderd', 'webactueel-translate-language-dropdowns'), value: num(counts.outdated), help: __('Content gewijzigd', 'webactueel-translate-language-dropdowns') }),
            el(Metric, { label: __('Contextconflicten', 'webactueel-translate-language-dropdowns'), value: num(c.summary && c.summary.conflict_groups), help: __('Meerdere varianten', 'webactueel-translate-language-dropdowns') }),
            el(Metric, { label: __('Toegewezen taken', 'webactueel-translate-language-dropdowns'), value: num(jobSummary.open), help: jobSummary.overdue ? sprintf(__('%d te laat', 'webactueel-translate-language-dropdowns'), jobSummary.overdue) : __('Open workflowjobs', 'webactueel-translate-language-dropdowns') })
          ),
          el('div', { className: 'wat-native-workflow-progress', role: 'progressbar', 'aria-label': __('Publicatievoortgang', 'webactueel-translate-language-dropdowns'), 'aria-valuetext': String(score) + '%', 'aria-valuemin': 0, 'aria-valuemax': 100, 'aria-valuenow': score },
            el('span', { style: { width: Math.max(0, Math.min(100, score)) + '%' } })
          ),
          el('div', { className: 'wat-native-workflow-panels' },
            el(Panel, null,
              el(PanelBody, { title: __('Vertaalstatus', 'webactueel-translate-language-dropdowns'), initialOpen: true },
                quality[0].loading ? el('span', { className: 'wat-native-workflow-loading-inline', role: 'status', 'aria-live': 'polite' }, el(Spinner), __('Kwaliteit laden...', 'webactueel-translate-language-dropdowns')) : el(WarningList, { items: q.warnings || [] })
              )
            ),
            el(Panel, null,
              el(PanelBody, { title: __('Contextwaarschuwingen', 'webactueel-translate-language-dropdowns'), initialOpen: true },
                context[0].loading ? el('span', { className: 'wat-native-workflow-loading-inline', role: 'status', 'aria-live': 'polite' }, el(Spinner), __('Context laden...', 'webactueel-translate-language-dropdowns')) : el(WarningList, { items: (c.conflicts || []).concat(c.reused_sources || []).slice(0, 8) })
              )
            ),
            el(Panel, null,
              el(PanelBody, { title: __('Toegewezen workflowtaken', 'webactueel-translate-language-dropdowns'), initialOpen: false },
                jobs[0].loading ? el('span', { className: 'wat-native-workflow-loading-inline', role: 'status', 'aria-live': 'polite' }, el(Spinner), __('Taken laden...', 'webactueel-translate-language-dropdowns')) : null,
                jobs[0].error ? el(Notice, { status: 'warning', isDismissible: false, politeness: 'polite' }, jobs[0].error) : null,
                !jobs[0].loading && !jobs[0].error ? el('ul', { className: 'wat-native-workflow-list' }, jobItems.length ? jobItems.map(function (job) {
                  var progress = job.total_items ? Math.round((job.processed_items / job.total_items) * 100) : 0;
                  var assignee = job.assignee_name || __('Niet toegewezen', 'webactueel-translate-language-dropdowns');
                  var due = job.due_at ? ' · ' + job.due_at : '';
                  return el('li', { key: 'job-' + job.id },
                    el('strong', null, sprintf(__('Job #%d · %s', 'webactueel-translate-language-dropdowns'), job.id, job.status || 'queued')),
                    el('span', null, sprintf(__('%s · %d%% verwerkt%s', 'webactueel-translate-language-dropdowns'), assignee, progress, due))
                  );
                }) : [el('li', { key: 'empty' }, __('Nog geen toegewezen workflowtaken.', 'webactueel-translate-language-dropdowns'))]) : null
              )
            )
          ),
          el('div', { className: 'wat-native-workflow-actions' },
            el(Button, { variant: 'primary', onClick: function () { openAdminTab('translate'); } }, __('Vertalingen openen', 'webactueel-translate-language-dropdowns')),
            el(Button, { variant: 'secondary', onClick: function () { openAdminTab('visual-editor'); } }, __('Visueel controleren', 'webactueel-translate-language-dropdowns')),
            el(Button, { variant: 'secondary', onClick: function () { openAdminTab('status'); } }, __('Systeemcontrole', 'webactueel-translate-language-dropdowns'))
          )
        )
      )
    );
  }

  function mountNativeWorkflow(attempt) {
    var root = document.getElementById('webactueel-translate-native-workflow-root');
    if (!root) {
      if ((attempt || 0) < 60) {
        window.setTimeout(function () { mountNativeWorkflow((attempt || 0) + 1); }, 100);
      }
      return;
    }

    if (root.getAttribute('data-wat-native-workflow-mounted') !== '1') {
      root.setAttribute('data-wat-native-workflow-mounted', '1');
      if (typeof wp.element.createRoot === 'function') {
        wp.element.createRoot(root).render(el(NativeWorkflowPanel));
      } else if (typeof wp.element.render === 'function') {
        wp.element.render(el(NativeWorkflowPanel), root);
      }
    }
    syncWorkflowVisibility();
  }

  window.addEventListener('popstate', syncWorkflowVisibility);
  window.addEventListener('wat-tab-change', syncWorkflowVisibility);
  mountNativeWorkflow(0);
})(window.wp, window.WebactueelTranslate || {});
