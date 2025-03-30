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

/**
 * Class DailyAttendance
 */
class DailyAttendance {

	/**
	 * DailyAttendance constructor.
	 */
	public function __construct() {

		$this->load_scripts();
		$this->define_classes_functions();
	}


	/**
	 * Loading classes and functions
	 */
	public function define_classes_functions(): void {

		require_once( PBDA_PLUGIN_DIR . 'includes/class-pb-settings.php' );

		require_once( PBDA_PLUGIN_DIR . 'includes/functions.php' );
		require_once( PBDA_PLUGIN_DIR . 'includes/class-functions.php' );
		require_once( PBDA_PLUGIN_DIR . 'includes/class-hooks.php' );
	}

	/**
	 * Loading scripts to backend
	 */
	public function admin_scripts(): void {

		wp_enqueue_style( 'tooltip', PBDA_PLUGIN_URL . 'assets/tool-tip.min.css' );
		wp_enqueue_style( 'icofont', PBDA_PLUGIN_URL . 'assets/fonts/icofont.min.css' );
		wp_enqueue_style( 'pbda_admin_style', PBDA_PLUGIN_URL . 'assets/admin/css/style.css' );
	}


	/**
	 * Loading scripts to the frontend
	 */
	public function front_scripts(): void {

		wp_enqueue_style( 'tooltip', PBDA_PLUGIN_URL . 'assets/tool-tip.min.css' );
		wp_enqueue_style( 'icofont', PBDA_PLUGIN_URL . 'assets/fonts/icofont.min.css' );
		wp_enqueue_style( 'pbda_style', PBDA_PLUGIN_URL . 'assets/front/css/style.css' );
	}


	/**
	 * Loading scripts
	 */
	public function load_scripts(): void {

		add_action( 'wp_enqueue_scripts', [ $this, 'front_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
	}
}

new DailyAttendance();