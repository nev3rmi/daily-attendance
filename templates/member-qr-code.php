<?php
if (!defined('ABSPATH')) exit;

/**
 * Template for displaying individual member QR codes
 * 
 * @var WP_User $user The user object
 * @var string $qr_data The QR code data
 */
?>
<div class="pbda-qr-item">
    <h3><?php echo esc_html($user->user_login); ?></h3>
    <div class="pbda-qr-code" id="qrcode-<?php echo esc_attr($user->ID); ?>"></div>
    <p><?php echo esc_html($user->user_email); ?></p>
</div>
<script>
    new QRCode(document.getElementById("qrcode-<?php echo esc_attr($user->ID); ?>"), {
        text: <?php echo json_encode($qr_data); ?>,
        width: 200,
        height: 200,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.L,
        margin: 4
    });
</script>
