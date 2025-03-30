<?php
class EmailManager {
    private static function is_wp_mail_smtp_active(): bool {
        return class_exists('WPMailSMTP');
    }

    public static function send_attendance_report(int $user_id, array $attendance_data, $report_id = null): bool {
        // Log start of email sending
        error_log("Starting to send attendance report for user $user_id");
        error_log("Attendance data: " . print_r($attendance_data, true));

        if (!self::is_wp_mail_smtp_active()) {
            error_log("WP Mail SMTP is not active");
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php _e('WP Mail SMTP is required for sending attendance reports. Please install and configure WP Mail SMTP plugin.', 'daily-attendance'); ?></p>
                    <p><a href="<?php echo admin_url('plugin-install.php?s=wp+mail+smtp&tab=search&type=term'); ?>" class="button button-primary"><?php _e('Install WP Mail SMTP', 'daily-attendance'); ?></a></p>
                </div>
                <?php
            });
            return false;
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            error_log("User not found: $user_id");
            return false;
        }

        // Get report title if report_id is provided
        $report_title = '';
        if ($report_id) {
            $report = get_post($report_id);
            $report_title = $report ? $report->post_title : '';
            error_log("Report title: $report_title");
        }

        $subject = $report_title ? 
            sprintf(__('Your Attendance Report for %s', 'daily-attendance'), $report_title) :
            sprintf(__('Your Attendance Report for %s', 'daily-attendance'), date('F Y'));
        
        ob_start();
        ?>
        <h2><?php printf(__('Hello %s,', 'daily-attendance'), $user->display_name); ?></h2>
        <p><?php _e('Here is your attendance report:', 'daily-attendance'); ?></p>
        <table style="border-collapse: collapse; width: 100%;">
            <tr style="background: #f8f9fa;">
                <th style="padding: 8px; border: 1px solid #ddd;"><?php _e('Date', 'daily-attendance'); ?></th>
                <th style="padding: 8px; border: 1px solid #ddd;"><?php _e('Time', 'daily-attendance'); ?></th>
            </tr>
            <?php foreach ($attendance_data as $date => $time): ?>
            <tr>
                <td style="padding: 8px; border: 1px solid #ddd;"><?php echo date('F j, Y', strtotime($date)); ?></td>
                <td style="padding: 8px; border: 1px solid #ddd;"><?php echo date('h:i A', $time); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php
        $message = ob_get_clean();

        error_log("Attempting to send email to: " . $user->user_email);

        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        $sent = wp_mail($user->user_email, $subject, $message);
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });

        error_log("Email send result: " . ($sent ? 'Success' : 'Failed'));

        return $sent;
    }
}
