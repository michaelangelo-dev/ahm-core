<?php
/**
 * Elementor Dynamic Tags provider for AHM Contact Info.
 *
 * Registers native Elementor Dynamic Tags for Phone, Email, Address, and Google Maps URL
 * supporting both Text and URL/Link controls across Elementor.
 *
 * @package AHM_Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class AHM_Elementor_Dynamic_Tags
{
    private static ?self $instance = null;

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('elementor/dynamic_tags/register', [$this, 'register_dynamic_tags']);
    }

    /**
     * Register AHM Contact Info dynamic tag category and tags.
     *
     * @param mixed $dynamic_tags_manager
     */
    public function register_dynamic_tags(mixed $dynamic_tags_manager): void
    {
        if (! class_exists('\Elementor\Core\DynamicTags\Tag') || ! class_exists('\Elementor\Core\DynamicTags\Data_Tag')) {
            return;
        }

        // Include class definitions when Elementor core classes exist
        require_once AHM_CORE_DIR . 'includes/elementor-dynamic-tags-definitions.php';

        // Register Dynamic Tag Group
        $dynamic_tags_manager->register_group('ahm-contact-info', [
            'title' => __('AHM Contact Info', 'ahm-core'),
        ]);

        // Register tag instances
        if (class_exists('AHM_Elementor_Phone_Text_Tag')) {
            $dynamic_tags_manager->register(new \AHM_Elementor_Phone_Text_Tag());
            $dynamic_tags_manager->register(new \AHM_Elementor_Phone_Url_Tag());
            $dynamic_tags_manager->register(new \AHM_Elementor_Email_Text_Tag());
            $dynamic_tags_manager->register(new \AHM_Elementor_Email_Url_Tag());
            $dynamic_tags_manager->register(new \AHM_Elementor_Address_Line1_Tag());
            $dynamic_tags_manager->register(new \AHM_Elementor_Address_Line2_Tag());
            $dynamic_tags_manager->register(new \AHM_Elementor_Address_Text_Tag());
            $dynamic_tags_manager->register(new \AHM_Elementor_Maps_Url_Tag());
        }

        if (class_exists('AHM_Elementor_Reading_Time_Tag')) {
            $dynamic_tags_manager->register(new \AHM_Elementor_Reading_Time_Tag());
        }

        if (class_exists('AHM_Elementor_Custom_Title_Tag')) {
            $dynamic_tags_manager->register(new \AHM_Elementor_Custom_Title_Tag());
        }

        if (class_exists('AHM_Elementor_Social_Share_Url_Tag')) {
            $dynamic_tags_manager->register(new \AHM_Elementor_Social_Share_Url_Tag());
        }

        if (class_exists('AHM_Elementor_Multi_Location_Tag')) {
            $dynamic_tags_manager->register(new \AHM_Elementor_Multi_Location_Tag());
        }
    }
}
