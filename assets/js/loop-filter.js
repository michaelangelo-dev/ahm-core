/**
 * AHM Loop Grid Filter
 *
 * Simple class-based filtering for Elementor Loop Grids.
 *
 * @package AHM_Core
 */
(function () {
    'use strict';

    function initFilter(wrap) {
        var selector = (wrap.getAttribute('data-target') || '').trim();
        if (!selector) return;

        var targetGrid = document.querySelector(selector);
        // If target selector does not exist, silently stop
        if (!targetGrid) return;

        var buttons = wrap.querySelectorAll('.ahm-filter-btn');
        if (!buttons.length) return;

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();

                // Toggle active state
                buttons.forEach(function (b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-selected', 'false');
                });
                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');

                var filter = btn.getAttribute('data-filter'); // 'all' or 'category-medical'
                var items = targetGrid.querySelectorAll('.e-loop-item');

                items.forEach(function (item) {
                    if (filter === 'all' || item.classList.contains(filter)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });

                // Dispatch resize for grid reflow
                window.dispatchEvent(new Event('resize'));
            });
        });
    }

    function run() {
        document.querySelectorAll('.ahm-loop-filter').forEach(initFilter);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }

    // Elementor editor preview hook
    window.addEventListener('elementor/frontend/init', function () {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction(
                'frontend/element_ready/ahm_loop_filter.default',
                function ($scope) {
                    var wrap = $scope[0]
                        ? ($scope[0].classList.contains('ahm-loop-filter') ? $scope[0] : $scope[0].querySelector('.ahm-loop-filter'))
                        : null;
                    if (wrap) initFilter(wrap);
                }
            );
        }
    });
})();
