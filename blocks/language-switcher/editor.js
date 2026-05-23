(function (wp) {
  'use strict';

  if (!wp || !wp.blocks || !wp.element || !wp.i18n) {
    return;
  }

  var el = wp.element.createElement;
  var __ = wp.i18n.__;
  var ServerSideRender = wp.serverSideRender;

  function Preview() {
    if (ServerSideRender) {
      return el(ServerSideRender, {
        block: 'webactueel/translate-language-switcher',
        attributes: {}
      });
    }

    return el('div', { className: 'wat-block-preview-fallback' },
      __('Webactueel taalkiezer wordt op de frontend weergegeven.', 'webactueel-translate-language-dropdowns')
    );
  }

  wp.blocks.registerBlockType('webactueel/translate-language-switcher', {
    edit: Preview,
    save: function () {
      return null;
    }
  });
}(window.wp || {}));
