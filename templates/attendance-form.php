<?php
/**
 * Template - Attendance form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $pbda, $current_user;

if ( isset( $_POST['pbda_nonce_value'] ) && wp_verify_nonce( $_POST['pbda_nonce_value'], 'pbda_nonce' ) ) {

	$response = pbda_insert_attendance();

	if ( is_wp_error( $response ) ) {
		printf( '<p class="pbda-notice pbda-error">%s</p>', $response->get_error_message() );
	} else {
		printf( '<p class="pbda-notice pbda-success">%s</p>', $response );
	}
}


?>

<form class="pbda-form" action="" method="post">

    <div class="form-input">
        <label for=""><?php esc_html_e( 'Display Name', 'daily-attendance' ); ?></label>
        <input type="text" required readonly value="<?php echo esc_attr( $current_user->display_name ); ?>">
    </div>

    <div class="form-input">
        <label for=""><?php esc_html_e( 'Current Date', 'daily-attendance' ); ?></label>
        <input type="text" required readonly
               value="<?php echo esc_attr( date( 'jS M, Y', current_time( 'timestamp' ) ) ); ?>">
    </div>

    <div class="form-input">
        <label for=""><?php esc_html_e( 'Current Time', 'daily-attendance' ); ?></label>
        <input type="text" required readonly
               value="<?php echo esc_attr( date( 'h:s A', current_time( 'timestamp' ) ) ); ?>">
    </div>

    <div class="form-submit">
		<?php wp_nonce_field( 'pbda_nonce', 'pbda_nonce_value' ); ?>
        <button class="pbda-button" type="submit"><?php esc_html_e( 'Save Attendance', 'daily-attendance' ); ?></button>
    </div>

</form>
