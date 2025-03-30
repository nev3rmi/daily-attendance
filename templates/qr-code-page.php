<?php
if (!defined('ABSPATH')) exit;

// Create attendance submission URL using the attendance form page
$attendance_page = get_option('pbda_attendance_page');
if (!$attendance_page) {
    // Create a new page with the shortcode if it doesn't exist
    $page_id = wp_insert_post(array(
        'post_title' => __('Attendance Submission', 'daily-attendance'),
        'post_content' => '[attendance_submit]',
        'post_status' => 'publish',
        'post_type' => 'page'
    ));
    update_option('pbda_attendance_page', $page_id);
    $attendance_page = $page_id;
}

// Fix URL encoding
$submission_url = esc_url_raw(add_query_arg(array(
    'attendance' => wp_create_nonce('pbda_attendance_' . date('Y-m-d')),
    'auto_submit' => '1'  // Add auto_submit parameter
), get_permalink($attendance_page)));

wp_enqueue_script('qrcode-js', PBDA_PLUGIN_URL . 'assets/js/qrcode.min.js', array(), '1.0.0', true);
?>

<div class="pbda-qr-container">
    <h2><?php esc_html_e('Scan QR Code for Attendance', 'daily-attendance'); ?></h2>
    <div id="qrcode"></div>
    <p class="qr-date"><?php echo esc_html(date('jS M, Y')); ?></p>
    <p class="qr-instructions"><?php esc_html_e('Please scan this QR code using your mobile device', 'daily-attendance'); ?></p>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            try {
                new QRCode(document.getElementById("qrcode"), {
                    text: <?php echo json_encode($submission_url); ?>, // Use json_encode instead of esc_js
                    width: 300,  // Increased size
                    height: 300, // Increased size
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.L, // Changed to L for better scan reliability
                    margin: 4 // Added margin
                });
            } catch (e) {
                console.error('QR Code generation failed:', e);
                document.getElementById("qrcode").innerHTML = '<?php esc_html_e('Error generating QR code', 'daily-attendance'); ?>';
            }
        });
    </script>
</div>

<style>
    .pbda-qr-container {
        text-align: center;
        padding: 30px;
        max-width: 600px;
        margin: 0 auto;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .pbda-qr-container h2 {
        color: #333;
        margin-bottom: 20px;
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
    #qrcode img {
        padding: 10px;
        background: #fff;
        display: inline-block !important;
    }
</style>
