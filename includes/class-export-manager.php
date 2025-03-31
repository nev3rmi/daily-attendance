<?php
class ExportManager {
    public static function generate_csv($report_id) {
        try {
            error_log("Starting CSV generation for report ID: $report_id");
            
            $report = get_post($report_id);
            if (!$report || $report->post_type !== 'da_reports') {
                throw new Exception('Invalid report ID');
            }
            
            $month = get_post_meta($report_id, '_month', true);
            if (empty($month)) {
                throw new Exception('Invalid report month');
            }
            
            $date = DateTime::createFromFormat('Ym', $month);
            if (!$date) {
                throw new Exception('Invalid date format');
            }

            // Set filename
            $filename = sprintf('attendance-report-%s.csv', $date->format('Y-m'));
            
            // Set headers for forced download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: public');
            header('Expires: 0');

            // Get all users
            $users = get_users(['fields' => ['ID', 'display_name', 'user_email']]);
            
            // Get number of days in month
            $num_days = cal_days_in_month(CAL_GREGORIAN, $date->format('m'), $date->format('Y'));
            
            // Create CSV output
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new Exception('Failed to create output stream');
            }
            
            // Write header row
            $header = ['Name', 'Email'];
            for ($i = 1; $i <= $num_days; $i++) {
                $header[] = $date->format('M') . ' ' . $i;
            }
            $header[] = 'Total Days';
            fputcsv($output, $header);
            
            // Write data rows
            foreach ($users as $user) {
                $row = [$user->display_name, $user->user_email];
                $attendances = pbda_get_user_attendance($user->ID, $report_id);
                $total_days = 0;
                
                // Fill attendance data
                for ($day = 1; $day <= $num_days; $day++) {
                    if (isset($attendances[$day])) {
                        $row[] = $attendances[$day]['time'];
                        $total_days++;
                    } else {
                        $row[] = '-';
                    }
                }
                
                $row[] = $total_days;
                fputcsv($output, $row);
            }
            
            fclose($output);

            // Make sure to flush output buffer and exit
            if (ob_get_level()) {
                ob_end_clean();
            }
            flush();
            exit();
            
        } catch (Exception $e) {
            error_log('CSV Export Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
