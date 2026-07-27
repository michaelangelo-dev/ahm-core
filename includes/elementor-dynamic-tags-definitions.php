<?php
/**
 * Dynamic Tag class definitions for Elementor.
 *
 * Included dynamically when elementor/dynamic_tags/register fires.
 *
 * @package AHM_Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('AHM_Elementor_Phone_Text_Tag') && class_exists('\Elementor\Core\DynamicTags\Tag')) {

    class AHM_Elementor_Phone_Text_Tag extends \Elementor\Core\DynamicTags\Tag
    {
        public function get_name(): string
        {
            return 'ahm-phone-text-tag';
        }

        public function get_title(): string
        {
            return __('AHM Phone Number', 'ahm-core');
        }

        public function get_group(): array
        {
            return ['ahm-contact-info'];
        }

        public function get_categories(): array
        {
            return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY];
        }

        public function render(): void
        {
            $options = \AHM_Contact_Info::get_options();
            $phone   = $options['phone'];

            if (! empty($phone)) {
                $uk_phone = \AHM_Contact_Info::format_uk_phone($phone);
                echo esc_html($uk_phone['display']);
            }
        }
    }

    class AHM_Elementor_Phone_Url_Tag extends \Elementor\Core\DynamicTags\Data_Tag
    {
        public function get_name(): string
        {
            return 'ahm-phone-url-tag';
        }

        public function get_title(): string
        {
            return __('AHM Phone Link (tel:)', 'ahm-core');
        }

        public function get_group(): array
        {
            return ['ahm-contact-info'];
        }

        public function get_categories(): array
        {
            return [\Elementor\Modules\DynamicTags\Module::URL_CATEGORY];
        }

        public function get_value(array $options = []): string
        {
            $saved = \AHM_Contact_Info::get_options();
            $phone = $saved['phone'];

            if (empty($phone)) {
                return '';
            }

            $uk_phone = \AHM_Contact_Info::format_uk_phone($phone);
            return $uk_phone['tel'];
        }
    }

    class AHM_Elementor_Email_Text_Tag extends \Elementor\Core\DynamicTags\Tag
    {
        public function get_name(): string
        {
            return 'ahm-email-text-tag';
        }

        public function get_title(): string
        {
            return __('AHM Email Address', 'ahm-core');
        }

        public function get_group(): array
        {
            return ['ahm-contact-info'];
        }

        public function get_categories(): array
        {
            return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY];
        }

        public function render(): void
        {
            $options = \AHM_Contact_Info::get_options();
            $email   = $options['email'];

            if (! empty($email)) {
                echo esc_html($email);
            }
        }
    }

    class AHM_Elementor_Email_Url_Tag extends \Elementor\Core\DynamicTags\Data_Tag
    {
        public function get_name(): string
        {
            return 'ahm-email-url-tag';
        }

        public function get_title(): string
        {
            return __('AHM Email Link (mailto:)', 'ahm-core');
        }

        public function get_group(): array
        {
            return ['ahm-contact-info'];
        }

        public function get_categories(): array
        {
            return [\Elementor\Modules\DynamicTags\Module::URL_CATEGORY];
        }

        public function get_value(array $options = []): string
        {
            $saved = \AHM_Contact_Info::get_options();
            $email = $saved['email'];

            if (empty($email)) {
                return '';
            }

            return 'mailto:' . $email;
        }
    }

    class AHM_Elementor_Address_Text_Tag extends \Elementor\Core\DynamicTags\Tag
    {
        public function get_name(): string
        {
            return 'ahm-address-text-tag';
        }

        public function get_title(): string
        {
            return __('AHM Physical Address', 'ahm-core');
        }

        public function get_group(): array
        {
            return ['ahm-contact-info'];
        }

        public function get_categories(): array
        {
            return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY];
        }

        public function render(): void
        {
            $options = \AHM_Contact_Info::get_options();
            $address = $options['address'];

            if (! empty($address)) {
                echo nl2br(esc_html($address));
            }
        }
    }

    class AHM_Elementor_Maps_Url_Tag extends \Elementor\Core\DynamicTags\Data_Tag
    {
        public function get_name(): string
        {
            return 'ahm-maps-url-tag';
        }

        public function get_title(): string
        {
            return __('AHM Google Maps URL', 'ahm-core');
        }

        public function get_group(): array
        {
            return ['ahm-contact-info'];
        }

        public function get_categories(): array
        {
            return [
                \Elementor\Modules\DynamicTags\Module::URL_CATEGORY,
                \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
            ];
        }

        public function get_value(array $options = []): string
        {
            $saved    = \AHM_Contact_Info::get_options();
            $maps_url = $saved['maps_url'];

            return ! empty($maps_url) ? $maps_url : '';
        }
    }
}
