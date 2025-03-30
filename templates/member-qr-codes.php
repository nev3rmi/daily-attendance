<?php
if (!defined('ABSPATH')) exit;

// Get users and enqueue required scripts
$users = get_users(['fields' => ['ID', 'user_login', 'user_email']]);
wp_enqueue_script('qrcode-js', PBDA_PLUGIN_URL . 'assets/js/qrcode.min.js', array('jquery'), '1.0.0', true);
?>

<div class="wrap">
    <h1><?php esc_html_e('View Members', 'daily-attendance'); ?></h1>
    <div class="pbda-qr-grid">
        <?php foreach ($users as $user): ?>
            <div class="pbda-qr-item">
                <h3><?php echo esc_html($user->user_login); ?></h3>
                <div class="pbda-qr-code" id="qrcode-<?php echo esc_attr($user->ID); ?>"></div>
                <p><?php echo esc_html($user->user_email); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    <?php foreach ($users as $user): ?>
        new QRCode(document.getElementById("qrcode-<?php echo esc_js($user->ID); ?>"), {
            text: <?php echo json_encode($this->generate_qr_data($user->ID)); ?>,
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.L,
            margin: 4
        });
    <?php endforeach; ?>
});
</script>
