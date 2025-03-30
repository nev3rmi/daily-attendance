<?php if (!defined('ABSPATH')) exit;

// Old QR code setup (unchanged)
$attendance_page = get_option('pbda_attendance_page');
if (!$attendance_page) {
    $page_id = wp_insert_post(array(
        'post_title'   => __('Attendance Submission', 'daily-attendance'),
        'post_content' => '[attendance_submit]',
        'post_status'  => 'publish',
        'post_type'    => 'page'
    ));
    update_option('pbda_attendance_page', $page_id);
    $attendance_page = $page_id;
}
$submission_url = esc_url_raw(add_query_arg(array(
    'attendance'  => wp_create_nonce('pbda_attendance_' . date('Y-m-d')),
    'auto_submit' => '1'
), get_permalink($attendance_page)));

// Enqueue required scripts
wp_enqueue_script('qrcode-js', PBDA_PLUGIN_URL . 'assets/js/qrcode.min.js', array(), '1.0.0', true);
wp_enqueue_script('html5-qrcode', 'https://unpkg.com/html5-qrcode', [], null, true);
wp_enqueue_script('jquery');

// Generate user QR data for old QR code (unchanged)
$qr_data = $submission_url;

?>
<div class="pbda-qr-container">
    <div class="pbda-qr-left">
        <h2><?php esc_html_e('Scan a QR Code', 'daily-attendance'); ?></h2>
        <div id="reader" style="width: 300px; height: 300px; margin: 0 auto;"></div>
        <p class="qr-instructions"><?php esc_html_e('Point your camera at a QR code to mark attendance.', 'daily-attendance'); ?></p>
    </div>
    <div class="pbda-qr-right">
        <h2><?php esc_html_e('Your Old QR Code', 'daily-attendance'); ?></h2>
        <div id="qrcode"></div>
        <p class="qr-date"><?php echo esc_html(date('jS M, Y')); ?></p>
        <p class="qr-instructions"><?php esc_html_e('Please scan this QR code using your mobile device', 'daily-attendance'); ?></p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const attendanceEndpoint = '<?php echo esc_url(rest_url('v1/attendances/submit')); ?>';

    // Initialize new QR scanner (left side)
    function initializeQrScanner() {
        if (typeof Html5Qrcode === 'undefined') {
            console.error('Html5Qrcode is not defined. Retrying...');
            setTimeout(initializeQrScanner, 500);
            return;
        }
        function onScanSuccess(decodedText) {
            try {
                const qrData = JSON.parse(decodedText);
                if (qrData.user_id && qrData.hash) {
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
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            onScanSuccess,
            onScanFailure
        ).catch(err => {
            console.error('Failed to start QR Code scanner:', err);
            alert('<?php esc_html_e('Failed to start camera. Please check permissions.', 'daily-attendance'); ?>');
        });
    }
    initializeQrScanner();

    // Generate old QR code (right side) using your old code style
    try {
        new QRCode(document.getElementById("qrcode"), {
            text: <?php echo json_encode($qr_data); ?>,
            width: 300,
            height: 300,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.L,
            margin: 4
        });
    } catch (e) {
        console.error('QR Code generation failed:', e);
        document.getElementById("qrcode").innerHTML = '<?php esc_html_e('Error generating QR code', 'daily-attendance'); ?>';
    }
});
</script>

<style>
/* ...existing styles... */
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
#qrcode {
    display: inline-block;
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.qr-date {
    font-size: 1.2em;
    color: #666;
    margin: 15px 0;
}
.qr-instructions {
    color: #777;
    font-size: 0.9em;
    margin-top: 15px;
}
</style>
