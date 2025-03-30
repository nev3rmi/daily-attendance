<?php
class SettingsManager {
    private $settings_page = 'daily-attendance-settings';
    private $option_group = 'pbda_settings';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
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
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_key => $tab_name): ?>
                    <!-- ...existing tab code... -->
                <?php endforeach; ?>
            </nav>

            <?php if ($current_tab === 'email'): ?>
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
                    padding: 10px 20px;
                }
                </style>

                <!-- Update Preview Button -->
                <button type="button" id="update-preview" class="button button-primary">
                    <span class="dashicons dashicons-update"></span> 
                    Update Preview
                </button>

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
            <?php elseif ($current_tab === 'notification'): ?>
                <div class="pbda-settings-section">
                    <h2>Notification Settings</h2>
                    <p>Coming soon...</p>
                </div>
            <?php elseif ($current_tab === 'advanced'): ?>
                <div class="pbda-settings-section">
                    <h2>Advanced Settings</h2>
                    <p>Coming soon...</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
