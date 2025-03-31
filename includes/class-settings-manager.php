<?php
class SettingsManager {
    private $settings_page = 'daily-attendance-settings';
    private $option_group = 'pbda_settings';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        $this->tabs = array(
            'email' => __('Email Templates', 'daily-attendance'),
            'api' => __('API Documentation', 'daily-attendance'),
            'notification' => __('Notifications', 'daily-attendance')
        );
    }

    public function add_settings_page() {
        // Change priority to 9999 to make it appear last
        add_submenu_page(
            'edit.php?post_type=da_reports',
            'Settings',
            'Settings',
            'manage_options',
            $this->settings_page,
            array($this, 'render_settings_page'),
            9999  // Very high priority to ensure it's last
        );
    }

    public function register_settings() {
        register_setting($this->option_group, 'pbda_email_template');
        register_setting($this->option_group, 'pbda_email_subject');
        
        add_settings_section(
            'pbda_email_section',
            'Email Template Settings',
            array($this, 'render_section_info'),
            $this->settings_page
        );

        add_settings_field(
            'pbda_email_subject',
            'Email Subject',
            array($this, 'render_subject_field'),
            $this->settings_page,
            'pbda_email_section'
        );

        add_settings_field(
            'pbda_email_template',
            'Email Body Template',
            array($this, 'render_template_field'),
            $this->settings_page,
            'pbda_email_section'
        );
    }

    public function render_subject_field() {
        $subject = get_option('pbda_email_subject', $this->get_default_subject());
        ?>
        <input type="text" 
               name="pbda_email_subject" 
               id="pbda_email_subject" 
               value="<?php echo esc_attr($subject); ?>" 
               class="large-text">
        <p class="description">Available shortcodes: [title], [username], [date]</p>
        <?php
    }

    private function get_default_subject() {
        return 'Attendance Report for [title]';
    }

    public function render_section_info() {
        $shortcodes = array(
            'subject' => array(
                '[title]' => 'Report title (e.g., "March 2024")',
                '[username]' => 'User\'s display name',
                '[date]' => 'Current date'
            ),
            'body' => array(
                '[title]' => 'Report title (e.g., "March 2024")',
                '[username]' => 'User\'s display name',
                '[email]' => 'User\'s email address',
                '[attendance_table]' => 'Attendance data table',
                '[total_days]' => 'Total days present',
                '[date]' => 'Current date'
            )
        );

        echo '<div class="shortcode-info">';
        echo '<div class="shortcode-subject">';
        echo '<p><strong>Available shortcodes for Subject:</strong></p>';
        echo '<ul class="shortcode-list">';
        foreach ($shortcodes['subject'] as $code => $desc) {
            printf('<li><code>%s</code> - %s</li>', esc_html($code), esc_html($desc));
        }
        echo '</ul></div>';

        echo '<div class="shortcode-body">';
        echo '<p><strong>Available shortcodes for Body:</strong></p>';
        echo '<ul class="shortcode-list">';
        foreach ($shortcodes['body'] as $code => $desc) {
            printf('<li><code>%s</code> - %s</li>', esc_html($code), esc_html($desc));
        }
        echo '</ul></div></div>';
    }

    public function render_template_field() {
        $template = get_option('pbda_email_template', $this->get_default_template());
        wp_editor($template, 'pbda_email_template', array(
            'textarea_name' => 'pbda_email_template',
            'textarea_rows' => 15,
            'media_buttons' => true,
            'teeny' => false,
            'tinymce' => true
        ));
    }

    private function get_default_template() {
        return '
<h2>Hello [username],</h2>

<p>Here is your attendance report for [title]:</p>

[attendance_table]

<p style="color: #666; margin-top: 15px;">
    Total Days Present: [total_days]
</p>

<p style="color: #888; font-size: 0.9em; margin-top: 20px;">
    Generated on [date]
</p>';
    }

    public function render_settings_page() {
        // Add priority to ensure it's loaded after other admin scripts
        wp_enqueue_style('dashicons');
        
        if (!current_user_can('manage_options')) return;
        
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'email';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <?php foreach ($this->tabs as $tab_key => $tab_name): ?>
                    <a href="?post_type=da_reports&page=<?php echo $this->settings_page; ?>&tab=<?php echo $tab_key; ?>" 
                       class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_name); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($current_tab === 'email'): ?>
                <?php $this->render_email_settings(); ?>
            <?php elseif ($current_tab === 'api'): ?>
                <?php $this->render_api_documentation(); ?>
            <?php elseif ($current_tab === 'notification'): ?>
                <?php $this->render_notification_settings(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_email_settings() {
        ?>
        <div class="pbda-settings-layout">
            <!-- Left side: Edit forms -->
            <div class="pbda-edit-section">
                <form action="options.php" method="post">
                    <?php settings_fields($this->option_group); ?>
                    
                    <!-- Subject Section -->
                    <div class="template-section">
                        <div class="template-edit">
                            <h3>Email Subject</h3>
                            <input type="text" 
                                name="pbda_email_subject" 
                                id="pbda_email_subject" 
                                value="<?php echo esc_attr(get_option('pbda_email_subject', $this->get_default_subject())); ?>" 
                                class="large-text">
                            
                            <div class="shortcode-info subject-shortcodes">
                                <p><strong>Available shortcodes for Subject:</strong></p>
                                <ul>
                                    <li><code>[title]</code> - Report title</li>
                                    <li><code>[username]</code> - User's display name</li>
                                    <li><code>[date]</code> - Current date</li>
                                </ul>
                            </div>
                        </div>
                        <div class="template-preview">
                            <h3>Subject Preview</h3>
                            <div id="template-preview-subject">
                                <strong>Subject:</strong> <span></span>
                            </div>
                        </div>
                    </div>

                    <!-- Body Section -->
                    <div class="template-section">
                        <div class="template-edit">
                            <h3>Email Body Template</h3>
                            <?php wp_editor(get_option('pbda_email_template', $this->get_default_template()), 'pbda_email_template', array(
                                'textarea_name' => 'pbda_email_template',
                                'textarea_rows' => 20,
                                'media_buttons' => true,
                                'teeny' => false,
                                'tinymce' => true
                            )); ?>
                            
                            <div class="shortcode-info body-shortcodes">
                                <p><strong>Available shortcodes for Body:</strong></p>
                                <ul>
                                    <li><code>[title]</code> - Report title</li>
                                    <li><code>[username]</code> - User's display name</li>
                                    <li><code>[email]</code> - User's email address</li>
                                    <li><code>[attendance_table]</code> - Attendance data table</li>
                                    <li><code>[total_days]</code> - Total days present</li>
                                    <li><code>[date]</code> - Current date</li>
                                </ul>
                            </div>
                        </div>
                        <div class="template-preview">
                            <h3>Body Preview</h3>
                            <div id="template-preview-body"></div>
                        </div>
                    </div>

                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>
        </div>

        <!-- Update Preview Button -->
        <button type="button" id="update-preview" class="button button-primary">
            <span class="dashicons dashicons-update"></span> 
            <span>Update Preview</span>
        </button>

        <style>
        .template-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .template-edit, .template-preview {
            min-width: 400px;
        }
        .shortcode-info {
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-left: 4px solid #2271b1;
        }
        .shortcode-info ul {
            margin: 5px 0 0 20px;
        }
        .shortcode-info li {
            margin: 5px 0;
        }
        #template-preview-subject {
            margin: 10px 0;
            padding: 15px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        #template-preview-body {
            margin: 10px 0;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-height: 400px;
        }
        .template-preview h3 {
            color: #666;
        }
        #update-preview {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 100;
            padding: 6px 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            height: auto;
        }
        #update-preview .dashicons {
            width: 16px;
            height: 16px;
            font-size: 16px;
            display: flex;
            align-items: center;
            margin: 0;
        }
        #update-preview span {
            line-height: 1.4;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            function getExampleTable() {
                return `
                    <table style="border-collapse: collapse; width: 100%;">
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 8px; border: 1px solid #ddd;">Date</th>
                            <th style="padding: 8px; border: 1px solid #ddd;">Day</th>
                            <th style="padding: 8px; border: 1px solid #ddd;">Time</th>
                        </tr>
                        <tr>
                            <td style="padding: 8px; border: 1px solid #ddd;">March 1, 2024</td>
                            <td style="padding: 8px; border: 1px solid #ddd;">Friday</td>
                            <td style="padding: 8px; border: 1px solid #ddd;">09:00 AM</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; border: 1px solid #ddd;">March 2, 2024</td>
                            <td style="padding: 8px; border: 1px solid #ddd;">Saturday</td>
                            <td style="padding: 8px; border: 1px solid #ddd;">08:55 AM</td>
                        </tr>
                    </table>`;
            }

            function updatePreview() {
                // Add loading indicator
                $('#template-preview-body').html('<div class="updating">Updating preview...</div>');
                
                var subject = $('#pbda_email_subject').val() || '<?php echo esc_js($this->get_default_subject()); ?>';
                var bodyContent = '';
                
                // Get content from TinyMCE or textarea
                if (tinymce.get('pbda_email_template')) {
                    bodyContent = tinymce.get('pbda_email_template').getContent();
                } else {
                    bodyContent = $('#pbda_email_template').val();
                }

                if (!bodyContent) {
                    bodyContent = '<?php echo esc_js($this->get_default_template()); ?>';
                }

                // Replace shortcodes
                subject = subject.replace(/\[username\]/g, 'John Doe')
                               .replace(/\[title\]/g, 'March 2024')
                               .replace(/\[date\]/g, new Date().toLocaleDateString());
                
                bodyContent = bodyContent.replace(/\[username\]/g, 'John Doe')
                                       .replace(/\[title\]/g, 'March 2024')
                                       .replace(/\[email\]/g, 'john@example.com')
                                       .replace(/\[date\]/g, new Date().toLocaleDateString())
                                       .replace(/\[total_days\]/g, '15')
                                       .replace(/\[attendance_table\]/g, getExampleTable());
                
                $('#template-preview-subject span').text(subject);
                $('#template-preview-body').html(bodyContent);
            }

            // Manual preview update button
            $('#update-preview').on('click', function() {
                updatePreview();
                $(this).find('.dashicons').addClass('spin');
                setTimeout(() => {
                    $(this).find('.dashicons').removeClass('spin');
                }, 500);
            });

            // Initial preview
            setTimeout(updatePreview, 1000);

            // Add spin animation
            $('<style>')
                .text('@keyframes spin { 100% { transform: rotate(360deg); } }' +
                      '.spin { animation: spin 0.5s linear; }' +
                      '.updating { padding: 20px; text-align: center; color: #666; }')
                .appendTo('head');
        });
        </script>
        <?php
    }

    private function render_api_documentation() {
        $example_user = reset(get_users(['fields' => ['ID', 'user_login']]));
        $example_hash = $example_user ? hash_hmac('sha256', $example_user->ID, get_option('pbda_qr_secret')) : 'generated_hash';
        $api_key = get_option('pbda_api_key', '');
        if (empty($api_key)) {
            $api_key = wp_generate_password(32, false);
            update_option('pbda_api_key', $api_key);
        }

        // Add copy functionality before rendering documentation
        ?>
        <script>
        function copyToClipboard(element) {
            const pre = element.closest('.code-block').querySelector('pre');
            const text = pre.textContent;
            navigator.clipboard.writeText(text).then(() => {
                // Show copied tooltip
                element.classList.add('copied');
                setTimeout(() => {
                    element.classList.remove('copied');
                }, 2000);
            });
        }
        </script>

        <style>
        .code-block {
            position: relative;
        }
        .copy-button {
            position: absolute;
            top: 5px;
            right: 5px;
            padding: 5px 10px;
            background: #2271b1;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        .copy-button:hover {
            opacity: 1;
        }
        .copy-button.copied:after {
            content: "Copied!";
            position: absolute;
            bottom: 100%;
            right: 0;
            background: #4CAF50;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-bottom: 5px;
        }
        .curl-example {
            background: #272822;
            color: #a6e22e !important;
        }
        .response-example {
            background: #272822;
            color: #66d9ef !important;
        }
        </style>

        <div class="api-documentation">
            <h2>API Documentation</h2>
            
            <!-- Authentication Section -->
            <div class="api-section auth-section">
                <h3>Authentication</h3>
                <p>Your API Key: <code><?php echo esc_html($api_key); ?></code></p>
                <button id="regenerate-api-key" class="button button-secondary">
                    <?php esc_html_e('Regenerate API Key', 'daily-attendance'); ?>
                </button>
                <div class="api-auth-methods">
                    <p><strong>Authentication Methods:</strong></p>
                    <ol>
                        <li>API Key (preferred): Send via header <code>X-API-Key: your_api_key</code></li>
                        <li>Admin Access: WordPress admin with manage_options capability</li>
                    </ol>
                </div>
            </div>

            <!-- Public Endpoints -->
            <div class="api-section">
                <h3>Public Endpoints (No Auth Required)</h3>
                
                <div class="api-endpoint">
                    <h4>1. Submit Attendance</h4>
                    <p><strong>Endpoint:</strong> <code>POST <?php echo esc_html(rest_url('v1/qr-attendance/submit')); ?></code></p>
                    <p><strong>Parameters:</strong></p>
                    <pre><code class="language-json">{
    "userName": "string",  // Optional: For login method
    "passWord": "string",  // Optional: For login method
    "hash": "string",      // Optional: For QR code method
    "user_id": "integer"   // Optional: For QR code method
}</code></pre>
                    <p><strong>Response:</strong></p>
                    <pre><code class="language-json">{
    "version": "V1",
    "success": boolean,
    "content": string
}</code></pre>
                </div>

                <div class="api-endpoint">
                    <h4>2. Get Public Reports</h4>
                    <p><strong>Endpoint:</strong> <code>GET <?php echo esc_html(rest_url('v1/qr-attendance/reports-public')); ?></code></p>
                    <p><strong>Response:</strong></p>
                    <pre><code class="language-json">{
    "success": true,
    "data": [{
        "id": integer,
        "title": string,
        "month": string,
        "formatted_date": string
    }]
}</code></pre>
                </div>
            </div>

            <!-- Protected Endpoints -->
            <div class="api-section">
                <h3>Protected Endpoints (API Key or Admin Required)</h3>
                
                <div class="api-endpoint">
                    <h4>1. Get Full Reports</h4>
                    <p><strong>Endpoint:</strong> <code>GET <?php echo esc_html(rest_url('v1/qr-attendance/reports')); ?></code></p>
                    <p><strong>Headers:</strong> <code>X-API-Key: your_api_key</code></p>
                    <p><strong>Response:</strong></p>
                    <pre><code class="language-json">{
    "success": true,
    "data": array,
    "auth_method": string
}</code></pre>
                </div>
            </div>

            <!-- API Key Only Endpoints -->
            <div class="api-section">
                <h3>API Key Only Endpoints</h3>
                
                <div class="api-endpoint">
                    <h4>1. Send Report to All Users</h4>
                    <p><strong>Endpoint:</strong> <code>POST <?php echo esc_html(rest_url('v1/qr-attendance/send-report-all')); ?></code></p>
                    <p><strong>Headers:</strong> <code>X-API-Key: your_api_key</code></p>
                    <p><strong>Parameters:</strong></p>
                    <pre><code class="language-json">{
    "report_id": integer
}</code></pre>
                    <p><strong>Response:</strong></p>
                    <pre><code class="language-json">{
    "success": true,
    "data": {
        "report_id": integer,
        "report_title": string,
        "total_users": integer,
        "results": [{
            "user_id": integer,
            "email": string,
            "status": string,
            "message": string
        }]
    }
}</code></pre>
                </div>

                <div class="api-endpoint">
                    <h4>2. Export CSV</h4>
                    <p><strong>Endpoint:</strong> <code>GET <?php echo esc_html(rest_url('v1/qr-attendance/export-csv/{report_id}')); ?></code></p>
                    <p><strong>Headers:</strong> <code>X-API-Key: your_api_key</code></p>
                    <p><strong>Response:</strong> CSV file download</p>
                </div>

                <div class="api-endpoint">
                    <h4>3. Send Report to Specific User</h4>
                    <p><strong>Endpoint:</strong> <code>POST <?php echo esc_html(rest_url('v1/qr-attendance/send-report')); ?></code></p>
                    <p><strong>Headers:</strong> <code>X-API-Key: your_api_key</code></p>
                    <p><strong>Parameters:</strong></p>
                    <pre><code class="language-json">{
    "user_id": integer,
    "report_id": integer
}</code></pre>
                    <p><strong>Response:</strong></p>
                    <pre><code class="language-json">{
    "success": boolean,
    "status": string,
    "message": string,
    "email_sent": boolean
}</code></pre>
                </div>
            </div>

            <!-- Example Requests -->
            <div class="api-section">
                <h3>Example Requests</h3>

                <!-- Submit Attendance -->
                <div class="api-endpoint">
                    <h4>Submit Attendance</h4>
                    <div class="code-block">
                        <button class="copy-button" onclick="copyToClipboard(this)">Copy</button>
                        <pre class="curl-example">curl -X POST "<?php echo esc_url(rest_url('v1/qr-attendance/submit')); ?>" \
     -H "Content-Type: application/json" \
     -d '{
  "user_id": <?php echo $example_user ? $example_user->ID : 1; ?>,
  "hash": "<?php echo esc_attr($example_hash); ?>"
}'</pre>
                    </div>
                </div>

                <!-- Get Reports -->
                <div class="api-endpoint">
                    <h4>Get Reports</h4>
                    <div class="code-block">
                        <button class="copy-button" onclick="copyToClipboard(this)">Copy</button>
                        <pre class="curl-example">curl -X GET "<?php echo esc_url(rest_url('v1/qr-attendance/reports')); ?>" \
     -H "X-API-Key: <?php echo esc_attr($api_key); ?>"</pre>
                    </div>
                </div>

                <!-- Send Report All -->
                <div class="api-endpoint">
                    <h4>Send Report to All Users</h4>
                    <div class="code-block">
                        <button class="copy-button" onclick="copyToClipboard(this)">Copy</button>
                        <pre class="curl-example">curl -X POST "<?php echo esc_url(rest_url('v1/qr-attendance/send-report-all')); ?>" \
     -H "Content-Type: application/json" \
     -H "X-API-Key: <?php echo esc_attr($api_key); ?>" \
     -d '{
    "report_id": 123
}'</pre>
                    </div>
                </div>

                <!-- Export CSV -->
                <div class="api-endpoint">
                    <h4>Export CSV</h4>
                    <div class="code-block">
                        <button class="copy-button" onclick="copyToClipboard(this)">Copy</button>
                        <pre class="curl-example">curl -X GET "<?php echo esc_url(rest_url('v1/qr-attendance/export-csv/123')); ?>" \
     -H "X-API-Key: <?php echo esc_attr($api_key); ?>" \
     --output "attendance.csv"</pre>
                    </div>
                </div>
            </div>

            <!-- Error Responses -->
            <div class="api-section">
                <h3>Common Error Responses</h3>
                
                <div class="api-error">
                    <h4>Authentication Error (401)</h4>
                    <pre><code class="language-json">{
    "success": false,
    "message": "Invalid API key"
}</code></pre>
                </div>

                <div class="api-error">
                    <h4>Bad Request (400)</h4>
                    <pre><code class="language-json">{
    "success": false,
    "message": "Error description"
}</code></pre>
                </div>

                <div class="api-error">
                    <h4>Not Found (404)</h4>
                    <pre><code class="language-json">{
    "success": false,
    "message": "Resource not found"
}</code></pre>
                </div>

                <div class="api-error">
                    <h4>Server Error (500)</h4>
                    <pre><code class="language-json">{
    "success": false,
    "message": "Internal server error"
}</code></pre>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_notification_settings() {
        ?>
        <div class="notification-settings-wrapper">
            <div class="coming-soon-content">
                <span class="dashicons dashicons-bell"></span>
                <h2><?php esc_html_e('Notification Settings', 'daily-attendance'); ?></h2>
                <p><?php esc_html_e('Email notification settings will be available in the next update. Stay tuned!', 'daily-attendance'); ?></p>
                
                <div class="planned-features">
                    <h3><?php esc_html_e('Planned Features', 'daily-attendance'); ?></h3>
                    <ul>
                        <li><?php esc_html_e('Daily attendance summary emails', 'daily-attendance'); ?></li>
                        <li><?php esc_html_e('Weekly attendance reports', 'daily-attendance'); ?></li>
                        <li><?php esc_html_e('Late arrival notifications', 'daily-attendance'); ?></li>
                        <li><?php esc_html_e('Absence alerts', 'daily-attendance'); ?></li>
                        <li><?php esc_html_e('Custom notification schedules', 'daily-attendance'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <style>
            .notification-settings-wrapper {
                margin: 50px auto;
                text-align: center;
                padding: 40px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                max-width: 600px;
            }
            .coming-soon-content .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                color: #2271b1;
                margin-bottom: 20px;
            }
            .coming-soon-content h2 {
                margin: 0 0 15px;
                color: #1d2327;
            }
            .coming-soon-content p {
                font-size: 15px;
                color: #646970;
                margin-bottom: 30px;
            }
            .planned-features {
                text-align: left;
                background: #f8f9fa;
                padding: 20px 30px;
                border-radius: 6px;
                margin-top: 30px;
            }
            .planned-features h3 {
                color: #2271b1;
                margin: 0 0 15px;
            }
            .planned-features ul {
                margin: 0;
                padding: 0 0 0 20px;
            }
            .planned-features li {
                margin: 10px 0;
                color: #50575e;
            }
        </style>
        <?php
    }
}
