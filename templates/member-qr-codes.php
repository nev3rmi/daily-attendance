<?php if (!defined('ABSPATH')) exit; 

// Enqueue QR code script properly
wp_enqueue_script('qrcode-js', PBDA_PLUGIN_URL . 'assets/js/qrcode.min.js', array('jquery'), '1.0.0', true);

// Get example user data properly
$users = get_users(['fields' => ['ID', 'user_login']]);
$example_user = !empty($users) ? $users[0] : null;
$example_qr_data = '';

if ($example_user) {
    $example_qr_data = json_encode([
        'user_id' => $example_user->ID,
        'timestamp' => time(),
        'hash' => hash_hmac('sha256', $example_user->ID . time(), get_option('pbda_qr_secret'))
    ], JSON_PRETTY_PRINT);
}

// Add month selector and send button at the top
$current_month = date('Y-m');
?>
<div class="wrap">
    <h1><?php esc_html_e('View Members', 'daily-attendance'); ?></h1>
    
    <!-- Add email controls -->
    <div class="pbda-email-controls">
        <select id="month-select">
            <?php 
            // Get existing reports
            $reports = get_posts(array(
                'post_type' => 'da_reports',
                'posts_per_page' => -1,
                'orderby' => 'meta_value',
                'meta_key' => '_month',
                'order' => 'DESC'
            ));

            foreach($reports as $report) {
                $month = get_post_meta($report->ID, '_month', true);
                $date = DateTime::createFromFormat('Ym', $month);
                if ($date) {
                    $selected = ($month === date('Ym')) ? 'selected' : '';
                    echo sprintf(
                        '<option value="%s" data-report-id="%d" %s>%s</option>', 
                        esc_attr($month),
                        $report->ID,
                        $selected,
                        esc_html($date->format('F Y'))
                    );
                }
            }
            ?>
        </select>
        <button class="button button-primary" id="send-all-reports">
            <?php esc_html_e('Send Reports to All', 'daily-attendance'); ?>
        </button>
        <span id="email-status" style="display:none;"></span>
    </div>

    <div class="pbda-info-box">
        <h3><?php esc_html_e('API Information', 'daily-attendance'); ?></h3>
        <p><?php esc_html_e('Endpoint:', 'daily-attendance'); ?> <code><?php echo esc_html(get_rest_url(null, 'v1/attendances/submit')); ?></code></p>
        <p><?php esc_html_e('Method:', 'daily-attendance'); ?> <code>POST</code></p>
        
        <div class="api-method">
            <h4><?php esc_html_e('Method 1: Username/Password', 'daily-attendance'); ?></h4>
            <pre><code>{
    "userName": "john_doe",
    "passWord": "your_password"
}</code></pre>
        </div>

        <div class="api-method">
            <h4><?php esc_html_e('Method 2: QR Code Hash', 'daily-attendance'); ?></h4>
            <pre><code>{
    "user_id": <?php echo $example_user ? $example_user->ID : 1; ?>,
    "hash": "<?php echo $example_user ? hash_hmac('sha256', $example_user->ID, get_option('pbda_qr_secret')) : 'generated_hash'; ?>"
}</code></pre>
        </div>

        <div class="api-method">
            <h4><?php esc_html_e('API Usage Example:', 'daily-attendance'); ?></h4>
            <pre><code>// Using curl
curl -X POST <?php echo esc_url(get_rest_url(null, 'v1/attendances/submit')); ?> \
-H "Content-Type: application/json" \
-H "Accept: application/json" \
-d '{
    "userName": "john_doe",
    "passWord": "your_password"
}'

