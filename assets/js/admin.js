/**
 * AHM Core — Admin JavaScript
 *
 * Quality slider, bulk conversion queue, and cache manager.
 *
 * @package AHM_Core
 */

(function ($) {
    'use strict';

    /* ============================================================
       Quality Slider
       ============================================================ */

    var $slider = $('#ahm_quality');
    var $output = $('#ahm_quality_output');

    if ($slider.length) {
        $slider.on('input', function () {
            $output.text(this.value);
        });
    }

    /* ============================================================
       Bulk Conversion
       ============================================================ */

    var $btn          = $('#ahm-btn-bulk');
    var $progressWrap = $('#ahm-bulk-progress');
    var $progressBar  = $('#ahm-progress-bar');
    var $progressText = $('#ahm-progress-text');
    var $progressLog  = $('#ahm-progress-log');

    if ($btn.length && typeof ahmAdmin !== 'undefined') {

        var isRunning = false;

        $btn.on('click', function () {
            if (isRunning) return;
            if (!confirm(ahmAdmin.i18n.confirm)) return;
            startBulk();
        });

        function startBulk() {
            isRunning = true;
            $btn.prop('disabled', true).text(ahmAdmin.i18n.scanning);
            $progressWrap.show();
            $progressBar.css('width', '0%').text('0%');
            $progressText.text(ahmAdmin.i18n.scanning);
            $progressLog.empty().removeClass('has-entries');

            $.post(ahmAdmin.ajaxUrl, {
                action: 'ahm_get_unconverted',
                nonce:  ahmAdmin.nonce
            }, function (res) {
                if (!res.success || !res.data.ids.length) {
                    $progressText.text(ahmAdmin.i18n.noImages);
                    bulkFinish();
                    return;
                }
                processQueue(res.data.ids, 0, res.data.total);
            }).fail(function () {
                $progressText.text(ahmAdmin.i18n.error);
                bulkFinish();
            });
        }

        function processQueue(ids, idx, total) {
            if (idx >= ids.length) {
                $progressBar.css('width', '100%').text('100%');
                $progressText.text('Updating Elementor CSS files…');

                // After all images are converted, rewrite Elementor CSS files.
                $.post(ahmAdmin.ajaxUrl, {
                    action: 'ahm_rewrite_elementor_css',
                    nonce:  ahmAdmin.nonce
                }, function (res) {
                    if (res.success && res.data.count > 0) {
                        bulkLog('✓ ' + res.data.message, 'success');
                    }
                }).always(function () {
                    $progressText.text(ahmAdmin.i18n.complete);
                    bulkFinish();
                });
                return;
            }

            var pct = Math.round((idx / total) * 100);
            $progressBar.css('width', pct + '%').text(pct + '%');
            $progressText.text(
                ahmAdmin.i18n.converting + ' ' + (idx + 1) + ' ' + ahmAdmin.i18n.of + ' ' + total + '…'
            );

            $.post(ahmAdmin.ajaxUrl, {
                action:        'ahm_bulk_convert',
                nonce:         ahmAdmin.nonce,
                attachment_id: ids[idx]
            }, function (res) {
                var d     = res.data || {};
                var title = d.title || 'ID ' + ids[idx];
                if (res.success) {
                    bulkLog('✓ ' + title + ' — ' + d.message, 'success');
                } else {
                    bulkLog('✗ ' + title + ' — ' + d.message, 'error');
                }
            }).fail(function () {
                bulkLog('✗ ID ' + ids[idx] + ' — Network error', 'error');
            }).always(function () {
                processQueue(ids, idx + 1, total);
            });
        }

        function bulkLog(text, type) {
            $progressLog.addClass('has-entries');
            $progressLog.append(
                $('<div/>').addClass('ahm-log-entry--' + type).text(text)
            );
            $progressLog.scrollTop($progressLog[0].scrollHeight);
        }

        function bulkFinish() {
            isRunning = false;
            $btn.prop('disabled', false).text('Start Bulk Conversion');
        }
    }

    /* ============================================================
       Cache Manager — Sequential Clear
       ============================================================ */

    var $clearBtn   = $('#ahm-btn-clear-all');
    var $stepsWrap  = $('#ahm-cache-steps');

    if ($clearBtn.length && typeof ahmAdmin !== 'undefined') {

        var cacheRunning = false;

        $clearBtn.on('click', function () {
            if (cacheRunning) return;
            cacheRunning = true;
            $clearBtn.prop('disabled', true);
            $stepsWrap.show();

            // Collect steps from the DOM (respects which ones are rendered).
            var steps = [];
            $stepsWrap.find('.ahm-cache-step').each(function () {
                steps.push($(this));
            });

            runCacheSteps(steps, 0);
        });

        function runCacheSteps(steps, idx) {
            if (idx >= steps.length) {
                cacheRunning = false;
                $clearBtn.prop('disabled', false);
                return;
            }

            var $step  = steps[idx];
            var stepId = $step.data('step');
            $step.addClass('is-active');
            $step.find('.ahm-step-icon').text('⏳');

            var actionMap = {
                'elementor':    'ahm_clear_elementor_cache',
                'webp-rewrite': 'ahm_rewrite_elementor_css',
                'rocket-rucss': 'ahm_clear_rocket_rucss',
                'rocket-cache': 'ahm_clear_rocket_cache'
            };

            var action = actionMap[stepId];
            if (!action) {
                markStep($step, 'skipped', 'Unknown step');
                runCacheSteps(steps, idx + 1);
                return;
            }

            $.post(ahmAdmin.ajaxUrl, {
                action: action,
                nonce:  ahmAdmin.nonce
            }, function (res) {
                if (res.success) {
                    markStep($step, 'done', res.data.message);
                } else {
                    markStep($step, 'error', (res.data && res.data.message) || 'Failed');
                }
            }).fail(function () {
                markStep($step, 'error', 'Network error');
            }).always(function () {
                runCacheSteps(steps, idx + 1);
            });
        }

        function markStep($step, status, message) {
            $step.removeClass('is-active');
            $step.addClass('is-' + status);

            var icons = { done: '✅', error: '❌', skipped: '⚠️' };
            $step.find('.ahm-step-icon').text(icons[status] || '—');
            $step.find('.ahm-step-status').text(message || '');
        }
    }

})(jQuery);
