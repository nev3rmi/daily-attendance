<?php
/**
 * Plugin Name: QR Code Attendance System
 * Plugin URI:  https://toho.vn/qr-attendance
 * Description: Modern attendance tracking system with QR code support and mobile-friendly interface
 * Version:     1.0.2
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Tested up to: 6.7.2
 * Author:      NeV3RmI
 * Author URI:  https://toho.vn/
 * Text Domain: daily-attendance
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access


define( 'PBDA_PLUGIN_URL', WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/' );
define( 'PBDA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PBDA_PLUGIN_FILE', plugin_basename( __FILE__ ) );

// Load dependencies first
require_once(PBDA_PLUGIN_DIR . 'includes/class-asset-manager.php');
require_once(PBDA_PLUGIN_DIR . 'includes/class-pb-settings.php');
require_once(PBDA_PLUGIN_DIR . 'includes/functions.php');
require_once(PBDA_PLUGIN_DIR . 'includes/class-functions.php');
require_once(PBDA_PLUGIN_DIR . 'includes/class-hooks.php');

/**
 * Class DailyAttendance
 */
class DailyAttendance {
    private $asset_manager;

    /**
     * DailyAttendance constructor.
     */
    public function __construct() {
        if (!class_exists('AssetManager')) {
            wp_die('AssetManager class not found. Please check if the plugin is installed correctly.');
        }
        $this->asset_manager = new AssetManager();
        $this->init_hooks();
    }

    /**
     * Initializing hooks
     */
    private function init_hooks(): void {
        add_action('wp_enqueue_scripts', [$this->asset_manager, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this->asset_manager, 'enqueue_admin_assets']);
    }
}

// Define plugin version if not already defined
if (!defined('PBDA_VERSION')) {
    define('PBDA_VERSION', '1.0.2');
}

new DailyAttendance();