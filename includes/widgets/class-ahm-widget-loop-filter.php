<?php
/**
 * Loop Grid Filter Buttons Widget for Elementor
 *
 * Lean filter buttons widget for Elementor Loop Grids.
 * Dynamically queries categories from the archive/query, or allows manual category selection.
 * Filters target Loop Grid by native WordPress post classes (e.g. .category-medical).
 *
 * @package AHM_Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;

class AHM_Widget_Loop_Filter extends Widget_Base
{
    public function get_name(): string
    {
        return 'ahm_loop_filter';
    }

    public function get_title(): string
    {
        return esc_html__('Loop Grid Filter Buttons', 'ahm-core');
    }

    public function get_icon(): string
    {
        return 'eicon-filter';
    }

    public function get_categories(): array
    {
        return ['ahm-widgets', 'general'];
    }

    public function get_keywords(): array
    {
        return ['filter', 'loop', 'grid', 'buttons', 'categories', 'archive', 'ahm'];
    }

    public function get_style_depends(): array
    {
        return ['ahm-loop-filter-css'];
    }

    public function get_script_depends(): array
    {
        return ['ahm-loop-filter-js'];
    }

    /**
     * Get list of all categories for manual selection.
     *
     * @return array<string, string>
     */
    private function get_all_categories_options(): array
    {
        $categories = get_terms([
            'taxonomy'   => 'category',
            'hide_empty' => false,
        ]);

        $options = [];
        if (! empty($categories) && ! is_wp_error($categories)) {
            foreach ($categories as $cat) {
                $options[$cat->slug] = $cat->name . ' (' . $cat->slug . ')';
            }
        }

        return $options;
    }

    protected function register_controls(): void
    {
        /*--------------------------------------------------------------
         * Content Tab
         *------------------------------------------------------------*/
        $this->start_controls_section(
            'section_filter_content',
            [
                'label' => esc_html__('Filter Settings', 'ahm-core'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'target_grid',
            [
                'label'       => esc_html__('Target Loop Grid Selector', 'ahm-core'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '.elementor-widget-loop-grid',
                'placeholder' => '.elementor-widget-loop-grid or #my-grid-id',
                'label_block' => true,
                'description' => esc_html__('Enter the CSS selector or ID of the Loop Grid to filter. If target does not exist, filter silently stops.', 'ahm-core'),
            ]
        );

        $this->add_control(
            'all_label',
            [
                'label'   => esc_html__('"All" Button Label', 'ahm-core'),
                'type'    => Controls_Manager::TEXT,
                'default' => esc_html__('All', 'ahm-core'),
            ]
        );

        $this->add_control(
            'custom_categories',
            [
                'label'       => esc_html__('Select Categories (Optional)', 'ahm-core'),
                'type'        => Controls_Manager::SELECT2,
                'multiple'    => true,
                'options'     => $this->get_all_categories_options(),
                'label_block' => true,
                'description' => esc_html__('Leave empty to automatically pull categories belonging to the current archive query.', 'ahm-core'),
            ]
        );

        $this->end_controls_section();

        /*--------------------------------------------------------------
         * Style Tab: Layout & Basic Buttons
         *------------------------------------------------------------*/
        $this->start_controls_section(
            'section_filter_style',
            [
                'label' => esc_html__('Layout & Alignment', 'ahm-core'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'alignment',
            [
                'label'   => esc_html__('Alignment', 'ahm-core'),
                'type'    => Controls_Manager::CHOOSE,
                'options' => [
                    'flex-start' => [
                        'title' => esc_html__('Start', 'ahm-core'),
                        'icon'  => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'ahm-core'),
                        'icon'  => 'eicon-text-align-center',
                    ],
                    'flex-end' => [
                        'title' => esc_html__('End', 'ahm-core'),
                        'icon'  => 'eicon-text-align-right',
                    ],
                ],
                'default'   => 'flex-end',
                'selectors' => [
                    '{{WRAPPER}} .ahm-filter-buttons' => 'justify-content: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'gap',
            [
                'label'      => esc_html__('Gap', 'ahm-core'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range'      => [
                    'px' => ['min' => 0, 'max' => 30],
                ],
                'default'    => [
                    'size' => 8,
                    'unit' => 'px',
                ],
                'selectors'  => [
                    '{{WRAPPER}} .ahm-filter-buttons' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_button_style',
            [
                'label' => esc_html__('Buttons', 'ahm-core'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'button_typography',
                'selector' => '{{WRAPPER}} .ahm-filter-btn',
            ]
        );

        $this->add_responsive_control(
            'button_padding',
            [
                'label'      => esc_html__('Padding', 'ahm-core'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .ahm-filter-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'button_radius',
            [
                'label'      => esc_html__('Border Radius', 'ahm-core'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .ahm-filter-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'button_border',
                'selector' => '{{WRAPPER}} .ahm-filter-btn',
            ]
        );

        $this->start_controls_tabs('tabs_button_colors');

        $this->start_controls_tab('tab_color_normal', ['label' => esc_html__('Normal', 'ahm-core')]);
        $this->add_control(
            'btn_color',
            [
                'label'     => esc_html__('Text Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .ahm-filter-btn:not(.is-active)' => 'color: {{VALUE}};'],
            ]
        );
        $this->add_control(
            'btn_bg_color',
            [
                'label'     => esc_html__('Background Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .ahm-filter-btn:not(.is-active)' => 'background-color: {{VALUE}};'],
            ]
        );
        $this->add_control(
            'btn_border_color',
            [
                'label'     => esc_html__('Border Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .ahm-filter-btn:not(.is-active)' => 'border-color: {{VALUE}};'],
            ]
        );
        $this->end_controls_tab();

        $this->start_controls_tab('tab_color_hover', ['label' => esc_html__('Hover', 'ahm-core')]);
        $this->add_control(
            'btn_hover_color',
            [
                'label'     => esc_html__('Text Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .ahm-filter-btn:not(.is-active):hover' => 'color: {{VALUE}};'],
            ]
        );
        $this->add_control(
            'btn_hover_bg_color',
            [
                'label'     => esc_html__('Background Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .ahm-filter-btn:not(.is-active):hover' => 'background-color: {{VALUE}};'],
            ]
        );
        $this->add_control(
            'btn_hover_border_color',
            [
                'label'     => esc_html__('Border Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .ahm-filter-btn:not(.is-active):hover' => 'border-color: {{VALUE}};'],
            ]
        );
        $this->end_controls_tab();

        $this->start_controls_tab('tab_color_active', ['label' => esc_html__('Active', 'ahm-core')]);
        $this->add_control(
            'btn_active_color',
            [
                'label'     => esc_html__('Text Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .ahm-filter-btn.is-active' => 'color: {{VALUE}};'],
            ]
        );
        $this->add_control(
            'btn_active_bg_color',
            [
                'label'     => esc_html__('Background Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .ahm-filter-btn.is-active' => 'background-color: {{VALUE}};'],
            ]
        );
        $this->add_control(
            'btn_active_border_color',
            [
                'label'     => esc_html__('Border Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .ahm-filter-btn.is-active' => 'border-color: {{VALUE}};'],
            ]
        );
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /**
     * Resolve categories: either from custom field or current archive query.
     *
     * @param array<string> $custom_slugs
     * @return array<\WP_Term>
     */
    private function resolve_categories(array $custom_slugs): array
    {
        // 1. If manual categories selected in widget setting
        if (! empty($custom_slugs)) {
            $terms = get_terms([
                'taxonomy'   => 'category',
                'slug'       => $custom_slugs,
                'hide_empty' => false,
            ]);
            return is_array($terms) ? $terms : [];
        }

        // 2. Query categories belonging to the current archive post type
        global $wpdb;
        $post_type = 'skin-condition';
        if (is_post_type_archive()) {
            $post_type = (string) get_query_var('post_type');
        }

        $term_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT tt.term_id 
                 FROM {$wpdb->term_taxonomy} tt
                 INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                 WHERE tt.taxonomy = 'category' AND p.post_type = %s AND p.post_status = 'publish'",
                $post_type
            )
        );

        if (! empty($term_ids)) {
            $terms = get_terms([
                'taxonomy'   => 'category',
                'include'    => $term_ids,
                'hide_empty' => false,
                'orderby'    => 'count',
                'order'      => 'DESC',
            ]);
            return is_array($terms) ? $terms : [];
        }

        // 3. Fallback: all non-empty categories
        $terms = get_terms([
            'taxonomy'   => 'category',
            'hide_empty' => true,
        ]);

        return is_array($terms) ? $terms : [];
    }

    protected function render(): void
    {
        $settings     = $this->get_settings_for_display();
        $target_grid  = ! empty($settings['target_grid']) ? esc_attr(trim($settings['target_grid'])) : '.elementor-widget-loop-grid';
        $all_label    = ! empty($settings['all_label']) ? $settings['all_label'] : esc_html__('All', 'ahm-core');
        $custom_slugs = ! empty($settings['custom_categories']) && is_array($settings['custom_categories']) ? $settings['custom_categories'] : [];

        $categories = $this->resolve_categories($custom_slugs);

        // Editor preview fallback if template preview has no categories
        if (empty($categories) && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            $categories = [
                (object) ['name' => 'Medical', 'slug' => 'medical'],
                (object) ['name' => 'Laser', 'slug' => 'laser'],
                (object) ['name' => 'Cosmetic', 'slug' => 'cosmetic'],
            ];
        }

        ?>
        <div class="ahm-loop-filter" data-target="<?php echo esc_attr($target_grid); ?>">
            <div class="ahm-filter-buttons" role="tablist">
                <button type="button" class="ahm-filter-btn is-active" data-filter="all" role="tab" aria-selected="true">
                    <?php echo esc_html($all_label); ?>
                </button>
                <?php foreach ($categories as $cat) : ?>
                    <button type="button"
                            class="ahm-filter-btn"
                            data-filter="category-<?php echo esc_attr($cat->slug ?? sanitize_title($cat->name)); ?>"
                            role="tab"
                            aria-selected="false">
                        <?php echo esc_html($cat->name); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
