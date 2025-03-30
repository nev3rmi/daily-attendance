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

			if ( $column == 'actions' ):
				?>
				<span class="export-cell">
					<form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" class="export-form">
						<input type="hidden" name="action" value="export_attendance_csv">
						<input type="hidden" name="report_id" value="<?php echo esc_attr($post_id); ?>">
						<input type="hidden" name="nonce" value="<?php echo wp_create_nonce('pbda_ajax_nonce'); ?>">
						<button type="submit" class="button export-csv">
							<?php esc_html_e('Export to CSV', 'daily-attendance'); ?>
						</button>
					</form>
				</span>
				<style>
					.export-cell { display: inline-block; }
					.export-form { margin: 0; padding: 0; }
				</style>
				<script>
				jQuery(document).ready(function($) {
					$('.export-form').on('submit', function(e) {
						e.preventDefault();
						const form = $(this);
						
						// Create an iframe for download
						const frame_name = 'download_frame_' + Math.random().toString(36).substring(7);
						const iframe = $('<iframe>', {
							name: frame_name,
							css: {
								display: 'none'
							}
						}).appendTo('body');

						// Set target and submit
						form.attr('target', frame_name);
						form.get(0).submit();

						// Cleanup after delay
						setTimeout(() => iframe.remove(), 5000);
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

			$count = 0;
			foreach ( $columns as $col_id => $col_label ) {
				$count ++;

				if ( $count == 3 ) {
					$new['actions'] = esc_html__('Export', 'daily-attendance');
				}

				if ( 'title' === $col_id ) {
					$new[ $col_id ] = esc_html__('Report Title', 'daily-attendance');
				} else {
					$new[ $col_id ] = $col_label;
				}
			}

			unset( $new['date'] );

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

			if ( $post->post_type === 'da_reports' ) {
				unset( $actions['inline hide-if-no-js'] );
			}

			if ( $post->post_type === 'da_reports' ) {

				$actions['view'] = str_replace( 'Edit', 'View', $actions['edit'] );

				unset( $actions['inline hide-if-no-js'] );
				unset( $actions['trash'] );
				unset( $actions['edit'] );
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


		public function register_api(): void {
			register_rest_route('v1', '/attendances/submit', array(
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
					)
					)
			));
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
						var date = '<?php echo date('Y-m'); ?>-' + $cell.closest('tr').find('td').index($cell);
						var userId = $cell.closest('tr').data('user-id');
						
						if ($cell.hasClass('yes')) {
							// Remove attendance
							if (confirm('<?php esc_html_e('Remove attendance?', 'daily-attendance'); ?>')) {
								$.post(ajaxurl, {
									action: 'remove_attendance',
									nonce: '<?php echo wp_create_nonce('pbda_ajax_nonce'); ?>',
									user_id: userId,
									date: date
								}, function(response) {
									if (response.success) {
										$cell.removeClass('yes').addClass('no').html('');
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

			$current_report_id = pbda_current_report_id();

			if ( empty( $current_report_id ) || ! $current_report_id ) {
				wp_insert_post( array(
					'post_type'   => 'da_reports',
					'post_title'  => sprintf( esc_html__( 'Report - %s', 'daily-attendance' ), date( 'M, Y' ) ),
					'post_status' => 'publish',
					'meta_input'  => array(
						'_month' => date( 'Ym' )
					)
				) );
			}
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
			
			$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
			$date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
			
			if (!current_user_can('manage_options')) {
				wp_send_json_error('Permission denied');
			}

			$report_id = pbda_current_report_id();
			$attendances = get_post_meta($report_id, 'pbda_attendance', false);
			
			foreach ($attendances as $key => $attendance) {
				if ($attendance['user_id'] == $user_id && $attendance['date'] == $date) {
					delete_post_meta($report_id, 'pbda_attendance', $attendance);
					wp_send_json_success('Attendance removed successfully');
				}
			}
			
			wp_send_json_error('Attendance not found');
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