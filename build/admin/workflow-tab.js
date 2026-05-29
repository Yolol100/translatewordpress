(function (wp) {
  'use strict';

  var __ = wp && wp.i18n && wp.i18n.__ ? function (text) { return wp.i18n.__(text, 'webactueel-translate-language-dropdowns'); } : function (text) { return text; };

  function requestedTab() {
    try {
      return new URLSearchParams(window.location.search).get('wat_tab') || window.localStorage.getItem('wat_tab') || 'dashboard';
    } catch (error) {
      return 'dashboard';
    }
  }

  function setUrlTab(tab) {
    try {
      window.localStorage.setItem('wat_tab', tab);
      var url = new URL(window.location.href);
      url.searchParams.set('wat_tab', tab);
      window.history.pushState({}, '', url.toString());
    } catch (error) {}
  }

  function setWorkflowActive(active) {
    var workflow = document.getElementById('webactueel-translate-native-workflow-root');
    var app = document.getElementById('webactueel-translate-admin-root');
    var buttons = document.querySelectorAll('.wat-native-tabs .components-tab-panel__tabs button');

    if (workflow) {
      workflow.hidden = !active;
      workflow.setAttribute('data-wat-workflow-active', active ? '1' : '0');
    }
    if (app) {
      app.classList.toggle('is-workflow-tab-active', active);
    }
    Array.prototype.forEach.call(buttons, function (button) {
      var isWorkflow = button.getAttribute('data-wat-workflow-tab') === '1';
      if (isWorkflow) {
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
      } else if (active) {
        button.classList.remove('is-active');
        button.setAttribute('aria-selected', 'false');
      }
    });
  }

  function install() {
    var attempts = 0;
    var timer = window.setInterval(function () {
      attempts += 1;
      var tabList = document.querySelector('.wat-native-tabs .components-tab-panel__tabs');
      if (!tabList) {
        if (attempts > 60) {
          window.clearInterval(timer);
        }
        return;
      }

      if (!tabList.querySelector('[data-wat-workflow-tab="1"]')) {
        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = __('Workflow', 'webactueel-translate-language-dropdowns');
        button.setAttribute('data-wat-workflow-tab', '1');
        button.addEventListener('click', function () {
          setUrlTab('workflow');
          setWorkflowActive(true);
        });
        tabList.insertBefore(button, tabList.children[1] || null);
      }

      Array.prototype.forEach.call(tabList.querySelectorAll('button:not([data-wat-workflow-tab="1"])'), function (button) {
        if (button.getAttribute('data-wat-main-tab-listener') === '1') {
          return;
        }
        button.setAttribute('data-wat-main-tab-listener', '1');
        button.addEventListener('click', function () {
          setWorkflowActive(false);
        });
      });

      setWorkflowActive(requestedTab() === 'workflow');
      window.clearInterval(timer);
    }, 100);
  }

  window.addEventListener('popstate', function () {
    setWorkflowActive(requestedTab() === 'workflow');
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', install);
  } else {
    install();
  }
})(window.wp);
