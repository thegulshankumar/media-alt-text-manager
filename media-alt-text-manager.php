<?php
/**
 * Plugin Name: Media Alt Text Manager
 * Plugin URI: https://wordpress.org/plugins/media-alt-text-manager/
 * Description: Adds a sortable Alt Text column to the media library for quick alt text management.
 * Version: 1.0.1
 * Author: Gulshan Kumar
 * Author URI: https://www.gulshankumar.net
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: media-alt-text-manager
 * Domain Path: /languages
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or exit();

/**
 * Register activation hook to display notice for list mode.
 */
register_activation_hook( __FILE__, 'matm_activation_hook' );

/**
 * Set transient for activation notice.
 */
function matm_activation_hook() {
    set_transient( 'matm_list_mode_notice', true, 5 );
}

/**
 * Display admin notice for switching to list mode in Media Library.
 *
 * This function checks for a transient and, if present, displays a notice
 * encouraging users to switch to list mode in the Media Library for better
 * alt text management. The notice is dismissible and the transient is
 * deleted after displaying.
 *
 * @return void
 */
function matm_display_list_mode_notice() {
    if (!get_transient('matm_list_mode_notice')) {
        return;
    }

    $list_mode_url = esc_url(admin_url('upload.php?mode=list'));
    $message = sprintf(
        /* translators: %1$s: opening link tag, %2$s: closing link tag */
        esc_html__('To efficiently manage Alt Text for images, please visit the %1$sMedia Library in List Mode%2$s.', 'media-alt-text-manager'),
        '<a href="' . $list_mode_url . '">',
        '</a>'
    );

    ?>
    <div class="notice notice-info is-dismissible">
        <p><?php echo wp_kses($message, ['a' => ['href' => []]]); ?></p>
    </div>
    <?php

    delete_transient('matm_list_mode_notice');
}

add_action('admin_notices', 'matm_display_list_mode_notice');


/**
 * Add custom action links to the plugin.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'media_alt_text_manager_add_action_links' );

function media_alt_text_manager_add_action_links( $links ) {
    $plugin_shortcuts = array(
        sprintf(
            '<a rel="%s" title="%s" href="%s" target="_blank">%s</a>',
            esc_attr( 'nofollow noopener' ),
            esc_attr( __( 'Hire for Technical Support', 'media-alt-text-manager' ) ),
            esc_url( 'https://www.gulshankumar.net/contact/' ),
            esc_html__( 'Work with Gulshan', 'media-alt-text-manager' )
        ),
        sprintf(
            '<a rel="%s" title="%s" href="%s" target="_blank" style="%s">%s</a>',
            esc_attr( 'nofollow noopener' ),
            esc_attr( __( 'Show your support', 'media-alt-text-manager' ) ),
            esc_url( 'https://ko-fi.com/gulshan' ),
            esc_attr( 'color:#080;' ),
            esc_html__( 'Buy developer a coffee', 'media-alt-text-manager' )
        )
    );

    return array_merge( $links, $plugin_shortcuts );
}

/**
 * Add a custom column for alt text in the media library.
 */
function matm_add_edit_alt_text_column( $columns ) {
    // Check if the current user can upload files
    if ( current_user_can( 'upload_files' ) ) {
        $columns['alt_text_edit'] = esc_html__( 'Alt Text', 'media-alt-text-manager' );
    }
    
    return $columns;
}

// Hook to add the custom column to the media library
add_filter( 'manage_media_columns', 'matm_add_edit_alt_text_column' );

/**
 * Populate the custom column with alt text input field.
 */
function matm_display_edit_alt_text_column( $column_name, $post_id ) {
    if ( $column_name === 'alt_text_edit' && wp_attachment_is_image( $post_id ) ) {
        // Check if the user can edit their own image or is an admin/editor
        if ( current_user_can( 'edit_post', $post_id ) || ( current_user_can( 'upload_files' ) && get_post_field( 'post_author', $post_id ) === get_current_user_id() ) ) {
            $alt_text = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
            
            echo '<input 
                placeholder="Empty" 
                type="text" 
                class="alt-text-input" 
                data-post-id="' . esc_attr( $post_id ) . '" 
                data-nonce="' . esc_attr( wp_create_nonce( 'matm_save_alt_text_' . $post_id ) ) . '" 
                value="' . esc_attr( $alt_text ) . '" 
            />';
        }
    }
}

