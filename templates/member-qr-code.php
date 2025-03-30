<?php if (!defined('ABSPATH')) exit; ?>

<div class="pbda-qr-item">
    <h3><?php echo esc_html($user->user_login); ?></h3>
    <div class="pbda-qr-code" id="qrcode-<?php echo esc_attr($user->ID); ?>"></div>
    <p><?php echo esc_html($user->user_email); ?></p>
</div>
<?php 
wp_add_inline_script('qrcode-js', sprintf('
    new QRCode(document.getElementById("qrcode-%1$s"), {
        text: %2$s,
        width: 200,
        height: 200,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.L,
        margin: 4
    });',
    esc_js($user->ID),
    json_encode($qr_data)
));
?>
