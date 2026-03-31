<?php
/**
 * Plugin Name: Multisite Discover
 * Plugin URI:  https://nebulawp.org
 * Description: Allows subsite admins to set their site public/private and manage branding (logo + banner). Network admins get a [discover] shortcode to showcase public subsites.
 * Version:     1.0.0
 * Author:      MightyX3N
 * Network:     true
 * Text Domain: multisite-discover
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MSD_VERSION',    '1.0.0' );
define( 'MSD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// Load sub-modules
// ---------------------------------------------------------------------------
require_once MSD_PLUGIN_DIR . 'includes/site-settings.php';   // Per-subsite settings page
require_once MSD_PLUGIN_DIR . 'includes/shortcode.php';       // [discover] shortcode
require_once MSD_PLUGIN_DIR . 'includes/ajax.php';            // AJAX helpers (image upload)
require_once MSD_PLUGIN_DIR . 'includes/integration-ultimate-multisite.php'; // Ultimate Multisite plan badge support

// ---------------------------------------------------------------------------
// Activation: create option defaults on every existing blog
// ---------------------------------------------------------------------------
register_activation_hook( __FILE__, 'msd_activate' );
function msd_activate() {
    if ( ! is_multisite() ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( __( 'Multisite Discover requires WordPress Multisite.', 'multisite-discover' ) );
    }
}

// ---------------------------------------------------------------------------
// Enqueue admin styles/scripts only on our settings page
// ---------------------------------------------------------------------------
add_action( 'admin_enqueue_scripts', 'msd_admin_enqueue' );
function msd_admin_enqueue( $hook ) {
    if ( $hook !== 'settings_page_multisite-discover' ) return;

    wp_enqueue_media();
    wp_enqueue_style(
        'msd-admin',
        MSD_PLUGIN_URL . 'assets/admin.css',
        [],
        MSD_VERSION
    );
    wp_enqueue_script(
        'msd-admin',
        MSD_PLUGIN_URL . 'assets/admin.js',
        [ 'jquery', 'media-upload', 'thickbox' ],
        MSD_VERSION,
        true
    );
    wp_localize_script( 'msd-admin', 'msdAdmin', [
        'logoTitle'   => __( 'Select or Upload Site Logo',   'multisite-discover' ),
        'bannerTitle' => __( 'Select or Upload Site Banner', 'multisite-discover' ),
        'useText'     => __( 'Use this image', 'multisite-discover' ),
    ] );
}
