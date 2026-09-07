<?php
/**
 * Site Utilities settings tab & feature controller.
 *
 * Provides site-wide controls for comments, caching exclusions, media alt-text automation,
 * security hardening, and custom shortcodes.
 *
 * @package AHM_Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class AHM_Site_Utilities
{
    private static ?self $instance = null;

    /** @var string Option key stored in wp_options */
    public const OPTION_KEY = 'ahm_site_utilities_settings';

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
        // Admin tab hook.
        add_action('ahm_tab_content_site-utilities', [$this, 'render_tab']);

        // Register WP Settings API.
        add_action('admin_init', [$this, 'register_settings']);

        // Handle bulk alt text action.
        add_action('admin_post_ahm_bulk_alt_text', [$this, 'handle_bulk_alt_text']);

        // Attach conditional feature hooks & shortcodes.
        $this->register_feature_hooks();
    }

    /**
     * Retrieve stored site utility options with default values.
     *
     * @return array{
     *   disable_comments: bool,
     *   wp_rocket_rucss_exclusions: bool,
     *   uppercase_alt_text: bool,
     *   enable_svg_uploads: bool,
     *   block_author_enum: bool,
     *   block_empty_author_archives: bool,
     *   prevent_cpt_404: bool,
     *   dynamic_treatment_form_options: bool,
     *   disable_google_fonts: bool,
     *   preload_primary_font: bool,
     *   enable_form_antispam: bool,
     *   antispam_block_cyrillic: bool,
     *   antispam_block_links: bool,
     *   antispam_silent_blackhole: bool
     * }
     */
    public static function get_options(): array
    {
        $defaults = [
            'disable_comments'               => true,
            'wp_rocket_rucss_exclusions'     => true,
            'uppercase_alt_text'             => true,
            'enable_svg_uploads'             => true,
            'block_author_enum'              => true,
            'block_empty_author_archives'    => true,
            'prevent_cpt_404'                => true,
            'dynamic_treatment_form_options' => true,
            'disable_google_fonts'           => true,
            'preload_primary_font'           => true,
            'enable_form_antispam'           => true,
            'antispam_block_cyrillic'        => true,
            'antispam_block_links'           => true,
            'antispam_silent_blackhole'      => false,
        ];

        $saved = get_option(self::OPTION_KEY, []);

        if (! is_array($saved)) {
            return $defaults;
        }

        return array_merge($defaults, $saved);
    }

    /*--------------------------------------------------------------
     * Settings Registration & Sanitization
     *------------------------------------------------------------*/

    public function register_settings(): void
    {
        register_setting(
            'ahm_site_utilities_group',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default'           => self::get_options(),
            ]
        );
    }

    /**
     * Sanitize settings checkboxes before saving to database.
     *
     * @param mixed $input Submitted raw form data.
     * @return array<string, bool>
     */
    public function sanitize_settings(mixed $input): array
    {
        $keys = [
            'disable_comments',
            'wp_rocket_rucss_exclusions',
            'uppercase_alt_text',
            'enable_svg_uploads',
            'block_author_enum',
            'block_empty_author_archives',
            'prevent_cpt_404',
            'dynamic_treatment_form_options',
            'disable_google_fonts',
            'preload_primary_font',
            'enable_form_antispam',
            'antispam_block_cyrillic',
            'antispam_block_links',
            'antispam_silent_blackhole',
        ];

        $sanitized = [];
        $raw_input = is_array($input) ? $input : [];

        foreach ($keys as $key) {
            $sanitized[$key] = ! empty($raw_input[$key]);
        }

        return $sanitized;
    }

    /*--------------------------------------------------------------
     * Feature Hooks & Shortcode Registration
     *------------------------------------------------------------*/

    private function register_feature_hooks(): void
    {
        $options = self::get_options();

        // 1. Comment Removal
        if (! empty($options['disable_comments'])) {
            add_action('init', [$this, 'disable_comments_everywhere']);
            add_filter('comments_open', '__return_false', 20, 2);
            add_filter('pings_open', '__return_false', 20, 2);
            add_filter('comments_array', '__return_empty_array', 10, 2);
            add_action('admin_menu', [$this, 'remove_comments_admin_menu']);
            add_action('wp_before_admin_bar_render', [$this, 'remove_comments_admin_bar']);
        }

        // 2. WP Rocket RUCSS Exclusions
        if (! empty($options['wp_rocket_rucss_exclusions'])) {
            add_filter('rocket_exclude_css_from_rucss', [$this, 'exclude_elementor_kit_css']);
            add_filter('rocket_rucss_exclude_css', [$this, 'exclude_dynamic_classes_rucss']);
        }

        // 3. Media Alt Text Formatting
        if (! empty($options['uppercase_alt_text'])) {
            add_action('add_attachment', [$this, 'set_alt_text_to_uppercase']);
        }

        // 4. Security: Block Author Enumeration
        if (! empty($options['block_author_enum'])) {
            add_action('init', [$this, 'block_author_enumeration']);
        }

        // 5. SEO: Block Empty Author Archives
        if (! empty($options['block_empty_author_archives'])) {
            add_action('template_redirect', [$this, 'block_empty_author_archives']);
        }

        // 6. CPT 404 Prevention for ACF Post Types
        if (! empty($options['prevent_cpt_404'])) {
            add_action('generate_rewrite_rules', [$this, 'ensure_acf_post_types_registered'], 1);
        }

        // 7. Dynamic Form Options for Elementor Pro Forms
        if (! empty($options['dynamic_treatment_form_options'])) {
            add_filter('elementor_pro/forms/render/item/select', [$this, 'populate_treatment_select_options'], 10, 3);
            add_filter('elementor_pro/forms/render/item/select', [$this, 'populate_location_select_options'], 10, 3);
        }

        // 8. Safe SVG Uploads & Media Library Previews
        if (! empty($options['enable_svg_uploads'])) {
            AHM_SVG_Support::get_instance();
        }

        // 9. Elementor Google Fonts Disabler & Typography Filter
        if (! empty($options['disable_google_fonts']) || 'yes' === get_option('ahm_disable_google_fonts')) {
            add_filter('elementor/frontend/print_google_fonts', '__return_false');
            add_filter('elementor/fonts/groups', [$this, 'restrict_elementor_font_groups']);
        }

        // 10. Primary Brand Font Preloader
        if (! empty($options['preload_primary_font']) || 'yes' === get_option('ahm_disable_google_fonts')) {
            add_action('wp_head', [$this, 'preload_primary_brand_font'], 1);
        }

        // 11. Native Elementor Form Anti-Spam Engine
        if (! empty($options['enable_form_antispam'])) {
            AHM_Form_Antispam::get_instance();
        }
    }

    /*--------------------------------------------------------------
     * Feature Implementations
     *------------------------------------------------------------*/

    /**
     * Restrict Elementor's typography control dropdown to Custom and System fonts only.
     *
     * @param array<string, mixed> $groups Existing font groups.
     * @return array<string, mixed> Modified font groups.
     */
    public function restrict_elementor_font_groups(array $groups): array
    {
        unset($groups['googlefonts'], $groups['earlyaccess']);
        return $groups;
    }

    /**
     * Inject high-priority woff2 font preloader into <head> for primary brand font.
     */
    public function preload_primary_brand_font(): void
    {
        $preload_url = get_option('ahm_preload_font_url');
        if (! empty($preload_url)) {
            echo '<link rel="preload" href="' . esc_url((string) $preload_url) . '" as="font" type="font/woff2" crossorigin>' . "\n";
        }
    }

    public function disable_comments_everywhere(): void
    {
        remove_post_type_support('post', 'comments');
        remove_post_type_support('post', 'trackbacks');
        remove_post_type_support('page', 'comments');
        remove_post_type_support('page', 'trackbacks');
    }

    public function remove_comments_admin_menu(): void
    {
        remove_menu_page('edit-comments.php');
    }

    public function remove_comments_admin_bar(): void
    {
        global $wp_admin_bar;
        if (is_object($wp_admin_bar)) {
            $wp_admin_bar->remove_menu('comments');
        }
    }

    public function exclude_elementor_kit_css(array $excluded_files): array
    {
        $kit_id = get_option('elementor_active_kit');
        if ($kit_id) {
            $excluded_files[] = '/wp-content/uploads/elementor/css/post-' . $kit_id . '\.css';
        }
        return $excluded_files;
    }

    public function exclude_dynamic_classes_rucss(array $exclusions): array
    {
        $additional = [
            'e-n-menu-content',
            'e-active',
            'menu-reset',
            'accordion-reset',
            'open',
            'e-load-more-pagination-end',
            'form-has-acceptance',
            'checked',
            'intlTelInput-initiated',
            'admin-only',
        ];

        return array_unique(array_merge($exclusions, $additional));
    }

    public function set_alt_text_to_uppercase(int $post_ID): void
    {
        if (! wp_attachment_is_image($post_ID)) {
            return;
        }

        $existing_alt = get_post_meta($post_ID, '_wp_attachment_image_alt', true);
        $source_text  = ! empty($existing_alt) ? (string) $existing_alt : (string) get_post($post_ID)->post_title;
        $clean_title  = preg_replace('/\s*[-_]\s*/', ' ', $source_text);
        $final_alt    = ucwords(strtolower(trim((string) $clean_title)));

        update_post_meta($post_ID, '_wp_attachment_image_alt', $final_alt);
    }

    public function handle_bulk_alt_text(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'ahm-core'));
        }

        check_admin_referer('ahm_bulk_alt_text_nonce');

        $images = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
        ]);

        $count = 0;
        foreach ($images as $image) {
            $existing_alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
            $source       = ! empty($existing_alt) ? (string) $existing_alt : (string) $image->post_title;
            $clean        = preg_replace('/\s*[-_]\s*/', ' ', $source);
            $final_alt    = ucwords(strtolower(trim((string) $clean)));

            update_post_meta($image->ID, '_wp_attachment_image_alt', $final_alt);
            $count++;
        }

        $redirect = add_query_arg([
            'page'             => 'ahm-core',
            'tab'              => 'site-utilities',
            'bulk_alt_updated' => $count,
        ], admin_url('admin.php'));

        wp_safe_redirect($redirect);
        exit;
    }

    public function block_author_enumeration(): void
    {
        if (! is_admin() && isset($_REQUEST['author'])) {
            wp_redirect(home_url(), 301);
            exit;
        }
    }

    public function block_empty_author_archives(): void
    {
        if (is_author()) {
            $author = get_queried_object();
            if ($author instanceof WP_User) {
                if (count_user_posts($author->ID) === 0) {
                    global $wp_query;
                    $wp_query->set_404();
                    status_header(404);
                    nocache_headers();
                }
            }
        }
    }

    /**
     * Permanently prevent CPT 404 errors by ensuring ACF Custom Post Types
     * are registered before WordPress compiles and saves rewrite rules.
     *
     * Dynamically checks all ACF-registered post types to verify if any are missing
     * from the WordPress global registry before rewrite rules are generated.
     *
     * @param WP_Rewrite|null $wp_rewrite WordPress rewrite component instance.
     * @return void
     */
    public function ensure_acf_post_types_registered(?WP_Rewrite $wp_rewrite = null): void
    {
        if (! function_exists('acf_get_store') || ! class_exists('ACF_Post_Type')) {
            return;
        }

        $needs_registration = false;

        // Dynamically retrieve configured ACF post types
        $acf_cpts = function_exists('acf_get_post_types') ? acf_get_post_types() : [];

        if (! empty($acf_cpts)) {
            foreach ($acf_cpts as $cpt) {
                $post_type_name = is_array($cpt) ? ($cpt['post_type'] ?? '') : ($cpt->post_type ?? '');
                if (! empty($post_type_name) && ! post_type_exists((string) $post_type_name)) {
                    $needs_registration = true;
                    break;
                }
            }
        } else {
            // Fallback: Check ACF internal store if acf_get_post_types() returns empty
            $store = acf_get_store('post-type');
            if ($store && is_object($store) && method_exists($store, 'get')) {
                $items = $store->get();
                if (is_array($items) && ! empty($items)) {
                    foreach ($items as $name => $item) {
                        $cpt_key = is_array($item) ? ($item['post_type'] ?? $name) : $name;
                        if (! empty($cpt_key) && ! post_type_exists((string) $cpt_key)) {
                            $needs_registration = true;
                            break;
                        }
                    }
                }
            }
        }

        // Trigger ACF post type registration if any post type is missing
        if ($needs_registration) {
            $acf_post_types = new ACF_Post_Type();
            if (method_exists($acf_post_types, 'register_post_types')) {
                $acf_post_types->register_post_types();
            }
        }
    }

    /**
     * Dynamically populates Elementor Form select fields matching custom_id 'treatment' or label 'Treatment'
     * with published Treatment CPT posts, preserving Post Types Order sorting.
     *
     * @param array<string, mixed> $item Form field settings item.
     * @param int $item_index Index of the field item.
     * @param object $widget Elementor form widget instance.
     * @return array<string, mixed> Modified field settings.
     */
    public function populate_treatment_select_options(array $item, int $item_index, object $widget): array
    {
        $custom_id = strtolower((string) ($item['custom_id'] ?? ''));

        if ($custom_id === 'treatment' || $custom_id === 'field_treatment') {
            $treatments = get_posts([
                'post_type'          => 'treatment',
                'posts_per_page'     => -1,
                'post_status'        => 'publish',
                'orderby'            => [
                    'menu_order' => 'ASC',
                    'date'       => 'DESC',
                    'ID'         => 'DESC',
                ],
                'suppress_filters'   => false, // Enables Post Types Order plugin filter
                'ignore_custom_sort' => false, // Forces PTO custom sort if plugin active
            ]);

            $options   = [];
            $options[] = 'Select treatment|';

            if (! empty($treatments)) {
                foreach ($treatments as $post) {
                    $title     = get_the_title($post->ID);
                    $options[] = esc_html($title) . '|' . esc_html($title);
                }
                $item['field_options'] = implode("\n", $options);
            }
        }

        return $item;
    }

    /**
     * Dynamically populates Elementor Form select fields matching custom_id 'location', 'field_location', 'facility', or 'field_facility'
     * with saved Hospital / Facility names from AHM Contact Info settings.
     *
     * @param array<string, mixed> $item Form field settings item.
     * @param int $item_index Index of the field item.
     * @param object $widget Elementor form widget instance.
     * @return array<string, mixed> Modified field settings.
     */
    public function populate_location_select_options(array $item, int $item_index, object $widget): array
    {
        $custom_id = strtolower((string) ($item['custom_id'] ?? ''));

        if (in_array($custom_id, ['location', 'field_location', 'facility', 'field_facility', 'hospital', 'field_hospital'], true)) {
            $contact_info   = \AHM_Contact_Info::get_options();
            $facility_names = [];

            // Primary Hospital / Facility Name
            if (! empty($contact_info['address_line1'])) {
                $facility_names[] = trim((string) $contact_info['address_line1']);
            }

            // Practice Locations 1-6
            if (! empty($contact_info['locations']) && is_array($contact_info['locations'])) {
                foreach ($contact_info['locations'] as $loc) {
                    if (! empty($loc['name'])) {
                        $facility_names[] = trim((string) $loc['name']);
                    }
                }
            }

            $facility_names = array_unique(array_filter($facility_names));

            $options   = [];
            $options[] = 'Select location|';

            if (! empty($facility_names)) {
                foreach ($facility_names as $name) {
                    $options[] = esc_html($name) . '|' . esc_html($name);
                }
                $item['field_options'] = implode("\n", $options);
            }
        }

        return $item;
    }

    /*--------------------------------------------------------------
     * Admin Tab Renderer
     *------------------------------------------------------------*/

    public function render_tab(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $options = self::get_options();

        if (isset($_GET['bulk_alt_updated'])) {
            $updated_count = (int) $_GET['bulk_alt_updated'];
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('%d media image Alt texts updated to Uppercase.', 'ahm-core'), $updated_count) . '</p></div>';
        }
        ?>
        <div class="ahm-card" style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:20px; max-width:800px; margin-top:20px;">
            <h2><?php esc_html_e('Site Utilities & Feature Controls', 'ahm-core'); ?></h2>
            <p><?php esc_html_e('Enable or disable individual site tweaks, security policies, and cache exclusions.', 'ahm-core'); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields('ahm_site_utilities_group'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Comment Management', 'ahm-core'); ?></th>
                        <td>
                            <label for="ahm_disable_comments">
                                <input type="checkbox" id="ahm_disable_comments" name="<?php echo esc_attr(self::OPTION_KEY); ?>[disable_comments]" value="1" <?php checked(! empty($options['disable_comments'])); ?> />
                                <strong><?php esc_html_e('Disable Comments Everywhere', 'ahm-core'); ?></strong>
                                <br />
                                <span class="description"><?php esc_html_e('Removes comment & trackback support from posts/pages, hides comment UI, and removes admin bar items.', 'ahm-core'); ?></span>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Cache & RUCSS', 'ahm-core'); ?></th>
                        <td>
                            <label for="ahm_wp_rocket_rucss_exclusions">
                                <input type="checkbox" id="ahm_wp_rocket_rucss_exclusions" name="<?php echo esc_attr(self::OPTION_KEY); ?>[wp_rocket_rucss_exclusions]" value="1" <?php checked(! empty($options['wp_rocket_rucss_exclusions'])); ?> />
                                <strong><?php esc_html_e('WP Rocket RUCSS & Elementor Exclusions', 'ahm-core'); ?></strong>
                                <br />
                                <span class="description"><?php esc_html_e('Excludes Elementor Active Kit CSS and dynamic menu/accordion/form classes from WP Rocket Unused CSS removal.', 'ahm-core'); ?></span>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Media & Uploads', 'ahm-core'); ?></th>
                        <td>
                            <fieldset>
                                <label for="ahm_enable_svg_uploads" style="margin-bottom:10px; display:block;">
                                    <input type="checkbox" id="ahm_enable_svg_uploads" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_svg_uploads]" value="1" <?php checked(! empty($options['enable_svg_uploads'])); ?> />
                                    <strong><?php esc_html_e('Safe SVG Uploads & Media Library Previews', 'ahm-core'); ?></strong>
                                    <br />
                                    <span class="description"><?php esc_html_e('Enables secure SVG uploads across WordPress Media Library, Custom Post Types, and ACF fields with automatic XML sanitization (stripping scripts and malicious entities) and admin thumbnail preview fixes.', 'ahm-core'); ?></span>
                                </label>

                                <label for="ahm_uppercase_alt_text" style="display:block;">
                                    <input type="checkbox" id="ahm_uppercase_alt_text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[uppercase_alt_text]" value="1" <?php checked(! empty($options['uppercase_alt_text'])); ?> />
                                    <strong><?php esc_html_e('Auto-Format Image Alt Text on Upload', 'ahm-core'); ?></strong>
                                    <br />
                                    <span class="description"><?php esc_html_e('Automatically converts newly uploaded image Alt texts into Title Case / Uppercase format.', 'ahm-core'); ?></span>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Security & Hardening', 'ahm-core'); ?></th>
                        <td>
                            <fieldset>
                                <label for="ahm_block_author_enum" style="margin-bottom:10px; display:block;">
                                    <input type="checkbox" id="ahm_block_author_enum" name="<?php echo esc_attr(self::OPTION_KEY); ?>[block_author_enum]" value="1" <?php checked(! empty($options['block_author_enum'])); ?> />
                                    <strong><?php esc_html_e('Block Author Enumeration (?author=N)', 'ahm-core'); ?></strong>
                                    <br />
                                    <span class="description"><?php esc_html_e('Redirects non-admin author scanning requests to the homepage to prevent user disclosure.', 'ahm-core'); ?></span>
                                </label>

                                <label for="ahm_block_empty_author_archives" style="display:block;">
                                    <input type="checkbox" id="ahm_block_empty_author_archives" name="<?php echo esc_attr(self::OPTION_KEY); ?>[block_empty_author_archives]" value="1" <?php checked(! empty($options['block_empty_author_archives'])); ?> />
                                    <strong><?php esc_html_e('Return 404 for Empty Author Archives', 'ahm-core'); ?></strong>
                                    <br />
                                    <span class="description"><?php esc_html_e('Protects admin accounts with 0 published posts by serving a 404 header on their archive page.', 'ahm-core'); ?></span>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Rewrite Rules & CPTs', 'ahm-core'); ?></th>
                        <td>
                            <label for="ahm_prevent_cpt_404">
                                <input type="checkbox" id="ahm_prevent_cpt_404" name="<?php echo esc_attr(self::OPTION_KEY); ?>[prevent_cpt_404]" value="1" <?php checked(! empty($options['prevent_cpt_404'])); ?> />
                                <strong><?php esc_html_e('Prevent ACF Custom Post Type 404 Errors', 'ahm-core'); ?></strong>
                                <br />
                                <span class="description"><?php esc_html_e('Ensures all ACF Custom Post Types are dynamically registered prior to rewrite rule compilation to prevent 404 permalink issues.', 'ahm-core'); ?></span>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Elementor Forms', 'ahm-core'); ?></th>
                        <td>
                            <label for="ahm_dynamic_treatment_form_options">
                                <input type="checkbox" id="ahm_dynamic_treatment_form_options" name="<?php echo esc_attr(self::OPTION_KEY); ?>[dynamic_treatment_form_options]" value="1" <?php checked(! empty($options['dynamic_treatment_form_options'])); ?> />
                                <strong><?php esc_html_e('Dynamic Treatment Options for Select Fields', 'ahm-core'); ?></strong>
                                <br />
                                <span class="description"><?php esc_html_e('Automatically populates Elementor form select fields having Custom ID "treatment" or Label "Treatment" with published Treatments in Post Types Order menu order.', 'ahm-core'); ?></span>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Elementor & Typography', 'ahm-core'); ?></th>
                        <td>
                            <fieldset>
                                <label for="ahm_disable_google_fonts" style="margin-bottom:10px; display:block;">
                                    <input type="checkbox" id="ahm_disable_google_fonts" name="<?php echo esc_attr(self::OPTION_KEY); ?>[disable_google_fonts]" value="1" <?php checked(! empty($options['disable_google_fonts'])); ?> />
                                    <strong><?php esc_html_e('Disable Elementor Google Fonts & Whitelist Custom Fonts', 'ahm-core'); ?></strong>
                                    <br />
                                    <span class="description"><?php esc_html_e('Prevents Elementor from requesting external Google Fonts APIs and restricts the font family dropdown to local custom and system fonts.', 'ahm-core'); ?></span>
                                </label>

                                <label for="ahm_preload_primary_font" style="display:block;">
                                    <input type="checkbox" id="ahm_preload_primary_font" name="<?php echo esc_attr(self::OPTION_KEY); ?>[preload_primary_font]" value="1" <?php checked(! empty($options['preload_primary_font'])); ?> />
                                    <strong><?php esc_html_e('Preload Primary Brand Font in <head>', 'ahm-core'); ?></strong>
                                    <br />
                                    <span class="description">
                                        <?php esc_html_e('Injects a high-priority woff2 preload link into the head for the primary brand font.', 'ahm-core'); ?>
                                        <?php
                                        $preload_url = get_option('ahm_preload_font_url');
                                        if (! empty($preload_url)) {
                                            echo '<br /><code>' . esc_html((string) $preload_url) . '</code>';
                                        }
                                        ?>
                                    </span>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Form Spam Protection', 'ahm-core'); ?></th>
                        <td>
                            <fieldset>
                                <label for="ahm_enable_form_antispam" style="margin-bottom:10px; display:block;">
                                    <input type="checkbox" id="ahm_enable_form_antispam" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_form_antispam]" value="1" <?php checked(! empty($options['enable_form_antispam'])); ?> />
                                    <strong><?php esc_html_e('Enable Elementor Form Anti-Spam Engine', 'ahm-core'); ?></strong>
                                    <br />
                                    <span class="description"><?php esc_html_e('Universal zero-plugin protection: off-screen honeypot trap, HMAC-signed time-trap velocity gate, and micro-JS interaction handshake.', 'ahm-core'); ?></span>
                                </label>

                                <label for="ahm_antispam_block_cyrillic" style="margin-bottom:10px; display:block;">
                                    <input type="checkbox" id="ahm_antispam_block_cyrillic" name="<?php echo esc_attr(self::OPTION_KEY); ?>[antispam_block_cyrillic]" value="1" <?php checked(! empty($options['antispam_block_cyrillic'])); ?> />
                                    <strong><?php esc_html_e('Block Cyrillic & Russian Characters in Submissions', 'ahm-core'); ?></strong>
                                    <br />
                                    <span class="description"><?php esc_html_e('Automatically flags and rejects automated spam containing Cyrillic characters in form fields (recommended for UK/English healthcare sites).', 'ahm-core'); ?></span>
                                </label>

                                <label for="ahm_antispam_block_links" style="margin-bottom:10px; display:block;">
                                    <input type="checkbox" id="ahm_antispam_block_links" name="<?php echo esc_attr(self::OPTION_KEY); ?>[antispam_block_links]" value="1" <?php checked(! empty($options['antispam_block_links'])); ?> />
                                    <strong><?php esc_html_e('Block Links in Name & Phone Fields', 'ahm-core'); ?></strong>
                                    <br />
                                    <span class="description"><?php esc_html_e('Rejects submissions attempting to place URLs or suspicious domain extensions into patient name or telephone fields.', 'ahm-core'); ?></span>
                                </label>

                                <label for="ahm_antispam_silent_blackhole" style="display:block;">
                                    <input type="checkbox" id="ahm_antispam_silent_blackhole" name="<?php echo esc_attr(self::OPTION_KEY); ?>[antispam_silent_blackhole]" value="1" <?php checked(! empty($options['antispam_silent_blackhole'])); ?> />
                                    <strong><?php esc_html_e('Silent Blackhole Mode (Deceive Bots)', 'ahm-core'); ?></strong>
                                    <br />
                                    <span class="description"><?php esc_html_e('When enabled, returns a successful delivery response to detected bots so they do not retry, while silently suppressing outgoing emails.', 'ahm-core'); ?></span>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Utility Settings', 'ahm-core')); ?>
            </form>

            <hr style="margin: 30px 0 20px 0; border:0; border-top:1px solid #ddd;" />

            <h3><?php esc_html_e('Media Tools', 'ahm-core'); ?></h3>
            <p><?php esc_html_e('Manually trigger bulk image Alt text formatting for all existing media library items:', 'ahm-core'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ahm_bulk_alt_text" />
                <?php wp_nonce_field('ahm_bulk_alt_text_nonce'); ?>
                <?php submit_button(__('Bulk Format Existing Media Alt Text', 'ahm-core'), 'secondary'); ?>
            </form>
        </div>
        <?php
    }
}
