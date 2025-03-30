<?php
/**
 * All Functions
 *
 * @author Pluginbazar
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access


if ( ! function_exists( 'pbda_get_attendance_report' ) ) {
	/**
	 * Return attendance report html table
	 *
	 * @param int $num_days
	 * @param bool $report_id
	 *
	 * @return string
	 */
	function pbda_get_attendance_report( $num_days = 0, $report_id = false ) {

		ob_start();

		$report_id = $report_id ?: get_the_ID();

		// Rendering Top Header of days
		printf( '<tr>%s</tr>', pbda_get_days_header( $num_days ) );


		// Rendering Users List
		foreach ( pbda()->get_users_list() as $user ) {

			if ( ! $user instanceof WP_User ) {
				continue;
			}

			$attendances = pbda_get_user_attendance( $user->ID, $report_id );

			ob_start();

			printf('<tr data-user-id="%d">', $user->ID);

			for ( $day = 0; $day <= $num_days; $day ++ ) {

				if ( $day === 0 ) {
					printf( '<td class="name">%s</td>', $user->display_name );
					continue;
				}

				if ( array_key_exists( $day, $attendances ) ) {
					printf( '<td class="yes tt--top tt--info" aria-label="%s"><i class="icofont-check-alt"></i></td>', pbda_format_time( $attendances[ $day ] ) );
				} else {
					printf( '<td class="no"></td>' );
				}
			}

			printf( '<tr>%s</tr>', ob_get_clean() );
		}

		// Rendering Table
		return sprintf( '<table class="pbda-reports">%s</table>', ob_get_clean() );
	}
}


if ( ! function_exists( 'pbda_get_user_attendance' ) ) {
	/**
	 * Return user attendance
	 *
	 * @param bool $user_id
	 * @param bool $report_id
	 *
	 * @return array|WP_Error
	 */
	function pbda_get_user_attendance( $user_id = false, $report_id = false ) {
		error_log("Getting attendance for user $user_id from report $report_id");

		$attendances = array();
		$user_id = $user_id ?: get_current_user_id();
		$report_id = $report_id ?: pbda_current_report_id();

		if ($user_id === 0 || $user_id === false) {
			error_log("Invalid user ID");
			return new WP_Error('invalid_user', esc_html__('Invalid user information', 'daily-attendance'));
		}

		if ($report_id === 0 || $report_id === false) {
			error_log("Invalid report ID");
			return new WP_Error('invalid_report', esc_html__('Something went wrong with Report ID', 'daily-attendance'));
		}

		$_month = get_post_meta($report_id, '_month', true);
		error_log("Getting attendance for month: $_month");

		$attendance_meta = get_post_meta($report_id, 'pbda_attendance', false);
		error_log("Raw attendance data: " . print_r($attendance_meta, true));

		foreach ($attendance_meta as $item) {
			$this_user_id = isset($item['user_id']) ? $item['user_id'] : '';
			$this_date = isset($item['date']) ? $item['date'] : '';
			$this_time = isset($item['current_time']) ? (int)$item['current_time'] : 0;

			if (empty($this_user_id) || $this_user_id === 0 || empty($this_date) || $this_user_id != $user_id) {
				continue;
			}

			$this_date = explode('-', $this_date);
			$this_year = isset($this_date[0]) ? $this_date[0] : '';
			$this_month = isset($this_date[1]) ? $this_date[1] : '';
			$this_day = isset($this_date[2]) ? (int)$this_date[2] : '';

			if (empty($this_day) || $this_day === 0 || $_month != sprintf('%s%s', $this_year, $this_month)) {
				continue;
			}

			$attendances[$this_day] = [
				'timestamp' => $this_time,
				'date' => sprintf('%s-%s-%02d', $this_year, $this_month, $this_day),
				'time' => pbda_format_time($this_time)
			];
		}

		error_log("Processed attendance data: " . print_r($attendances, true));
		return $attendances;
	}
}


if ( ! function_exists( 'pbda_insert_attendance' ) ) {
	/**
	 * Insert new attendance
	 *
	 * @param bool $user_id
	 *
	 * @return false|int|WP_Error
	 */
	function pbda_insert_attendance( $user_id = false ) {

		$user_id = ! $user_id || empty( $user_id ) || $user_id === 0 ? get_current_user_id() : $user_id;

		if ( ! $user_id || empty( $user_id ) || $user_id === 0 ) {
			return new WP_Error( 'invalid_user', esc_html__( 'Invalid user information', 'daily-attendance' ) );
		}

		$user      = get_user_by( 'ID', $user_id );
		$report_id = pbda_current_report_id();

		if ( ! $report_id || empty( $report_id ) || $report_id === 0 ) {
			return new WP_Error( 'invalid_report', esc_html__( 'Something went wrong with Report ID', 'daily-attendance' ) );
		}

		if ( pbda_is_duplicate_attendance( $user_id, $report_id ) ) {
			return new WP_Error( 'duplicate', sprintf( esc_html__( 'Hello %s, There is an entry for you today!', 'daily-attendance' ), $user->display_name ) );
		}

		// Get WordPress timezone setting
		$wp_timezone = wp_timezone();
		$current_time = new DateTime('now', $wp_timezone);

		$args = array(
			'user_id'      => $user_id,
			'report_id'    => $report_id,
			'date'         => $current_time->format('Y-m-d'),
			'current_time' => $current_time->getTimestamp(),
			'timezone'     => $wp_timezone->getName() // Store timezone info
		);

		error_log("Inserting attendance: " . print_r([
			'user_id' => $user_id,
			'date' => $current_time->format('Y-m-d H:i:s T'),
			'timestamp' => $current_time->getTimestamp()
		], true));

		$response = add_post_meta( $report_id, 'pbda_attendance', $args );

		if ( $response && is_int( $response ) ) {
			return sprintf( esc_html__( 'Hello %s, Attendance added successfully', 'daily-attendance' ), $user->display_name );
		}

		return false;
	}
}


