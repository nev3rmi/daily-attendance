<?php
if (!defined('ABSPATH')) exit;

// Enqueue required scripts
wp_enqueue_script('qrcode-js', PBDA_PLUGIN_URL . 'assets/js/qrcode.min.js', array(), '1.0.0', true);
wp_enqueue_script('html5-qrcode', 'https://unpkg.com/html5-qrcode/minified/html5-qrcode.min.js', [], null, true);
wp_enqueue_script('jquery');

// Generate the user's QR code
$current_user_id = get_current_user_id();
$qr_data = json_encode([
    'user_id' => $current_user_id,
    'hash' => hash_hmac('sha256', $current_user_id, get_option('pbda_qr_secret'))
]);
?>

<div class="pbda-qr-container">
    <div class="pbda-qr-left">
        <h2><?php esc_html_e('Scan QR Code', 'daily-attendance'); ?></h2>
        <div id="reader" style="width: 300px; height: 300px; margin: 0 auto;"></div>
        <p class="qr-instructions"><?php esc_html_e('Point your camera at a QR code to scan.', 'daily-attendance'); ?></p>
    </div>
    <div class="pbda-qr-right">
        <h2><?php esc_html_e('Your QR Code', 'daily-attendance'); ?></h2>
        <div class="pbda-qr-code">
            <img src="https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=<?php echo urlencode($qr_data); ?>" 
                 alt="<?php esc_attr_e('Your Attendance QR Code', 'daily-attendance'); ?>">
        </div>
        <p class="qr-instructions"><?php esc_html_e('This is your personal QR code for attendance.', 'daily-attendance'); ?></p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const attendanceEndpoint = '<?php echo esc_url(rest_url('v1/attendances/submit')); ?>';

    function onScanSuccess(decodedText) {
        try {
            const qrData = JSON.parse(decodedText);
            if (qrData.user_id && qrData.hash) {
                // Submit attendance via AJAX
                $.ajax({
                    url: attendanceEndpoint,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(qrData),
                    success: function(response) {
                        if (response.success) {
                            alert('<?php esc_html_e('Attendance marked successfully!', 'daily-attendance'); ?>');
                        } else {
                            alert('<?php esc_html_e('Failed to mark attendance:', 'daily-attendance'); ?> ' + response.content);
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e('An error occurred while submitting attendance.', 'daily-attendance'); ?>');
                    }
                });
            } else {
                alert('<?php esc_html_e('Invalid QR code data.', 'daily-attendance'); ?>');
            }
        } catch (e) {
            alert('<?php esc_html_e('Failed to parse QR code data.', 'daily-attendance'); ?>');
        }
    }

    function onScanFailure(error) {
        console.warn('QR Code scan failed:', error);
    }

    const html5QrCode = new Html5Qrcode("reader");
    html5QrCode.start(
        { facingMode: "environment" }, // Use the back camera
        {
            fps: 10, // Scans per second
            qrbox: { width: 250, height: 250 } // Scanning area
        },
        onScanSuccess,
        onScanFailure
    ).catch(err => {
        console.error('Failed to start QR Code scanner:', err);
        alert('<?php esc_html_e('Failed to start camera. Please check permissions.', 'daily-attendance'); ?>');
    });
});
</script>

<style>
.pbda-qr-container {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    padding: 30px;
    max-width: 900px;
    margin: 0 auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.pbda-qr-left, .pbda-qr-right {
    flex: 1;
    text-align: center;
}
.pbda-qr-left h2, .pbda-qr-right h2 {
    color: #333;
    margin-bottom: 20px;
}
#reader {
    display: inline-block;
    margin: 20px 0;
    background: #f5f5f5;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.pbda-qr-code img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.qr-instructions {
    color: #777;
    font-size: 0.9em;
    margin-top: 15px;
}
</style>
