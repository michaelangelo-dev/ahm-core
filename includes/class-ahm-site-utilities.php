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
     *   block_author_enum: bool,
     *   block_empty_author_archives: bool,
     *   prevent_cpt_404: bool,
     *   enable_shortcode_minute_read: bool,
     *   enable_shortcode_custom_title: bool,
     *   enable_shortcode_custom_share: bool
     * }
     */
    public static function get_options(): array
    {
        $defaults = [
            'disable_comments'             => true,
            'wp_rocket_rucss_exclusions'   => true,
            'uppercase_alt_text'           => true,
            'block_author_enum'            => true,
            'block_empty_author_archives'  => true,
            'prevent_cpt_404'              => true,
            'enable_shortcode_minute_read'  => true,
            'enable_shortcode_custom_title' => true,
            'enable_shortcode_custom_share' => true,
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
            'block_author_enum',
            'block_empty_author_archives',
            'prevent_cpt_404',
            'enable_shortcode_minute_read',
            'enable_shortcode_custom_title',
            'enable_shortcode_custom_share',
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

        // 6. Shortcodes
        add_action('init', function () use ($options): void {
            if (! empty($options['enable_shortcode_minute_read'])) {
                add_shortcode('minute_read', [$this, 'shortcode_minute_read']);
            }
            if (! empty($options['enable_shortcode_custom_title'])) {
                add_shortcode('get_post_custom_title', [$this, 'shortcode_custom_title']);
            }
            if (! empty($options['enable_shortcode_custom_share'])) {
                add_shortcode('custom_share_url', [$this, 'shortcode_custom_share_url']);
            }
        });

        // 7. CPT 404 Prevention for ACF Post Types
        if (! empty($options['prevent_cpt_404'])) {
            add_action('generate_rewrite_rules', [$this, 'ensure_acf_post_types_registered'], 1);
        }
    }

    /*--------------------------------------------------------------
     * Feature Implementations
     *------------------------------------------------------------*/

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

    /*--------------------------------------------------------------
     * Shortcode Handlers
     *------------------------------------------------------------*/

    public function shortcode_minute_read(): string
    {
        global $post;

        if (! $post instanceof WP_Post) {
            return '';
        }

        $content    = get_post_field('post_content', $post->ID);
        $word_count = str_word_count(strip_tags((string) $content));
        $wpm        = 200;
        $minutes    = (int) ceil($word_count / $wpm);
        $label      = __(' min read', 'ahm-core');

        return sprintf('<span class="post-meta__read-time">%d%s</span>', $minutes, $label);
    }

    public function shortcode_custom_title(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'has_bold' => 'true',
        ], (array) $atts, 'get_post_custom_title');

        $title = get_the_title();

        if (function_exists('get_field')) {
            $acf_field = get_field('treatment_page_-_alternate_title');
            $title     = ! empty($acf_field) ? (string) $acf_field : $title;
        }

        $title = esc_html($title);

        if (filter_var($parsed['has_bold'], FILTER_VALIDATE_BOOLEAN)) {
            $title = $this->format_title_with_bold($title);
        }

        return $title;
    }

    private function format_title_with_bold(string $title): string
    {
        $words = explode(' ', $title);
        $count = count($words);

        if (0 === $count) {
            return '';
        }

        $num_to_bold = match ($count) {
            1, 2    => 1,
            3       => 2,
            default => 3,
        };

        $num_to_bold = min($num_to_bold, $count);
        $bold_part   = array_slice($words, 0, $num_to_bold);
        $normal_part = array_slice($words, $num_to_bold);

        $output = '<b>' . implode(' ', $bold_part) . '</b>';

        if (! empty($normal_part)) {
            $output .= ' ' . implode(' ', $normal_part);
        }

        return $output;
    }

    public function shortcode_custom_share_url(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'type' => 'facebook',
        ], (array) $atts, 'custom_share_url');

        $url   = urlencode(get_permalink());
        $title = urlencode(get_the_title());

        return match (strtolower((string) $parsed['type'])) {
            'twitter', 'x' => "https://twitter.com/intent/tweet?url={$url}&text={$title}",
            'linkedin'     => "https://www.linkedin.com/sharing/share-offsite/?url={$url}",
            default        => "https://www.facebook.com/sharer/sharer.php?u={$url}",
        };
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
            <p><?php esc_html_e('Enable or disable individual site tweaks, security policies, cache exclusions, and shortcodes.', 'ahm-core'); ?></p>

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
                        <th scope="row"><?php esc_html_e('Media Alt Text', 'ahm-core'); ?></th>
                        <td>
                            <label for="ahm_uppercase_alt_text" style="margin-bottom:10px; display:block;">
                                <input type="checkbox" id="ahm_uppercase_alt_text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[uppercase_alt_text]" value="1" <?php checked(! empty($options['uppercase_alt_text'])); ?> />
                                <strong><?php esc_html_e('Auto-Format Image Alt Text on Upload', 'ahm-core'); ?></strong>
                                <br />
                                <span class="description"><?php esc_html_e('Automatically converts newly uploaded image Alt texts into Title Case / Uppercase format.', 'ahm-core'); ?></span>
                            </label>
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
                        <th scope="row"><?php esc_html_e('Shortcodes Control', 'ahm-core'); ?></th>
                        <td>
                            <fieldset>
                                <label for="ahm_enable_shortcode_minute_read" style="margin-bottom:10px; display:block;">
                                    <input type="checkbox" id="ahm_enable_shortcode_minute_read" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_shortcode_minute_read]" value="1" <?php checked(! empty($options['enable_shortcode_minute_read'])); ?> />
                                    <code>[minute_read]</code> — <?php esc_html_e('Reading time calculator (~200 wpm).', 'ahm-core'); ?>
                                </label>

                                <label for="ahm_enable_shortcode_custom_title" style="margin-bottom:10px; display:block;">
                                    <input type="checkbox" id="ahm_enable_shortcode_custom_title" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_shortcode_custom_title]" value="1" <?php checked(! empty($options['enable_shortcode_custom_title'])); ?> />
                                    <code>[get_post_custom_title has_bold="true"]</code> — <?php esc_html_e('Fetches title/ACF alternate title with bold first words.', 'ahm-core'); ?>
                                </label>

                                <label for="ahm_enable_shortcode_custom_share" style="display:block;">
                                    <input type="checkbox" id="ahm_enable_shortcode_custom_share" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_shortcode_custom_share]" value="1" <?php checked(! empty($options['enable_shortcode_custom_share'])); ?> />
                                    <code>[custom_share_url type="facebook|twitter|linkedin"]</code> — <?php esc_html_e('Generates social share links.', 'ahm-core'); ?>
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
