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
        $tabs = array(
            'email' => __('Email Templates', 'daily-attendance'),
            'notification' => __('Notifications', 'daily-attendance'),
            'advanced' => __('Advanced', 'daily-attendance')
        );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_key => $tab_name): ?>
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
            <?php else: ?>
                <div class="coming-soon-wrapper">
                    <div class="coming-soon-content">
                        <span class="dashicons dashicons-clock"></span>
                        <h2><?php echo esc_html($tabs[$current_tab]); ?></h2>
                        <p><?php esc_html_e('This feature is coming soon! Stay tuned for updates.', 'daily-attendance'); ?></p>
                    </div>
                </div>
                <style>
                    .coming-soon-wrapper {
                        margin: 50px auto;
                        text-align: center;
                        padding: 40px;
                        background: #fff;
                        border-radius: 8px;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                        max-width: 500px;
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
                    }
                </style>
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
        ?>
        <div class="api-documentation">
            <h2><?php esc_html_e('API Documentation', 'daily-attendance'); ?></h2>
            
            <div class="api-section">
                <h3>1. Mark Attendance</h3>
                <p><strong>Endpoint:</strong> <code><?php echo esc_html(rest_url('v1/attendances/submit')); ?></code></p>
                <p><strong>Method:</strong> POST</p>
                
                <div class="api-method">
                    <h4><?php esc_html_e('Method 1: Username/Password', 'daily-attendance'); ?></h4>
                    <pre>{
    "userName": "john_doe",
    "passWord": "your_password"
}</pre>
                </div>

                <div class="api-method">
                    <h4><?php esc_html_e('Method 2: QR Code Hash', 'daily-attendance'); ?></h4>
                    <pre>{
    "user_id": <?php echo $example_user ? $example_user->ID : 1; ?>,
    "hash": "<?php echo esc_attr($example_hash); ?>"
}</pre>
                </div>
            </div>

            <div class="api-section">
                <h3>2. Export Report to CSV</h3>
                <p><strong>Endpoint:</strong> <code><?php echo esc_html(rest_url('v1/export-csv/{report_id}')); ?></code></p>
                <p><strong>Method:</strong> GET</p>
                <p><strong>Required:</strong> Admin authentication (nonce)</p>
                <pre>GET <?php echo esc_html(rest_url('v1/export-csv/123')); ?>?_wpnonce=your_nonce</pre>
            </div>

            <div class="api-section">
                <h3>3. Send Attendance Report</h3>
                <p><strong>Endpoint:</strong> <code><?php echo esc_html(rest_url('v1/send-report')); ?></code></p>
                <p><strong>Method:</strong> POST</p>
                <p><strong>Required:</strong> Admin authentication</p>
                <pre>{
    "user_id": 123,
    "month": "202403"  // Optional, defaults to current month
}</pre>
            </div>

            <div class="api-section">
                <h3>Example Usage</h3>
                <pre>// Using curl
curl -X POST <?php echo esc_url(rest_url('v1/attendances/submit')); ?> \
-H "Content-Type: application/json" \
-H "Accept: application/json" \
-d '{
    "userName": "john_doe",
    "passWord": "your_password"
}'

// Or using fetch API
fetch('<?php echo esc_url(rest_url('v1/attendances/submit')); ?>', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    body: JSON.stringify({
        userName: 'john_doe',
        passWord: 'your_password'
    })
}).then(r => r.json()).then(console.log);</pre>
            </div>
        </div>
        <?php
    }
}
