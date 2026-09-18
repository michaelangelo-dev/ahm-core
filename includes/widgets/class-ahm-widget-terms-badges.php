<?php
/**
 * Terms Badges / Pills Widget for Elementor
 *
 * Displays taxonomy terms (e.g. Tags, Categories) for the current post as an unstyled
 * or fully customizable semantic <ul> <li> list of pill badges.
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
use Elementor\Group_Control_Box_Shadow;

class AHM_Widget_Terms_Badges extends Widget_Base
{
    /**
     * Get widget name.
     */
    public function get_name(): string
    {
        return 'ahm_terms_badges';
    }

    /**
     * Get widget title.
     */
    public function get_title(): string
    {
        return esc_html__('Terms Badges / Pills', 'ahm-core');
    }

    /**
     * Get widget icon.
     */
    public function get_icon(): string
    {
        return 'eicon-tags';
    }

    /**
     * Get widget categories.
     */
    public function get_categories(): array
    {
        return ['ahm-widgets', 'general'];
    }

    /**
     * Get widget keywords.
     */
    public function get_keywords(): array
    {
        return ['terms', 'tags', 'categories', 'pills', 'badges', 'taxonomy', 'ahm'];
    }

    /**
     * Retrieve available public taxonomies.
     *
     * @return array<string, string>
     */
    private function get_taxonomy_options(): array
    {
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $options    = [];

        foreach ($taxonomies as $tax) {
            $options[$tax->name] = $tax->label . ' (' . $tax->name . ')';
        }

        return ! empty($options) ? $options : ['post_tag' => 'Tags (post_tag)'];
    }

    /**
     * Register widget controls.
     */
    protected function register_controls(): void
    {
        /*--------------------------------------------------------------
         * Content Tab: Settings
         *------------------------------------------------------------*/
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__('Terms Settings', 'ahm-core'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'taxonomy',
            [
                'label'       => esc_html__('Taxonomy', 'ahm-core'),
                'type'        => Controls_Manager::SELECT,
                'default'     => 'post_tag',
                'options'     => $this->get_taxonomy_options(),
                'description' => esc_html__('Select which taxonomy to display terms from (e.g. Tags, Categories).', 'ahm-core'),
            ]
        );

        $this->add_control(
            'max_terms',
            [
                'label'       => esc_html__('Max Terms to Display', 'ahm-core'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 1,
                'max'         => 20,
                'step'        => 1,
                'default'     => '',
                'description' => esc_html__('Leave empty to display all assigned terms.', 'ahm-core'),
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label'   => esc_html__('Order By', 'ahm-core'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'name',
                'options' => [
                    'name'       => esc_html__('Name', 'ahm-core'),
                    'count'      => esc_html__('Count', 'ahm-core'),
                    'term_id'    => esc_html__('ID', 'ahm-core'),
                    'term_order' => esc_html__('Term Order', 'ahm-core'),
                ],
            ]
        );

        $this->add_control(
            'order',
            [
                'label'   => esc_html__('Order', 'ahm-core'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'ASC',
                'options' => [
                    'ASC'  => esc_html__('Ascending (A-Z)', 'ahm-core'),
                    'DESC' => esc_html__('Descending (Z-A)', 'ahm-core'),
                ],
            ]
        );

        $this->add_control(
            'link_to_archive',
            [
                'label'        => esc_html__('Link to Term Archive', 'ahm-core'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Yes', 'ahm-core'),
                'label_off'    => esc_html__('No', 'ahm-core'),
                'return_value' => 'yes',
                'default'      => 'no',
            ]
        );

        $this->end_controls_section();

        /*--------------------------------------------------------------
         * Style Tab: List Container (UL)
         *------------------------------------------------------------*/
        $this->start_controls_section(
            'section_style_list',
            [
                'label' => esc_html__('List Layout', 'ahm-core'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'alignment',
            [
                'label'     => esc_html__('Alignment', 'ahm-core'),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'flex-start' => [
                        'title' => esc_html__('Start', 'ahm-core'),
                        'icon'  => 'eicon-text-align-left',
                    ],
                    'center'     => [
                        'title' => esc_html__('Center', 'ahm-core'),
                        'icon'  => 'eicon-text-align-center',
                    ],
                    'flex-end'   => [
                        'title' => esc_html__('End', 'ahm-core'),
                        'icon'  => 'eicon-text-align-right',
                    ],
                ],
                'default'   => 'flex-start',
                'selectors' => [
                    '{{WRAPPER}} .ahm-terms-list' => 'justify-content: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'items_gap',
            [
                'label'      => esc_html__('Gap Between Badges', 'ahm-core'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range'      => [
                    'px' => ['min' => 0, 'max' => 30, 'step' => 1],
                ],
                'default'    => [
                    'unit' => 'px',
                    'size' => 6,
                ],
                'selectors'  => [
                    '{{WRAPPER}} .ahm-terms-list' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'list_margin',
            [
                'label'      => esc_html__('Margin', 'ahm-core'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'default'    => [
                    'top'      => '6',
                    'right'    => '0',
                    'bottom'   => '8',
                    'left'     => '0',
                    'unit'     => 'px',
                    'isLinked' => false,
                ],
                'selectors'  => [
                    '{{WRAPPER}} .ahm-terms-list' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        /*--------------------------------------------------------------
         * Style Tab: Badge Pills (LI / SPAN / A)
         *------------------------------------------------------------*/
        $this->start_controls_section(
            'section_style_pills',
            [
                'label' => esc_html__('Badge Pills', 'ahm-core'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'pill_typography',
                'selector' => '{{WRAPPER}} .ahm-terms-item, {{WRAPPER}} .ahm-terms-item a',
            ]
        );

        $this->start_controls_tabs('tabs_pill_style');

        // Normal State Tab
        $this->start_controls_tab(
            'tab_pill_normal',
            [
                'label' => esc_html__('Normal', 'ahm-core'),
            ]
        );

        $this->add_control(
            'pill_text_color',
            [
                'label'     => esc_html__('Text Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#32587B',
                'selectors' => [
                    '{{WRAPPER}} .ahm-terms-item'   => 'color: {{VALUE}};',
                    '{{WRAPPER}} .ahm-terms-item a' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pill_bg_color',
            [
                'label'     => esc_html__('Background Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#F3F7FB',
                'selectors' => [
                    '{{WRAPPER}} .ahm-terms-item' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'pill_border',
                'selector' => '{{WRAPPER}} .ahm-terms-item',
            ]
        );

        $this->end_controls_tab();

        // Hover State Tab
        $this->start_controls_tab(
            'tab_pill_hover',
            [
                'label' => esc_html__('Hover', 'ahm-core'),
            ]
        );

        $this->add_control(
            'pill_text_color_hover',
            [
                'label'     => esc_html__('Text Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ahm-terms-item:hover'   => 'color: {{VALUE}};',
                    '{{WRAPPER}} .ahm-terms-item:hover a' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pill_bg_color_hover',
            [
                'label'     => esc_html__('Background Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ahm-terms-item:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pill_border_color_hover',
            [
                'label'     => esc_html__('Border Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ahm-terms-item:hover' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_responsive_control(
            'pill_border_radius',
            [
                'label'      => esc_html__('Border Radius', 'ahm-core'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default'    => [
                    'top'      => '999',
                    'right'    => '999',
                    'bottom'   => '999',
                    'left'     => '999',
                    'unit'     => 'px',
                    'isLinked' => true,
                ],
                'separator'  => 'before',
                'selectors'  => [
                    '{{WRAPPER}} .ahm-terms-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'pill_padding',
            [
                'label'      => esc_html__('Padding', 'ahm-core'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default'    => [
                    'top'      => '4',
                    'right'    => '12',
                    'bottom'   => '4',
                    'left'     => '12',
                    'unit'     => 'px',
                    'isLinked' => false,
                ],
                'selectors'  => [
                    '{{WRAPPER}} .ahm-terms-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'pill_box_shadow',
                'selector' => '{{WRAPPER}} .ahm-terms-item',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend.
     */
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $taxonomy = ! empty($settings['taxonomy']) ? sanitize_key($settings['taxonomy']) : 'post_tag';
        $link     = ! empty($settings['link_to_archive']) && 'yes' === $settings['link_to_archive'];

        $post_id = get_the_ID();
        $terms   = [];

        if ($post_id) {
            $terms = get_the_terms($post_id, $taxonomy);
        }

        // Live Editor Preview Fallback: Display sample terms if previewing in empty canvas/template editor
        if (empty($terms) || is_wp_error($terms)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                $terms = [
                    (object) ['term_id' => 1, 'name' => 'Sample Badge 1', 'slug' => 'sample-1'],
                    (object) ['term_id' => 2, 'name' => 'Sample Badge 2', 'slug' => 'sample-2'],
                ];
            } else {
                return;
            }
        }

        // Apply Sorting
        $orderby = $settings['orderby'] ?? 'name';
        $order   = $settings['order'] ?? 'ASC';

        usort($terms, function ($a, $b) use ($orderby, $order) {
            $val_a = $a->$orderby ?? $a->name;
            $val_b = $b->$orderby ?? $b->name;
            $cmp   = is_numeric($val_a) && is_numeric($val_b) ? $val_a <=> $val_b : strcasecmp((string) $val_a, (string) $val_b);
            return 'DESC' === $order ? -$cmp : $cmp;
        });

        // Limit count if configured
        if (! empty($settings['max_terms']) && is_numeric($settings['max_terms'])) {
            $terms = array_slice($terms, 0, (int) $settings['max_terms']);
        }

        ?>
        <ul class="ahm-terms-list">
            <?php foreach ($terms as $term) : ?>
                <li class="ahm-terms-item ahm-term-<?php echo esc_attr($term->slug ?? sanitize_title($term->name)); ?>">
                    <?php if ($link && ! empty($term->term_id) && ! \Elementor\Plugin::$instance->editor->is_edit_mode()) : ?>
                        <a href="<?php echo esc_url(get_term_link($term, $taxonomy)); ?>">
                            <?php echo esc_html($term->name); ?>
                        </a>
                    <?php else : ?>
                        <span class="ahm-term-text">
                            <?php echo esc_html($term->name); ?>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <style>
            .ahm-terms-list {
                list-style: none !important;
                padding: 0 !important;
                margin: 6px 0 !important;
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
            }
            .ahm-terms-item {
                display: inline-flex !important;
                align-items: center !important;
                box-sizing: border-box !important;
                line-height: 1.4 !important;
                white-space: nowrap !important;
                transition: all 0.2s ease !important;
            }
            .ahm-terms-item a {
                color: inherit !important;
                text-decoration: none !important;
            }
        </style>
        <?php
    }
}