// Hook to display the custom column content
add_action( 'manage_media_custom_column', 'matm_display_edit_alt_text_column', 10, 2 );

/**
 * Make the custom column sortable.
 */
function matm_sortable_alt_text_status_column( $columns ) {
    $columns['alt_text_edit'] = 'alt_text_status';
    return $columns;
}

// Hook to make the custom column sortable
add_filter( 'manage_upload_sortable_columns', 'matm_sortable_alt_text_status_column' );

/**
 * Define sorting logic for the custom column.
 */
function matm_sort_media_by_alt_text_status( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() || ! current_user_can( 'upload_files' ) ) {
        return;
    }

    $orderby = $query->get( 'orderby' );

    if ( 'alt_text_status' === $orderby ) {
        $order = strtoupper( $query->get( 'order', 'ASC' ) );

        // Meta query to filter images without alt text
        $query->set( 'meta_query', array(
            'relation' => 'OR',
            array(
                'key'     => '_wp_attachment_image_alt',
                'compare' => 'NOT EXISTS', // Images without alt text
            ),
            array(
                'key'     => '_wp_attachment_image_alt',
                'value'   => '',
                'compare' => '=', // Images with empty alt text
            ),
        ) );

        // Set orderby to prioritize meta_query results and then by date
        if ( 'ASC' === $order ) {
            $query->set( 'orderby', array(
                'meta_value' => 'ASC', // Images without alt text first
                'post_date'  => 'ASC', // Older images first
            ) );
        } else {
            $query->set( 'orderby', array(
                'meta_value' => 'ASC', // Images without alt text first
                'post_date'  => 'DESC', // Newer images first
            ) );
        }
    }
}

// Hook to sort the media library by alt text status
add_action( 'pre_get_posts', 'matm_sort_media_by_alt_text_status' );

/**
 * Register REST API endpoint for saving alt text.
 */
add_action( 'rest_api_init', function() {
    register_rest_route( 'matm/v1', '/save-alt-text', array(
        'methods'  => 'POST',
        'callback' => 'matm_save_alt_text',
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' ); // Allow Admin and Editor
        },
    ) );
});

/**
 * Save alt text via REST API.
 */
function matm_save_alt_text( $request ) {
    $post_id  = intval( $request['post_id'] );
    $alt_text = sanitize_text_field( $request['alt_text'] );
    $nonce    = sanitize_key( $request['nonce'] );

    // Verify the nonce
    if ( ! wp_verify_nonce( $nonce, 'matm_save_alt_text_' . $post_id ) ) {
        return new WP_Error( 'invalid_nonce', 'Nonce verification failed', array( 'status' => 403 ) );
    }

    // Ensure the user can edit the post
    if ( ! current_user_can( 'edit_post', $post_id ) && !( current_user_can( 'upload_files' ) && get_post_field( 'post_author', $post_id ) === get_current_user_id() ) ) {
        return new WP_Error( 'forbidden', 'You do not have permission to edit this alt text.', array( 'status' => 403 ) );
    }

    // Update the alt text
    if ( update_post_meta( $post_id, '_wp_attachment_image_alt', $alt_text ) ) {
        return array( 'success' => true );
    } else {
        return new WP_Error( 'failed_update', 'Failed to update alt text', array( 'status' => 500 ) );
    }
}

/**
 * Get the plugin version from the plugin file header.
 */
$plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
$version = $plugin_data['Version'];

/**
 * Enqueue JavaScript for handling the alt text input.
 */
function matm_enqueue_scripts() {
    global $version; // Make the version variable accessible inside the function

    wp_enqueue_script( 
        'alt-text-editor', 
        plugin_dir_url( __FILE__ ) . 'js/alt-editor.js', 
        array( 'jquery' ), 
        $version, 
        true 
    );
    
    // Localize script to pass the nonce and API root URL
    wp_localize_script( 'alt-text-editor', 'wpApiSettings', array(
        'root'  => esc_url( rest_url() ),
        'nonce' => wp_create_nonce( 'wp_rest' ) // Use the correct nonce for REST API
    ) );
}

// Hook to enqueue scripts in admin
add_action( 'admin_enqueue_scripts', 'matm_enqueue_scripts' );