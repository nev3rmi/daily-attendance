<?php
class EmailManager {
    public static function is_wp_mail_smtp_active(): bool {
        return class_exists('WPMailSMTP\WP');
    }

    public static function get_smtp_status(): array {
        $status = [
            'active' => false,
            'message' => ''
        ];

        if (!self::is_wp_mail_smtp_active()) {
            $status['message'] = 'WP Mail SMTP plugin is not installed or activated';
            return $status;
        }

        // Check if mailer is configured
        $mail_settings = get_option('wp_mail_smtp', []);
        if (empty($mail_settings['mail']['mailer'])) {
            $status['message'] = 'WP Mail SMTP mailer is not configured';
            return $status;
        }

        $status['active'] = true;
        $status['message'] = 'SMTP is configured and active';
        return $status;
    }

    private static function parse_template($template, $user, $attendance_data, $report_title) {
        $total_days = count($attendance_data);
        
        // Generate attendance table
        ob_start();
        ?>
        <table style="border-collapse: collapse; width: 100%;">
            <tr style="background: #f8f9fa;">
                <th style="padding: 8px; border: 1px solid #ddd;">Date</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Day</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Time</th>
            </tr>
            <?php 
            if (empty($attendance_data)) {
                echo '<tr><td colspan="3" style="text-align: center; padding: 15px;">No attendance records found</td></tr>';
            } else {
                foreach ($attendance_data as $date => $data) {
                    ?>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><?php echo date('F j, Y', strtotime($date)); ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd;"><?php echo date('l', strtotime($date)); ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd;"><?php echo $data['time']; ?></td>
                    </tr>
                    <?php
                }
            }
            ?>
        </table>
        <?php
        $attendance_table = ob_get_clean();

        $replacements = array(
            '[title]' => $report_title,
            '[username]' => $user->display_name,
            '[email]' => $user->user_email,
            '[attendance_table]' => $attendance_table,
            '[total_days]' => $total_days,
            '[date]' => date_i18n(get_option('date_format'))
        );

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    public static function send_attendance_report(int $user_id, array $attendance_data, $report_id = null): array {
        $smtp_status = self::get_smtp_status();
        
        $debug_info = [
            'start_time' => current_time('Y-m-d H:i:s'),
            'user_id' => $user_id,
            'user_email' => '',
            'attendance_data' => $attendance_data,
            'report_id' => $report_id,
            'report_title' => '',
            'smtp_active' => $smtp_status['active'],
            'smtp_message' => $smtp_status['message'],
            'status' => 'started',
            'message' => ''
        ];

        error_log("SMTP Status: " . print_r($smtp_status, true));

        if (!$smtp_status['active']) {
            $debug_info['status'] = 'error';
            $debug_info['message'] = $smtp_status['message'];
            return $debug_info;
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            error_log("User not found: $user_id");
            $debug_info['status'] = 'error';
            $debug_info['message'] = "User not found: $user_id";
            return $debug_info;
        }

        $debug_info['user_email'] = $user->user_email;

        // Get report title if report_id is provided
        $report_title = '';
        if ($report_id) {
            $report = get_post($report_id);
            $report_title = $report ? $report->post_title : '';
            error_log("Report title: $report_title");
            $debug_info['report_title'] = $report_title;
        }

        $subject = $report_title ? 
            sprintf(__('Your Attendance Report for %s', 'daily-attendance'), $report_title) :
            sprintf(__('Your Attendance Report for %s', 'daily-attendance'), date('F Y'));
        
        $template = get_option('pbda_email_template');
        if (empty($template)) {
            // Load default template from SettingsManager
            require_once PBDA_PLUGIN_DIR . 'includes/class-settings-manager.php';
            $settings = new SettingsManager();
            $template = $settings->get_default_template();
        }

        $message = self::parse_template($template, $user, $attendance_data, $report_title);

        error_log("Attempting to send email to: " . $user->user_email);

        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        $sent = wp_mail($user->user_email, $subject, $message);
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });

        error_log("Email send result: " . ($sent ? 'Success' : 'Failed'));

        if ($sent) {
            $debug_info['status'] = 'success';
            $debug_info['message'] = sprintf(
                'Email sent successfully to %s with %d attendance records',
                $user->user_email,
                count($attendance_data)
            );
        } else {
            $debug_info['status'] = 'error';
            $debug_info['message'] = 'Failed to send email: ' . error_get_last()['message'] ?? 'Unknown error';
        }

        $debug_info['end_time'] = current_time('Y-m-d H:i:s');
        error_log("Email send complete: " . print_r($debug_info, true));

        return $debug_info;
    }
}
