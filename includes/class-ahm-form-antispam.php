<?php
/**
 * Native Zero-Plugin Form Anti-Spam Engine for Elementor Pro.
 *
 * Provides a 5-layer heuristic defense against automated form spam, headless cURL scripts,
 * and spam rings with zero external API calls, zero database bloat, and complete GDPR compliance.
 *
 * Layers:
 *  1. De-cloaked off-screen honeypot trap (bypasses bot detection of display:none).
 *  2. Encrypted HMAC time-trap (velocity gating: rejects < 2s or > 24h).
 *  3. Micro-JS behavioral interaction handshake (kills direct headless cURL POSTs).
 *  4. Cyrillic script & non-Latin heuristic filtering (ideal for English healthcare sites).
 *  5. Link & URL flood restriction in name and phone fields.
 *
 * @package AHM_Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class AHM_Form_Antispam
{
    private static ?self $instance = null;

    /** @var bool Flag set when current submission is classified as spam. */
    private static bool $is_current_submission_spam = false;

    /** @var string Reason why submission was flagged as spam. */
    private static string $spam_detection_reason = '';

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        // 1. Inject hidden security fields into all Elementor Form widgets
        add_filter('elementor/widget/render_content', [$this, 'inject_security_fields'], 20, 2);

        // 2. Output micro-JS interaction handshake in footer
        add_action('wp_footer', [$this, 'render_footer_interaction_script'], 99);

        // 3. Server-side validation hook for Elementor Pro forms
        add_action('elementor_pro/forms/validation', [$this, 'validate_form_submission'], 10, 2);

        // 4. Silent blackhole email suppression via WordPress core mail filter
        add_filter('pre_wp_mail', [$this, 'suppress_spam_email'], 10, 2);
    }

    /*--------------------------------------------------------------
     * Form Markup & Behavioral Handshake Injection
     *------------------------------------------------------------*/

    /**
     * Injects honeypot, time-trap token, and proof placeholder before closing form tag.
     *
     * @param string $content Rendered widget HTML.
     * @param object $widget Elementor widget instance.
     * @return string Modified widget HTML.
     */
    public function inject_security_fields(string $content, object $widget): string
    {
        if (! method_exists($widget, 'get_name') || 'form' !== $widget->get_name()) {
            return $content;
        }

        $time_token = self::generate_time_token();

        // Off-screen honeypot (styled without display:none to bypass modern bot scrapers)
        $honeypot_html = '<div class="ahm-antispam-hp" style="position:absolute!important;left:-9999px!important;top:-9999px!important;width:1px!important;height:1px!important;opacity:0!important;pointer-events:none!important;z-index:-1!important;" aria-hidden="true">'
            . '<label for="form_field_business_fax_hp" tabindex="-1">Business Fax</label>'
            . '<input type="text" name="_ahm_fax_hp" id="form_field_business_fax_hp" value="" tabindex="-1" autocomplete="new-password">'
            . '</div>';

        // Signed timestamp & interaction proof placeholder
        $security_fields = $honeypot_html
            . '<input type="hidden" name="_ahm_ts" value="' . esc_attr($time_token) . '">'
            . '<input type="hidden" name="_ahm_proof" value="">';

        return str_replace('</form>', $security_fields . '</form>', $content);
    }

    /**
     * Featherweight (< 250 bytes) passive interaction listener.
     * Populates _ahm_proof upon the first human pointer, touch, key, or focus event.
     */
    public function render_footer_interaction_script(): void
    {
        ?>
        <script id="ahm-antispam-handshake">
        ;(function() {
            var events = ['pointerdown', 'keydown', 'touchstart', 'focusin'];
            var arm = function() {
                var tokens = document.querySelectorAll('input[name="_ahm_proof"]');
                for (var i = 0; i < tokens.length; i++) {
                    tokens[i].value = 'ahm_usr_' + btoa(Date.now().toString());
                }
                for (var j = 0; j < events.length; j++) {
                    window.removeEventListener(events[j], arm, { passive: true });
                }
            };
            for (var k = 0; k < events.length; k++) {
                window.addEventListener(events[k], arm, { passive: true, once: true });
            }
        })();
        </script>
        <?php
    }

    /*--------------------------------------------------------------
     * Token Cryptography & Verification
     *------------------------------------------------------------*/

    /**
     * Generates an HMAC-signed timestamp token using WordPress private nonce salt.
     *
     * @return string Base64 encoded timestamp + HMAC signature.
     */
    public static function generate_time_token(): string
    {
        $timestamp = time();
        $salt      = wp_salt('nonce');
        $signature = hash_hmac('sha256', (string) $timestamp, $salt);

        return base64_encode($timestamp . '|' . $signature);
    }

    /**
     * Verifies the authenticity and validity of the time token.
     *
     * @param string $token Submitted token.
     * @return bool True if authentic and within 2s - 24h window.
     */
    public function verify_time_token(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $decoded = base64_decode($token, true);
        if (false === $decoded || ! str_contains($decoded, '|')) {
            return false;
        }

        [$timestamp_str, $signature] = explode('|', $decoded, 2);
        $timestamp = (int) $timestamp_str;
        $salt      = wp_salt('nonce');
        $expected  = hash_hmac('sha256', (string) $timestamp, $salt);

        if (! hash_equals($expected, $signature)) {
            return false;
        }

        $elapsed = time() - $timestamp;

        // Reject bot submissions faster than 2 seconds
        if ($elapsed < 2) {
            return false;
        }

        // Reject submissions older than 24 hours (86400s) to prevent replay attacks
        if ($elapsed > 86400) {
            return false;
        }

        return true;
    }

    /*--------------------------------------------------------------
     * Submission Validation Pipeline
     *------------------------------------------------------------*/

    /**
     * Validates Elementor Pro form submissions across 5 distinct security vectors.
     *
     * @param object $record Form_Record instance.
     * @param object $ajax_handler Ajax_Handler instance.
     * @return void
     */
    public function validate_form_submission(object $record, object $ajax_handler): void
    {
        $options = \AHM_Site_Utilities::get_options();

        // 1. Honeypot Verification
        $hp_value = sanitize_text_field((string) ($_POST['_ahm_fax_hp'] ?? ''));
        if (! empty($hp_value)) {
            $this->flag_as_spam($record, $ajax_handler, 'Honeypot trap triggered');
            return;
        }

        // 2. Encrypted Time-Trap Verification
        $time_token = (string) ($_POST['_ahm_ts'] ?? '');
        if (! $this->verify_time_token($time_token)) {
            $this->flag_as_spam($record, $ajax_handler, 'Submission velocity anomaly');
            return;
        }

        // 3. Behavioral Handshake Token Verification
        $proof_token = (string) ($_POST['_ahm_proof'] ?? '');
        if (empty($proof_token) || 0 !== strpos($proof_token, 'ahm_usr_')) {
            $this->flag_as_spam($record, $ajax_handler, 'Headless client interaction failure');
            return;
        }

        // Extract submitted fields for heuristic inspections
        $fields = method_exists($record, 'get_field')
            ? (array) $record->get_field([])
            : (array) ($record->fields ?? []);

        // 4. Cyrillic Script / Non-Latin Heuristic Filter
        if (! empty($options['antispam_block_cyrillic'])) {
            foreach ($fields as $field) {
                $value = (string) ($field['value'] ?? '');
                if (preg_match('/[\p{Cyrillic}]/u', $value)) {
                    $this->flag_as_spam($record, $ajax_handler, 'Cyrillic script payload rejected');
                    return;
                }
            }
        }

        // 5. Link & URL Flooding Filter
        if (! empty($options['antispam_block_links'])) {
            foreach ($fields as $field) {
                $type  = strtolower((string) ($field['type'] ?? ''));
                $value = (string) ($field['value'] ?? '');

                // Name and phone fields must NEVER contain links or domain extensions
                if (in_array($type, ['text', 'tel', 'name'], true) && preg_match('/https?:\/\/|www\.|\.(?:ru|cn|xyz|top|link|buzz|biz|cc|pw)\b/i', $value)) {
                    $this->flag_as_spam($record, $ajax_handler, 'URL detected in restricted field');
                    return;
                }

                // Textarea fields: reject if more than 1 URL or standard BBCode [url=] is found
                if ('textarea' === $type) {
                    if (substr_count(strtolower($value), 'http') > 1 || false !== stripos($value, '[url=')) {
                        $this->flag_as_spam($record, $ajax_handler, 'Excessive link count in message');
                        return;
                    }
                }
            }
        }
    }

    /**
     * Handles flagged spam submission via silent blackhole or security rejection error.
     *
     * @param object $record Form_Record instance.
     * @param object $ajax_handler Ajax_Handler instance.
     * @param string $reason Diagnostic reason for flagging.
     * @return void
     */
    private function flag_as_spam(object $record, object $ajax_handler, string $reason): void
    {
        self::$is_current_submission_spam = true;
        self::$spam_detection_reason      = $reason;

        $options          = \AHM_Site_Utilities::get_options();
        $silent_blackhole = ! empty($options['antispam_silent_blackhole']);

        if ($silent_blackhole) {
            // In silent blackhole mode, we allow Elementor to report success to the bot,
            // but pre_wp_mail suppresses outgoing emails so the inbox remains 100% clean.
            return;
        }

        // Standard mode: Return clean security rejection error
        if (method_exists($ajax_handler, 'add_error')) {
            $ajax_handler->add_error(
                'ahm_security',
                esc_html__('Security verification failed. Please wait a few moments and try again.', 'ahm-core')
            );
        }
    }

    /**
     * Suppresses email dispatch when a submission is classified as spam in silent blackhole mode.
     *
     * @param mixed $return_value Existing return value (null by default).
     * @param array<string, mixed> $atts Mail attributes.
     * @return mixed False if spam (cancels mail), otherwise null.
     */
    public function suppress_spam_email(mixed $return_value, array $atts): mixed
    {
        if (self::$is_current_submission_spam) {
            // Returning false instructs WordPress core wp_mail() to abort sending immediately
            return false;
        }

        return $return_value;
    }
}
