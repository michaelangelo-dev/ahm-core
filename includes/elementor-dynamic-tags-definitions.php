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

    class AHM_Elementor_Phone_Text_Tag extends \Elementor\Core\DynamicTags\Data_Tag
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
            return [
                \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
                \Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
            ];
        }

        public function get_value(array $options = []): string
        {
            $saved = \AHM_Contact_Info::get_options();
            $phone = $saved['phone'];

            if (empty($phone)) {
                return '';
            }

            $uk_phone = \AHM_Contact_Info::format_uk_phone($phone);
            return (string) $uk_phone['display'];
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

    class AHM_Elementor_Email_Text_Tag extends \Elementor\Core\DynamicTags\Data_Tag
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
            return [
                \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
                \Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
            ];
        }

        public function get_value(array $options = []): string
        {
            $saved = \AHM_Contact_Info::get_options();
            $email = $saved['email'];

            return ! empty($email) ? (string) $email : '';
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

    class AHM_Elementor_Address_Line1_Tag extends \Elementor\Core\DynamicTags\Data_Tag
    {
        public function get_name(): string
        {
            return 'ahm-address-line1-tag';
        }

        public function get_title(): string
        {
            return __('AHM Location / Hospital Name', 'ahm-core');
        }

        public function get_group(): array
        {
            return ['ahm-contact-info'];
        }

        public function get_categories(): array
        {
            return [
                \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
                \Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
            ];
        }

        public function get_value(array $options = []): string
        {
            $saved = \AHM_Contact_Info::get_options();
            return (string) ($saved['address_line1'] ?? '');
        }
    }

    class AHM_Elementor_Address_Line2_Tag extends \Elementor\Core\DynamicTags\Data_Tag
    {
        public function get_name(): string
        {
            return 'ahm-address-line2-tag';
        }

        public function get_title(): string
        {
            return __('AHM Town & Postcode', 'ahm-core');
        }

        public function get_group(): array
        {
            return ['ahm-contact-info'];
        }

        public function get_categories(): array
        {
            return [
                \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
                \Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
            ];
        }

        public function get_value(array $options = []): string
        {
            $saved = \AHM_Contact_Info::get_options();
            return (string) ($saved['address_line2'] ?? '');
        }
    }

    class AHM_Elementor_Address_Text_Tag extends \Elementor\Core\DynamicTags\Data_Tag
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
            return [
                \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
                \Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
            ];
        }

        public function get_value(array $options = []): string
        {
            $saved   = \AHM_Contact_Info::get_options();
            $address = $saved['address'] ?? '';

            if (empty($address)) {
                return '';
            }

            // Convert newlines to clean comma-separated single line string for inputs & maps
            return (string) preg_replace('/\s*[\r\n]+\s*/', ', ', trim((string) $address));
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
                \Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
            ];
        }

        public function get_value(array $options = []): string
        {
            $saved    = \AHM_Contact_Info::get_options();
            $maps_url = $saved['maps_url'];

            return ! empty($maps_url) ? $maps_url : '';
        }
    }

    /**
     * Dynamic Tag for calculating and outputting estimated post reading time in minutes.
     * Output is raw integer string so label (e.g. "min read") can be added in Advanced "After" options.
     */
    class AHM_Elementor_Reading_Time_Tag extends \Elementor\Core\DynamicTags\Tag
    {
        public function get_name(): string
        {
            return 'ahm-reading-time-tag';
        }

        public function get_title(): string
        {
            return __('AHM Reading Time', 'ahm-core');
        }

        public function get_group(): array
        {
            return ['ahm-contact-info'];
        }

        public function get_categories(): array
        {
            return [
                \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
                \Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
            ];
        }

        public function render(): void
        {
            global $post;

            if (! $post instanceof \WP_Post) {
                return;
            }

            $content    = get_post_field('post_content', $post->ID);
            $word_count = str_word_count(strip_tags((string) $content));
            $wpm        = 200;
            $minutes    = (int) ceil($word_count / $wpm);

            echo (string) max(1, $minutes);
        }
    }

    /**
     * Dynamic Tag for outputting post title or ACF alternate title without HTML bold wrappers.
     */
    class AHM_Elementor_Custom_Title_Tag extends \Elementor\Core\DynamicTags\Data_Tag
    {
        public function get_name(): string
        {
            return 'ahm-custom-title-tag';
        }

        public function get_title(): string
        {
            return __('AHM Custom Post Title', 'ahm-core');
        }

        public function get_group(): array
        {
            return ['ahm-contact-info'];
        }

        public function get_categories(): array
        {
            return [
                \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
                \Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
            ];
        }

        public function get_value(array $options = []): string
        {
            $title = get_the_title();

            if (function_exists('get_field')) {
                $acf_field = get_field('treatment_page_-_alternate_title');
                $title     = ! empty($acf_field) ? (string) $acf_field : $title;
            }

            return (string) $title;
        }
    }

    /**
     * Dynamic Tag for generating social share URLs (Facebook, Twitter/X, LinkedIn).
     */
    class AHM_Elementor_Social_Share_Url_Tag extends \Elementor\Core\DynamicTags\Data_Tag
    {
        public function get_name(): string
        {
            return 'ahm-social-share-url-tag';
        }

        public function get_title(): string
        {
            return __('AHM Social Share URL', 'ahm-core');
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
                \Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
            ];
        }

        protected function register_controls(): void
        {
            $this->add_control(
                'network',
                [
                    'label'   => __('Social Network', 'ahm-core'),
                    'type'    => \Elementor\Controls_Manager::SELECT,
                    'default' => 'facebook',
                    'options' => [
                        'facebook' => __('Facebook', 'ahm-core'),
                        'twitter'  => __('Twitter / X', 'ahm-core'),
                        'linkedin' => __('LinkedIn', 'ahm-core'),
                    ],
                ]
            );
        }

        public function get_value(array $options = []): string
        {
            $network = $this->get_settings('network') ?: 'facebook';
            $url     = urlencode(get_permalink());
            $title   = urlencode(get_the_title());

            return match (strtolower((string) $network)) {
                'twitter', 'x' => "https://twitter.com/intent/tweet?url={$url}&text={$title}",
                'linkedin'     => "https://www.linkedin.com/sharing/share-offsite/?url={$url}",
                default        => "https://www.facebook.com/sharer/sharer.php?u={$url}",
            };
        }
    }

    /**
     * Dynamic Tag for selecting practice location index (Location 1-6) and field (Name, Address, Full, Maps URL).
     */
    class AHM_Elementor_Multi_Location_Tag extends \Elementor\Core\DynamicTags\Data_Tag
    {
        public function get_name(): string
        {
            return 'ahm-multi-location-tag';
        }

        public function get_title(): string
        {
            return __('AHM Location Details', 'ahm-core');
        }

        public function get_group(): array
        {
            return ['ahm-contact-info'];
        }

        public function get_categories(): array
        {
            return [
                \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
                \Elementor\Modules\DynamicTags\Module::URL_CATEGORY,
                \Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
            ];
        }

        protected function register_controls(): void
        {
            $this->add_control(
                'location_index',
                [
                    'label'   => __('Select Location', 'ahm-core'),
                    'type'    => \Elementor\Controls_Manager::SELECT,
                    'default' => '0',
                    'options' => [
                        '0' => __('Location 1', 'ahm-core'),
                        '1' => __('Location 2', 'ahm-core'),
                        '2' => __('Location 3', 'ahm-core'),
                        '3' => __('Location 4', 'ahm-core'),
                        '4' => __('Location 5', 'ahm-core'),
                        '5' => __('Location 6', 'ahm-core'),
                    ],
                ]
            );

            $this->add_control(
                'field_type',
                [
                    'label'   => __('Output Field', 'ahm-core'),
                    'type'    => \Elementor\Controls_Manager::SELECT,
                    'default' => 'full',
                    'options' => [
                        'name'     => __('Facility / Hospital Name', 'ahm-core'),
                        'address'  => __('Town & Postcode', 'ahm-core'),
                        'full'     => __('Full Combined Address', 'ahm-core'),
                        'maps_url' => __('Google Maps URL', 'ahm-core'),
                    ],
                ]
            );
        }

        public function get_value(array $options = []): string
        {
            $idx   = (int) ($this->get_settings('location_index') ?: 0);
            $field = (string) ($this->get_settings('field_type') ?: 'full');

            $saved     = \AHM_Contact_Info::get_options();
            $locations = $saved['locations'] ?? [];

            if (empty($locations[$idx])) {
                return '';
            }

            $loc = $locations[$idx];

            return match ($field) {
                'name'     => (string) ($loc['name'] ?? ''),
                'address'  => (string) ($loc['address'] ?? ''),
                'maps_url' => (string) ($loc['maps_url'] ?? ''),
                default    => trim(($loc['name'] ?? '') . ', ' . ($loc['address'] ?? ''), ', '),
            };
        }
    }
}




