<?php
/**
 * Class Hooks
 */


if ( ! class_exists( 'PBDA_Hooks' ) ) {

	/**
	 * Class PBDA_Hooks
	 */
	class PBDA_Hooks {

		/**
		 * PBDA_Hooks constructor.
		 */
		public function __construct() {

			add_action( 'init', array( $this, 'ob_start' ) );
			add_action( 'wp_footer', array( $this, 'ob_end' ) );
			add_action( 'init', array( $this, 'register_post_types' ) );
			add_action( 'init', array( $this, 'generate_monthly_report' ) );
			add_action( 'admin_menu', array( $this, 'remove_publish_box' ) );
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
			add_action( 'rest_api_init', array( $this, 'register_api' ) );
			add_action('init', array($this, 'handle_attendance_actions'));
			remove_action('admin_menu', array($this, 'add_attendance_menu'));
			remove_action('admin_post_edit_attendance', array($this, 'handle_edit_attendance'));
			remove_action('admin_post_delete_attendance', array($this, 'handle_delete_attendance'));
			add_action('wp_ajax_add_attendance', array($this, 'ajax_add_attendance'));
			add_action('wp_ajax_remove_attendance', array($this, 'ajax_remove_attendance'));
			add_action('wp_login', array($this, 'handle_login_redirect'), 10, 2);
			add_filter('login_redirect', array($this, 'custom_login_redirect'), 10, 3);
			add_action('template_redirect', array($this, 'handle_attendance_submission'));
			add_action('wp_login', array($this, 'process_attendance_after_login'), 10, 2);
			add_action('wp_ajax_send_attendance_report', array($this, 'ajax_send_attendance_report'));
			add_action('wp_ajax_export_attendance_csv', array($this, 'ajax_export_attendance_csv'));

			add_shortcode( 'attendance_form', array( $this, 'display_attendance_form' ) );
			add_shortcode('attendance_qr', array($this, 'display_qr_code'));
			add_shortcode('attendance_submit', array($this, 'display_submit_form'));

			add_filter( 'post_row_actions', array( $this, 'remove_row_actions' ), 10, 1 );
			add_action( 'manage_da_reports_posts_columns', array( $this, 'add_columns' ), 16, 1 );
			add_action( 'manage_da_reports_posts_custom_column', array( $this, 'columns_content' ), 10, 2 );
		}

		/**
		 * Content of custom column
		 *
		 * @param $column
		 * @param $post_id
		 */
		public function columns_content(string $column, int $post_id): void {
			if ($column == 'actions'):
				$nonce = wp_create_nonce('wp_rest');
				$export_url = rest_url("v1/export-csv/{$post_id}") . "?_wpnonce={$nonce}";
				?>
				<div class="row-actions">
					<span class="export-csv-action">
						<a href="<?php echo esc_url($export_url); ?>" class="export-csv" data-nonce="<?php echo esc_attr($nonce); ?>" data-report="<?php echo esc_attr($post_id); ?>">
							<?php esc_html_e('Export to CSV', 'daily-attendance'); ?>
						</a>
					</span>
				</div>
				<script>
				jQuery(document).ready(function($) {
					$('.export-csv').on('click', function(e) {
						e.preventDefault();
						const url = $(this).attr('href');
						const nonce = $(this).data('nonce');
						
						const form = $('<form>', {
							'method': 'GET',
							'action': url
						});
						
						form.append($('<input>', {
							'type': 'hidden',
							'name': '_wpnonce',
							'value': nonce
						}));
						
						$('body').append(form);
						form.submit();
						form.remove();
					});
				});
				</script>
				<?php
			endif;

			if ( $column == 'created_on' ):

				printf( esc_html__( 'Created %s ago', 'daily-attendance' ), human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ) );
				printf( '<div class="row-actions pbda-report-created"><span>%s</span></div>', get_the_time( 'jS M, Y - g:i a' ) );

			endif;
		}


		/**
		 * Add Custom column
		 *
		 * @param $columns
		 *
		 * @return array
		 */
		public function add_columns(array $columns): array {
			$new = array();
			foreach ($columns as $col_id => $col_label) {
				if ('title' === $col_id) {
					$new[$col_id] = esc_html__('Report Title', 'daily-attendance');
				} else {
					$new[$col_id] = $col_label;
				}
			}
			unset($new['date']);
			$new['created_on'] = esc_html__('Created on', 'daily-attendance');
			return $new;
		}


		/**
		 * Remove Post row actions
		 *
		 * @param $actions
		 *
		 * @return mixed
		 */
		public function remove_row_actions(array $actions): array {
			global $post;
			if ($post->post_type === 'da_reports') {
				$nonce = wp_create_nonce('wp_rest');
				$export_url = rest_url("v1/export-csv/{$post->ID}") . "?_wpnonce={$nonce}";
				
				$actions = array(
					'view' => sprintf('<a href="%s">%s</a>', 
						get_permalink($post->ID), 
						esc_html__('View', 'daily-attendance')
					),
					'export' => sprintf('<a href="%s" class="export-csv" data-nonce="%s" data-report="%d">%s</a>',
						esc_url($export_url),
						esc_attr($nonce),
						$post->ID,
						esc_html__('Export to CSV', 'daily-attendance')
					)
				);
			}
			return $actions;
		}


		public function serve_attendances_submit(WP_REST_Request $request): WP_REST_Response {
			// Get raw input
			$json = file_get_contents('php://input');
			$params = json_decode($json, true);

			// Fallback to other methods if JSON parsing fails
			if (json_last_error() !== JSON_ERROR_NONE) {
				$params = $request->get_json_params();
				if (empty($params)) {
					$params = $request->get_params();
				}
			}

			// Debug logging
			error_log('Raw input: ' . $json);
			error_log('Parsed params: ' . print_r($params, true));
			error_log('Request headers: ' . print_r(getallheaders(), true));

			// Check for hash authentication first 
			$hash = isset($params['hash']) ? sanitize_text_field($params['hash']) : '';
			$user_id = isset($params['user_id']) ? intval($params['user_id']) : 0;

			if ($hash && $user_id) {
				// Verify hash without timestamp
				$expected_hash = hash_hmac('sha256', $user_id, get_option('pbda_qr_secret'));
				
				if (hash_equals($expected_hash, $hash)) {
					$response = pbda_insert_attendance($user_id);
					
					if (!is_wp_error($response)) {
						// Send email report after successful attendance
						pbda_send_attendance_report($user_id);
					}

					return new WP_REST_Response(array(
						'version' => 'V1',
						'success' => !is_wp_error($response),
						'content' => is_wp_error($response) ? $response->get_error_message() : $response,
					));
				}
			}

			 // Username/password authentication
			 $user_name = isset($params['userName']) ? sanitize_text_field($params['userName']) : '';
			 $password = isset($params['passWord']) ? sanitize_text_field($params['passWord']) : '';
			 
			 if (empty($user_name) || empty($password)) {
				 return new WP_REST_Response(array(
					 'version' => 'V1',
					 'success' => false,
					 'content' => sprintf(
						 'Invalid authentication method. Received: %s', 
						 json_encode($params)
					 )
				 ));
			 }

			$current_user = wp_authenticate( $user_name, $password );

			if ( is_wp_error( $current_user ) ) {
				return new WP_REST_Response( array(
					'version' => 'V1',
					'success' => false,
					'content' => $current_user->get_error_message(),
				) );
			}

			if ( ! $current_user instanceof WP_User ) {
				return new WP_REST_Response( array(
					'version' => 'V1',
					'success' => false,
					'content' => esc_html__( 'Invalid username or password!', 'daily-attendance' ),
				) );
			}

			$response = pbda_insert_attendance( $current_user->ID );

			if ( is_wp_error( $response ) ) {
				return new WP_REST_Response( array(
					'version' => 'V1',
					'success' => false,
					'content' => $response->get_error_message(),
				) );
			}

			return new WP_REST_Response( array(
				'version' => 'V1',
				'success' => true,
				'content' => $response,
			) );
		}

		private function verify_api_key_with_details(): array {
			$api_key = '';
			$source = 'none';
			
			// Check header with different possible formats
			$headers = getallheaders();
			$possible_header_names = ['X-API-Key', 'x-api-key', 'X-Api-Key', 'HTTP_X_API_KEY'];
			
			foreach ($possible_header_names as $header_name) {
				if (isset($headers[$header_name])) {
					$api_key = $headers[$header_name];
					$source = 'header';
					break;
				}
			}
			
			// Check GET parameter if not in header
			if (empty($api_key) && isset($_GET['api_key'])) {
				$api_key = sanitize_text_field($_GET['api_key']);
				$source = 'query';
			}
			
			// Check POST parameter if not in GET
			if (empty($api_key) && isset($_POST['api_key'])) {
				$api_key = sanitize_text_field($_POST['api_key']);
				$source = 'post';
			}
			
			$stored_key = get_option('pbda_api_key', '');
			$keys_match = !empty($api_key) && !empty($stored_key) && hash_equals($stored_key, $api_key);
			
			return [
				'received_key' => $api_key,
				'stored_key' => $stored_key,
				'keys_match' => $keys_match,
				'source' => $source
			];
		}

		public function verify_api_key(): bool {
			$result = $this->verify_api_key_with_details();
			return $result['keys_match'];
		}

		private function api_verify_key_test(): WP_REST_Response {
			$result = $this->verify_api_key_with_details();
			
			return new WP_REST_Response([
				'success' => true,
				'data' => $result,
				'message' => $result['keys_match'] ? 'API keys match' : 'API keys do not match'
			]);
		}

		/**
		 * API Endpoints Documentation
		 * 
		 * Authentication Methods:
		 * 1. API Key: Send via X-API-Key header, query parameter, or POST parameter
		 * 2. Admin Access: WordPress admin with manage_options capability
		 * 
		 * Public Endpoints (No Auth Required):
		 * 1. POST /v1/qr-attendance/submit
		 *    Description: Submit attendance for a user
		 *    Auth: None
		 *    Params: 
		 *      - userName/passWord (for login method)
		 *      - hash/user_id (for QR code method)
		 *    Returns: {
		 *      "version": "V1",
		 *      "success": boolean,
		 *      "content": string
		 *    }
		 * 
		 * 2. GET /v1/qr-attendance/reports-public
		 *    Description: Get limited report info without authentication
		 *    Auth: None
		 *    Returns: {
		 *      "success": true,
		 *      "data": [{
		 *        "id": integer,
		 *        "title": string,
		 *        "month": string,
		 *        "formatted_date": string
		 *      }]
		 *    }
		 * 
		 * Protected Endpoints (API Key or Admin Required):
		 * 1. GET /v1/qr-attendance/reports
		 *    Description: Get full attendance reports
		 *    Auth: API Key or Admin
		 *    Headers: X-API-Key: {api_key}
		 *    Returns: {
		 *      "success": true,
		 *      "data": array,
		 *      "auth_method": string
		 *    }
		 * 
		 * API Key Only Endpoints:
		 * 1. POST /v1/qr-attendance/send-report-all
		 *    Description: Send report to all users with attendance records
		 *    Auth: API Key
		 *    Headers: X-API-Key: {api_key}
		 *    Params: report_id (integer)
		 *    Returns: {
		 *      "success": true,
		 *      "data": {
		 *        "report_id": integer,
		 *        "report_title": string,
		 *        "total_users": integer,
		 *        "results": [{
		 *          "user_id": integer,
		 *          "email": string,
		 *          "status": string,
		 *          "message": string
		 *        }]
		 *      }
		 *    }
		 * 
		 * 2. GET /v1/qr-attendance/export-csv/{report_id}
		 *    Description: Export report as CSV file
		 *    Auth: API Key
		 *    Headers: X-API-Key: {api_key}
		 *    Params: report_id (in URL)
		 *    Returns: CSV file download
		 * 
		 * 3. POST /v1/qr-attendance/send-report
		 *    Description: Send report to specific user
		 *    Auth: API Key
		 *    Headers: X-API-Key: {api_key}
		 *    Params: 
		 *      - user_id (integer)
		 *      - report_id (integer)
		 *    Returns: {
		 *      "success": boolean,
		 *      "status": string,
		 *      "message": string,
		 *      "email_sent": boolean
		 *    }
		 * 
		 * Common Error Responses:
		 * 1. Authentication Error (401):
		 *    {
		 *      "success": false,
		 *      "message": "Invalid API key"
		 *    }
		 * 
		 * 2. Bad Request (400):
		 *    {
		 *      "success": false,
		 *      "message": "Error description"
		 *    }
		 * 
		 * 3. Not Found (404):
		 *    {
		 *      "success": false,
		 *      "message": "Resource not found"
		 *    }
		 * 
		 * 4. Server Error (500):
		 *    {
		 *      "success": false,
		 *      "message": "Internal server error"
		 *    }
		 */

		public function register_api(): void {
			// Add plugin prefix to namespace
			$namespace = 'v1';

			// Public endpoints (no API key required)
			register_rest_route($namespace, '/qr-attendance/submit', array(
				'methods' => 'POST',
				'callback' => array($this, 'serve_attendances_submit'),
				'permission_callback' => '__return_true',
				'args' => array(
					'userName' => array(
						'required' => false,
						'type' => 'string',
					),
					'passWord' => array(
						'required' => false,
						'type' => 'string',
					),
					'hash' => array(
						'required' => false,
						'type' => 'string',
					),
					'user_id' => array(
						'required' => false,
						'type' => 'integer',
						'validate_callback' => function($param) {
							return is_numeric($param);
						}
					)
				)
			));

			// Admin endpoints (requires WP admin login)
			register_rest_route($namespace, '/qr-attendance/reports', array(
				'methods' => 'GET',
				'callback' => array($this, 'api_get_reports'),
				'permission_callback' => function() {
					$auth = $this->verify_api_key_with_details();
					error_log('API Authentication: ' . ($auth['keys_match'] ? 'SUCCESS' : 'FAILED') . ' via ' . $auth['source']);
					
					if ($auth['keys_match']) {
						return true;
					}
					
					// Fallback to admin check
					$admin_access = current_user_can('manage_options');
					error_log('Admin Authentication: ' . ($admin_access ? 'SUCCESS' : 'FAILED'));
					return $admin_access;
				}
			));

			 // Commented out public endpoint for security reasons
			 /*
			 register_rest_route($namespace, '/qr-attendance/reports-public', array(
				 'methods' => 'GET',
				 'callback' => array($this, 'api_get_reports_public'),
				 'permission_callback' => '__return_true'
			 ));
			 */

			register_rest_route($namespace, '/qr-attendance/send-report-all', array(
				'methods' => 'POST',
				'callback' => array($this, 'api_send_report_all'),
				'permission_callback' => array($this, 'verify_api_key'),
				'args' => array(
					'report_id' => array(
						'required' => true,
						'type' => 'integer'
					)
				)
			));

			// API key endpoints
			register_rest_route($namespace, '/qr-attendance/export-csv/(?P<report_id>\d+)', array(
				'methods' => 'GET',
				'callback' => array($this, 'api_export_csv'),
				'permission_callback' => array($this, 'verify_api_key'),
				'args' => array(
					'report_id' => array(
						'required' => true,
						'type' => 'integer',
						'validate_callback' => function($param) {
							return is_numeric($param);
						}
					)
				)
			));

			register_rest_route($namespace, '/qr-attendance/send-report', array(
				'methods' => 'POST',
				'callback' => array($this, 'api_send_report'),
				'permission_callback' => array($this, 'verify_api_key'),
				'args' => array(
					'user_id' => array(
						'required' => true,
						'type' => 'integer'
					),
					'report_id' => array(  // Changed from month to report_id
						'required' => true,
						'type' => 'integer'
					)
				)
			));

			// Add regenerate API key endpoint
			add_action('wp_ajax_regenerate_api_key', array($this, 'regenerate_api_key'));

			 // Public verify-api-key endpoint removed for security
			 /* 
			 register_rest_route($namespace, '/qr-attendance/verify-api-key', array(
				 'methods' => 'GET',
				 'callback' => array($this, 'api_verify_key_test'),
				 'permission_callback' => '__return_true'
			 ));
			 */
		}

		public function regenerate_api_key(): void {
			check_ajax_referer('pbda_regenerate_api_key', 'nonce');
			
			if (!current_user_can('manage_options')) {
				wp_send_json_error('Permission denied');
			}
			
			$new_key = wp_generate_password(32, false);
			update_option('pbda_api_key', $new_key);
			wp_send_json_success();
		}

		public function check_admin_permission(): bool {
			return current_user_can('manage_options');
		}

		public function api_export_csv(WP_REST_Request $request): void {
			$report_id = $request->get_param('report_id');
			require_once PBDA_PLUGIN_DIR . 'includes/class-export-manager.php';
			ExportManager::generate_csv($report_id);
		}

		public function api_send_report(WP_REST_Request $request): WP_REST_Response {
			$user_id = $request->get_param('user_id');
				$report_id = $request->get_param('report_id');
				
				// Verify report exists and is a valid attendance report
				$report = get_post($report_id);
				if (!$report || $report->post_type !== 'da_reports') {
					return new WP_REST_Response([
						'success' => false,
						'message' => 'Invalid report ID'
					], 400);
				}
				
				$result = pbda_send_attendance_report($user_id, $report_id);
				return new WP_REST_Response($result);
		}

		public function api_get_reports(): WP_REST_Response {
			try {
				// Log authentication method used
				$auth_method = $this->verify_api_key() ? 'API Key' : 'WordPress Admin';
				error_log('Fetching reports using auth method: ' . $auth_method);
				
				$reports = pbda_get_all_reports();
				
				if (empty($reports)) {
					error_log('No reports found');
					return new WP_REST_Response([
						'success' => true,
						'message' => 'No reports found',
						'data' => []
					]);
				}

				error_log('Successfully fetched ' . count($reports) . ' reports');
				return new WP_REST_Response([
					'success' => true,
					'data' => $reports,
					'auth_method' => $auth_method
				]);

			} catch (Exception $e) {
				error_log('Reports API Error: ' . $e->getMessage());
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Error fetching reports: ' . $e->getMessage()
				], 500);
			}
		}

		public function api_get_reports_public(): WP_REST_Response {
			try {
				error_log('Fetching reports without authentication');
				$reports = pbda_get_all_reports();
				
				// Sanitize sensitive data for public endpoint
				$public_reports = array_map(function($report) {
					return [
						'id' => $report['id'],
						'title' => $report['title'],
						'month' => $report['month'],
						'formatted_date' => $report['formatted_date']
					];
				}, $reports);

				return new WP_REST_Response([
					'success' => true,
					'data' => $public_reports
				]);

			} catch (Exception $e) {
				error_log('Public Reports API Error: ' . $e->getMessage());
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Error fetching reports'
				], 500);
			}
		}
		
		public function api_send_report_all(WP_REST_Request $request): WP_REST_Response {
			try {
				$report_id = $request->get_param('report_id');
				
				// Verify report exists
				$report = get_post($report_id);
				if (!$report || $report->post_type !== 'da_reports') {
					return new WP_REST_Response([
						'success' => false,
						'message' => 'Invalid report ID'
					], 400);
				}
			
				// Get all attendance records for this report
				$attendances = get_post_meta($report_id, 'pbda_attendance', false);
				if (empty($attendances)) {
					return new WP_REST_Response([
						'success' => false,
						'message' => 'No attendance records found for this report'
					], 404);
				}

				// Extract unique user IDs from attendance records
				$user_ids = array_unique(array_map(function($attendance) {
					return $attendance['user_id'];
				}, $attendances));

				$results = [];
				foreach ($user_ids as $user_id) {
					$user = get_user_by('id', $user_id);
					if (!$user) continue;

					$result = pbda_send_attendance_report($user_id, $report_id);
					$results[] = [
						'user_id' => $user_id,
						'email' => $user->user_email,
						'status' => $result['status'],
						'message' => $result['message']
					];
				}
			
				return new WP_REST_Response([
					'success' => true,
					'data' => [
						'report_id' => $report_id,
						'report_title' => $report->post_title,
						'total_users' => count($user_ids),
						'results' => $results
					]
				]);

			} catch (Exception $e) {
				error_log('Send Report All Error: ' . $e->getMessage());
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Error sending reports: ' . $e->getMessage()
				], 500);
			}
		}

		/**
		 * Display attendance form
		 *
		 * @return false|string
		 */
		public function display_attendance_form(): string {

			if ( ! is_user_logged_in() ) {
				return sprintf( '<p class="pbda-notice pbda-error">%s <a href="%s">%s</a></p>',
					esc_html__( 'You must login to access this content', 'daily-attendance' ),
					wp_login_url( get_permalink() ),
					esc_html__( 'Click here to Login', 'daily-attendance' )
				);
			}

			ob_start();

			include PBDA_PLUGIN_DIR . 'templates/attendance-form.php';

			return ob_get_clean();
		}


		/**
		 * Display QR Code page
		 */
		public function display_qr_code(): string {
			ob_start();
			include PBDA_PLUGIN_DIR . 'templates/qr-code-page.php';
			return ob_get_clean();
		}

		/**
		 * Display submission form
		 */
		public function display_submit_form(): string {
			ob_start();
			include PBDA_PLUGIN_DIR . 'templates/submission-form.php';
			return ob_get_clean();
		}

		/**
		 * Validate daily token
		 */
		private function validate_daily_token(string $token): bool {
			return wp_verify_nonce($token, 'pbda_daily_' . date('Y-m-d'));
		}

		/**
		 * Display Reports
		 *
		 * @param $post
		 */
		public function display_reports(WP_Post $post): void {

			$this_month = (int) date( 'm' );
			$this_year  = (int) date( 'Y' );
			$num_days   = cal_days_in_month( CAL_GREGORIAN, $this_month, $this_year );
			$export_url = '';

			?>
            <div class="pbda-reports-wrap">

                <div class="pbda-report-info">

                    <div class="pbda-info">
                        <span class="label"><?php esc_html_e( 'Report Month', 'daily-attendance' ); ?></span>
                        <span class="value"><?php echo date( 'F, Y' ); ?></span>
                    </div>

                    <div class="pbda-info">
                        <span class="label"><?php esc_html_e( 'Users Count', 'daily-attendance' ); ?></span>
                        <span class="value"><?php echo count( pbda()->get_users_list() ); ?></span>
                    </div>

                    <div class="pbda-info">
                        <span class="label"><?php esc_html_e( 'Export', 'daily-attendance' ); ?></span>
                        <span class="value">
                            <button class="button export-csv" data-report="<?php echo esc_attr($post->ID); ?>">
                                <?php esc_html_e('Export to CSV', 'daily-attendance'); ?>
                            </button>
                        </span>
                    </div>

                </div>

				<?php echo pbda_get_attendance_report( $num_days, $post->ID ); ?>
            </div>
			<?php
			wp_enqueue_script('jquery');
			?>
			<script>
			jQuery(document).ready(function($) {
				$('.pbda-reports td').click(function() {
					if (!$(this).hasClass('name')) {
						var $cell = $(this);
						// Fix date calculation by getting the actual day number
						var dayIndex = $cell.index();
						var day = dayIndex.toString().padStart(2, '0'); // Ensure 2 digits
						var date = '<?php echo date('Y-m'); ?>-' + day;
						var userId = $cell.closest('tr').data('user-id');
						
						if ($cell.hasClass('yes')) {
							// Remove attendance
							if (confirm('<?php esc_html_e('Remove attendance?', 'daily-attendance'); ?>')) {
								 $.ajax({
									url: ajaxurl,
									method: 'POST',
									data: {
										action: 'remove_attendance',
										nonce: '<?php echo wp_create_nonce('pbda_ajax_nonce'); ?>',
										user_id: userId,
										date: date,
										report_id: <?php echo esc_js($post->ID); ?>
									},
									success: function(response) {
										if (response.success) {
											$cell.removeClass('yes').addClass('no')
												 .removeClass('tt--top tt--info')
												 .removeAttr('aria-label')
												 .html('');
										} else {
											alert(response.data || 'Failed to remove attendance');
										}
									}
								});
							}
						} else {
							// Add attendance
							if (confirm('<?php esc_html_e('Add attendance?', 'daily-attendance'); ?>')) {
								$.post(ajaxurl, {
									action: 'add_attendance',
									nonce: '<?php echo wp_create_nonce('pbda_ajax_nonce'); ?>',
									user_id: userId,
									date: date
								}, function(response) {
									if (response.success) {
										$cell.removeClass('no').addClass('yes')
											 .html('<i class="icofont-check-alt"></i>');
									}
								});
							}
						}
					}
				});

                $('.export-csv').click(function(e) {
                    e.preventDefault();
                    const reportId = $(this).data('report');
                    
                    // Create form and submit
                    const form = $('<form>', {
                        'method': 'POST',
                        'action': ajaxurl
                    });
                    
                    // Add required fields
                    form.append($('<input>', {
                        'type': 'hidden',
                        'name': 'action',
                        'value': 'export_attendance_csv'
                    }));
                    
                    form.append($('<input>', {
                        'type': 'hidden',
                        'name': 'report_id',
                        'value': reportId
                    }));
                    
                    form.append($('<input>', {
                        'type': 'hidden',
                        'name': 'nonce',
                        'value': '<?php echo wp_create_nonce("pbda_ajax_nonce"); ?>'
                    }));
                    
                    // Submit form
                    $('body').append(form);
                    form.submit();
                    form.remove();
                });
			});
			</script>
			<?php
		}


		/**
		 * Add meta boxes
		 *
		 * @param $post_type
		 */
		public function add_meta_boxes(string $post_type): void {

			if ( in_array( $post_type, array( 'da_reports' ) ) ) {

				add_meta_box( 'da_reports', esc_html__( 'Attendance Reports', 'daily-attendance' ), array(
					$this,
					'display_reports'
				), $post_type, 'normal', 'high' );
			}
		}


		/**
		 * Remove publish box for specific post type
		 */
		public function remove_publish_box(): void {
			remove_meta_box( 'submitdiv', 'da_reports', 'side' );
		}


		/**
		 * Generate Monthly Report
		 */
		public function generate_monthly_report(): void {
			// Simply call pbda_current_report_id() which now handles duplicates
			pbda_current_report_id();
		}


		/**
		 * Register Post Types
		 */
		public function register_post_types(): void {
			pbda()->PB_Settings()->register_post_type('da_reports', array(
				'singular'      => esc_html__('Attendance Report', 'daily-attendance'),
				'plural'        => esc_html__('Attendance Reports', 'daily-attendance'),
				'menu_icon'     => 'dashicons-id-alt',
				'menu_position' => 30,
				'supports'      => array(''),
				'capabilities'  => array(
					'create_posts' => false
				),
				'map_meta_cap'  => true,
				'labels'        => array(
					'menu_name'  => esc_html__('Daily Attendance', 'daily-attendance'),
					'all_items'  => esc_html__('Monthly Reports', 'daily-attendance'),
				),
			));
		}


		/**
		 * Return Buffered Content
		 *
		 * @param $buffer
		 *
		 * @return mixed
		 */
		public function ob_callback(string $buffer): string {
			return $buffer;
		}


		/**
		 * Start of Output Buffer
		 */
		public function ob_start(): void {
			ob_start( array( $this, 'ob_callback' ) );
		}


		/**
		 * End of Output Buffer
		 */
		public function ob_end(): void {
			if ( ob_get_length() ) {
				ob_end_flush();
			}
		}

		/**
		 * Handle attendance actions from URL
		 */
		public function handle_attendance_actions(): void {
			if (isset($_GET['pbda_action']) && $_GET['pbda_action'] === 'attendance_submit') {
				$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
				$timestamp = isset($_GET['ts']) ? (int)$_GET['ts'] : 0;
				
				// Validate timestamp (within 5 minutes)
				if (time() - $timestamp > 300) {
					wp_die(esc_html__('QR code has expired. Please scan again.', 'daily-attendance'));
				}

				if (!is_user_logged_in()) {
					// Store submission data in session
					if (!session_id()) {
						session_start();
					}
					$_SESSION['pbda_attendance_data'] = array(
						'token' => $token,
						'timestamp' => $timestamp
					);
					
					// Redirect to login with return URL
					wp_redirect(wp_login_url(add_query_arg(array(
						'pbda_action' => 'attendance_submit',
						'auto_submit' => '1'
					), home_url('/'))));
					exit;
				}

				// Handle submission for logged-in user
				if ($this->validate_daily_token($token)) {
					$response = pbda_insert_attendance();
					
					echo '<div class="pbda-response-overlay">';
					if (is_wp_error($response)) {
						echo '<div class="pbda-notice pbda-error">' . esc_html($response->get_error_message()) . '</div>';
					} else {
						echo '<div class="pbda-notice pbda-success">' . esc_html($response) . '</div>';
					}
					echo '</div>';
					
					// Add styles and auto-close script
					add_action('wp_footer', function() {
						?>
						<style>
							.pbda-response-overlay {
								position: fixed;
								top: 0;
								left: 0;
								right: 0;
								bottom: 0;
								background: rgba(0,0,0,0.8);
								display: flex;
								align-items: center;
								justify-content: center;
								z-index: 9999;
							}
							.pbda-notice {
								background: #fff;
								padding: 20px;
								border-radius: 8px;
								text-align: center;
								max-width: 90%;
							}
							.pbda-success { color: #4CAF50; }
							.pbda-error { color: #f44336; }
						</style>
						<script>setTimeout(function() { 
							window.close();
							// Fallback if window.close() fails
							window.location.href = '<?php echo esc_url(home_url()); ?>';
						}, 3000);</script>
						<?php
					});
				}
			}
		}

		/**
		 * AJAX handler to add attendance
		 */
		public function ajax_add_attendance(): void {
			check_ajax_referer('pbda_ajax_nonce', 'nonce');
			
			$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
			$date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
			
			if (!current_user_can('manage_options')) {
				wp_send_json_error('Permission denied');
			}

			// Get WordPress timezone and create DateTime object
			$wp_timezone = wp_timezone();
			$date_obj = new DateTime($date . ' ' . current_time('H:i:s'), $wp_timezone);
			
			$args = array(
				'user_id' => $user_id,
				'report_id' => pbda_current_report_id(),
				'date' => $date,
				'current_time' => $date_obj->getTimestamp(),
				'timezone' => $wp_timezone->getName()
			);

			error_log("Adding attendance: " . print_r([
				'date' => $date,
				'formatted_time' => $date_obj->format('Y-m-d H:i:s T'),
				'timestamp' => $date_obj->getTimestamp()
			], true));

			$response = add_post_meta(pbda_current_report_id(), 'pbda_attendance', $args);
			
			if ($response) {
				wp_send_json_success('Attendance added successfully');
			} else {
				wp_send_json_error('Failed to add attendance');
			}
		}

		/**
		 * AJAX handler to remove attendance
		 */
		public function ajax_remove_attendance(): void {
			check_ajax_referer('pbda_ajax_nonce', 'nonce');
			
			if (!current_user_can('manage_options')) {
				wp_send_json_error('Permission denied');
			}

			$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
			$date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
			$report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : 0;

			if (!$report_id) {
				$report_id = pbda_current_report_id();
			}

			// Get all attendance records
			$attendances = get_post_meta($report_id, 'pbda_attendance', false);
			
			foreach ($attendances as $attendance) {
				if (isset($attendance['user_id']) && 
					isset($attendance['date']) && 
					$attendance['user_id'] == $user_id && 
					$attendance['date'] == $date) {
					
					// Found matching record, delete it
					$deleted = delete_post_meta($report_id, 'pbda_attendance', $attendance);
					if ($deleted) {
						wp_send_json_success(['message' => 'Attendance removed successfully']);
						return;
					}
				}
			}
			
			wp_send_json_error(['message' => 'Attendance record not found']);
		}

		/**
		 * Custom login redirect
		 */
		public function custom_login_redirect($redirect_to, $requested_redirect_to, $user) {
			if (!empty($_GET['pbda_action']) && $_GET['pbda_action'] === 'submit') {
				$token = isset($_GET['token']) ? $_GET['token'] : '';
				$date = isset($_GET['date']) ? $_GET['date'] : '';
				return add_query_arg(array(
					'pbda_action' => 'submit',
					'token' => $token,
					'date' => $date,
					'auto_submit' => '1'
				), home_url('/'));
			}
			return $redirect_to;
		}

		public function handle_login_redirect($user_login, $user): void {
			if (!session_id()) {
				session_start();
			}

			// Check for stored attendance data
			if (isset($_SESSION['pbda_attendance_data'])) {
				$data = $_SESSION['pbda_attendance_data'];
				if ($this->validate_daily_token($data['token'])) {
					$response = pbda_insert_attendance($user->ID);
					// Clear session data
					unset($_SESSION['pbda_attendance_data']);
					
					// Redirect to success page
					wp_redirect(add_query_arg(array(
						'pbda_action' => 'attendance_success',
						'status' => is_wp_error($response) ? 'error' : 'success',
						'message' => is_wp_error($response) ? urlencode($response->get_error_message()) : urlencode($response)
					), home_url('/')));
					exit;
				}
			}
		}

		public function handle_attendance_submission(): void {
			// Only run on the attendance submission page
			if (!is_page(get_option('pbda_attendance_page'))) {
				return;
			}

			$nonce = isset($_GET['attendance']) ? $_GET['attendance'] : '';
			
			// Store the attendance request in session for after login
			if (!is_user_logged_in() && $nonce) {
				if (!session_id()) {
					session_start();
				}
				$_SESSION['pbda_pending_attendance'] = array(
					'nonce' => $nonce,
					'redirect_url' => get_permalink() . '?auto_submit=1'
				);
			}
		}

		public function process_attendance_after_login($user_login, $user): void {
			if (!session_id()) {
				session_start();
			}

			if (isset($_SESSION['pbda_pending_attendance'])) {
				$data = $_SESSION['pbda_pending_attendance'];
				unset($_SESSION['pbda_pending_attendance']);
				
				if (wp_verify_nonce($data['nonce'], 'pbda_attendance_' . date('Y-m-d'))) {
					wp_redirect($data['redirect_url']);
					exit;
				}
			}
		}

		public function ajax_send_attendance_report(): void {
			check_ajax_referer('pbda_send_report', 'nonce');
			
			if (!current_user_can('manage_options')) {
				wp_send_json_error(['message' => __('Permission denied', 'daily-attendance')]);
				return;
			}

			$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
			$month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : date('Ym');
			
			error_log("Processing send report request for user: $user_id, month: $month");
			
			// Get report ID
			$report_id = pbda_get_report_id_by_month($month);
			
			if (!$report_id) {
				wp_send_json_error([
					'status' => 'error',
					'message' => __('No attendance data found for this month', 'daily-attendance'),
					'attendance_data' => [],
					'start_time' => current_time('Y-m-d H:i:s'),
					'smtp_active' => EmailManager::is_wp_mail_smtp_active(),
					'report_title' => date('F Y', strtotime($month . '01'))
				]);
				return;
			}

			$result = pbda_send_attendance_report($user_id, $report_id);
			wp_send_json_success($result);
		}

		public function ajax_export_attendance_csv(): void {
			try {
				error_log('Export CSV request started');
				
				// Check nonce
				if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pbda_ajax_nonce')) {
					throw new Exception('Security check failed');
				}
				
				// Check permissions
				if (!current_user_can('manage_options')) {
					throw new Exception('Permission denied');
				}
				
				// Get report ID
				$report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : 0;
				if (!$report_id) {
					throw new Exception('Invalid report ID');
				}

				error_log("Exporting CSV for report ID: $report_id");
				
				// Generate CSV
				require_once PBDA_PLUGIN_DIR . 'includes/class-export-manager.php';
				ExportManager::generate_csv($report_id);
				
				// ExportManager handles the exit
			} catch (Exception $e) {
				error_log('CSV Export error: ' . $e->getMessage());
				wp_die($e->getMessage());
			}
		}
	}

	new PBDA_Hooks();
}