// Or using fetch API
fetch('<?php echo esc_url(get_rest_url(null, 'v1/attendances/submit')); ?>', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    body: JSON.stringify({
        userName: 'john_doe',
        passWord: 'your_password'
    })
}).then(r => r.json()).then(console.log);</code></pre>
        </div>

        <div class="api-response">
            <h4><?php esc_html_e('Response Format:', 'daily-attendance'); ?></h4>
            <pre><code>{
    "version": "V1",
    "success": true,
    "content": "Attendance marked successfully for John Doe"
}</code></pre>
        </div>
    </div>
    <div class="pbda-qr-grid">
        <?php 
        $users = get_users(['fields' => ['ID', 'user_login', 'user_email']]);
        foreach ($users as $user): ?>
            <div class="pbda-qr-item" data-user-id="<?php echo esc_attr($user->ID); ?>">
                <h3><?php echo esc_html($user->user_login); ?></h3>
                <div class="pbda-qr-code" id="qrcode-<?php echo esc_attr($user->ID); ?>"></div>
                <p><?php echo esc_html($user->user_email); ?></p>
                <button class="button send-report" data-user-id="<?php echo esc_attr($user->ID); ?>">
                    <?php esc_html_e('Send Report', 'daily-attendance'); ?>
                </button>
                <span class="report-status"></span>
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
.api-method, .api-response {
    margin: 20px 0;
}
.api-method h4, .api-response h4 {
    margin: 10px 0;
    color: #333;
}
pre {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 4px;
    overflow: auto;
    margin: 10px 0;
    border: 1px solid #ddd;
}
code {
    font-family: monospace;
    font-size: 13px;
    line-height: 1.4;
}

.pbda-email-controls {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
    display: flex;
    gap: 15px;
    align-items: center;
}

#month-select {
    padding: 5px 10px;
    min-width: 200px;
}

.report-status {
    display: block;
    margin-top: 10px;
    font-size: 0.9em;
}

.status-success {
    color: #4CAF50;
}

.status-error {
    color: #f44336;
}

#email-status {
    padding: 5px 10px;
    border-radius: 4px;
}

.email-status-success {
    background: #e8f5e9;
    color: #2e7d32;
}

.email-status-error {
    background: #ffebee;
    color: #c62828;
}
</style>

<script>
jQuery(document).ready(function($) {
    // ...existing QR code generation code...

    function sendReport(userId) {
        const month = $('#month-select').val();
        const $status = $(`.pbda-qr-item[data-user-id="${userId}"] .report-status`);
        
        $status.html('<?php esc_html_e('Sending...', 'daily-attendance'); ?>');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'send_attendance_report',
                nonce: '<?php echo wp_create_nonce('pbda_send_report'); ?>',
                user_id: userId,
                month: month
            },
            success: function(response) {
                if (response.success) {
                    $status.html(response.data).addClass('status-success').removeClass('status-error');
                } else {
                    $status.html(response.data).addClass('status-error').removeClass('status-success');
                }
                setTimeout(() => $status.html(''), 5000);
            },
            error: function() {
                $status.html('<?php esc_html_e('Failed to send report', 'daily-attendance'); ?>')
                    .addClass('status-error').removeClass('status-success');
                setTimeout(() => $status.html(''), 5000);
            }
        });
    }

    // Individual send buttons
    $('.send-report').click(function() {
        const userId = $(this).data('user-id');
        sendReport(userId);
    });

    // Send all button
    $('#send-all-reports').click(function() {
        const $status = $('#email-status');
        const $button = $(this);
        
        $button.prop('disabled', true);
        $status.html('<?php esc_html_e('Sending reports...', 'daily-attendance'); ?>')
            .show()
            .removeClass('email-status-success email-status-error');

        let sent = 0;
        const total = $('.pbda-qr-item').length;

        $('.pbda-qr-item').each(function(index) {
            const userId = $(this).data('user-id');
            setTimeout(() => {
                sendReport(userId);
                sent++;
                if (sent === total) {
                    $button.prop('disabled', false);
                    $status.html('<?php esc_html_e('All reports sent!', 'daily-attendance'); ?>')
                        .addClass('email-status-success');
                    setTimeout(() => $status.fadeOut(), 5000);
                }
            }, index * 1000); // Stagger requests 1 second apart
        });
    });
});
</script>
