<?php
/**
 * Contact Info settings tab & shortcode provider.
 *
 * Manages site-wide phone, email, address, and Google Maps URL,
 * providing custom WordPress shortcodes, UK phone formatting, and inline link filter for display across themes and Elementor widgets.
 *
 * @package AHM_Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class AHM_Contact_Info
{
    private static ?self $instance = null;

    /** @var string Option key stored in wp_options */
    public const OPTION_KEY = 'ahm_contact_info_settings';

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
        // Admin tab content hook.
        add_action('ahm_tab_content_contact-info', [$this, 'render_tab']);

        // Register WP Settings API.
        add_action('admin_init', [$this, 'register_settings']);

        // Register Shortcodes on init.
        add_action('init', [$this, 'register_shortcodes']);

        // Enqueue frontend CSS inheritance styles.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        // Content filters for inline shortcodes and plain text phone auto-linking.
        add_filter('the_content', [$this, 'filter_content_hrefs'], 11);
        add_filter('elementor/widget/render_content', [$this, 'filter_content_hrefs'], 11);
    }

    /**
     * Enqueue frontend CSS for contact links.
     */
    public function enqueue_frontend_assets(): void
    {
        wp_enqueue_style(
            'ahm-contact-frontend-css',
            AHM_CORE_URL . 'assets/css/frontend.css',
            [],
            AHM_CORE_VERSION
        );
    }

    /**
     * Retrieve stored contact info options with default values.
     *
     * @return array{phone: string, email: string, address: string, maps_url: string, auto_link_all_emails: bool, auto_link_all_phones: bool}
     */
    public static function get_options(): array
    {
        $defaults = [
            'phone'                => '',
            'email'                => '',
            'address'              => '',
            'maps_url'             => '',
            'auto_link_all_emails' => false,
            'auto_link_all_phones' => false,
        ];

        $saved = get_option(self::OPTION_KEY, []);

        if (! is_array($saved)) {
            return $defaults;
        }

        return array_merge($defaults, $saved);
    }

    /**
     * Helper to format phone number into UK standard layout if starting with +44 or 0.
     *
     * @param string $phone Raw phone string.
     * @return array{display: string, tel: string, raw_digits: string}
     */
    public static function format_uk_phone(string $phone): array
    {
        $trimmed = trim($phone);
        if (empty($trimmed)) {
            return ['display' => '', 'tel' => '', 'raw_digits' => ''];
        }

        $has_plus_44   = str_starts_with($trimmed, '+44');
        $starts_with_0 = str_starts_with($trimmed, '0');

        $digits = preg_replace('/\D/', '', $trimmed);
        $display = $trimmed;
        $tel = 'tel:' . ($has_plus_44 ? '+' . $digits : $digits);

        if ($has_plus_44 || $starts_with_0) {
            $national_digits = ($has_plus_44 && str_starts_with($digits, '44')) ? '0' . substr($digits, 2) : $digits;

            // International tel: link (+44...)
            if (str_starts_with($national_digits, '0')) {
                $tel = 'tel:+44' . substr($national_digits, 1);
            } else {
                $tel = 'tel:+' . $digits;
            }

            $len = strlen($national_digits);
            if ($has_plus_44) {
                $intl_digits = substr($national_digits, 1);
                if (str_starts_with($national_digits, '02') && strlen($intl_digits) >= 10) {
                    $display = '+44 ' . substr($intl_digits, 0, 2) . ' ' . substr($intl_digits, 2, 4) . ' ' . substr($intl_digits, 6);
                } elseif (strlen($intl_digits) >= 10) {
                    $display = '+44 ' . substr($intl_digits, 0, 4) . ' ' . substr($intl_digits, 4);
                } else {
                    $display = '+44 ' . $intl_digits;
                }
            } else {
                if (str_starts_with($national_digits, '02') && $len === 11) {
                    $display = substr($national_digits, 0, 3) . ' ' . substr($national_digits, 3, 4) . ' ' . substr($national_digits, 7);
                } elseif ($len === 11) {
                    $display = substr($national_digits, 0, 5) . ' ' . substr($national_digits, 5);
                } else {
                    $display = $national_digits;
                }
            }
        }

        return [
            'display'    => trim($display),
            'tel'        => $tel,
            'raw_digits' => $digits,
        ];
    }

    /*--------------------------------------------------------------
     * Settings Registration & Sanitization
     *------------------------------------------------------------*/

    public function register_settings(): void
    {
        register_setting(
            'ahm_contact_info_group',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default'           => [
                    'phone'                => '',
                    'email'                => '',
                    'address'              => '',
                    'maps_url'             => '',
                    'auto_link_all_emails' => false,
                    'auto_link_all_phones' => false,
                ],
            ]
        );
    }

    /**
     * Sanitize settings fields before saving to database.
     *
     * @param mixed $input Submitted raw form data.
     * @return array{phone: string, email: string, address: string, maps_url: string, auto_link_all_emails: bool, auto_link_all_phones: bool}
     */
    public function sanitize_settings(mixed $input): array
    {
        $sanitized = [
            'phone'                => '',
            'email'                => '',
            'address'              => '',
            'maps_url'             => '',
            'auto_link_all_emails' => false,
            'auto_link_all_phones' => false,
        ];

        if (! is_array($input)) {
            return $sanitized;
        }

        if (isset($input['phone'])) {
            $sanitized['phone'] = sanitize_text_field((string) $input['phone']);
        }

        if (isset($input['email'])) {
            $sanitized['email'] = sanitize_email((string) $input['email']);
        }

        if (isset($input['address'])) {
            $sanitized['address'] = sanitize_textarea_field((string) $input['address']);
        }

        if (isset($input['maps_url'])) {
            $sanitized['maps_url'] = esc_url_raw((string) $input['maps_url']);
        }

        $sanitized['auto_link_all_emails'] = ! empty($input['auto_link_all_emails']);
        $sanitized['auto_link_all_phones'] = ! empty($input['auto_link_all_phones']);

        return $sanitized;
    }

    /*--------------------------------------------------------------
     * Content Filter for Links & Plain Text Phone Numbers
     *------------------------------------------------------------*/

    /**
     * Parse HTML content to normalize shortcodes in <a> href attributes AND
     * automatically convert plain text phone numbers / emails into clickable links with .ahm-contact-link styling.
     */
    public function filter_content_hrefs(mixed $content): mixed
    {
        if (! is_string($content) || empty($content)) {
            return $content;
        }

        $options  = self::get_options();
        $phone    = $options['phone'];
        $email    = $options['email'];
        $maps_url = $options['maps_url'];

        $auto_all_emails = ! empty($options['auto_link_all_emails']);
        $auto_all_phones = ! empty($options['auto_link_all_phones']);

        $uk_phone  = ! empty($phone) ? self::format_uk_phone($phone) : ['display' => '', 'tel' => '', 'raw_digits' => ''];

        // Step 1: Process existing <a> tags with href shortcodes or matching targets & format inner text
        $regex_a = '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>(.*?)<\/a>/is';

        $content = preg_replace_callback(
            $regex_a,
            function (array $matches) use ($phone, $uk_phone, $email, $maps_url): string {
                $before   = $matches[1];
                $raw_href = $matches[2];
                $after    = $matches[3];
                $text     = $matches[4];

                $decoded_href = urldecode($raw_href);
                $new_href = null;
                $link_type_class = '';
                $is_phone = false;

                if (
                    str_contains($decoded_href, '[ahm_phone]') ||
                    str_contains($decoded_href, 'ahm_phone') ||
                    (! empty($phone) && str_contains($decoded_href, $phone)) ||
                    (! empty($uk_phone['raw_digits']) && str_contains($decoded_href, $uk_phone['raw_digits'])) ||
                    (! empty($phone) && str_contains($text, $phone)) ||
                    (! empty($uk_phone['raw_digits']) && str_contains($text, $uk_phone['raw_digits']))
                ) {
                    if (! empty($uk_phone['tel'])) {
                        $new_href = $uk_phone['tel'];
                        $link_type_class = 'ahm-contact-link ahm-contact-phone-link';
                        $is_phone = true;
                    }
                } elseif (
                    str_contains($decoded_href, '[ahm_email]') ||
                    str_contains($decoded_href, 'ahm_email') ||
                    (! empty($email) && str_contains($decoded_href, $email)) ||
                    (! empty($email) && str_contains($text, $email))
                ) {
                    if (! empty($email)) {
                        $new_href = 'mailto:' . $email;
                        $link_type_class = 'ahm-contact-link ahm-contact-email-link';
                    }
                } elseif (
                    str_contains($decoded_href, '[ahm_maps_url]') ||
                    str_contains($decoded_href, 'ahm_maps_url')
                ) {
                    if (! empty($maps_url)) {
                        $new_href = $maps_url;
                        $link_type_class = 'ahm-contact-link ahm-contact-maps-link';
                    }
                }

                if (null === $new_href) {
                    return $matches[0];
                }

                // Format inner text if this is a phone link and text matches raw phone or phone string
                if ($is_phone && ! empty($uk_phone['display'])) {
                    if (! empty($phone) && str_contains($text, $phone)) {
                        $text = str_replace($phone, $uk_phone['display'], $text);
                    } elseif (! empty($uk_phone['raw_digits']) && str_contains($text, $uk_phone['raw_digits'])) {
                        $text = str_replace($uk_phone['raw_digits'], $uk_phone['display'], $text);
                    } elseif ($text === $raw_href || str_contains($text, '[ahm_phone]')) {
                        $text = str_replace('[ahm_phone]', $uk_phone['display'], $text);
                    }
                }

                $all_attrs = trim($before . ' ' . $after);
                if (preg_match('/class="([^"]+)"/i', $all_attrs, $class_match)) {
                    $existing_classes = $class_match[1];
                    if (! str_contains($existing_classes, 'ahm-contact-link')) {
                        $updated_classes = trim($existing_classes . ' ' . $link_type_class);
                        $attrs_combined = preg_replace(
                            '/class="([^"]+)"/i',
                            'class="' . esc_attr($updated_classes) . '"',
                            $all_attrs
                        );
                        return sprintf('<a %s href="%s">%s</a>', $attrs_combined, esc_url($new_href), $text);
                    }
                } else {
                    $spacer = ! empty($all_attrs) ? ' ' : '';
                    return sprintf(
                        '<a %s%shref="%s" class="%s">%s</a>',
                        $all_attrs,
                        $spacer,
                        esc_url($new_href),
                        esc_attr($link_type_class),
                        $text
                    );
                }

                return sprintf('<a %s href="%s">%s</a>', $all_attrs, esc_url($new_href), $text);
            },
            $content
        );

        // Step 2: Auto-convert plain text phone numbers & emails outside HTML tags and existing <a> tags
        $parts = preg_split('/(<a\s+[^>]*?>.*?<\/a>|<[^>]+>)/is', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (is_array($parts)) {
            // Specified AHM phone number matching
            if (! empty($phone) && ! empty($uk_phone['tel'])) {
                $search_patterns = array_unique(array_filter([
                    $phone,
                    $uk_phone['display'],
                    $uk_phone['raw_digits'],
                ]));

                $phone_replacement = sprintf(
                    '<a href="%s" class="ahm-contact-link ahm-contact-phone-link">%s</a>',
                    esc_url($uk_phone['tel']),
                    esc_html($uk_phone['display'])
                );

                foreach ($parts as &$part) {
                    if (str_starts_with($part, '<')) {
                        continue;
                    }
                    foreach ($search_patterns as $pattern) {
                        if (str_contains($part, $pattern)) {
                            $part = str_replace($pattern, $phone_replacement, $part);
                            break;
                        }
                    }
                }
                unset($part);
            }

            // Global email auto-linking toggle
            if ($auto_all_emails) {
                $email_regex = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/';
                foreach ($parts as &$part) {
                    if (str_starts_with($part, '<')) {
                        continue;
                    }
                    $part = preg_replace_callback($email_regex, function (array $em_match): string {
                        $matched_email = $em_match[0];
                        return sprintf(
                            '<a href="%s" class="ahm-contact-link ahm-contact-email-link">%s</a>',
                            esc_url('mailto:' . $matched_email),
                            esc_html($matched_email)
                        );
                    }, $part);
                }
                unset($part);
            }

            // Global UK phone auto-linking toggle
            if ($auto_all_phones) {
                $uk_phone_regex = '/(?:\+44\s?7\d{3}|\+44\s?[12]\d{3}|07\d{3}|0[12]\d{3})\s?\d{6}/';
                foreach ($parts as &$part) {
                    if (str_starts_with($part, '<')) {
                        continue;
                    }
                    $part = preg_replace_callback($uk_phone_regex, function (array $ph_match): string {
                        $raw_match = $ph_match[0];
                        $fmt = self::format_uk_phone($raw_match);
                        return sprintf(
                            '<a href="%s" class="ahm-contact-link ahm-contact-phone-link">%s</a>',
                            esc_url($fmt['tel']),
                            esc_html($fmt['display'])
                        );
                    }, $part);
                }
                unset($part);
            }

            $content = implode('', $parts);
        }

        return $content;
    }

    /*--------------------------------------------------------------
     * Admin Tab Rendering
     *------------------------------------------------------------*/

    public function render_tab(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $options = self::get_options();
        ?>
        <div class="ahm-card" style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:20px; max-width:800px; margin-top:20px;">
            <h2><?php esc_html_e('Contact Information Settings', 'ahm-core'); ?></h2>
            <p><?php esc_html_e('Configure company contact details used across your site and Elementor widgets via shortcodes and dynamic tags.', 'ahm-core'); ?></p>

            <form method="post" action="options.php">
                <?php
                settings_fields('ahm_contact_info_group');
                ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="ahm_phone"><?php esc_html_e('Phone Number', 'ahm-core'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="ahm_phone" name="<?php echo esc_attr(self::OPTION_KEY); ?>[phone]" value="<?php echo esc_attr($options['phone']); ?>" class="regular-text" placeholder="+44 7700 900123 or 07700 900123" />
                            <p class="description"><?php esc_html_e('Contact phone number. Numbers starting with +44 or 0 will automatically format into UK standard layout.', 'ahm-core'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="ahm_email"><?php esc_html_e('Email Address', 'ahm-core'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="ahm_email" name="<?php echo esc_attr(self::OPTION_KEY); ?>[email]" value="<?php echo esc_attr($options['email']); ?>" class="regular-text" placeholder="info@example.com" />
                            <p class="description"><?php esc_html_e('Contact email address.', 'ahm-core'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="ahm_address"><?php esc_html_e('Physical Address', 'ahm-core'); ?></label>
                        </th>
                        <td>
                            <textarea id="ahm_address" name="<?php echo esc_attr(self::OPTION_KEY); ?>[address]" rows="4" class="large-text" placeholder="123 Business St&#10;Suite 100&#10;City, State 12345"><?php echo esc_textarea($options['address']); ?></textarea>
                            <p class="description"><?php esc_html_e('Multi-line street address or location details.', 'ahm-core'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="ahm_maps_url"><?php esc_html_e('Google Maps URL', 'ahm-core'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="ahm_maps_url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[maps_url]" value="<?php echo esc_attr($options['maps_url']); ?>" class="large-text" placeholder="https://maps.app.goo.gl/..." />
                            <p class="description"><?php esc_html_e('Google Maps share URL for your business location.', 'ahm-core'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Global Auto-Linking', 'ahm-core'); ?></th>
                        <td>
                            <fieldset>
                                <label for="ahm_auto_link_all_emails" style="margin-bottom:8px; display:block;">
                                    <input type="checkbox" id="ahm_auto_link_all_emails" name="<?php echo esc_attr(self::OPTION_KEY); ?>[auto_link_all_emails]" value="1" <?php checked(! empty($options['auto_link_all_emails'])); ?> />
                                    <strong><?php esc_html_e('Auto-link ALL Email Addresses', 'ahm-core'); ?></strong>
                                    <br />
                                    <span class="description"><?php esc_html_e('Automatically converts any email address in content into a mailto: link with inherited CSS styling.', 'ahm-core'); ?></span>
                                </label>

                                <label for="ahm_auto_link_all_phones" style="display:block;">
                                    <input type="checkbox" id="ahm_auto_link_all_phones" name="<?php echo esc_attr(self::OPTION_KEY); ?>[auto_link_all_phones]" value="1" <?php checked(! empty($options['auto_link_all_phones'])); ?> />
                                    <strong><?php esc_html_e('Auto-link ALL UK Phone Numbers', 'ahm-core'); ?></strong>
                                    <br />
                                    <span class="description"><?php esc_html_e('Automatically converts any UK phone number pattern in content into a formatted tel: link.', 'ahm-core'); ?></span>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Contact Info', 'ahm-core')); ?>
            </form>

            <hr style="margin: 30px 0 20px 0; border:0; border-top:1px solid #ddd;" />

            <h3><?php esc_html_e('Available Shortcodes', 'ahm-core'); ?></h3>
            <p><?php esc_html_e('Use these shortcodes in standard content, theme templates, or Elementor widgets:', 'ahm-core'); ?></p>

            <table class="widefat striped" style="max-width:100%;">
                <thead>
                    <tr>
                        <th style="width: 250px;"><strong><?php esc_html_e('Shortcode', 'ahm-core'); ?></strong></th>
                        <th><strong><?php esc_html_e('Output / Description', 'ahm-core'); ?></strong></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[ahm_phone]</code></td>
                        <td><?php esc_html_e('Outputs formatted UK phone number text. Plain text phone numbers in Text Editor widgets automatically convert to clickable tel: links.', 'ahm-core'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[ahm_phone link="true"]</code></td>
                        <td><?php esc_html_e('Outputs phone number wrapped in a clickable tel link:', 'ahm-core'); ?> <code>&lt;a href="tel:..." class="ahm-contact-link ahm-contact-phone-link"&gt;...&lt;/a&gt;</code></td>
                    </tr>
                    <tr>
                        <td><code>[ahm_email]</code></td>
                        <td><?php esc_html_e('Outputs plain email address.', 'ahm-core'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[ahm_email link="true"]</code></td>
                        <td><?php esc_html_e('Outputs email address wrapped in a clickable mailto link with inherited font styling.', 'ahm-core'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[ahm_address]</code></td>
                        <td><?php esc_html_e('Outputs formatted physical address with line breaks.', 'ahm-core'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[ahm_address link="true"]</code></td>
                        <td><?php esc_html_e('Outputs formatted address wrapped in a Google Maps external link.', 'ahm-core'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[ahm_maps_url]</code></td>
                        <td><?php esc_html_e('Outputs the raw Google Maps URL.', 'ahm-core'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[ahm_maps_url link="true"]</code></td>
                        <td><?php esc_html_e('Outputs a clickable hyperlink labeled "View on Google Maps".', 'ahm-core'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[ahm_contact field="phone|email|address|maps_url"]</code></td>
                        <td><?php esc_html_e('Unified shortcode syntax with optional link="true" parameter.', 'ahm-core'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /*--------------------------------------------------------------
     * Shortcode Handlers
     *------------------------------------------------------------*/

    public function register_shortcodes(): void
    {
        add_shortcode('ahm_phone', [$this, 'shortcode_phone']);
        add_shortcode('ahm_email', [$this, 'shortcode_email']);
        add_shortcode('ahm_address', [$this, 'shortcode_address']);
        add_shortcode('ahm_maps_url', [$this, 'shortcode_maps_url']);
        add_shortcode('ahm_contact', [$this, 'shortcode_unified']);
    }

    /**
     * [ahm_phone link="true|false" text="Custom Text"]
     *
     * @param array<string, mixed>|string $atts
     */
    public function shortcode_phone(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'link' => 'false',
            'text' => '',
        ], (array) $atts, 'ahm_phone');

        $options = self::get_options();
        $phone   = $options['phone'];

        if (empty($phone)) {
            return '';
        }

        $uk_phone     = self::format_uk_phone($phone);
        $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($uk_phone['display']);
        $is_link      = filter_var($parsed['link'], FILTER_VALIDATE_BOOLEAN);

        if ($is_link) {
            return sprintf(
                '<a href="%s" class="ahm-contact-link ahm-contact-phone-link">%s</a>',
                esc_url($uk_phone['tel']),
                $display_text
            );
        }

        return $display_text;
    }

    /**
     * [ahm_email link="true|false" text="Custom Text"]
     *
     * @param array<string, mixed>|string $atts
     */
    public function shortcode_email(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'link' => 'false',
            'text' => '',
        ], (array) $atts, 'ahm_email');

        $options = self::get_options();
        $email   = $options['email'];

        if (empty($email)) {
            return '';
        }

        $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($email);
        $is_link      = filter_var($parsed['link'], FILTER_VALIDATE_BOOLEAN);

        if ($is_link) {
            return sprintf(
                '<a href="%s" class="ahm-contact-link ahm-contact-email-link">%s</a>',
                esc_url('mailto:' . $email),
                $display_text
            );
        }

        return $display_text;
    }

    /**
     * [ahm_address link="true|false" raw="false|true"]
     *
     * @param array<string, mixed>|string $atts
     */
    public function shortcode_address(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'link' => 'false',
            'raw'  => 'false',
        ], (array) $atts, 'ahm_address');

        $options  = self::get_options();
        $address  = $options['address'];
        $maps_url = $options['maps_url'];

        if (empty($address)) {
            return '';
        }

        $is_raw  = filter_var($parsed['raw'], FILTER_VALIDATE_BOOLEAN);
        $is_link = filter_var($parsed['link'], FILTER_VALIDATE_BOOLEAN);

        $formatted = $is_raw ? esc_html($address) : nl2br(esc_html($address));

        if ($is_link && ! empty($maps_url)) {
            return sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" class="ahm-contact-link ahm-contact-address-link">%s</a>',
                esc_url($maps_url),
                $formatted
            );
        }

        return $formatted;
    }

    /**
     * [ahm_maps_url link="true|false" text="View on Google Maps"]
     *
     * @param array<string, mixed>|string $atts
     */
    public function shortcode_maps_url(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'link' => 'false',
            'text' => __('View on Google Maps', 'ahm-core'),
        ], (array) $atts, 'ahm_maps_url');

        $options  = self::get_options();
        $maps_url = $options['maps_url'];

        if (empty($maps_url)) {
            return '';
        }

        $is_link = filter_var($parsed['link'], FILTER_VALIDATE_BOOLEAN);

        if ($is_link) {
            $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($maps_url);
            return sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" class="ahm-contact-link ahm-contact-maps-link">%s</a>',
                esc_url($maps_url),
                $display_text
            );
        }

        return esc_url($maps_url);
    }

    /**
     * Unified shortcode syntax:
     * [ahm_contact field="phone|email|address|maps_url" link="true|false"]
     *
     * @param array<string, mixed>|string $atts
     */
    public function shortcode_unified(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'field' => 'phone',
            'link'  => 'false',
            'text'  => '',
            'raw'   => 'false',
        ], (array) $atts, 'ahm_contact');

        $field = strtolower(trim((string) $parsed['field']));

        return match ($field) {
            'phone'            => $this->shortcode_phone($parsed),
            'email'            => $this->shortcode_email($parsed),
            'address'          => $this->shortcode_address($parsed),
            'maps_url', 'maps' => $this->shortcode_maps_url($parsed),
            default            => '',
        };
    }
}