if ( ! function_exists( 'pbda_is_duplicate_attendance' ) ) {
	/**
	 * Check and return boolean result for duplicate attendance
	 *
	 * @param bool $user_id
	 * @param bool $report_id
	 *
	 * @return bool
	 */
	function pbda_is_duplicate_attendance( $user_id = false, $report_id = false ) {

		$is_duplicate = false;
		$user_id      = $user_id ?: get_current_user_id();
		$report_id    = $report_id ?: pbda_current_report_id();

		if ( $user_id === 0 || $user_id === false ) {
			return true;
		}

		if ( $report_id === 0 || $report_id === false ) {
			return true;
		}

		foreach ( pbda()->get_meta( 'pbda_attendance', $report_id, array(), false ) as $item ) {

			if ( ! isset( $item['user_id'] ) || $item['user_id'] != $user_id ) {
				continue;
			}

			if ( ! $is_duplicate && isset( $item['date'] ) && $item['date'] == date( 'Y-m-d', current_time( 'timestamp' ) ) ) {
				$is_duplicate = true;
			}
		}

		return $is_duplicate;
	}
}


if ( ! function_exists( 'pbda_current_report_id' ) ) {
	/**
	 * Return current month report ID
	 *
	 * @return mixed|void
	 */
	function pbda_current_report_id() {

		$da_reports = get_posts( array(
			'post_type'      => 'da_reports',
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_month',
					'value'   => date( 'Ym', current_time( 'timestamp' ) ),
					'compare' => '=',
				),
			),
		) );

		return apply_filters( 'pbda_current_report_id', reset( $da_reports ) );
	}
}


if ( ! function_exists( 'pbda_get_days_header' ) ) {
	function pbda_get_days_header( $num_days = 0 ) {


		ob_start();

		for ( $day = 0; $day <= $num_days; $day ++ ) {
			if ( $day === 0 ) {
				printf( '<td class="blank"></td>' );
				continue;
			}
			printf( '<th>%s</th>', $day );
		}

		return ob_get_clean();
	}
}


if ( ! function_exists( 'pbda' ) ) {
	/**
	 * Return global $pbda
	 *
	 * @return PBDA_Functions
	 */
	function pbda() {
		global $pbda;

		if ( empty( $pbda ) ) {
			$pbda = new PBDA_Functions();
		}

		return $pbda;
	}
}

// Add a helper function to format time with timezone
function pbda_format_time($attendance_data) {
    // Check if we're getting array or timestamp
    if (is_array($attendance_data)) {
        $timestamp = isset($attendance_data['timestamp']) ? $attendance_data['timestamp'] : 0;
    } else {
        $timestamp = (int)$attendance_data;
    }

    if (empty($timestamp)) {
        return 'N/A';
    }

    // Get WordPress timezone
    $wp_timezone = wp_timezone();
    
    try {
        $date = new DateTime();
        $date->setTimestamp($timestamp);
        $date->setTimezone($wp_timezone);
        error_log("Formatting time: Original timestamp: $timestamp, Formatted: " . $date->format('Y-m-d H:i:s T'));
        return $date->format('h:i A');
    } catch (Exception $e) {
        error_log("Time formatting error: " . $e->getMessage());
        return 'Invalid Time';
    }
}

function pbda_send_attendance_report($user_id = false, $report_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    error_log("Starting attendance report for user $user_id from report $report_id");

    // Check SMTP status first
    $smtp_status = EmailManager::get_smtp_status();
    if (!$smtp_status['active']) {
        return array(
            'status' => 'error',
            'message' => $smtp_status['message'],
            'start_time' => current_time('Y-m-d H:i:s'),
            'smtp_active' => false,
            'attendance_data' => [],
            'report_title' => '',
            'user_id' => $user_id
        );
    }

    error_log("Sending attendance report for user $user_id from report $report_id");
    
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return array(
            'status' => 'error',
            'message' => "User not found: $user_id",
            'start_time' => current_time('Y-m-d H:i:s')
        );
    }

    // Get attendance data
    $attendances = pbda_get_user_attendance($user_id, $report_id);
    
    if (is_wp_error($attendances)) {
        error_log("Error getting attendance: " . $attendances->get_error_message());
        return array(
            'status' => 'error',
            'message' => $attendances->get_error_message(),
            'start_time' => current_time('Y-m-d H:i:s')
        );
    }

    // Format attendance data for email
    $attendance_data = array();
    foreach ($attendances as $day => $data) {
        $attendance_data[$data['date']] = array(
            'timestamp' => $data['timestamp'],
            'time' => $data['time'],
            'day' => $day
        );
    }

    error_log("Formatted attendance data: " . print_r($attendance_data, true));

    return EmailManager::send_attendance_report($user_id, $attendance_data, $report_id);
}

function pbda_get_report_id_by_month($month) {
    $args = array(
        'post_type' => 'da_reports',
        'posts_per_page' => 1,
        'meta_query' => array(
            array(
                'key' => '_month',
                'value' => $month,
                'compare' => '='
            )
        )
    );
    
    $query = new WP_Query($args);
    return $query->have_posts() ? $query->posts[0]->ID : false;
}