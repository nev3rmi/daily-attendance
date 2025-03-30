<?php
/**
 * Class Functions
 *
 * @author Pluginbazar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access

if ( ! class_exists( 'PBDA_Functions' ) ) {
	/**
	 * Class PBDA_Functions
	 */
	class PBDA_Functions {

		/**
		 * Get list of WordPress users
		 * 
		 * @return WP_User[]
		 */
		public function get_users_list(): array {
			return get_users();
		}

		/**
		 * Return Post Meta Value
		 *
		 * @param string|bool $meta_key
		 * @param int|bool $post_id
		 * @param mixed $default
		 * @param bool $single
		 *
		 * @return mixed
		 */
		public function get_meta(
			string|bool $meta_key = false,
			int|bool $post_id = false,
			mixed $default = '',
			bool $single = true
		): mixed {

			if ( ! $meta_key ) {
				return '';
			}

			$post_id    = ! $post_id ? get_the_ID() : $post_id;
			$meta_value = get_post_meta( $post_id, $meta_key, $single );
			$meta_value = empty( $meta_value ) ? $default : $meta_value;

			return apply_filters( 'eem_filters_get_meta', $meta_value, $meta_key, $post_id, $default );
		}

		/**
		 * Return option value
		 *
		 * @param string $option_key
		 * @param string $default_val
		 *
		 * @return mixed
		 */
		public function get_option(
			string $option_key = '',
			string $default_val = ''
		): mixed {

			if ( empty( $option_key ) ) {
				return '';
			}

			$option_val = get_option( $option_key, $default_val );
			$option_val = empty( $option_val ) ? $default_val : $option_val;

			return apply_filters( 'pbda_filters_option_' . $option_key, $option_val );
		}

		/**
		 * Return PB_Settings class
		 *
		 * @param array $args
		 *
		 * @return PB_Settings
		 */
		public function PB_Settings(array $args = []): PB_Settings {

			return new PB_Settings( $args );
		}
	}
}

global $pbda;

$pbda = new PBDA_Functions();