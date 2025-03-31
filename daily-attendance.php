<?php
/**
 * Plugin Name: QR Code Attendance System
 * Plugin URI:  https://github.com/nev3rmi/daily-attendance
 * Description: Modern attendance tracking system with QR code support and mobile-friendly interface
 * Version:     1.0.5
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Tested up to: 6.7.2
 * Author:      NeV3RmI
 * Author URI:  https://github.com/nev3rmi/
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
require_once(PBDA_PLUGIN_DIR . 'includes/class-email-manager.php');
require_once(PBDA_PLUGIN_DIR . 'includes/class-settings-manager.php');

/**
 * Class DailyAttendance
 */
class DailyAttendance {
    private $asset_manager;
    private $settings_manager;
    private static $qr_secret;

    /**
     * DailyAttendance constructor.
     */
    public function __construct() {
        if (!class_exists('AssetManager')) {
            wp_die('AssetManager class not found. Please check if the plugin is installed correctly.');
        }
        $this->asset_manager = new AssetManager();
        $this->settings_manager = new SettingsManager();
        $this->init_hooks();
        self::$qr_secret = get_option('pbda_qr_secret');
        if (empty(self::$qr_secret)) {
            self::$qr_secret = bin2hex(random_bytes(32));
            update_option('pbda_qr_secret', self::$qr_secret);
        }
    }

    /**
     * Initializing hooks
     */
    private function init_hooks(): void {
        add_action('wp_enqueue_scripts', [$this->asset_manager, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this->asset_manager, 'enqueue_admin_assets']);
        add_action('show_user_profile', [$this, 'add_qr_code_field']);
        add_action('edit_user_profile', [$this, 'add_qr_code_field']);
    }

    public function add_qr_code_field($user): void {
        if (!current_user_can('manage_options') && $user->ID != get_current_user_id()) {
            return;
        }
        $qr_data = $this->generate_qr_data($user->ID);
        ?>
        <h3>Attendance QR Code</h3>
        <table class="form-table">
            <tr>
                <th>Your QR Code</th>
                <td>
                    <div class="pbda-qr-code">
                        <img src="https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=<?php echo urlencode($qr_data); ?>" 
                             alt="Attendance QR Code">
                    </div>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_report_page(): void {
        $num_days = date('t'); // Get number of days in current month
        $current_report = get_post(pbda_current_report_id());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="pbda-report-header">
                <div class="pbda-info">
                    <span class="label"><?php esc_html_e('Report Title', 'daily-attendance'); ?></span>
                    <span class="value"><?php echo esc_html($current_report->post_title); ?></span>
                </div>
                <div class="pbda-info">
                    <span class="label"><?php esc_html_e('Total Users', 'daily-attendance'); ?></span>
                    <span class="value"><?php echo count(get_users()); ?></span>
                </div>
            </div>
            <?php echo pbda_get_attendance_report($num_days, pbda_current_report_id()); ?>
        </div>
        <?php
    }

    public function render_view_members_page(): void {
        include PBDA_PLUGIN_DIR . 'templates/member-qr-codes.php';
    }

    private function generate_qr_data($user_id): string {
        // Generate permanent QR code for scanning
        $data = [
            'user_id' => $user_id,
            'hash' => hash_hmac('sha256', $user_id, self::$qr_secret)
        ];
        return json_encode($data);
    }

    public static function activate(): void {
        $secret = get_option('pbda_qr_secret');
        if (empty($secret)) {
            add_option('pbda_qr_secret', bin2hex(random_bytes(32)));
        }
        
        // Clean up any duplicate reports
        if (function_exists('pbda_cleanup_duplicate_reports')) {
            pbda_cleanup_duplicate_reports();
        }
    }
}

// Define plugin version if not already defined
if (!defined('PBDA_VERSION')) {
    define('PBDA_VERSION', '1.0.4');
}

// Register activation hook
register_activation_hook(__FILE__, ['DailyAttendance', 'activate']);

// Remove the PB_Settings instantiation and replace with just the DailyAttendance instance
$dailyAttendance = new DailyAttendance();

// Add admin menu handlers
add_action('admin_menu', function() use ($dailyAttendance) {
    // Add View Members submenu under da_reports
    add_submenu_page(
        'edit.php?post_type=da_reports', // Parent slug
        'View Members',                   // Page title
        'View Members',                   // Menu title
        'manage_options',                 // Capability
        'view-members',                   // Menu slug
        [$dailyAttendance, 'render_view_members_page'] // Callback
    );
});

// Add new filter for plugin row meta
add_filter('plugin_row_meta', function($links, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $links[2] = '<a href="https://github.com/nev3rmi/daily-attendance" target="_blank">View details</a>';
    }
    return $links;
}, 10, 2);

// Add filter to modify the plugin details URL
add_filter('self_admin_url', function($url) {
    if (strpos($url, 'plugin=daily-attendance') !== false) {
        $url = str_replace('plugin=daily-attendance', 'plugin=daily-qr-attendance', $url);
    }
    return $url;
});