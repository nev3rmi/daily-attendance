<?php if (!defined('ABSPATH')) exit; 

// Enqueue QR code script properly
wp_enqueue_script('qrcode-js', PBDA_PLUGIN_URL . 'assets/js/qrcode.min.js', array('jquery'), '1.0.0', true);
?>

<div class="wrap">
    <h1><?php esc_html_e('View Members', 'daily-attendance'); ?></h1>
    <div class="pbda-info-box">
        <h3><?php esc_html_e('API Information', 'daily-attendance'); ?></h3>
        <p><?php esc_html_e('Endpoint:', 'daily-attendance'); ?> <code><?php echo esc_html(get_rest_url(null, 'v1/attendances/submit')); ?></code></p>
        <p><?php esc_html_e('Authentication Methods:', 'daily-attendance'); ?></p>
        <ul>
            <li><?php esc_html_e('Method 1: Username/Password', 'daily-attendance'); ?>
                <br><code>userName, passWord</code>
            </li>
            <li><?php esc_html_e('Method 2: QR Code Hash', 'daily-attendance'); ?>
                <br><code>hash, user_id, timestamp</code>
            </li>
        </ul>
    </div>
    <div class="pbda-qr-grid">
        <?php 
        $users = get_users(['fields' => ['ID', 'user_login', 'user_email']]);
        foreach ($users as $user): ?>
            <div class="pbda-qr-item">
                <h3><?php echo esc_html($user->user_login); ?></h3>
                <div class="pbda-qr-code" id="qrcode-<?php echo esc_attr($user->ID); ?>"></div>
                <p><?php echo esc_html($user->user_email); ?></p>
            </div>
            <script>
                jQuery(function($) {
                    new QRCode(document.getElementById("qrcode-<?php echo esc_js($user->ID); ?>"), {
                        text: <?php echo json_encode($this->generate_qr_data($user->ID)); ?>,
                        width: 200,
                        height: 200,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.L,
                        margin: 4
                    });
                });
            </script>
        <?php endforeach; ?>
    </div>
</div>

<style>
.pbda-info-box {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.pbda-info-box code {
    background: #f5f5f5;
    padding: 3px 6px;
    border-radius: 4px;
}
</style>
