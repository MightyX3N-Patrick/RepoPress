<?php
/**
 * Plugin Name:       RepoPress
 * Plugin URI:        https://github.com/MightyX3N-Patrick/RepoPress
 * Description:       Browse and install plugins from GitHub repositories directly within WordPress.
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            MightyX3N-Patrick
 * Author URI:        https://github.com/MightyX3N-Patrick
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Network:           true
 * Text Domain:       repopress
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'REPOPRESS_VERSION', '1.0.0' );
define( 'REPOPRESS_DIR', plugin_dir_path( __FILE__ ) );
define( 'REPOPRESS_URL', plugin_dir_url( __FILE__ ) );
define( 'REPOPRESS_DEFAULT_REPO', 'https://github.com/MightyX3N-Patrick/RepoPress' );

require_once REPOPRESS_DIR . 'includes/class-repopress-github.php';
require_once REPOPRESS_DIR . 'includes/class-repopress-installer.php';
require_once REPOPRESS_DIR . 'includes/class-repopress-admin.php';

function repopress_init() {
    new RepoPress_Admin();
}
add_action( 'init', 'repopress_init' );
