<?php
if (!defined('ABSPATH')) exit;

$token = isset($_GET['attendance']) ? sanitize_text_field($_GET['attendance']) : '';
$auto_submit = isset($_GET['auto_submit']) && $_GET['auto_submit'] === '1';

if (isset($_POST['submit_attendance']) && check_admin_referer('submit_attendance')) {
    $response = pbda_insert_attendance();
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
    } else {
        $success_message = $response;
    }
}

?>
<div class="pbda-submit-form">
    <?php if (isset($error_message)): ?>
        <div class="pbda-notice pbda-error"><?php echo esc_html($error_message); ?></div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
        <div class="pbda-notice pbda-success">
            <?php echo esc_html($success_message); ?>
            <script>
                setTimeout(function() {
                    window.close();
                    window.location.href = '<?php echo esc_url(home_url()); ?>';
                }, 2000);
            </script>
        </div>
    <?php else: ?>
        <?php if (is_user_logged_in()): ?>
            <form method="post" action="" id="attendance-form">
                <?php wp_nonce_field('submit_attendance'); ?>
                <input type="hidden" name="submit_attendance" value="1">
                <button type="submit" class="button"><?php esc_html_e('Submit Attendance', 'daily-attendance'); ?></button>
            </form>
            <?php if ($auto_submit): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Show a brief message
                        var msg = document.createElement('div');
                        msg.className = 'auto-submit-message';
                        msg.textContent = '<?php esc_html_e('Submitting attendance...', 'daily-attendance'); ?>';
                        document.getElementById('attendance-form').appendChild(msg);
                        
                        // Submit the form after a short delay
                        setTimeout(function() {
                            document.getElementById('attendance-form').submit();
                        }, 500);
                    });
                </script>
                <style>
                    .auto-submit-message {
                        margin-top: 10px;
                        color: #666;
                        font-style: italic;
                    }
                </style>
            <?php endif; ?>
        <?php else: ?>
            <p><?php 
                printf(
                    esc_html__('Please %s to submit attendance', 'daily-attendance'),
                    sprintf(
                        '<a href="%s">%s</a>',
                        esc_url(wp_login_url(add_query_arg(array(
                            'auto_submit' => '1',
                            'attendance' => $token
                        ), get_permalink()))),
                        esc_html__('login', 'daily-attendance')
                    )
                );
            ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.pbda-submit-form {
    max-width: 400px;
    margin: 20px auto;
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}
.pbda-notice {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}
.pbda-success {
    background: #e7f7ed;
    color: #4CAF50;
}
.pbda-error {
    background: #ffebee;
    color: #f44336;
}
button.button {
    background: #4CAF50;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
</style>
