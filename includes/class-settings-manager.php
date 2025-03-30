<?php
class SettingsManager {
    private $settings_page = 'daily-attendance-settings';
    private $option_group = 'pbda_settings';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=da_reports',
            'Settings',
            'Settings',
            'manage_options',
            $this->settings_page,
            array($this, 'render_settings_page')
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
        echo '<p>Configure your email template using the following shortcodes:</p>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li><code>[title]</code> - Report title (e.g., "March 2024")</li>';
        echo '<li><code>[username]</code> - User\'s display name</li>';
        echo '<li><code>[attendance_table]</code> - Attendance data table</li>';
        echo '<li><code>[total_days]</code> - Total days present</li>';
        echo '<li><code>[email]</code> - User\'s email</li>';
        echo '<li><code>[date]</code> - Current date</li>';
        echo '</ul>';
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
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="pbda-settings-container">
                <div class="pbda-settings-form">
                    <form action="options.php" method="post">
                        <?php settings_fields($this->option_group); ?>
                        <div class="form-field">
                            <label for="pbda_email_subject">Email Subject</label>
                            <input type="text" 
                                name="pbda_email_subject" 
                                id="pbda_email_subject" 
                                value="<?php echo esc_attr(get_option('pbda_email_subject', $this->get_default_subject())); ?>" 
                                class="large-text">
                            <p class="description">Available shortcodes: [title], [username], [date]</p>
                        </div>

                        <div class="form-field">
                            <label for="pbda_email_template">Email Body Template</label>
                            <?php 
                            wp_editor(get_option('pbda_email_template', $this->get_default_template()), 'pbda_email_template', array(
                                'textarea_name' => 'pbda_email_template',
                                'textarea_rows' => 20,
                                'media_buttons' => true,
                                'teeny' => false,
                                'tinymce' => true
                            ));
                            ?>
                        </div>
                        <?php submit_button('Save Settings'); ?>
                    </form>
                </div>

                <div class="pbda-settings-preview">
                    <h3>Live Preview</h3>
                    <div id="template-preview-subject">
                        <strong>Subject:</strong> <span></span>
                    </div>
                    <div id="template-preview-body"></div>
                </div>
            </div>

            <style>
            .pbda-settings-container {
                display: flex;
                gap: 30px;
                margin-top: 20px;
            }
            .pbda-settings-form {
                flex: 1;
                min-width: 500px;
            }
            .pbda-settings-preview {
                flex: 1;
                min-width: 400px;
                position: sticky;
                top: 32px;
            }
            #template-preview-subject {
                margin-bottom: 15px;
                padding: 10px;
                background: #f5f5f5;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            #template-preview-body {
                border: 1px solid #ddd;
                padding: 20px;
                background: #fff;
                border-radius: 4px;
                min-height: 400px;
            }
            .form-field {
                margin-bottom: 20px;
            }
            .form-field label {
                display: block;
                margin-bottom: 5px;
                font-weight: 500;
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
                    // Update subject preview
                    var subject = $('#pbda_email_subject').val() || '<?php echo esc_js($this->get_default_subject()); ?>';
                    subject = subject.replace('[username]', 'John Doe')
                                   .replace('[title]', 'March 2024')
                                   .replace('[date]', new Date().toLocaleDateString());
                    $('#template-preview-subject span').text(subject);

                    // Update body preview
                    var bodyContent;
                    if (tinymce.activeEditor) {
                        bodyContent = tinymce.activeEditor.getContent();
                    } else {
                        bodyContent = $('#pbda_email_template').val();
                    }
                    
                    if (!bodyContent) {
                        bodyContent = '<?php echo esc_js($this->get_default_template()); ?>';
                    }

                    bodyContent = bodyContent.replace(/\[username\]/g, 'John Doe')
                                           .replace(/\[title\]/g, 'March 2024')
                                           .replace(/\[email\]/g, 'john@example.com')
                                           .replace(/\[date\]/g, new Date().toLocaleDateString())
                                           .replace(/\[total_days\]/g, '15')
                                           .replace(/\[attendance_table\]/g, getExampleTable());
                    
                    $('#template-preview-body').html(bodyContent);
                }

                // Watch for subject changes
                $('#pbda_email_subject').on('input', updatePreview);

                // Watch for body changes
                if (typeof tinymce !== 'undefined') {
                    tinymce.on('addeditor', function(e) {
                        e.editor.on('input change keyup', updatePreview);
                    });
                }

                // Also watch for textarea changes (when TinyMCE is disabled)
                $('#pbda_email_template').on('input', updatePreview);

                // Initial preview
                setTimeout(updatePreview, 1000);

                // Update preview when switching between visual/text editor
                $(document).on('click', '.wp-switch-editor', function() {
                    setTimeout(updatePreview, 100);
                });
            });
            </script>
        </div>
        <?php
    }
}
