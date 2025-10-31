/**
 * @file
 * Global utilities.
 *
 */
(function (Drupal) {

  'use strict';

  Drupal.behaviors.sentinel_portal = {
    attach: function (context, settings) {
      // Sidebar collapsible menus: hide submenus by default via CSS, toggle with class.
      var sidebar = context.querySelector('#sidebar_first.sidebar nav');
      if (!sidebar) { return; }

      var items = sidebar.querySelectorAll('ul.nav > li.nav-item.menu-item--expanded');
      items.forEach(function (item) {
        if (item.dataset.collapsibleInit === '1') { return; }
        item.dataset.collapsibleInit = '1';

        // Find direct children - use children property for better compatibility
        var link = Array.from(item.children).find(function(child) {
          return child.tagName === 'A' && child.classList.contains('nav-link');
        });
        var submenu = Array.from(item.children).find(function(child) {
          return child.tagName === 'UL';
        });
        if (!link || !submenu) { return; }

        // Start collapsed.
        item.classList.remove('is-open');

        // Toggle on click.
        link.addEventListener('click', function (e) {
          // If submenu exists, toggle open/close instead of navigating.
          if (submenu) {
            e.preventDefault();
            item.classList.toggle('is-open');
          }
        }, { passive: false });
      });
    }
  };

})(Drupal);
