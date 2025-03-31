<?php
/**
 * PB Settings
 *
 * Quick settings page generator for WordPress
 *
 * @package PB_Settings
 * @version 3.0.5
 * @author Pluginbazar
 * @copyright 2019 Pluginbazar.com
 * @see https://github.com/jaedm97/PB-Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access

require_once PBDA_PLUGIN_DIR . 'includes/settings/class-pb-settings-fields.php';
require_once PBDA_PLUGIN_DIR . 'includes/settings/class-pb-settings-menu.php';
require_once PBDA_PLUGIN_DIR . 'includes/settings/class-pb-settings-tabs.php';
require_once PBDA_PLUGIN_DIR . 'includes/settings/class-pb-settings-post-types.php';

if ( ! class_exists( 'PB_Settings' ) ) {
	class PB_Settings {
		use PB_Settings_Fields;
		use PB_Settings_Menu;
		use PB_Settings_Tabs;
		use PB_Settings_Post_Types;

		/** @var array */
		protected array $data = [];
		
		/** @var string|null */  
		protected ?string $disabled_notice = null;

		/** @var array */
		private array $options = [];
		
		/** @var array */
		private array $checked = [];

		/**
		 * PB_Settings constructor.
		 *
		 * @param array $args Configuration arguments
		 */
		public function __construct(array $args = []) {
			$this->data = $args;

			if ($this->add_in_menu()) {
				add_action('admin_menu', [$this, 'add_menu_in_admin_menu'], 12);
			}

			$this->disabled_notice = $this->get_disabled_notice();
			$this->set_options();

			add_action('admin_init', [$this, 'display_fields'], 12);
			add_filter('whitelist_options', [$this, 'whitelist_options'], 99, 1);
		}

		/**
		 * Add Menu in WordPress Admin Menu
		 */
		function add_menu_in_admin_menu() {

			if ( "menu" == $this->get_menu_type() ) {
				$menu_ret = add_menu_page( $this->get_menu_name(), $this->get_menu_title(), $this->get_capability(), $this->get_menu_slug(), array(
					$this,
					'display_function'
				), $this->get_menu_icon(), $this->get_menu_position() );

				do_action( 'pb_settings_menu_added_' . $this->get_menu_slug(), $menu_ret );
			}

			if ( "submenu" == $this->get_menu_type() ) {
				$submenu_ret = add_submenu_page( $this->get_parent_slug(), $this->get_page_title(), $this->get_menu_title(), $this->get_capability(), $this->get_menu_slug(), array(
					$this,
					'display_function'
				) );

				do_action( 'pb_settings_submenu_added_' . $this->get_menu_slug(), $submenu_ret );
			}
		}

		/**
		 * Display Settings Fields
		 */
		function display_fields() {

			foreach ( $this->get_settings_fields() as $key => $setting ):

				add_settings_section( $key, isset( $setting['title'] ) ? $setting['title'] : "", array(
					$this,
					'section_callback'
				), $this->get_current_page() );

				foreach ( $setting['options'] as $option ) :

					$option_id    = isset( $option['id'] ) ? $option['id'] : '';
					$option_title = isset( $option['title'] ) ? $option['title'] : '';

					if ( empty( $option_id ) ) {
						continue;
					}

					add_settings_field( $option_id, $option_title, array(
						$this,
						'field_generator'
					), $this->get_current_page(), $key, $option );

				endforeach;

			endforeach;
		}

		/**
		 * Add new options to $whitelist_options
		 *
		 * @param $whitelist_options
		 *
		 * @return mixed
		 */
		function whitelist_options( $whitelist_options ) {

			foreach ( $this->get_pages() as $page_id => $page ) :
				$page_settings = isset( $page['page_settings'] ) ? $page['page_settings'] : array();
				foreach ( $page_settings as $section ):
					foreach ( $section['options'] as $option ):
						$whitelist_options[ $page_id ][] = $option['id'];
					endforeach;
				endforeach;
			endforeach;

			return $whitelist_options;
		}

		/**
		 * Display Settings Tab Page
		 */
		function display_function() {

			global $pagenow;
			parse_str( $_SERVER['QUERY_STRING'], $nav_url_args );

			$tab_count = 0;

			?>
            <div class="wrap">
                <h2><?php echo esc_html( $this->get_menu_page_title() ); ?></h2><br>

				<?php settings_errors(); ?>

                <nav class="nav-tab-wrapper">
					<?php
					foreach ( $this->get_pages() as $page_id => $page ) {

						$tab_count ++;

						$active              = $this->get_current_page() == $page_id ? 'nav-tab-active' : '';
						$nav_url_args['tab'] = $page_id;
						$nav_menu_url        = http_build_query( $nav_url_args );
						$page_nav            = isset( $page['page_nav'] ) ? $page['page_nav'] : '';

						printf( '<a href="%s?%s" class="nav-tab %s">%s</a>', $pagenow, $nav_menu_url, $active, $page_nav );
					}
					?>
                </nav>

				<?php
				do_action( 'pb_settings_before_page_' . $this->get_current_page() );

				if ( $this->show_submit_button() ) {
					printf( '<form class="pb_settings_form" action="options.php" method="post">%s%s</form>',
						$this->get_settings_fields_html(), get_submit_button()
					);
				} else {
					print( $this->get_settings_fields_html() );
				}

				do_action( $this->get_current_page() );

				do_action( 'pb_settings_after_page_' . $this->get_current_page() );
				?>
            </div>
			<?php
		}

		/**
		 * Return All Settings HTML
		 *
		 * @return false|string
		 */
		function get_settings_fields_html() {

			ob_start();

			do_action( 'pb_settings_page_' . $this->get_current_page() );

			settings_fields( $this->get_current_page() );
			do_settings_sections( $this->get_current_page() );

			return ob_get_clean();
		}

		/**
		 * Set options from Data object
		 */
		private function set_options() {

			foreach ( $this->get_pages() as $page ):
				$setting_sections = isset( $page['page_settings'] ) ? $page['page_settings'] : array();
				foreach ( $setting_sections as $setting_section ):
					if ( isset( $setting_section['options'] ) ) {
						$this->options = array_merge( $this->options, $setting_section['options'] );
					}
				endforeach;
			endforeach;
		}

		/**
		 * Return Options
		 *
		 * @return array
		 */
		function get_options() {
			return $this->options;
		}

		/**
		 * Return whether Submit button to show or hide
		 *
		 * @return bool
		 */
		private function show_submit_button() {
			return isset( $this->get_pages()[ $this->get_current_page() ]['show_submit'] )
				? $this->get_pages()[ $this->get_current_page() ]['show_submit']
				: true;
		}

		/**
		 * Return Current Page
		 *
		 * @return mixed|string
		 */
		function get_current_page() {

			$all_pages   = $this->get_pages();
			$page_keys   = array_keys( $all_pages );
			$default_tab = ! empty( $all_pages ) ? reset( $page_keys ) : "";

			return isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : $default_tab;
		}

		/**
		 * Return menu type
		 *
		 * @return mixed|string
		 */
		private function get_menu_type() {
			if ( isset( $this->data['menu_type'] ) ) {
				return $this->data['menu_type'];
			} else {
				return "main";
			}
		}

		/**
		 * Return Pages
		 *
		 * @return array|mixed
		 */
		private function get_pages() {
			if ( isset( $this->data['pages'] ) ) {
				$pages = $this->data['pages'];
			} else {
				return array();
			}

			$pages_sorted = array();
			$increment    = 0;

			foreach ( $pages as $page_key => $page ) {

				$increment += 5;
				$priority  = isset( $page['priority'] ) ? $page['priority'] : $increment;

				$pages_sorted[ $page_key ] = $priority;
			}
			array_multisort( $pages_sorted, SORT_ASC, $pages );

			return $pages;
		}

		/**
		 * Return settings fields
		 *
		 * @return array
		 */
		private function get_settings_fields() {
			if ( isset( $this->get_pages()[ $this->get_current_page() ]['page_settings'] ) ) {
				return $this->get_pages()[ $this->get_current_page() ]['page_settings'];
			} else {
				return array();
			}
		}

		/**
		 * @return mixed|string
		 */
		private function get_menu_position() {
			if ( isset( $this->data['position'] ) ) {
				return $this->data['position'];
			} else {
				return 60;
			}
		}

		/**
		 * @return mixed|string
		 */
		private function get_menu_icon() {
			if ( isset( $this->data['menu_icon'] ) ) {
				return $this->data['menu_icon'];
			} else {
				return "dashicons-admin-tools";
			}
		}

		/**
		 * Return menu slug
		 *
		 * @return mixed|string
		 */
		function get_menu_slug() {
			if ( isset( $this->data['menu_slug'] ) ) {
				return $this->data['menu_slug'];
			} else {
				return "my-custom-settings";
			}
		}

		/**
		 * Get user capability
		 *
		 * @return mixed|string
		 */
		private function get_capability() {
			if ( isset( $this->data['capability'] ) ) {
				return $this->data['capability'];
			} else {
				return "manage_options";
			}
		}

		/**
		 * Return menu page title
		 *
		 * @return mixed|string
		 */
		private function get_menu_page_title() {
			if ( isset( $this->data['menu_page_title'] ) ) {
				return $this->data['menu_page_title'];
			} else {
				return "My Custom Menu";
			}
		}

		/**
		 * Return menu name
		 *
		 * @return mixed|string
		 */
		private function get_menu_name() {
			if ( isset( $this->data['menu_name'] ) ) {
				return $this->data['menu_name'];
			} else {
				return "Menu Name";
			}
		}

		/**
		 * Return menu title
		 *
		 * @return mixed|string
		 */
		private function get_menu_title() {
			if ( isset( $this->data['menu_title'] ) ) {
				return $this->data['menu_title'];
			} else {
				return "Menu Title";
			}
		}

		/**
		 * Return menu page title
		 *
		 * @return mixed|string
		 */
		private function get_page_title() {
			if ( isset( $this->data['page_title'] ) ) {
				return $this->data['page_title'];
			} else {
				return "Page Title";
			}
		}

		/**
		 * Check whether to add in WordPress Admin menu or not
		 *
		 * @return bool
		 */
		private function add_in_menu() {
			if ( isset( $this->data['add_in_menu'] ) && $this->data['add_in_menu'] ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Return parent menu slug
		 *
		 * @return mixed|string
		 */
		function get_parent_slug() {
			if ( isset( $this->data['parent_slug'] ) && $this->data['parent_slug'] ) {
				return $this->data['parent_slug'];
			} else {
				return "";
			}
		}

		/**
		 * Return disabled notice
		 *
		 * @return mixed|string
		 */
		function get_disabled_notice() {
			if ( isset( $this->data['disabled_notice'] ) && $this->data['disabled_notice'] ) {
				return $this->data['disabled_notice'];
			} else {
				return "";
			}
		}

		/**
		 * Return Option Value for Given Option ID
		 *
		 * @param bool $option_id
		 * @param string|mixed $default
		 *
		 * @return bool|mixed|void
		 */
		function get_option_value( $option_id = false, $default = '' ) {

			if ( ! $option_id || empty( $option_id ) ) {
				return false;
			}

			$option = array();
			foreach ( $this->get_options() as $__option ) {
				if ( ! isset( $__option['id'] ) || $__option['id'] != $option_id ) {
					continue;
				}
				$option = $__option;
			}

			// Check from DB
			$option_value = get_option( $option_id, '' );

			// Check from given value
			if ( empty( $option_value ) ) {
				$option_value = isset( $option['value'] ) ? $option['value'] : '';
			}

			// Check from default value
			if ( empty( $option_value ) ) {
				$option_value = isset( $option['default'] ) ? $option['default'] : '';
			}

			// Set given Default value
			if ( empty( $option_value ) ) {
				$option_value = $default;
			}

			return apply_filters( 'pb_settings_option_value', $option_value, $option_id, $option );
		}

		private function render_settings_page() {
			$logo_url = PBDA_PLUGIN_URL . 'assets/images/pb.png';
		}
	}
}