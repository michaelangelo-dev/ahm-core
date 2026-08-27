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
     * @return array{phone: string, email: string, gmc_number: string, address_line1: string, address_line2: string, address: string, maps_url: string, booking_url: string, locations: array<int, array{name: string, address: string, phone: string, email: string, maps_url: string, booking_url: string}>, auto_link_all_emails: bool, auto_link_all_phones: bool}
     */
    public static function get_options(): array
    {
        $default_locations = array_fill(0, 6, [
            'name'        => '',
            'address'     => '',
            'phone'       => '',
            'email'       => '',
            'maps_url'    => '',
            'booking_url' => '',
        ]);

        $defaults = [
            'phone'                => '',
            'phone_format_intl'    => false,
            'email'                => '',
            'gmc_number'           => '',
            'address_line1'        => '',
            'address_line2'        => '',
            'address'              => '',
            'maps_url'             => '',
            'booking_url'          => '',
            'locations'            => $default_locations,
            'auto_link_all_emails' => false,
            'auto_link_all_phones' => false,
        ];

        $saved = get_option(self::OPTION_KEY, []);

        if (! is_array($saved)) {
            return $defaults;
        }

        $merged = array_merge($defaults, $saved);

        if (! isset($saved['locations']) || ! is_array($saved['locations'])) {
            $merged['locations'] = $default_locations;
        } else {
            $locations = [];
            for ($i = 0; $i < 6; $i++) {
                $loc = isset($saved['locations'][$i]) && is_array($saved['locations'][$i]) ? $saved['locations'][$i] : [];
                $locations[] = [
                    'name'        => (string) ($loc['name'] ?? ''),
                    'address'     => (string) ($loc['address'] ?? ''),
                    'phone'       => (string) ($loc['phone'] ?? ''),
                    'email'       => (string) ($loc['email'] ?? ''),
                    'maps_url'    => (string) ($loc['maps_url'] ?? ''),
                    'booking_url' => (string) ($loc['booking_url'] ?? ''),
                ];
            }
            $merged['locations'] = $locations;
        }

        return $merged;
    }

    /**
     * Helper to format phone number into UK standard layout if starting with +44 or 0.
     *
     * @param string $phone Raw phone string.
     * @param bool|null $override_intl Optional explicit override for international formatting (+44).
     * @return array{display: string, tel: string, raw_digits: string}
     */
    public static function format_uk_phone(string $phone, ?bool $override_intl = null): array
    {
        $trimmed = trim($phone);
        if (empty($trimmed)) {
            return ['display' => '', 'tel' => '', 'raw_digits' => ''];
        }

        $saved_options = self::get_options();
        $use_intl      = null !== $override_intl ? $override_intl : ! empty($saved_options['phone_format_intl']);

        $digits        = (string) preg_replace('/\D/', '', $trimmed);
        $has_plus_44   = str_starts_with($trimmed, '+44');
        $starts_with_0 = str_starts_with($trimmed, '0');

        $display = $trimmed;
        $tel     = 'tel:' . ($has_plus_44 ? '+' . $digits : $digits);

        if ($has_plus_44 || $starts_with_0 || str_starts_with($digits, '44')) {
            $national_digits = (str_starts_with($digits, '44')) ? '0' . substr($digits, 2) : $digits;
            if (! str_starts_with($national_digits, '0')) {
                $national_digits = '0' . $national_digits;
            }

            // International tel: link (+44...)
            $tel = 'tel:+44' . substr($national_digits, 1);

            $len         = strlen($national_digits);
            $body_digits = substr($national_digits, 1);

            if ($use_intl) {
                if (str_starts_with($national_digits, '02') && strlen($body_digits) >= 10) {
                    $display = '+44 ' . substr($body_digits, 0, 2) . ' ' . substr($body_digits, 2, 4) . ' ' . substr($body_digits, 6);
                } elseif (strlen($body_digits) >= 10) {
                    $display = '+44 ' . substr($body_digits, 0, 4) . ' ' . substr($body_digits, 4);
                } else {
                    $display = '+44 ' . $body_digits;
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
                'default'           => self::get_options(),
            ]
        );
    }

    /**
     * Sanitize contact info form submissions.
     *
     * @param mixed $input Submitted raw form data.
     * @return array<string, mixed>
     */
    public function sanitize_settings(mixed $input): array
    {
        $sanitized = [
            'phone'                => '',
            'email'                => '',
            'gmc_number'           => '',
            'address_line1'        => '',
            'address_line2'        => '',
            'address'              => '',
            'maps_url'             => '',
            'booking_url'          => '',
            'locations'            => array_fill(0, 6, ['name' => '', 'address' => '', 'phone' => '', 'email' => '', 'maps_url' => '', 'booking_url' => '']),
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

        if (isset($input['gmc_number'])) {
            $sanitized['gmc_number'] = sanitize_text_field((string) $input['gmc_number']);
        }

        if (isset($input['address_line1'])) {
            $sanitized['address_line1'] = sanitize_text_field((string) $input['address_line1']);
        }

        if (isset($input['address_line2'])) {
            $sanitized['address_line2'] = sanitize_text_field((string) $input['address_line2']);
        }

        if (isset($input['address'])) {
            $sanitized['address'] = sanitize_textarea_field((string) $input['address']);
        }

        if (isset($input['maps_url'])) {
            $sanitized['maps_url'] = esc_url_raw((string) $input['maps_url']);
        }

        if (isset($input['booking_url'])) {
            $sanitized['booking_url'] = esc_url_raw((string) $input['booking_url']);
        }

        $sanitized_locations = [];
        $raw_locations       = isset($input['locations']) && is_array($input['locations']) ? $input['locations'] : [];
        for ($i = 0; $i < 6; $i++) {
            $loc                   = isset($raw_locations[$i]) && is_array($raw_locations[$i]) ? $raw_locations[$i] : [];
            $sanitized_locations[] = [
                'name'        => isset($loc['name']) ? sanitize_text_field((string) $loc['name']) : '',
                'address'     => isset($loc['address']) ? sanitize_text_field((string) $loc['address']) : '',
                'phone'       => isset($loc['phone']) ? sanitize_text_field((string) $loc['phone']) : '',
                'email'       => isset($loc['email']) ? sanitize_email((string) $loc['email']) : '',
                'maps_url'    => isset($loc['maps_url']) ? esc_url_raw((string) $loc['maps_url']) : '',
                'booking_url' => isset($loc['booking_url']) ? esc_url_raw((string) $loc['booking_url']) : '',
            ];
        }
        $sanitized['locations'] = $sanitized_locations;

        $sanitized['phone_format_intl']    = ! empty($input['phone_format_intl']);
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

        // Collect all phones (primary + practice locations)
        $phones_to_process = [];
        if (! empty($phone)) {
            $phones_to_process[] = $phone;
        }
        if (! empty($options['locations']) && is_array($options['locations'])) {
            foreach ($options['locations'] as $loc) {
                if (! empty($loc['phone'])) {
                    $phones_to_process[] = (string) $loc['phone'];
                }
            }
        }
        $phones_to_process = array_values(array_unique($phones_to_process));

        // Collect all emails (primary + practice locations)
        $emails_to_process = [];
        if (! empty($email)) {
            $emails_to_process[] = $email;
        }
        if (! empty($options['locations']) && is_array($options['locations'])) {
            foreach ($options['locations'] as $loc) {
                if (! empty($loc['email'])) {
                    $emails_to_process[] = (string) $loc['email'];
                }
            }
        }
        $emails_to_process = array_values(array_unique($emails_to_process));

        $uk_phone  = ! empty($phone) ? self::format_uk_phone($phone) : ['display' => '', 'tel' => '', 'raw_digits' => ''];

        // Step 1: Process existing <a> tags with href shortcodes or matching targets & format inner text
        $regex_a = '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>(.*?)<\/a>/is';

        $content = preg_replace_callback(
            $regex_a,
            function (array $matches) use ($phones_to_process, $emails_to_process, $maps_url): string {
                $before   = $matches[1];
                $raw_href = $matches[2];
                $after    = $matches[3];
                $text     = $matches[4];

                $decoded_href     = urldecode($raw_href);
                $new_href         = null;
                $link_type_class  = '';
                $is_phone         = false;
                $matched_uk_phone = null;

                if (str_contains($decoded_href, '[ahm_phone]') || str_contains($decoded_href, 'ahm_phone')) {
                    $p_first = $phones_to_process[0] ?? '';
                    if (! empty($p_first)) {
                        $matched_uk_phone = self::format_uk_phone($p_first);
                        $new_href         = $matched_uk_phone['tel'];
                        $link_type_class  = 'ahm-contact-link ahm-contact-phone-link';
                        $is_phone         = true;
                    }
                } else {
                    foreach ($phones_to_process as $p_item) {
                        $p_uk = self::format_uk_phone($p_item);
                        if (
                            (! empty($p_item) && str_contains($decoded_href, $p_item)) ||
                            (! empty($p_uk['raw_digits']) && str_contains($decoded_href, $p_uk['raw_digits'])) ||
                            (! empty($p_item) && str_contains($text, $p_item)) ||
                            (! empty($p_uk['raw_digits']) && str_contains($text, $p_uk['raw_digits']))
                        ) {
                            if (! empty($p_uk['tel'])) {
                                $new_href         = $p_uk['tel'];
                                $link_type_class  = 'ahm-contact-link ahm-contact-phone-link';
                                $is_phone         = true;
                                $matched_uk_phone = $p_uk;
                                break;
                            }
                        }
                    }
                }

                if (null === $new_href) {
                    if (str_contains($decoded_href, '[ahm_email]') || str_contains($decoded_href, 'ahm_email')) {
                        $em_first = $emails_to_process[0] ?? '';
                        if (! empty($em_first)) {
                            $new_href        = 'mailto:' . $em_first;
                            $link_type_class = 'ahm-contact-link ahm-contact-email-link';
                        }
                    } else {
                        foreach ($emails_to_process as $em_item) {
                            if (
                                (! empty($em_item) && str_contains($decoded_href, $em_item)) ||
                                (! empty($em_item) && str_contains($text, $em_item))
                            ) {
                                $new_href        = 'mailto:' . $em_item;
                                $link_type_class = 'ahm-contact-link ahm-contact-email-link';
                                break;
                            }
                        }
                    }
                }

                if (null === $new_href) {
                    if (
                        str_contains($decoded_href, '[ahm_maps_url]') ||
                        str_contains($decoded_href, 'ahm_maps_url')
                    ) {
                        if (! empty($maps_url)) {
                            $new_href        = $maps_url;
                            $link_type_class = 'ahm-contact-link ahm-contact-maps-link';
                        }
                    }
                }

                if (null === $new_href) {
                    return $matches[0];
                }

                // Format inner text if this is a phone link and text matches raw phone or phone string
                if ($is_phone && ! empty($matched_uk_phone['display'])) {
                    foreach ($phones_to_process as $p_item) {
                        $p_uk = self::format_uk_phone($p_item);
                        if (! empty($p_item) && str_contains($text, $p_item)) {
                            $text = str_replace($p_item, $p_uk['display'], $text);
                        } elseif (! empty($p_uk['raw_digits']) && str_contains($text, $p_uk['raw_digits'])) {
                            $text = str_replace($p_uk['raw_digits'], $p_uk['display'], $text);
                        }
                    }
                    if ($text === $raw_href || str_contains($text, '[ahm_phone]')) {
                        $text = str_replace('[ahm_phone]', $matched_uk_phone['display'], $text);
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
            // Specified AHM phone numbers matching (primary + practice locations)
            foreach ($phones_to_process as $p_item) {
                $p_uk = self::format_uk_phone($p_item);
                if (empty($p_uk['tel'])) {
                    continue;
                }

                $search_patterns = array_unique(array_filter([
                    $p_item,
                    $p_uk['display'],
                    $p_uk['raw_digits'],
                ]));

                $phone_replacement = sprintf(
                    '<a href="%s" class="ahm-contact-link ahm-contact-phone-link">%s</a>',
                    esc_url($p_uk['tel']),
                    esc_html($p_uk['display'])
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

            // Specified AHM email addresses matching (primary + practice locations)
            foreach ($emails_to_process as $em_item) {
                $email_replacement = sprintf(
                    '<a href="%s" class="ahm-contact-link ahm-contact-email-link">%s</a>',
                    esc_url('mailto:' . $em_item),
                    esc_html($em_item)
                );

                foreach ($parts as &$part) {
                    if (str_starts_with($part, '<')) {
                        continue;
                    }
                    if (str_contains($part, $em_item)) {
                        $part = str_replace($em_item, $email_replacement, $part);
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
                            <br />
                            <label for="ahm_phone_format_intl" style="margin-top:6px; display:inline-block;">
                                <input type="checkbox" id="ahm_phone_format_intl" name="<?php echo esc_attr(self::OPTION_KEY); ?>[phone_format_intl]" value="1" <?php checked(! empty($options['phone_format_intl'])); ?> />
                                <strong><?php esc_html_e('Format Display as +44 (International UK Format)', 'ahm-core'); ?></strong>
                            </label>
                            <p class="description"><?php esc_html_e('If checked, displays with +44 prefix (e.g. +44 1656 857878). If unchecked, displays with 0 prefix (e.g. 01656 857878).', 'ahm-core'); ?></p>
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
                            <label for="ahm_gmc_number"><?php esc_html_e('GMC Number', 'ahm-core'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="ahm_gmc_number" name="<?php echo esc_attr(self::OPTION_KEY); ?>[gmc_number]" value="<?php echo esc_attr($options['gmc_number'] ?? ''); ?>" class="regular-text" placeholder="e.g. 1234567" />
                            <p class="description"><?php esc_html_e('General Medical Council (GMC) registration number.', 'ahm-core'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="ahm_address_line1"><?php esc_html_e('Hospital / Facility Name', 'ahm-core'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="ahm_address_line1" name="<?php echo esc_attr(self::OPTION_KEY); ?>[address_line1]" value="<?php echo esc_attr($options['address_line1']); ?>" class="large-text" placeholder="e.g. RJAH, Ludlow Wing" />
                            <p class="description"><?php esc_html_e('Hospital, building, or practice name line.', 'ahm-core'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="ahm_address_line2"><?php esc_html_e('Town & Postcode', 'ahm-core'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="ahm_address_line2" name="<?php echo esc_attr(self::OPTION_KEY); ?>[address_line2]" value="<?php echo esc_attr($options['address_line2']); ?>" class="large-text" placeholder="e.g. Gobowen, Oswestry SY10 7AG" />
                            <p class="description"><?php esc_html_e('Town, county, or postcode line.', 'ahm-core'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="ahm_address"><?php esc_html_e('Full Physical Address', 'ahm-core'); ?></label>
                        </th>
                        <td>
                            <textarea id="ahm_address" name="<?php echo esc_attr(self::OPTION_KEY); ?>[address]" rows="4" class="large-text" placeholder="123 Business St&#10;Suite 100&#10;City, State 12345"><?php echo esc_textarea($options['address']); ?></textarea>
                            <p class="description"><?php esc_html_e('Full multi-line street address or combined location details.', 'ahm-core'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="ahm_maps_url"><?php esc_html_e('Primary Google Maps URL', 'ahm-core'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="ahm_maps_url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[maps_url]" value="<?php echo esc_attr($options['maps_url']); ?>" class="large-text" placeholder="https://maps.app.goo.gl/..." />
                            <p class="description"><?php esc_html_e('Primary Google Maps share URL for main business location.', 'ahm-core'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="ahm_booking_url"><?php esc_html_e('Primary Booking URL', 'ahm-core'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="ahm_booking_url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[booking_url]" value="<?php echo esc_attr($options['booking_url'] ?? ''); ?>" class="large-text" placeholder="https://..." />
                            <p class="description"><?php esc_html_e('Primary external appointment / consultation booking link (e.g. Doctify, Top Doctors, or booking portal).', 'ahm-core'); ?></p>
                        </td>
                    </tr>
                </table>

                <hr style="margin: 25px 0 15px 0; border:0; border-top:1px solid #e5e5e5;" />

                <h3><?php esc_html_e('Practice Locations (Multi-Location Support)', 'ahm-core'); ?></h3>
                <p class="description"><?php esc_html_e('Add up to 6 practice locations / consulting rooms. Access these in Elementor using the "AHM Location Details" dynamic tag.', 'ahm-core'); ?></p>

                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 15px; margin-top: 15px; margin-bottom: 20px;">
                    <?php
                    $locations = $options['locations'] ?? [];
                    for ($i = 0; $i < 6; $i++) :
                        $loc_name  = esc_attr($locations[$i]['name'] ?? '');
                        $loc_addr  = esc_attr($locations[$i]['address'] ?? '');
                        $loc_phone = esc_attr($locations[$i]['phone'] ?? '');
                        $loc_email = esc_attr($locations[$i]['email'] ?? '');
                        $loc_maps  = esc_attr($locations[$i]['maps_url'] ?? '');
                        $loc_book  = esc_attr($locations[$i]['booking_url'] ?? '');
                        $num       = $i + 1;
                        ?>
                        <div style="background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 4px; padding: 12px 15px;">
                            <strong style="display:block; margin-bottom: 8px; color: #1d2327; font-size: 13px;">
                                <?php echo sprintf(esc_html__('Location %d', 'ahm-core'), $num); ?>
                            </strong>
                            <div style="margin-bottom: 8px;">
                                <label style="display:block; font-size: 11px; color:#646970; margin-bottom: 2px;"><?php esc_html_e('Facility / Hospital Name:', 'ahm-core'); ?></label>
                                <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[locations][<?php echo $i; ?>][name]" value="<?php echo $loc_name; ?>" class="widefat" placeholder="e.g. RJAH, Ludlow Wing" />
                            </div>
                            <div style="margin-bottom: 8px;">
                                <label style="display:block; font-size: 11px; color:#646970; margin-bottom: 2px;"><?php esc_html_e('Town & Postcode:', 'ahm-core'); ?></label>
                                <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[locations][<?php echo $i; ?>][address]" value="<?php echo $loc_addr; ?>" class="widefat" placeholder="e.g. Gobowen, Oswestry SY10 7AG" />
                            </div>
                            <div style="margin-bottom: 8px;">
                                <label style="display:block; font-size: 11px; color:#646970; margin-bottom: 2px;"><?php esc_html_e('Phone Number (Optional):', 'ahm-core'); ?></label>
                                <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[locations][<?php echo $i; ?>][phone]" value="<?php echo $loc_phone; ?>" class="widefat" placeholder="e.g. 01691 404000 or +44 1691 404000" />
                            </div>
                            <div style="margin-bottom: 8px;">
                                <label style="display:block; font-size: 11px; color:#646970; margin-bottom: 2px;"><?php esc_html_e('Email Address (Optional):', 'ahm-core'); ?></label>
                                <input type="email" name="<?php echo esc_attr(self::OPTION_KEY); ?>[locations][<?php echo $i; ?>][email]" value="<?php echo $loc_email; ?>" class="widefat" placeholder="e.g. appointments@hospital.co.uk" />
                            </div>
                            <div style="margin-bottom: 8px;">
                                <label style="display:block; font-size: 11px; color:#646970; margin-bottom: 2px;"><?php esc_html_e('Google Maps Link (Optional):', 'ahm-core'); ?></label>
                                <input type="url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[locations][<?php echo $i; ?>][maps_url]" value="<?php echo $loc_maps; ?>" class="widefat" placeholder="https://maps.app.goo.gl/..." />
                            </div>
                            <div>
                                <label style="display:block; font-size: 11px; color:#646970; margin-bottom: 2px;"><?php esc_html_e('Booking Link (Optional):', 'ahm-core'); ?></label>
                                <input type="url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[locations][<?php echo $i; ?>][booking_url]" value="<?php echo $loc_book; ?>" class="widefat" placeholder="https://..." />
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <table class="form-table" role="presentation">

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
                        <td><code>[ahm_gmc]</code> / <code>[ahm_gmc_number]</code></td>
                        <td><?php esc_html_e('Outputs plain GMC registration number.', 'ahm-core'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[ahm_gmc link="true"]</code></td>
                        <td><?php esc_html_e('Outputs GMC number wrapped in an external link to the GMC Medical Register.', 'ahm-core'); ?></td>
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
                        <td><code>[ahm_booking_url]</code></td>
                        <td><?php esc_html_e('Outputs the raw Primary Booking / Appointment URL.', 'ahm-core'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[ahm_booking_url link="true"]</code></td>
                        <td><?php esc_html_e('Outputs a clickable hyperlink labeled "Book an Appointment".', 'ahm-core'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[ahm_contact field="phone|email|gmc|address|maps_url|booking_url"]</code></td>
                        <td><?php esc_html_e('Unified shortcode syntax with optional link="true" parameter.', 'ahm-core'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[ahm_location index="1" field="name|address|phone|email|maps_url|booking_url|full"]</code></td>
                        <td><?php esc_html_e('Practice location shortcode for index 1-6 with optional link="true" parameter.', 'ahm-core'); ?></td>
                    </tr>
                    <tr>
                        <td><code>before="..." after="..." fallback="..."</code></td>
                        <td><?php esc_html_e('Shortcodes accept before, after, and fallback attributes for prefixing, suffixing, or default fallback text when empty.', 'ahm-core'); ?></td>
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
        add_shortcode('ahm_gmc', [$this, 'shortcode_gmc_number']);
        add_shortcode('ahm_gmc_number', [$this, 'shortcode_gmc_number']);
        add_shortcode('ahm_address', [$this, 'shortcode_address']);
        add_shortcode('ahm_maps_url', [$this, 'shortcode_maps_url']);
        add_shortcode('ahm_booking_url', [$this, 'shortcode_booking_url']);
        add_shortcode('ahm_booking', [$this, 'shortcode_booking_url']);
        add_shortcode('ahm_contact', [$this, 'shortcode_unified']);
        add_shortcode('ahm_location', [$this, 'shortcode_location']);
    }

    /**
     * Wrap shortcode output with optional before, after, and fallback strings.
     *
     * @param string $output The formatted shortcode string (or empty if option value is missing).
     * @param array<string, mixed> $parsed The parsed shortcode attributes array.
     * @return string
     */
    private function wrap_shortcode_output(string $output, array $parsed): string
    {
        $before   = isset($parsed['before']) ? (string) $parsed['before'] : '';
        $after    = isset($parsed['after']) ? (string) $parsed['after'] : '';
        $fallback = isset($parsed['fallback']) ? (string) $parsed['fallback'] : '';

        if ($output === '') {
            if ($fallback !== '') {
                return $before . $fallback . $after;
            }
            return '';
        }

        return $before . $output . $after;
    }

    /**
     * [ahm_phone link="true|false" text="Custom Text" before="" after="" fallback=""]
     *
     * @param array<string, mixed>|string $atts
     */
    public function shortcode_phone(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'link'     => 'false',
            'text'     => '',
            'before'   => '',
            'after'    => '',
            'fallback' => '',
        ], (array) $atts, 'ahm_phone');

        $options = self::get_options();
        $phone   = $options['phone'];

        if (empty($phone)) {
            return $this->wrap_shortcode_output('', $parsed);
        }

        $uk_phone     = self::format_uk_phone($phone);
        $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($uk_phone['display']);
        $is_link      = filter_var($parsed['link'], FILTER_VALIDATE_BOOLEAN);

        if ($is_link) {
            $res = sprintf(
                '<a href="%s" class="ahm-contact-link ahm-contact-phone-link">%s</a>',
                esc_url($uk_phone['tel']),
                $display_text
            );
            return $this->wrap_shortcode_output($res, $parsed);
        }

        return $this->wrap_shortcode_output($display_text, $parsed);
    }

    /**
     * [ahm_email link="true|false" text="Custom Text" before="" after="" fallback=""]
     *
     * @param array<string, mixed>|string $atts
     */
    public function shortcode_email(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'link'     => 'false',
            'text'     => '',
            'before'   => '',
            'after'    => '',
            'fallback' => '',
        ], (array) $atts, 'ahm_email');

        $options = self::get_options();
        $email   = $options['email'];

        if (empty($email)) {
            return $this->wrap_shortcode_output('', $parsed);
        }

        $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($email);
        $is_link      = filter_var($parsed['link'], FILTER_VALIDATE_BOOLEAN);

        if ($is_link) {
            $res = sprintf(
                '<a href="%s" class="ahm-contact-link ahm-contact-email-link">%s</a>',
                esc_url('mailto:' . $email),
                $display_text
            );
            return $this->wrap_shortcode_output($res, $parsed);
        }

        return $this->wrap_shortcode_output($display_text, $parsed);
    }

    /**
     * [ahm_gmc link="true|false" text="Custom Text" before="" after="" fallback=""]
     * [ahm_gmc_number link="true|false" text="Custom Text" before="" after="" fallback=""]
     *
     * @param array<string, mixed>|string $atts
     */
    public function shortcode_gmc_number(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'link'     => 'false',
            'text'     => '',
            'before'   => '',
            'after'    => '',
            'fallback' => '',
        ], (array) $atts, 'ahm_gmc');

        $options    = self::get_options();
        $gmc_number = $options['gmc_number'] ?? '';

        if (empty($gmc_number)) {
            return $this->wrap_shortcode_output('', $parsed);
        }

        $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($gmc_number);
        $is_link      = filter_var($parsed['link'], FILTER_VALIDATE_BOOLEAN);

        if ($is_link) {
            $digits  = preg_replace('/\D/', '', $gmc_number);
            $gmc_url = ! empty($digits) ? 'https://www.gmc-uk.org/doctors/' . $digits : 'https://www.gmc-uk.org/doctors/';
            $res     = sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" class="ahm-contact-link ahm-contact-gmc-link">%s</a>',
                esc_url($gmc_url),
                $display_text
            );
            return $this->wrap_shortcode_output($res, $parsed);
        }

        return $this->wrap_shortcode_output($display_text, $parsed);
    }

    /**
     * [ahm_address link="true|false" raw="false|true" before="" after="" fallback=""]
     *
     * @param array<string, mixed>|string $atts
     */
    public function shortcode_address(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'link'     => 'false',
            'raw'      => 'false',
            'before'   => '',
            'after'    => '',
            'fallback' => '',
        ], (array) $atts, 'ahm_address');

        $options  = self::get_options();
        $address  = $options['address'];
        $maps_url = $options['maps_url'];

        if (empty($address)) {
            return $this->wrap_shortcode_output('', $parsed);
        }

        $is_raw  = filter_var($parsed['raw'], FILTER_VALIDATE_BOOLEAN);
        $is_link = filter_var($parsed['link'], FILTER_VALIDATE_BOOLEAN);

        $formatted = $is_raw ? esc_html($address) : nl2br(esc_html($address));

        if ($is_link && ! empty($maps_url)) {
            $res = sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" class="ahm-contact-link ahm-contact-address-link">%s</a>',
                esc_url($maps_url),
                $formatted
            );
            return $this->wrap_shortcode_output($res, $parsed);
        }

        return $this->wrap_shortcode_output($formatted, $parsed);
    }

    /**
     * [ahm_maps_url link="true|false" text="View on Google Maps" before="" after="" fallback=""]
     *
     * @param array<string, mixed>|string $atts
     */
    public function shortcode_maps_url(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'link'     => 'false',
            'text'     => __('View on Google Maps', 'ahm-core'),
            'before'   => '',
            'after'    => '',
            'fallback' => '',
        ], (array) $atts, 'ahm_maps_url');

        $options  = self::get_options();
        $maps_url = $options['maps_url'];

        if (empty($maps_url)) {
            return $this->wrap_shortcode_output('', $parsed);
        }

        $is_link = filter_var($parsed['link'], FILTER_VALIDATE_BOOLEAN);

        if ($is_link) {
            $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($maps_url);
            $res          = sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" class="ahm-contact-link ahm-contact-maps-link">%s</a>',
                esc_url($maps_url),
                $display_text
            );
            return $this->wrap_shortcode_output($res, $parsed);
        }

        return $this->wrap_shortcode_output(esc_url($maps_url), $parsed);
    }

    /**
     * [ahm_booking_url link="true|false" text="Book an Appointment" before="" after="" fallback=""]
     * [ahm_booking link="true|false" text="Book an Appointment" before="" after="" fallback=""]
     *
     * @param array<string, mixed>|string $atts
     */
    public function shortcode_booking_url(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'link'     => 'false',
            'text'     => __('Book an Appointment', 'ahm-core'),
            'before'   => '',
            'after'    => '',
            'fallback' => '',
        ], (array) $atts, 'ahm_booking_url');

        $options     = self::get_options();
        $booking_url = $options['booking_url'] ?? '';

        if (empty($booking_url)) {
            return $this->wrap_shortcode_output('', $parsed);
        }

        $is_link = filter_var($parsed['link'], FILTER_VALIDATE_BOOLEAN);

        if ($is_link) {
            $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($booking_url);
            $res          = sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" class="ahm-contact-link ahm-contact-booking-link">%s</a>',
                esc_url($booking_url),
                $display_text
            );
            return $this->wrap_shortcode_output($res, $parsed);
        }

        return $this->wrap_shortcode_output(esc_url($booking_url), $parsed);
    }

    /**
     * Unified shortcode syntax:
     * [ahm_contact field="phone|email|gmc|address|maps_url|booking_url" link="true|false" before="" after="" fallback=""]
     *
     * @param array<string, mixed>|string $atts
     */
    public function shortcode_unified(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'field'    => 'phone',
            'link'     => 'false',
            'text'     => '',
            'raw'      => 'false',
            'before'   => '',
            'after'    => '',
            'fallback' => '',
        ], (array) $atts, 'ahm_contact');

        $field = strtolower(trim((string) $parsed['field']));

        return match ($field) {
            'phone'                            => $this->shortcode_phone($parsed),
            'email'                            => $this->shortcode_email($parsed),
            'gmc', 'gmc_number', 'gmc-number'  => $this->shortcode_gmc_number($parsed),
            'address'                          => $this->shortcode_address($parsed),
            'maps_url', 'maps'                 => $this->shortcode_maps_url($parsed),
            'booking', 'booking_url', 'book'   => $this->shortcode_booking_url($parsed),
            default                            => '',
        };
    }

    /**
     * Practice location shortcode syntax:
     * [ahm_location index="1-6" field="name|address|phone|email|maps_url|booking_url|full" link="true|false" text="Custom Text" before="" after="" fallback=""]
     *
     * @param array<string, mixed>|string $atts
     */
    public function shortcode_location(array|string $atts = []): string
    {
        $parsed = shortcode_atts([
            'index'    => '1',
            'field'    => 'name',
            'link'     => 'false',
            'text'     => '',
            'before'   => '',
            'after'    => '',
            'fallback' => '',
        ], (array) $atts, 'ahm_location');

        $idx       = max(0, min(5, ((int) $parsed['index']) - 1));
        $options   = self::get_options();
        $locations = $options['locations'] ?? [];

        if (empty($locations[$idx])) {
            return $this->wrap_shortcode_output('', $parsed);
        }

        $loc     = $locations[$idx];
        $field   = strtolower(trim((string) $parsed['field']));
        $is_link = filter_var($parsed['link'], FILTER_VALIDATE_BOOLEAN);

        switch ($field) {
            case 'phone':
                $phone = $loc['phone'] ?? '';
                if (empty($phone)) {
                    return $this->wrap_shortcode_output('', $parsed);
                }
                $uk_phone     = self::format_uk_phone($phone);
                $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($uk_phone['display']);
                if ($is_link) {
                    $res = sprintf(
                        '<a href="%s" class="ahm-contact-link ahm-contact-phone-link">%s</a>',
                        esc_url($uk_phone['tel']),
                        $display_text
                    );
                    return $this->wrap_shortcode_output($res, $parsed);
                }
                return $this->wrap_shortcode_output($display_text, $parsed);

            case 'email':
                $email = $loc['email'] ?? '';
                if (empty($email)) {
                    return $this->wrap_shortcode_output('', $parsed);
                }
                $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($email);
                if ($is_link) {
                    $res = sprintf(
                        '<a href="%s" class="ahm-contact-link ahm-contact-email-link">%s</a>',
                        esc_url('mailto:' . $email),
                        $display_text
                    );
                    return $this->wrap_shortcode_output($res, $parsed);
                }
                return $this->wrap_shortcode_output($display_text, $parsed);

            case 'address':
                $addr = $loc['address'] ?? '';
                if (empty($addr)) {
                    return $this->wrap_shortcode_output('', $parsed);
                }
                $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($addr);
                if ($is_link && ! empty($loc['maps_url'])) {
                    $res = sprintf(
                        '<a href="%s" target="_blank" rel="noopener noreferrer" class="ahm-contact-link ahm-contact-address-link">%s</a>',
                        esc_url($loc['maps_url']),
                        $display_text
                    );
                    return $this->wrap_shortcode_output($res, $parsed);
                }
                return $this->wrap_shortcode_output($display_text, $parsed);

            case 'maps_url':
            case 'maps':
                $maps_url = $loc['maps_url'] ?? '';
                if (empty($maps_url)) {
                    return $this->wrap_shortcode_output('', $parsed);
                }
                if ($is_link) {
                    $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($maps_url);
                    $res = sprintf(
                        '<a href="%s" target="_blank" rel="noopener noreferrer" class="ahm-contact-link ahm-contact-maps-link">%s</a>',
                        esc_url($maps_url),
                        $display_text
                    );
                    return $this->wrap_shortcode_output($res, $parsed);
                }
                return $this->wrap_shortcode_output(esc_url($maps_url), $parsed);

            case 'booking':
            case 'booking_url':
            case 'book':
                $booking_url = $loc['booking_url'] ?? '';
                if (empty($booking_url)) {
                    return $this->wrap_shortcode_output('', $parsed);
                }
                if ($is_link) {
                    $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($booking_url);
                    $res = sprintf(
                        '<a href="%s" target="_blank" rel="noopener noreferrer" class="ahm-contact-link ahm-contact-booking-link">%s</a>',
                        esc_url($booking_url),
                        $display_text
                    );
                    return $this->wrap_shortcode_output($res, $parsed);
                }
                return $this->wrap_shortcode_output(esc_url($booking_url), $parsed);

            case 'full':
                $full = trim(($loc['name'] ?? '') . ', ' . ($loc['address'] ?? ''), ', ');
                if (empty($full)) {
                    return $this->wrap_shortcode_output('', $parsed);
                }
                $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($full);
                if ($is_link && ! empty($loc['maps_url'])) {
                    $res = sprintf(
                        '<a href="%s" target="_blank" rel="noopener noreferrer" class="ahm-contact-link ahm-contact-address-link">%s</a>',
                        esc_url($loc['maps_url']),
                        $display_text
                    );
                    return $this->wrap_shortcode_output($res, $parsed);
                }
                return $this->wrap_shortcode_output($display_text, $parsed);

            case 'name':
            default:
                $name = $loc['name'] ?? '';
                if (empty($name)) {
                    return $this->wrap_shortcode_output('', $parsed);
                }
                $display_text = ! empty($parsed['text']) ? esc_html((string) $parsed['text']) : esc_html($name);
                return $this->wrap_shortcode_output($display_text, $parsed);
        }
    }
}
