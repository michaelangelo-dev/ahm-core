<?php
/**
 * Elementor Widgets Manager settings tab & custom widgets controller.
 *
 * Provides admin UI controls for enabling/disabling custom Elementor widgets
 * and registers widgets and their assets when active.
 *
 * @package AHM_Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class AHM_Elementor_Widgets
{
    private static ?self $instance = null;

    /** @var string Option key stored in wp_options */
    public const OPTION_KEY = 'ahm_elementor_widgets_settings';

    /**
     * Get singleton instance.
     */
    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        // Admin tab hook
        add_action('ahm_tab_content_widgets', [$this, 'render_tab']);

        // Register WP Settings API
        add_action('admin_init', [$this, 'register_settings']);

        // Register Elementor hooks
        add_action('elementor/elements/categories_registered', [$this, 'register_categories']);
        add_action('elementor/widgets/register', [$this, 'register_widgets']);

        // Register and enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('elementor/frontend/after_register_scripts', [$this, 'register_assets']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_editor_assets']);

        // Register shortcode
        add_shortcode('ahm_expanding_gallery', [$this, 'render_shortcode']);
    }

    /**
     * Retrieve stored widget options with default values.
     *
     * @return array{
     *   enable_expanding_gallery_carousel: bool
     * }
     */
    public static function get_options(): array
    {
        $defaults = [
            'enable_expanding_gallery_carousel' => true,
            'enable_terms_badges'               => true,
            'enable_loop_filter'                => true,
        ];

        $saved = get_option(self::OPTION_KEY, []);

        if (! is_array($saved)) {
            return $defaults;
        }

        return array_merge($defaults, $saved);
    }

    /**
     * Register settings in WordPress Settings API.
     */
    public function register_settings(): void
    {
        register_setting(
            'ahm_elementor_widgets_group',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default'           => self::get_options(),
            ]
        );
    }

    /**
     * Sanitize widget settings checkboxes before saving.
     *
     * @param mixed $input Submitted raw form data.
     * @return array<string, bool>
     */
    public function sanitize_settings(mixed $input): array
    {
        $keys = [
            'enable_expanding_gallery_carousel',
            'enable_terms_badges',
            'enable_loop_filter',
        ];

        $sanitized = [];
        $raw_input = is_array($input) ? $input : [];

        foreach ($keys as $key) {
            $sanitized[$key] = ! empty($raw_input[$key]);
        }

        return $sanitized;
    }

    /**
     * Register custom Elementor widget categories.
     *
     * @param mixed $elements_manager
     */
    public function register_categories(mixed $elements_manager): void
    {
        if (is_object($elements_manager) && method_exists($elements_manager, 'add_category')) {
            $elements_manager->add_category(
                'ahm-widgets',
                [
                    'title' => esc_html__('AHM Elements', 'ahm-core'),
                    'icon'  => 'fa fa-plug',
                ]
            );
        }
    }

    /**
     * Register custom Elementor widgets.
     *
     * @param mixed $widgets_manager
     */
    public function register_widgets(mixed $widgets_manager): void
    {
        if (! did_action('elementor/loaded')) {
            return;
        }

        $options = self::get_options();

        if (! empty($options['enable_expanding_gallery_carousel'])) {
            require_once AHM_CORE_DIR . 'includes/widgets/class-ahm-widget-expanding-gallery-carousel.php';
            if (class_exists('AHM_Widget_Expanding_Gallery_Carousel') && method_exists($widgets_manager, 'register')) {
                $widgets_manager->register(new \AHM_Widget_Expanding_Gallery_Carousel());
            }
        }

        if (! empty($options['enable_terms_badges'])) {
            require_once AHM_CORE_DIR . 'includes/widgets/class-ahm-widget-terms-badges.php';
            if (class_exists('AHM_Widget_Terms_Badges') && method_exists($widgets_manager, 'register')) {
                $widgets_manager->register(new \AHM_Widget_Terms_Badges());
            }
        }

        if (! empty($options['enable_loop_filter'])) {
            require_once AHM_CORE_DIR . 'includes/widgets/class-ahm-widget-loop-filter.php';
            if (class_exists('AHM_Widget_Loop_Filter') && method_exists($widgets_manager, 'register')) {
                $widgets_manager->register(new \AHM_Widget_Loop_Filter());
            }
        }
    }

    /**
     * Register frontend assets (CSS & JS).
     */
    public function register_assets(): void
    {
        $options = self::get_options();

        if (! empty($options['enable_expanding_gallery_carousel'])) {
            $css_ver = file_exists(AHM_CORE_DIR . 'assets/css/expanding-gallery-carousel.css')
                ? (string) filemtime(AHM_CORE_DIR . 'assets/css/expanding-gallery-carousel.css')
                : AHM_CORE_VERSION;
            $js_ver  = file_exists(AHM_CORE_DIR . 'assets/js/expanding-gallery-carousel.js')
                ? (string) filemtime(AHM_CORE_DIR . 'assets/js/expanding-gallery-carousel.js')
                : AHM_CORE_VERSION;

            wp_register_style(
                'ahm-expanding-gallery-carousel-css',
                AHM_CORE_URL . 'assets/css/expanding-gallery-carousel.css',
                [],
                $css_ver
            );

            wp_register_script(
                'ahm-expanding-gallery-carousel-js',
                AHM_CORE_URL . 'assets/js/expanding-gallery-carousel.js',
                ['jquery'],
                $js_ver,
                true
            );

            // If page contains expanding carousel classes, auto-enqueue
            global $post;
            if ($post instanceof \WP_Post) {
                $content = $post->post_content . ' ' . get_post_meta($post->ID, '_elementor_data', true);
                if (
                    str_contains($content, 'gallery-expanding-carousel') ||
                    str_contains($content, 'ahm_expanding_gallery_carousel') ||
                    str_contains($content, 'gallery-accordion-section') ||
                    str_contains($content, '[ahm_expanding_gallery')
                ) {
                    wp_enqueue_style('ahm-expanding-gallery-carousel-css');
                    wp_enqueue_script('ahm-expanding-gallery-carousel-js');
                }
            }
        }

        if (! empty($options['enable_loop_filter'])) {
            $filter_css_ver = file_exists(AHM_CORE_DIR . 'assets/css/loop-filter.css')
                ? (string) filemtime(AHM_CORE_DIR . 'assets/css/loop-filter.css')
                : AHM_CORE_VERSION;
            $filter_js_ver  = file_exists(AHM_CORE_DIR . 'assets/js/loop-filter.js')
                ? (string) filemtime(AHM_CORE_DIR . 'assets/js/loop-filter.js')
                : AHM_CORE_VERSION;

            wp_register_style(
                'ahm-loop-filter-css',
                AHM_CORE_URL . 'assets/css/loop-filter.css',
                [],
                $filter_css_ver
            );

            wp_register_script(
                'ahm-loop-filter-js',
                AHM_CORE_URL . 'assets/js/loop-filter.js',
                [],
                $filter_js_ver,
                true
            );

            global $post;
            if ($post instanceof \WP_Post) {
                $content = $post->post_content . ' ' . get_post_meta($post->ID, '_elementor_data', true);
                if (
                    str_contains($content, 'ahm_loop_filter') ||
                    str_contains($content, 'ahm-loop-filter') ||
                    str_contains($content, 'ahm-filter-btn')
                ) {
                    wp_enqueue_style('ahm-loop-filter-css');
                    wp_enqueue_script('ahm-loop-filter-js');
                }
            }
        }
    }

    /**
     * Enqueue assets inside the Elementor Live Editor for instant previewing.
     */
    public function enqueue_editor_assets(): void
    {
        $options = self::get_options();
        if (! empty($options['enable_expanding_gallery_carousel'])) {
            $css_ver = file_exists(AHM_CORE_DIR . 'assets/css/expanding-gallery-carousel.css')
                ? (string) filemtime(AHM_CORE_DIR . 'assets/css/expanding-gallery-carousel.css')
                : AHM_CORE_VERSION;
            $js_ver  = file_exists(AHM_CORE_DIR . 'assets/js/expanding-gallery-carousel.js')
                ? (string) filemtime(AHM_CORE_DIR . 'assets/js/expanding-gallery-carousel.js')
                : AHM_CORE_VERSION;

            wp_enqueue_style(
                'ahm-expanding-gallery-carousel-css',
                AHM_CORE_URL . 'assets/css/expanding-gallery-carousel.css',
                [],
                $css_ver
            );

            wp_enqueue_script(
                'ahm-expanding-gallery-carousel-js',
                AHM_CORE_URL . 'assets/js/expanding-gallery-carousel.js',
                ['jquery'],
                $js_ver,
                true
            );
        }

        if (! empty($options['enable_loop_filter'])) {
            $filter_css_ver = file_exists(AHM_CORE_DIR . 'assets/css/loop-filter.css')
                ? (string) filemtime(AHM_CORE_DIR . 'assets/css/loop-filter.css')
                : AHM_CORE_VERSION;
            $filter_js_ver  = file_exists(AHM_CORE_DIR . 'assets/js/loop-filter.js')
                ? (string) filemtime(AHM_CORE_DIR . 'assets/js/loop-filter.js')
                : AHM_CORE_VERSION;

            wp_enqueue_style(
                'ahm-loop-filter-css',
                AHM_CORE_URL . 'assets/css/loop-filter.css',
                [],
                $filter_css_ver
            );

            wp_enqueue_script(
                'ahm-loop-filter-js',
                AHM_CORE_URL . 'assets/js/loop-filter.js',
                [],
                $filter_js_ver,
                true
            );
        }
    }

    /**
     * Render expanding gallery via shortcode [ahm_expanding_gallery].
     *
     * @param array<string, mixed>|string $atts
     * @return string
     */
    public function render_shortcode(array|string $atts = []): string
    {
        $options = self::get_options();
        if (empty($options['enable_expanding_gallery_carousel'])) {
            return '';
        }

        wp_enqueue_style('ahm-expanding-gallery-carousel-css');
        wp_enqueue_script('ahm-expanding-gallery-carousel-js');

        $args = shortcode_atts([
            'eyebrow'  => 'Gallery',
            'title'    => 'Inside Kent Skin & Laser Clinic',
            'subtitle' => 'A glimpse of the clinic, our equipment and the treatments we deliver every day.',
        ], is_array($atts) ? $atts : []);

        $slides = [
            [
                'img'      => content_url('/uploads/2026/09/kent-clinic-image-1.webp'),
                'eyebrow'  => 'Consultation Rooms',
                'heading'  => 'Unhurried, specialist-led appointments',
                'vertical' => 'Consultation Rooms',
            ],
            [
                'img'      => content_url('/uploads/2026/09/kent-clinic-category-image-1.webp'),
                'eyebrow'  => 'Aesthetics Suite',
                'heading'  => 'Advanced non-surgical rejuvenation',
                'vertical' => 'Aesthetics Suite',
            ],
            [
                'img'      => content_url('/uploads/2026/09/kent-clinic-image-1.webp'),
                'eyebrow'  => 'Mole Clinic',
                'heading'  => 'Dermoscopic skin cancer checks & mapping',
                'vertical' => 'Mole Clinic',
            ],
            [
                'img'      => content_url('/uploads/2026/09/kent-clinic-category-image-1.webp'),
                'eyebrow'  => 'Skin Treatments',
                'heading'  => 'Medical-grade laser & aesthetic dermatology',
                'vertical' => 'Skin Treatments',
            ],
            [
                'img'      => content_url('/uploads/2026/09/kent-clinic-image-1.webp'),
                'eyebrow'  => 'Skin Boosters',
                'heading'  => 'Deep cellular hydration & skin remodeling',
                'vertical' => 'Skin Boosters',
            ],
        ];

        $total = count($slides);

        ob_start();
        ?>
        <div class="gallery-section-container ahm-expanding-gallery-shortcode">
            <div class="gallery-header">
                <div class="gallery-header-content">
                    <?php if (! empty($args['eyebrow'])): ?>
                        <div class="gallery-eyebrow"><?php echo esc_html($args['eyebrow']); ?></div>
                    <?php endif; ?>
                    <?php if (! empty($args['title'])): ?>
                        <h2 class="gallery-title"><?php echo esc_html($args['title']); ?></h2>
                    <?php endif; ?>
                    <?php if (! empty($args['subtitle'])): ?>
                        <p class="gallery-subtitle"><?php echo esc_html($args['subtitle']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="gallery-nav-box">
                    <button type="button" class="gallery-nav-btn prev-btn" aria-label="<?php esc_attr_e('Previous slide', 'ahm-core'); ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <button type="button" class="gallery-nav-btn next-btn" aria-label="<?php esc_attr_e('Next slide', 'ahm-core'); ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="expanding-carousel elementor-widget-n-carousel">
                <div class="swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($slides as $idx => $s): ?>
                            <div class="swiper-slide<?php echo $idx === 0 ? ' is-expanded swiper-slide-active' : ''; ?>" data-index="<?php echo esc_attr((string) $idx); ?>">
                                <div class="slide-bg" style="background-image: url('<?php echo esc_url($s['img']); ?>');"></div>
                                <div class="slide-overlay"></div>
                                <div class="slide-badge"><?php echo esc_html(($idx + 1) . ' / ' . $total); ?></div>
                                <div class="slide-content-expanded">
                                    <div class="slide-eyebrow"><?php echo esc_html($s['eyebrow']); ?></div>
                                    <h3 class="slide-heading"><?php echo esc_html($s['heading']); ?></h3>
                                </div>
                                <div class="slide-content-collapsed">
                                    <div class="slide-vertical-title"><?php echo esc_html($s['vertical']); ?></div>
                                    <div class="slide-plus-btn" aria-hidden="true">+</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the admin tab content.
     */
    public function render_tab(): void
    {
        $options = self::get_options();
        ?>
        <div class="wrap ahm-tab-content-inner" style="max-width: 1000px;">
            <h2><?php esc_html_e('Custom Elementor Widgets', 'ahm-core'); ?></h2>
            <p class="description">
                <?php esc_html_e('Enable or disable custom Elementor widgets tailored for medical, clinical, and aesthetic websites. Deactivating unused widgets completely unhooks their scripts, styles, and builder controls for maximum velocity and performance.', 'ahm-core'); ?>
            </p>

            <form method="post" action="options.php" style="margin-top: 24px;">
                <?php
                settings_fields('ahm_elementor_widgets_group');
                ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="ahm_enable_expanding_gallery_carousel">
                                    <strong><?php esc_html_e('Expanding Gallery Carousel', 'ahm-core'); ?></strong>
                                </label>
                                <br />
                                <?php if (! empty($options['enable_expanding_gallery_carousel'])): ?>
                                    <span class="badge" style="display:inline-block; padding:3px 8px; font-size:11px; font-weight:600; border-radius:12px; background:#dcfce7; color:#15803d; margin-top:6px;">
                                        <?php esc_html_e('ACTIVE', 'ahm-core'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="display:inline-block; padding:3px 8px; font-size:11px; font-weight:600; border-radius:12px; background:#f1f5f9; color:#64748b; margin-top:6px;">
                                        <?php esc_html_e('DISABLED', 'ahm-core'); ?>
                                    </span>
                                <?php endif; ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label for="ahm_enable_expanding_gallery_carousel" style="display:block; margin-bottom:8px;">
                                        <input type="checkbox"
                                               id="ahm_enable_expanding_gallery_carousel"
                                               name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_expanding_gallery_carousel]"
                                               value="1"
                                               <?php checked(! empty($options['enable_expanding_gallery_carousel'])); ?> />
                                        <strong><?php esc_html_e('Enable Expanding Gallery Carousel Widget & Assets', 'ahm-core'); ?></strong>
                                    </label>
                                    <p class="description" style="line-height:1.6; margin-bottom:12px;">
                                        <?php esc_html_e('Registers the native Elementor widget "Expanding Gallery Carousel" under the "AHM Elements" panel. Includes active card enlargement (540px), collapsed vertical pills (135px) with circular "+" display indicators, whole-card click trigger, header navigation cycling, and dynamic badge counters.', 'ahm-core'); ?>
                                    </p>
                                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; font-size:12px; color:#334155;">
                                        <div style="margin-bottom:6px;">
                                            <strong><?php esc_html_e('Elementor Widget:', 'ahm-core'); ?></strong> <code>AHM Elements &gt; Expanding Gallery Carousel</code>
                                        </div>
                                        <div style="margin-bottom:6px;">
                                            <strong><?php esc_html_e('Nested Carousel Support:', 'ahm-core'); ?></strong> <?php esc_html_e('Works automatically on standard Elementor Nested Carousel containers with class', 'ahm-core'); ?> <code>.gallery-expanding-carousel</code> <?php esc_html_e('inside', 'ahm-core'); ?> <code>.gallery-accordion-section</code>.
                                        </div>
                                        <div>
                                            <strong><?php esc_html_e('Shortcode:', 'ahm-core'); ?></strong> <code>[ahm_expanding_gallery]</code>
                                        </div>
                                    </div>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ahm_enable_terms_badges">
                                    <strong><?php esc_html_e('Terms Badges / Pills', 'ahm-core'); ?></strong>
                                </label>
                                <br />
                                <?php if (! empty($options['enable_terms_badges'])): ?>
                                    <span class="badge" style="display:inline-block; padding:3px 8px; font-size:11px; font-weight:600; border-radius:12px; background:#dcfce7; color:#15803d; margin-top:6px;">
                                        <?php esc_html_e('ACTIVE', 'ahm-core'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="display:inline-block; padding:3px 8px; font-size:11px; font-weight:600; border-radius:12px; background:#f1f5f9; color:#64748b; margin-top:6px;">
                                        <?php esc_html_e('DISABLED', 'ahm-core'); ?>
                                    </span>
                                <?php endif; ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label for="ahm_enable_terms_badges" style="display:block; margin-bottom:8px;">
                                        <input type="checkbox"
                                               id="ahm_enable_terms_badges"
                                               name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_terms_badges]"
                                               value="1"
                                               <?php checked(! empty($options['enable_terms_badges'])); ?> />
                                        <strong><?php esc_html_e('Enable Terms Badges / Pills Widget', 'ahm-core'); ?></strong>
                                    </label>
                                    <p class="description" style="line-height:1.6; margin-bottom:12px;">
                                        <?php esc_html_e('Registers the native Elementor widget "Terms Badges / Pills" under the "AHM Elements" panel. Renders taxonomy terms (Tags, Categories) for the current post as an unstyled or fully customizable semantic <ul> <li> list of pill badges. Perfect for Loop Grid cards, single treatment pages, and condition archives.', 'ahm-core'); ?>
                                    </p>
                                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; font-size:12px; color:#334155;">
                                        <div>
                                            <strong><?php esc_html_e('Elementor Widget:', 'ahm-core'); ?></strong> <code>AHM Elements &gt; Terms Badges / Pills</code> (ID: <code>ahm_terms_badges</code>)
                                        </div>
                                    </div>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ahm_enable_loop_filter">
                                    <strong><?php esc_html_e('Loop Grid Filter Buttons', 'ahm-core'); ?></strong>
                                </label>
                                <br />
                                <?php if (! empty($options['enable_loop_filter'])): ?>
                                    <span class="badge" style="display:inline-block; padding:3px 8px; font-size:11px; font-weight:600; border-radius:12px; background:#dcfce7; color:#15803d; margin-top:6px;">
                                        <?php esc_html_e('ACTIVE', 'ahm-core'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="display:inline-block; padding:3px 8px; font-size:11px; font-weight:600; border-radius:12px; background:#f1f5f9; color:#64748b; margin-top:6px;">
                                        <?php esc_html_e('DISABLED', 'ahm-core'); ?>
                                    </span>
                                <?php endif; ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label for="ahm_enable_loop_filter" style="display:block; margin-bottom:8px;">
                                        <input type="checkbox"
                                               id="ahm_enable_loop_filter"
                                               name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_loop_filter]"
                                               value="1"
                                               <?php checked(! empty($options['enable_loop_filter'])); ?> />
                                        <strong><?php esc_html_e('Enable Loop Grid Filter Buttons Widget & Assets', 'ahm-core'); ?></strong>
                                    </label>
                                    <p class="description" style="line-height:1.6; margin-bottom:12px;">
                                        <?php esc_html_e('Registers the native Elementor widget "Loop Grid Filter Buttons" under the "AHM Elements" panel. Renders dynamic taxonomy buttons (e.g. All, Medical, Laser, Cosmetic) that instantly filter an Elementor Loop Grid widget client-side without page reload.', 'ahm-core'); ?>
                                    </p>
                                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; font-size:12px; color:#334155;">
                                        <div>
                                            <strong><?php esc_html_e('Elementor Widget:', 'ahm-core'); ?></strong> <code>AHM Elements &gt; Loop Grid Filter Buttons</code> (ID: <code>ahm_loop_filter</code>)
                                        </div>
                                    </div>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php
                if (function_exists('submit_button')) {
                    submit_button(esc_html__('Save Widget Settings', 'ahm-core'));
                } else {
                    echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="' . esc_attr__('Save Widget Settings', 'ahm-core') . '"></p>';
                }
                ?>
            </form>
        </div>
        <?php
    }
}
