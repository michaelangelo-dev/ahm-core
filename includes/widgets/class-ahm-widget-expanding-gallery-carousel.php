<?php
/**
 * Elementor Widget: Expanding Gallery Carousel
 *
 * Custom Elementor widget rendering an interactive expanding card carousel
 * with custom slide templates, color/gradient overlays, 60fps smooth width transitions,
 * and parent-scoped header navigation.
 *
 * @package AHM_Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Icons_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Plugin;

final class AHM_Widget_Expanding_Gallery_Carousel extends Widget_Base
{
    public function get_name(): string
    {
        return 'ahm_expanding_gallery_carousel';
    }

    public function get_title(): string
    {
        return esc_html__('Expanding Gallery Carousel', 'ahm-core');
    }

    public function get_icon(): string
    {
        return 'eicon-slides';
    }

    public function get_categories(): array
    {
        return ['ahm-widgets', 'general'];
    }

    public function get_keywords(): array
    {
        return ['gallery', 'carousel', 'slider', 'expanding', 'cards', 'accordion', 'template', 'clinic', 'ahm'];
    }

    public function get_style_depends(): array
    {
        return ['ahm-expanding-gallery-carousel-css'];
    }

    public function get_script_depends(): array
    {
        return ['swiper', 'ahm-expanding-gallery-carousel-js'];
    }

    /**
     * Retrieve list of saved Elementor templates for dropdown selection.
     *
     * @return array<int|string, string>
     */
    private function get_elementor_templates(): array
    {
        $templates = [
            '' => esc_html__('— None (Use Eyebrow & Heading Fields) —', 'ahm-core'),
        ];

        $posts = get_posts([
            'post_type'      => 'elementor_library',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        if (! empty($posts)) {
            foreach ($posts as $post) {
                $doc_type   = get_post_meta($post->ID, '_elementor_template_type', true);
                $type_label = $doc_type ? ' (' . ucfirst((string) $doc_type) . ')' : '';
                $templates[$post->ID] = esc_html($post->post_title) . $type_label;
            }
        }

        return $templates;
    }

    protected function register_controls(): void
    {
        /*--------------------------------------------------------------
         * Content Tab: Slides Repeater
         *------------------------------------------------------------*/
        $this->start_controls_section(
            'section_slides',
            [
                'label' => esc_html__('Slides', 'ahm-core'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'slide_title',
            [
                'label'       => esc_html__('Title / Label', 'ahm-core'),
                'type'        => Controls_Manager::TEXT,
                'default'     => esc_html__('Slide Title', 'ahm-core'),
                'placeholder' => esc_html__('Slide Title', 'ahm-core'),
                'label_block' => true,
            ]
        );

        $repeater->add_control(
            'image',
            [
                'label'   => esc_html__('Background Image', 'ahm-core'),
                'type'    => Controls_Manager::MEDIA,
                'default' => [
                    'url' => content_url('/uploads/2026/09/kent-clinic-image-1.webp'),
                ],
            ]
        );

        $repeater->add_control(
            'vertical_title',
            [
                'label'       => esc_html__('Title (Vertical / Collapsed)', 'ahm-core'),
                'type'        => Controls_Manager::TEXT,
                'description' => esc_html__('Vertical text displayed when this slide is collapsed. Leave empty to inherit Slide Title.', 'ahm-core'),
                'placeholder' => esc_html__('Vertical label', 'ahm-core'),
            ]
        );

        $has_template_query = class_exists('\ElementorPro\Modules\QueryControl\Controls\Template_Query')
            && class_exists('\ElementorPro\Modules\QueryControl\Module');

        if ($has_template_query) {
            $document_types = Plugin::instance()->documents->get_document_types([
                'show_in_library' => true,
            ]);

            $repeater->add_control(
                'template_id',
                [
                    'label'        => esc_html__('Slide Template (Optional)', 'ahm-core'),
                    'type'         => \ElementorPro\Modules\QueryControl\Controls\Template_Query::CONTROL_ID,
                    'label_block'  => true,
                    'autocomplete' => [
                        'object' => \ElementorPro\Modules\QueryControl\Module::QUERY_OBJECT_LIBRARY_TEMPLATE,
                        'query'  => [
                            'meta_query' => [
                                [
                                    'key'     => \Elementor\Core\Base\Document::TYPE_META_KEY,
                                    'value'   => array_keys($document_types),
                                    'compare' => 'IN',
                                ],
                            ],
                        ],
                    ],
                    'actions'      => [
                        'new'  => [
                            'visible' => false,
                        ],
                        'edit' => [
                            'visible'      => true,
                            'label'        => esc_html__('Edit Template', 'ahm-core'),
                            'after_action' => 'open_new_tab',
                        ],
                    ],
                    'description'  => esc_html__('Search and select a saved Elementor Section or Container template. If assigned, overrides Eyebrow & Heading.', 'ahm-core'),
                    'separator'    => 'before',
                ]
            );
        } else {
            $repeater->add_control(
                'template_id',
                [
                    'label'       => esc_html__('Slide Template (Optional)', 'ahm-core'),
                    'type'        => Controls_Manager::SELECT2,
                    'label_block' => true,
                    'options'     => $this->get_elementor_templates(),
                    'default'     => '',
                    'description' => esc_html__('Select a saved Elementor Section or Container template to render inside the active expanded card. If assigned, overrides Eyebrow & Heading.', 'ahm-core'),
                    'separator'   => 'before',
                ]
            );
        }

        $repeater->add_control(
            'eyebrow',
            [
                'label'       => esc_html__('Eyebrow (Fallback)', 'ahm-core'),
                'type'        => Controls_Manager::TEXT,
                'default'     => esc_html__('Consultation Rooms', 'ahm-core'),
                'placeholder' => esc_html__('Eyebrow tag', 'ahm-core'),
                'condition'   => [
                    'template_id' => '',
                ],
            ]
        );

        $repeater->add_control(
            'heading',
            [
                'label'       => esc_html__('Title Opened (Fallback)', 'ahm-core'),
                'type'        => Controls_Manager::TEXT,
                'default'     => esc_html__('Unhurried, specialist-led appointments', 'ahm-core'),
                'placeholder' => esc_html__('Opened card heading', 'ahm-core'),
                'condition'   => [
                    'template_id' => '',
                ],
            ]
        );

        $repeater->add_control(
            'overlay_type',
            [
                'label'     => esc_html__('Overlay Style', 'ahm-core'),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'color'    => [
                        'title' => esc_html__('Solid Color', 'ahm-core'),
                        'icon'  => 'eicon-paint-brush',
                    ],
                    'gradient' => [
                        'title' => esc_html__('Gradient', 'ahm-core'),
                        'icon'  => 'eicon-barcode',
                    ],
                ],
                'default'   => 'color',
                'separator' => 'before',
            ]
        );

        $repeater->add_control(
            'overlay_color',
            [
                'label'     => esc_html__('Overlay Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'default'   => 'rgba(12, 35, 64, 0.65)',
                'condition' => [
                    'overlay_type' => 'color',
                ],
            ]
        );

        $repeater->add_control(
            'overlay_gradient_color_a',
            [
                'label'     => esc_html__('Gradient Start (Top)', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'default'   => 'rgba(12, 35, 64, 0.15)',
                'condition' => [
                    'overlay_type' => 'gradient',
                ],
            ]
        );

        $repeater->add_control(
            'overlay_gradient_color_b',
            [
                'label'     => esc_html__('Gradient End (Bottom)', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'default'   => 'rgba(12, 35, 64, 0.88)',
                'condition' => [
                    'overlay_type' => 'gradient',
                ],
            ]
        );

        $repeater->add_control(
            'overlay_gradient_angle',
            [
                'label'      => esc_html__('Gradient Angle', 'ahm-core'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['deg'],
                'range'      => [
                    'deg' => ['min' => 0, 'max' => 360, 'step' => 5],
                ],
                'default'    => ['unit' => 'deg', 'size' => 180],
                'condition'  => [
                    'overlay_type' => 'gradient',
                ],
            ]
        );

        $this->add_control(
            'slides',
            [
                'label'       => esc_html__('Slide Items', 'ahm-core'),
                'type'        => Controls_Manager::REPEATER,
                'fields'      => $repeater->get_controls(),
                'title_field' => '{{{ slide_title }}}',
                'default'     => [
                    [
                        'slide_title'              => esc_html__('Consultation Rooms', 'ahm-core'),
                        'image'                    => ['url' => content_url('/uploads/2026/09/kent-clinic-image-1.webp')],
                        'vertical_title'           => esc_html__('Consultation Rooms', 'ahm-core'),
                        'eyebrow'                  => esc_html__('Consultation Rooms', 'ahm-core'),
                        'heading'                  => esc_html__('Unhurried, specialist-led appointments', 'ahm-core'),
                        'overlay_type'             => 'gradient',
                        'overlay_gradient_color_a' => 'rgba(12, 35, 64, 0.15)',
                        'overlay_gradient_color_b' => 'rgba(12, 35, 64, 0.88)',
                        'overlay_gradient_angle'   => ['unit' => 'deg', 'size' => 180],
                    ],
                    [
                        'slide_title'              => esc_html__('Aesthetics Suite', 'ahm-core'),
                        'image'                    => ['url' => content_url('/uploads/2026/09/kent-clinic-category-image-1.webp')],
                        'vertical_title'           => esc_html__('Aesthetics Suite', 'ahm-core'),
                        'eyebrow'                  => esc_html__('Aesthetics Suite', 'ahm-core'),
                        'heading'                  => esc_html__('Advanced non-surgical rejuvenation', 'ahm-core'),
                        'overlay_type'             => 'gradient',
                        'overlay_gradient_color_a' => 'rgba(12, 35, 64, 0.15)',
                        'overlay_gradient_color_b' => 'rgba(12, 35, 64, 0.88)',
                        'overlay_gradient_angle'   => ['unit' => 'deg', 'size' => 180],
                    ],
                    [
                        'slide_title'              => esc_html__('Mole Clinic', 'ahm-core'),
                        'image'                    => ['url' => content_url('/uploads/2026/09/kent-clinic-image-1.webp')],
                        'vertical_title'           => esc_html__('Mole Clinic', 'ahm-core'),
                        'eyebrow'                  => esc_html__('Mole Clinic', 'ahm-core'),
                        'heading'                  => esc_html__('Dermoscopic skin cancer checks & mapping', 'ahm-core'),
                        'overlay_type'             => 'gradient',
                        'overlay_gradient_color_a' => 'rgba(12, 35, 64, 0.15)',
                        'overlay_gradient_color_b' => 'rgba(12, 35, 64, 0.88)',
                        'overlay_gradient_angle'   => ['unit' => 'deg', 'size' => 180],
                    ],
                    [
                        'slide_title'              => esc_html__('Skin Treatments', 'ahm-core'),
                        'image'                    => ['url' => content_url('/uploads/2026/09/kent-clinic-category-image-1.webp')],
                        'vertical_title'           => esc_html__('Skin Treatments', 'ahm-core'),
                        'eyebrow'                  => esc_html__('Skin Treatments', 'ahm-core'),
                        'heading'                  => esc_html__('Medical-grade laser & aesthetic dermatology', 'ahm-core'),
                        'overlay_type'             => 'gradient',
                        'overlay_gradient_color_a' => 'rgba(12, 35, 64, 0.15)',
                        'overlay_gradient_color_b' => 'rgba(12, 35, 64, 0.88)',
                        'overlay_gradient_angle'   => ['unit' => 'deg', 'size' => 180],
                    ],
                    [
                        'slide_title'              => esc_html__('Skin Boosters', 'ahm-core'),
                        'image'                    => ['url' => content_url('/uploads/2026/09/kent-clinic-image-1.webp')],
                        'vertical_title'           => esc_html__('Skin Boosters', 'ahm-core'),
                        'eyebrow'                  => esc_html__('Skin Boosters', 'ahm-core'),
                        'heading'                  => esc_html__('Deep cellular hydration & skin remodeling', 'ahm-core'),
                        'overlay_type'             => 'gradient',
                        'overlay_gradient_color_a' => 'rgba(12, 35, 64, 0.15)',
                        'overlay_gradient_color_b' => 'rgba(12, 35, 64, 0.88)',
                        'overlay_gradient_angle'   => ['unit' => 'deg', 'size' => 180],
                    ],
                ],
            ]
        );

        $this->end_controls_section();

        /*--------------------------------------------------------------
         * Content Tab: Navigation Controls
         *------------------------------------------------------------*/
        $this->start_controls_section(
            'section_navigation',
            [
                'label' => esc_html__('Navigation', 'ahm-core'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_arrows',
            [
                'label'        => esc_html__('Enable Navigation Arrows', 'ahm-core'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Yes', 'ahm-core'),
                'label_off'    => esc_html__('No', 'ahm-core'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'prev_icon',
            [
                'label'       => esc_html__('Previous Icon', 'ahm-core'),
                'type'        => Controls_Manager::ICONS,
                'default'     => [
                    'value'   => 'fas fa-arrow-left',
                    'library' => 'fa-solid',
                ],
                'condition'   => ['show_arrows' => 'yes'],
            ]
        );

        $this->add_control(
            'next_icon',
            [
                'label'       => esc_html__('Next Icon', 'ahm-core'),
                'type'        => Controls_Manager::ICONS,
                'default'     => [
                    'value'   => 'fas fa-arrow-right',
                    'library' => 'fa-solid',
                ],
                'condition'   => ['show_arrows' => 'yes'],
            ]
        );

        $this->add_control(
            'nav_target_selector',
            [
                'label'       => esc_html__('Parent Header Container Class', 'ahm-core'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '.gallery-nav-box',
                'placeholder' => '.gallery-nav-box',
                'description' => esc_html__('Class of the container in the header row where arrows will be placed. If not present in the parent section, arrows will not be visible.', 'ahm-core'),
                'condition'   => ['show_arrows' => 'yes'],
            ]
        );

        $this->end_controls_section();

        /*--------------------------------------------------------------
         * Style Tab: Card Geometry & Responsive Widths
         *------------------------------------------------------------*/
        $this->start_controls_section(
            'section_style_cards',
            [
                'label' => esc_html__('Card Geometry & Spacing', 'ahm-core'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'space_between',
            [
                'label'      => esc_html__('Space Between Slides (Gap)', 'ahm-core'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => [
                    'px' => ['min' => 0, 'max' => 60, 'step' => 1],
                ],
                'desktop_default' => ['unit' => 'px', 'size' => 16],
                'tablet_default'  => ['unit' => 'px', 'size' => 12],
                'mobile_default'  => ['unit' => 'px', 'size' => 8],
                'selectors'  => [
                    '{{WRAPPER}} .swiper-wrapper' => 'gap: {{SIZE}}{{UNIT}} !important; --slide-gap: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}}'                 => '--slide-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'slide_height',
            [
                'label'      => esc_html__('Slide Height / Active Height', 'ahm-core'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => [
                    'px' => ['min' => 200, 'max' => 800, 'step' => 10],
                ],
                'desktop_default' => ['unit' => 'px', 'size' => 520],
                'tablet_default'  => ['unit' => 'px', 'size' => 380],
                'mobile_default'  => ['unit' => 'px', 'size' => 320],
                'selectors'  => [
                    '{{WRAPPER}}'                           => '--slide-height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .swiper-slide.is-expanded' => 'height: {{SIZE}}{{UNIT}} !important; max-height: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->add_responsive_control(
            'collapsed_height',
            [
                'label'      => esc_html__('Collapsed Card Height (Stacked)', 'ahm-core'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => [
                    'px' => ['min' => 50, 'max' => 120, 'step' => 2],
                ],
                'tablet_default'  => ['unit' => 'px', 'size' => 72],
                'mobile_default'  => ['unit' => 'px', 'size' => 72],
                'selectors'  => [
                    '{{WRAPPER}}'                                => '--collapsed-height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .swiper-slide:not(.is-expanded)' => 'height: {{SIZE}}{{UNIT}} !important; min-height: {{SIZE}}{{UNIT}} !important; max-height: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->add_responsive_control(
            'collapsed_width',
            [
                'label'      => esc_html__('Collapsed Card Width', 'ahm-core'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => [
                    'px' => ['min' => 60, 'max' => 250, 'step' => 5],
                ],
                'desktop_default' => ['unit' => 'px', 'size' => 135],
                'tablet_default'  => ['unit' => 'px', 'size' => 100],
                'selectors'  => [
                    '{{WRAPPER}} .swiper-slide:not(.is-expanded)' => 'width: {{SIZE}}{{UNIT}} !important; --collapsed-width: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}}'                                => '--collapsed-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'expanded_width',
            [
                'label'       => esc_html__('Expanded Card Width', 'ahm-core'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px', '%'],
                'range'       => [
                    'px' => ['min' => 300, 'max' => 900, 'step' => 4],
                    '%'  => ['min' => 40, 'max' => 100],
                ],
                'desktop_default' => ['unit' => 'px', 'size' => 616],
                'tablet_default'  => ['unit' => 'px', 'size' => 420],
                'selectors'   => [
                    '{{WRAPPER}} .swiper-slide.is-expanded' => 'width: {{SIZE}}{{UNIT}} !important; --expanded-width: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}}'                           => '--expanded-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'slide_border_radius',
            [
                'label'      => esc_html__('Border Radius', 'ahm-core'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range'      => [
                    'px' => ['min' => 0, 'max' => 50],
                ],
                'default'    => ['unit' => 'px', 'size' => 20],
                'selectors'  => [
                    '{{WRAPPER}} .swiper-slide' => 'border-radius: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->end_controls_section();

        /*--------------------------------------------------------------
         * Style Tab: Navigation Buttons
         *------------------------------------------------------------*/
        $this->start_controls_section(
            'section_style_navigation',
            [
                'label'     => esc_html__('Navigation Buttons', 'ahm-core'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['show_arrows' => 'yes'],
            ]
        );

        $this->add_responsive_control(
            'nav_btn_size',
            [
                'label'      => esc_html__('Button Size', 'ahm-core'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => [
                    'px' => ['min' => 30, 'max' => 70],
                ],
                'default'    => ['unit' => 'px', 'size' => 44],
                'selectors'  => [
                    '{{WRAPPER}} .gallery-nav-btn, .gallery-nav-box .gallery-nav-btn' => 'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important; min-width: {{SIZE}}{{UNIT}} !important; max-width: {{SIZE}}{{UNIT}} !important; min-height: {{SIZE}}{{UNIT}} !important; max-height: {{SIZE}}{{UNIT}} !important; padding: 0 !important;',
                ],
            ]
        );

        $this->add_responsive_control(
            'nav_icon_size',
            [
                'label'      => esc_html__('Icon Size', 'ahm-core'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => [
                    'px' => ['min' => 10, 'max' => 36],
                ],
                'default'    => ['unit' => 'px', 'size' => 14],
                'selectors'  => [
                    '{{WRAPPER}} .gallery-nav-btn i, {{WRAPPER}} .gallery-nav-btn svg, .gallery-nav-box .gallery-nav-btn i, .gallery-nav-box .gallery-nav-btn svg' => 'font-size: {{SIZE}}{{UNIT}} !important; width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important; min-width: {{SIZE}}{{UNIT}} !important; min-height: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->start_controls_tabs('tabs_nav_style');

        $this->start_controls_tab(
            'tab_nav_normal',
            ['label' => esc_html__('Normal', 'ahm-core')]
        );

        $this->add_control(
            'nav_btn_bg',
            [
                'label'     => esc_html__('Background Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .gallery-nav-btn, .gallery-nav-box .gallery-nav-btn, .gallery-nav-box button.gallery-nav-btn' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'nav_btn_color',
            [
                'label'     => esc_html__('Icon Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#0c2340',
                'selectors' => [
                    '{{WRAPPER}} .gallery-nav-btn, {{WRAPPER}} .gallery-nav-btn svg, .gallery-nav-box .gallery-nav-btn, .gallery-nav-box .gallery-nav-btn svg, .gallery-nav-box button.gallery-nav-btn svg' => 'color: {{VALUE}}; fill: {{VALUE}};',
                    '{{WRAPPER}} .gallery-nav-btn svg path, .gallery-nav-box .gallery-nav-btn svg path, .gallery-nav-box button.gallery-nav-btn svg path' => 'fill: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'nav_btn_border_color',
            [
                'label'     => esc_html__('Border Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#dbe2e6',
                'selectors' => [
                    '{{WRAPPER}} .gallery-nav-btn, .gallery-nav-box .gallery-nav-btn, .gallery-nav-box button.gallery-nav-btn' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_nav_hover',
            ['label' => esc_html__('Hover', 'ahm-core')]
        );

        $this->add_control(
            'nav_btn_bg_hover',
            [
                'label'     => esc_html__('Background Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gallery-nav-btn:hover, .gallery-nav-box .gallery-nav-btn:hover, {{WRAPPER}} button.gallery-nav-btn:hover, .gallery-nav-box button.gallery-nav-btn:hover' => 'background-color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'nav_btn_color_hover',
            [
                'label'     => esc_html__('Icon Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gallery-nav-btn:hover, {{WRAPPER}} .gallery-nav-btn:hover svg, .gallery-nav-box .gallery-nav-btn:hover, .gallery-nav-box .gallery-nav-btn:hover svg, {{WRAPPER}} button.gallery-nav-btn:hover svg, .gallery-nav-box button.gallery-nav-btn:hover svg' => 'color: {{VALUE}} !important; fill: {{VALUE}} !important;',
                    '{{WRAPPER}} .gallery-nav-btn:hover svg path, .gallery-nav-box .gallery-nav-btn:hover svg path, {{WRAPPER}} button.gallery-nav-btn:hover svg path, .gallery-nav-box button.gallery-nav-btn:hover svg path' => 'fill: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'nav_btn_border_color_hover',
            [
                'label'     => esc_html__('Border Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gallery-nav-btn:hover, .gallery-nav-box .gallery-nav-btn:hover, {{WRAPPER}} button.gallery-nav-btn:hover, .gallery-nav-box button.gallery-nav-btn:hover' => 'border-color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->end_controls_section();

        /*--------------------------------------------------------------
         * Style Tab: Slide Content & Typography
         *------------------------------------------------------------*/
        $this->start_controls_section(
            'section_style_content',
            [
                'label' => esc_html__('Slide Content & Badges', 'ahm-core'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'badge_bg',
            [
                'label'     => esc_html__('Badge Background', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .slide-badge' => 'background-color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'badge_color',
            [
                'label'     => esc_html__('Badge Text Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#192535',
                'selectors' => [
                    '{{WRAPPER}} .slide-badge' => 'color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'eyebrow_color',
            [
                'label'     => esc_html__('Slide Eyebrow Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#DE5C29',
                'selectors' => [
                    '{{WRAPPER}} .slide-eyebrow' => 'color: {{VALUE}} !important;',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'eyebrow_typography',
                'selector' => '{{WRAPPER}} .slide-eyebrow',
            ]
        );

        $this->add_control(
            'heading_color',
            [
                'label'     => esc_html__('Slide Heading Color', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .slide-heading' => 'color: {{VALUE}} !important;',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'heading_typography',
                'selector' => '{{WRAPPER}} .slide-heading',
            ]
        );

        $this->add_control(
            'vertical_title_color',
            [
                'label'     => esc_html__('Vertical Title Color (Collapsed)', 'ahm-core'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .slide-vertical-title' => 'color: {{VALUE}} !important;',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'vertical_title_typography',
                'selector' => '{{WRAPPER}} .slide-vertical-title',
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $slides   = $settings['slides'] ?? [];
        if (empty($slides)) {
            return;
        }

        $total_slides    = count($slides);
        $show_arrows     = ('yes' === ($settings['show_arrows'] ?? ''));
        $target_selector = ! empty($settings['nav_target_selector']) ? esc_attr($settings['nav_target_selector']) : '.gallery-nav-box';

        $sb_desktop = $settings['space_between']['size'] ?? 16;
        $sb_tablet  = $settings['space_between_tablet']['size'] ?? 12;
        $sb_mobile  = $settings['space_between_mobile']['size'] ?? 8;
        ?>
        <div class="gallery-section-container ahm-expanding-gallery-widget"
             data-nav-target="<?php echo esc_attr($target_selector); ?>"
             data-space-between="<?php echo esc_attr((string) $sb_desktop); ?>"
             data-space-between-tablet="<?php echo esc_attr((string) $sb_tablet); ?>"
             data-space-between-mobile="<?php echo esc_attr((string) $sb_mobile); ?>">

            <?php if ($show_arrows): ?>
                <div class="gallery-nav-box ahm-nav-source" style="display:none;">
                    <button type="button" class="gallery-nav-btn prev-btn" aria-label="<?php esc_attr_e('Previous slide', 'ahm-core'); ?>">
                        <?php
                        if (! empty($settings['prev_icon']['value'])) {
                            Icons_Manager::render_icon($settings['prev_icon'], ['aria-hidden' => 'true']);
                        } else {
                            echo '<svg aria-hidden="true" class="e-font-icon-svg e-fas-arrow-left" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M257.5 445.1l-22.2 22.2c-9.4 9.4-24.6 9.4-33.9 0L7 273c-9.4-9.4-9.4-24.6 0-33.9L201.4 44.7c9.4-9.4 24.6-9.4 33.9 0l22.2 22.2c9.5 9.5 9.3 25-.4 34.3L136.6 216H424c13.3 0 24 10.7 24 24v32c0 13.3-10.7 24-24 24H136.6l120.5 114.8c9.8 9.3 10 24.8.4 34.3z"></path></svg>';
                        }
                        ?>
                    </button>
                    <button type="button" class="gallery-nav-btn next-btn" aria-label="<?php esc_attr_e('Next slide', 'ahm-core'); ?>">
                        <?php
                        if (! empty($settings['next_icon']['value'])) {
                            Icons_Manager::render_icon($settings['next_icon'], ['aria-hidden' => 'true']);
                        } else {
                            echo '<svg aria-hidden="true" class="e-font-icon-svg e-fas-arrow-right" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M190.5 66.9l22.2-22.2c9.4-9.4 24.6-9.4 33.9 0L441 239c9.4 9.4 9.4 24.6 0 33.9L246.6 467.3c-9.4 9.4-24.6 9.4-33.9 0l-22.2-22.2c-9.5-9.5-9.3-25 .4-34.3L311.4 296H24c-13.3 0-24-10.7-24-24v-32c0-13.3 10.7-24 24-24h287.4L190.9 101.2c-9.8-9.3-10-24.8-.4-34.3z"></path></svg>';
                        }
                        ?>
                    </button>
                </div>
            <?php endif; ?>

            <div class="expanding-carousel elementor-widget-n-carousel">
                <div class="swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($slides as $index => $slide): ?>
                            <?php
                            $is_first        = (0 === $index);
                            $img_url         = ! empty($slide['image']['url']) ? esc_url($slide['image']['url']) : '';
                            $slide_title     = ! empty($slide['slide_title']) ? $slide['slide_title'] : sprintf(__('Slide #%d', 'ahm-core'), $index + 1);
                            $eyebrow         = ! empty($slide['eyebrow']) ? $slide['eyebrow'] : '';
                            $heading         = ! empty($slide['heading']) ? $slide['heading'] : '';
                            $vertical_title  = ! empty($slide['vertical_title']) ? $slide['vertical_title'] : ($eyebrow ?: $slide_title);
                            $badge_text      = sprintf('%d / %d', $index + 1, $total_slides);
                            $active_classes  = $is_first ? ' is-expanded swiper-slide-active' : '';
                            $template_id     = ! empty($slide['template_id']) ? (int) $slide['template_id'] : 0;

                            // Overlay calculation: Solid Color vs Gradient
                            $overlay_type    = $slide['overlay_type'] ?? 'color';
                            if ('gradient' === $overlay_type) {
                                $col_a = ! empty($slide['overlay_gradient_color_a']) ? $slide['overlay_gradient_color_a'] : 'rgba(12, 35, 64, 0.15)';
                                $col_b = ! empty($slide['overlay_gradient_color_b']) ? $slide['overlay_gradient_color_b'] : 'rgba(12, 35, 64, 0.88)';
                                $angle = isset($slide['overlay_gradient_angle']['size']) ? (int) $slide['overlay_gradient_angle']['size'] : 180;
                                $overlay_style = sprintf(' style="background: linear-gradient(%ddeg, %s 0%%, %s 100%%);"', $angle, esc_attr($col_a), esc_attr($col_b));
                            } else {
                                $col = ! empty($slide['overlay_color']) ? $slide['overlay_color'] : 'rgba(12, 35, 64, 0.65)';
                                $overlay_style = sprintf(' style="background: %s;"', esc_attr($col));
                            }
                            ?>
                            <div class="swiper-slide<?php echo esc_attr($active_classes); ?>" data-index="<?php echo esc_attr((string) $index); ?>" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr(sprintf(__('%d of %d', 'ahm-core'), $index + 1, $total_slides)); ?>">
                                <?php if ($img_url): ?>
                                    <div class="slide-bg" style="background-image: url('<?php echo $img_url; ?>');"></div>
                                <?php endif; ?>

                                <div class="slide-overlay"<?php echo $overlay_style; ?>></div>
                                <div class="slide-badge"><?php echo esc_html($badge_text); ?></div>

                                <div class="slide-content-expanded">
                                    <?php
                                    if ($template_id > 0 && class_exists('\Elementor\Plugin') && 'publish' === get_post_status($template_id)) {
                                        echo Plugin::instance()->frontend->get_builder_content_for_display($template_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    } else {
                                        if ($eyebrow) {
                                            echo '<div class="slide-eyebrow">' . esc_html($eyebrow) . '</div>';
                                        }
                                        if ($heading) {
                                            echo '<h3 class="slide-heading">' . esc_html($heading) . '</h3>';
                                        }
                                    }
                                    ?>
                                </div>

                                <div class="slide-content-collapsed">
                                    <div class="slide-vertical-title"><?php echo esc_html($vertical_title); ?></div>
                                    <div class="slide-plus-btn" aria-hidden="true">+</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
