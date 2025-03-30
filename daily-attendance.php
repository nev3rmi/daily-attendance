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
    private static $qr_secret;

    /**
     * DailyAttendance constructor.
     */
    public function __construct() {
        if (!class_exists('AssetManager')) {
            wp_die('AssetManager class not found. Please check if the plugin is installed correctly.');
        }
        $this->asset_manager = new AssetManager();
        $this->init_hooks();
        $this->init_admin_menu();
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

    private function init_admin_menu(): void {
        add_action('admin_menu', function() {
            add_menu_page(
                'Daily Attendance',
                'Daily Attendance',
                'manage_options',
                'daily-attendance',
                [$this, 'render_report_page'],
                'dashicons-id-alt',
                30
            );
            add_submenu_page(
                'daily-attendance',
                'Report',
                'Report',
                'manage_options',
                'daily-attendance',
                [$this, 'render_report_page']
            );
            add_submenu_page(
                'daily-attendance',
                'View Members',
                'View Members',
                'manage_options',
                'view-members',
                [$this, 'render_view_members_page']
            );
        });
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

    public function render_qr_list_page(): void {
        $users = get_users(['fields' => ['ID', 'user_login', 'user_email']]);
        ?>
        <div class="wrap">
            <h1>User QR Codes</h1>
            <div class="pbda-qr-grid">
                <?php foreach ($users as $user): ?>
                    <div class="pbda-qr-item">
                        <h3><?php echo esc_html($user->user_login); ?></h3>
                        <div class="pbda-qr-code">
                            <img src="https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=<?php 
                                echo urlencode($this->generate_qr_data($user->ID)); 
                            ?>" alt="QR Code">
                        </div>
                        <p><?php echo esc_html($user->user_email); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function render_report_page(): void {
        ?>
        <div class="wrap">
            <h1>Attendance Report</h1>
            <p>Report content goes here...</p>
        </div>
        <?php
    }

    public function render_view_members_page(): void {
        $users = get_users(['fields' => ['ID', 'user_login', 'user_email']]);
        ?>
        <div class="wrap">
            <h1>View Members</h1>
            <div class="pbda-qr-grid">
                <?php foreach ($users as $user): ?>
                    <div class="pbda-qr-item">
                        <h3><?php echo esc_html($user->user_login); ?></h3>
                        <div class="pbda-qr-code">
                            <img src="https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=<?php 
                                echo urlencode($this->generate_qr_data($user->ID)); 
                            ?>" alt="QR Code">
                        </div>
                        <p><?php echo esc_html($user->user_email); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private function generate_qr_data($user_id): string {
        return json_encode([
            'user_id' => $user_id,
            'timestamp' => time(),
            'hash' => hash_hmac('sha256', $user_id . time(), self::$qr_secret)
        ]);
    }

    public static function activate(): void {
        $secret = get_option('pbda_qr_secret');
        if (empty($secret)) {
            add_option('pbda_qr_secret', bin2hex(random_bytes(32)));
        }
    }
}

// Define plugin version if not already defined
if (!defined('PBDA_VERSION')) {
    define('PBDA_VERSION', '1.0.2');
}

// Register activation hook
register_activation_hook(__FILE__, ['DailyAttendance', 'activate']);

new DailyAttendance();