<?php
/*
Plugin Name: Google Drive Video Importer
Description: Import video files from Google Drive into the WordPress Media Library.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

// Include required files
define('GDVI_PATH', plugin_dir_path(__FILE__));
define('GDVI_URL', plugin_dir_url(__FILE__));

require_once GDVI_PATH . 'includes/class-gdvi-google-client.php';
require_once GDVI_PATH . 'includes/class-gdvi-importer.php';

class GDVI_Main {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_gdvi_list_drive_items', [$this, 'ajax_list_drive_items']);
        add_action('wp_ajax_gdvi_import_video', [$this, 'ajax_import_video']);
        add_action('wp_ajax_gdvi_revoke_access', [$this, 'ajax_revoke_access']);
        add_action('wp_ajax_gdvi_add_category', [$this, 'ajax_add_category']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('admin_init', [$this, 'handle_oauth_callback']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('upload_mimes', [$this, 'add_video_mime_types']);
    }

    public function add_video_mime_types($mimes) {
        $mimes['mp4'] = 'video/mp4';
        return $mimes;
    }

    public function register_settings() {
        register_setting('gdvi_settings', 'gdvi_google_client_id');
        register_setting('gdvi_settings', 'gdvi_google_client_secret');
    }

    public function add_admin_menu() {
        add_menu_page(
            'Google Drive Video Importer',
            'Drive Video Import',
            'manage_options',
            'gdvi-importer',
            [$this, 'render_admin_page'],
            'dashicons-video-alt3'
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_gdvi-importer') return;
        
        wp_enqueue_script(
            'gdvi-admin-js',
            GDVI_URL . 'assets/admin.js',
            array('jquery'),
            filemtime(GDVI_PATH . 'assets/admin.js'),
            true
        );
        
        wp_localize_script('gdvi-admin-js', 'gdvi_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('gdvi_nonce')
        ]);
        
        wp_enqueue_style('gdvi-admin-css', GDVI_URL . 'assets/admin.css');
    }

    public function render_admin_page() {
        require GDVI_PATH . 'admin/admin-page.php';
    }

    public function handle_oauth_callback() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'gdvi-importer') return;
        
        if (isset($_GET['code'])) {
            $client = new GDVI_Google_Client();
            $client->handle_oauth_callback($_GET['code']);
            wp_redirect(admin_url('admin.php?page=gdvi-importer'));
            exit;
        }
    }

    public function ajax_list_drive_items() {
        check_ajax_referer('gdvi_nonce');
        
        $parent_id = isset($_POST['parent_id']) ? sanitize_text_field($_POST['parent_id']) : null;
        $client = new GDVI_Google_Client();
        $items = $client->list_drive_items($parent_id);
        
        wp_send_json_success($items);
    }

    public function ajax_import_video() {
        check_ajax_referer('gdvi_nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }
        
        $file_id = sanitize_text_field($_POST['file_id'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        
        if (!$file_id) {
            wp_send_json_error('Missing file ID');
        }
        
        $importer = new GDVI_Importer();
        $result = $importer->import_video($file_id, $category);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }

    public function ajax_revoke_access() {
        check_ajax_referer('gdvi_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $client = new GDVI_Google_Client();
        $client->revoke_access();
        
        wp_send_json_success('Access revoked.');
    }

    public function register_taxonomy() {
        register_taxonomy('gdvi_video_category', 'attachment', [
            'labels' => [
                'name' => 'Video Categories',
                'singular_name' => 'Video Category',
                'add_new_item' => 'Add New Video Category',
                'edit_item' => 'Edit Video Category',
                'update_item' => 'Update Video Category',
                'view_item' => 'View Video Category',
                'separate_items_with_commas' => 'Separate categories with commas',
                'add_or_remove_items' => 'Add or remove categories',
                'choose_from_most_used' => 'Choose from the most used categories',
                'popular_items' => 'Popular Categories',
                'search_items' => 'Search Categories',
                'not_found' => 'Not Found',
                'no_terms' => 'No categories',
                'items_list' => 'Categories list',
                'items_list_navigation' => 'Categories list navigation',
            ],
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
        ]);
    }

    public function ajax_add_category() {
        check_ajax_referer('gdvi_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $category_name = sanitize_text_field($_POST['category_name'] ?? '');
        
        if (empty($category_name)) {
            wp_send_json_error('Category name is required');
        }
        
        $term = wp_insert_term($category_name, 'gdvi_video_category');
        
        if (is_wp_error($term)) {
            wp_send_json_error($term->get_error_message());
        } else {
            $term_obj = get_term($term['term_id'], 'gdvi_video_category');
            wp_send_json_success([
                'name' => $term_obj->name,
                'slug' => $term_obj->slug,
                'id' => $term_obj->term_id
            ]);
        }
    }
}
add_action('wp_ajax_gdvi_list_imported_files_by_category', function() {
    check_ajax_referer('gdvi_nonce', '_ajax_nonce');
    $category = sanitize_text_field($_POST['category'] ?? '');
    if (!$category) {
        wp_send_json_error('No category specified');
    }
    // Query posts with this category
    $args = [
        'post_type' => 'gdvi_video', // or your custom post type
        'tax_query' => [
            [
                'taxonomy' => 'gdvi_video_category',
                'field'    => 'slug',
                'terms'    => $category,
            ],
        ],
        'posts_per_page' => -1,
    ];
    $query = new WP_Query($args);
    $files = [];
    foreach ($query->posts as $post) {
        $meta = get_post_meta($post->ID);
        $files[] = [
            'id' => $meta['gdvi_drive_id'][0] ?? '',
            'name' => $post->post_title,
            'mimeType' => $meta['gdvi_drive_mime'][0] ?? '',
            'size' => $meta['gdvi_drive_size'][0] ?? '',
            'thumbnailLink' => $meta['gdvi_drive_thumb'][0] ?? '',
            'preview_url' => $meta['gdvi_drive_preview'][0] ?? '',
            'download_url' => $meta['gdvi_drive_download'][0] ?? '',
            'is_folder' => false,
            'is_shortcut' => false,
        ];
    }
    wp_send_json_success($files);
});
new GDVI_Main();