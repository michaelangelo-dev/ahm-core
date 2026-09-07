<?php
/**
 * Safe SVG Upload & Media Preview Engine.
 *
 * Provides native, secure SVG uploads across WordPress media library, Custom Post Types,
 * and Advanced Custom Fields (ACF) with automatic XML sanitization and admin preview rendering.
 *
 * @package AHM_Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class AHM_SVG_Support
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
        // 1. Whitelist SVG MIME types
        add_filter('upload_mimes', [$this, 'allow_svg_mime_types']);

        // 2. Fix WordPress 4.7.1+ MIME / filetype validation false negatives
        add_filter('wp_check_filetype_and_ext', [$this, 'fix_svg_filetype_check'], 10, 4);

        // 3. Sanitize uploaded SVG files on upload
        add_filter('wp_handle_upload_prefilter', [$this, 'sanitize_svg_upload']);

        // 4. Populate dimensions and synthetic thumbnail sizes for JS / Media Library / ACF
        add_filter('wp_prepare_attachment_for_js', [$this, 'prepare_svg_attachment_for_js'], 10, 3);
        add_filter('wp_get_attachment_metadata', [$this, 'fix_svg_attachment_metadata'], 10, 2);

        // 5. Admin CSS for proper SVG thumbnail rendering
        add_action('admin_head', [$this, 'render_admin_preview_css']);
    }

    /*--------------------------------------------------------------
     * MIME Types & Upload Validation
     *------------------------------------------------------------*/

    /**
     * Whitelist SVG MIME types for users with upload permissions.
     *
     * @param array<string, string> $mimes Existing MIME types.
     * @return array<string, string> Modified MIME types.
     */
    public function allow_svg_mime_types(array $mimes): array
    {
        if (current_user_can('upload_files')) {
            $mimes['svg']  = 'image/svg+xml';
            $mimes['svgz'] = 'image/svg+xml';
        }

        return $mimes;
    }

    /**
     * Resolve WordPress core false-negative filetype checks for SVG.
     *
     * @param array<string, mixed> $data File data with 'ext', 'type', 'proper_filename'.
     * @param string $file Full path to uploaded temporary file.
     * @param string $filename Original filename.
     * @param array<string, string>|null $mimes Allowed MIME types.
     * @return array<string, mixed>
     */
    public function fix_svg_filetype_check(array $data, string $file, string $filename, ?array $mimes = null): array
    {
        if (! empty($data['ext']) && ! empty($data['type'])) {
            return $data;
        }

        $filetype = wp_check_filetype($filename, $mimes);
        $ext      = strtolower((string) ($filetype['ext'] ?? ''));

        if ('svg' === $ext || 'svgz' === $ext) {
            $data['ext']  = $ext;
            $data['type'] = 'image/svg+xml';
        }

        return $data;
    }

    /*--------------------------------------------------------------
     * XML Sanitization Engine
     *------------------------------------------------------------*/

    /**
     * Sanitize SVG XML content prior to moving file to uploads directory.
     *
     * @param array<string, mixed> $file Uploaded file array containing 'tmp_name', 'name', 'type', 'error'.
     * @return array<string, mixed>
     */
    public function sanitize_svg_upload(array $file): array
    {
        if (! empty($file['error'])) {
            return $file;
        }

        $filename = (string) ($file['name'] ?? '');
        $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ('svg' !== $ext && 'svgz' !== $ext) {
            return $file;
        }

        $tmp_path = (string) ($file['tmp_name'] ?? '');
        if (empty($tmp_path) || ! file_exists($tmp_path)) {
            return $file;
        }

        $content = (string) file_get_contents($tmp_path);
        if (empty($content)) {
            $file['error'] = esc_html__('Uploaded SVG file is empty.', 'ahm-core');
            return $file;
        }

        $is_gzipped = ('svgz' === $ext || 0 === strpos($content, "\x1f\x8b\x08"));

        if ($is_gzipped) {
            $uncompressed = @gzdecode($content);
            if (false === $uncompressed) {
                $file['error'] = esc_html__('Invalid compressed SVG (svgz) file.', 'ahm-core');
                return $file;
            }
            $content = $uncompressed;
        }

        // Check for XML External Entity (XXE) attacks
        if (preg_match('/<!ENTITY/i', $content) || preg_match('/SYSTEM\s+["\']/i', $content)) {
            $file['error'] = esc_html__('Security check failed: External entities (XXE) are strictly prohibited in SVG uploads.', 'ahm-core');
            return $file;
        }

        // Verify root <svg tag exists
        if (false === stripos($content, '<svg')) {
            $file['error'] = esc_html__('Invalid SVG: Missing root <svg> element.', 'ahm-core');
            return $file;
        }

        // Strip dangerous tags: script, foreignObject, iframe, embed, object, applet
        $dangerous_tags = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'applet', 'meta', 'link'];
        foreach ($dangerous_tags as $tag) {
            $content = preg_replace('/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/is', '', (string) $content);
            $content = preg_replace('/<' . $tag . '\b[^>]*\/?>/is', '', (string) $content);
        }

        // Strip inline JavaScript event handlers (e.g. onload, onerror, onclick, etc.)
        $content = preg_replace('/\s*on[a-z]+\s*=\s*(["\'][^"\']*["\']|[^\s>]+)/i', '', (string) $content);

        // Strip dangerous protocols in href, xlink:href, or src (javascript:, vbscript:, data:text/html)
        $content = preg_replace('/(href|xlink:href|src)\s*=\s*(["\']\s*(?:javascript|vbscript|data:text\/html):[^"\']*["\'])/i', '', (string) $content);

        // If Elementor SVG handler is available, run secondary sanitization pass
        if (class_exists('\Elementor\Plugin')) {
            $assets_manager = \Elementor\Plugin::$instance->assets_manager ?? null;
            if ($assets_manager && method_exists($assets_manager, 'get_asset')) {
                $svg_handler = $assets_manager->get_asset('svg-handler');
                if ($svg_handler && method_exists($svg_handler, 'sanitize_svg')) {
                    // Elementor sanitizer accepts file path
                    file_put_contents($tmp_path, $content);
                    $elementor_result = $svg_handler->sanitize_svg($tmp_path);
                    if (false === $elementor_result) {
                        $file['error'] = esc_html__('SVG sanitization failed: File rejected by security engine.', 'ahm-core');
                        return $file;
                    }
                    $content = (string) file_get_contents($tmp_path);
                }
            }
        }

        // Re-encode gzipped if svgz
        if ($is_gzipped) {
            $gzipped = @gzencode((string) $content, 9);
            if (false !== $gzipped) {
                $content = $gzipped;
            }
        }

        // Save sanitized content back to temporary file
        file_put_contents($tmp_path, $content);

        return $file;
    }

    /*--------------------------------------------------------------
     * Dimension & Metadata Extraction
     *------------------------------------------------------------*/

    /**
     * Extract width and height dimensions from SVG content.
     *
     * @param string $file_path Path to SVG file on disk.
     * @return array{width: int, height: int}
     */
    public function get_svg_dimensions(string $file_path): array
    {
        $default = ['width' => 100, 'height' => 100];

        if (! file_exists($file_path)) {
            return $default;
        }

        $content = @file_get_contents($file_path);
        if (empty($content)) {
            return $default;
        }

        // Unpack if svgz
        if (0 === strpos($content, "\x1f\x8b\x08")) {
            $decompressed = @gzdecode($content);
            if (false !== $decompressed) {
                $content = $decompressed;
            }
        }

        // Find root <svg> opening tag
        if (preg_match('/<svg\b([^>]*)>/is', (string) $content, $matches)) {
            $attributes = $matches[1];

            $width  = 0;
            $height = 0;

            if (preg_match('/\bwidth\s*=\s*["\']([0-9.]+)(?:px)?["\']/i', $attributes, $w_match)) {
                $width = (int) round((float) $w_match[1]);
            }

            if (preg_match('/\bheight\s*=\s*["\']([0-9.]+)(?:px)?["\']/i', $attributes, $h_match)) {
                $height = (int) round((float) $h_match[1]);
            }

            // Fallback to viewBox if width or height missing or percentage
            if (($width <= 0 || $height <= 0) && preg_match('/\bviewBox\s*=\s*["\']\s*([0-9.-]+)[\s,]+([0-9.-]+)[\s,]+([0-9.-]+)[\s,]+([0-9.-]+)\s*["\']/i', $attributes, $vb_match)) {
                $width  = (int) round(abs((float) $vb_match[3]));
                $height = (int) round(abs((float) $vb_match[4]));
            }

            if ($width > 0 && $height > 0) {
                return ['width' => $width, 'height' => $height];
            }
        }

        return $default;
    }

    /**
     * Populate width, height, and synthetic sizes in attachment JS payload.
     * Fixes 0x0 display in WordPress Media Library grid and ACF image fields.
     *
     * @param array<string, mixed> $response Prepared attachment data.
     * @param WP_Post $attachment Attachment post object.
     * @param mixed $meta Attachment meta data.
     * @return array<string, mixed>
     */
    public function prepare_svg_attachment_for_js(array $response, WP_Post $attachment, mixed $meta): array
    {
        if ('image/svg+xml' !== ($response['mime'] ?? '')) {
            return $response;
        }

        $svg_path = get_attached_file($attachment->ID);
        $dimensions = ($svg_path && file_exists($svg_path))
            ? $this->get_svg_dimensions($svg_path)
            : ['width' => 100, 'height' => 100];

        $response['width']  = $dimensions['width'];
        $response['height'] = $dimensions['height'];

        $url = $response['url'] ?? wp_get_attachment_url($attachment->ID);

        // Populate synthetic sizes so ACF and Media Modal can render previews seamlessly
        $sizes = [
            'full'      => [
                'url'         => $url,
                'width'       => $response['width'],
                'height'      => $response['height'],
                'orientation' => $response['width'] >= $response['height'] ? 'landscape' : 'portrait',
            ],
            'thumbnail' => [
                'url'         => $url,
                'width'       => min(150, (int) $response['width']),
                'height'      => min(150, (int) $response['height']),
                'orientation' => $response['width'] >= $response['height'] ? 'landscape' : 'portrait',
            ],
            'medium'    => [
                'url'         => $url,
                'width'       => min(300, (int) $response['width']),
                'height'      => min(300, (int) $response['height']),
                'orientation' => $response['width'] >= $response['height'] ? 'landscape' : 'portrait',
            ],
        ];

        $response['sizes'] = $sizes;

        return $response;
    }

    /**
     * Provide synthetic width and height metadata for SVG attachments.
     *
     * @param mixed $data Existing metadata.
     * @param int $post_id Attachment ID.
     * @return mixed
     */
    public function fix_svg_attachment_metadata(mixed $data, int $post_id): mixed
    {
        if (! is_array($data)) {
            $data = [];
        }

        $file = get_attached_file($post_id);
        if (! $file || ! preg_match('/\.svgz?$/i', $file)) {
            return $data;
        }

        if (empty($data['width']) || empty($data['height'])) {
            $dimensions = $this->get_svg_dimensions($file);
            $data['width']  = $dimensions['width'];
            $data['height'] = $dimensions['height'];
            $data['file']   = _wp_relative_upload_path($file);
        }

        return $data;
    }

    /*--------------------------------------------------------------
     * Admin UI Thumbnail Styling
     *------------------------------------------------------------*/

    /**
     * Injects scoped CSS ensuring SVGs display properly sized in WordPress admin & ACF.
     */
    public function render_admin_preview_css(): void
    {
        ?>
        <style id="ahm-svg-admin-preview-css">
            .attachment-preview .thumbnail img[src$=".svg"],
            .attachment-preview .thumbnail img[src$=".svgz"],
            .media-icon img[src$=".svg"],
            .media-icon img[src$=".svgz"],
            .attachment-info .thumbnail img[src$=".svg"],
            .attachment-info .thumbnail img[src$=".svgz"],
            .acf-image-uploader img[src$=".svg"],
            .acf-image-uploader img[src$=".svgz"],
            .acf-file-uploader img[src$=".svg"],
            .acf-file-uploader img[src$=".svgz"] {
                width: 100% !important;
                height: auto !important;
                max-height: 120px !important;
                object-fit: contain !important;
            }
        </style>
        <?php
    }
}
