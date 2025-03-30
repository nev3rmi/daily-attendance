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
        
        add_settings_section(
            'pbda_email_section',
            'Email Template Settings',
            array($this, 'render_section_info'),
            $this->settings_page
        );

        add_settings_field(
            'pbda_email_template',
            'Email Template',
            array($this, 'render_template_field'),
            $this->settings_page,
            'pbda_email_section'
        );
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
            
            <div class="pbda-settings-preview" style="float: right; width: 300px; margin-left: 20px;">
                <h3>Template Preview</h3>
                <div id="template-preview" style="border: 1px solid #ddd; padding: 15px; background: #fff;">
                </div>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_group);
                do_settings_sections($this->settings_page);
                submit_button('Save Settings');
                ?>
            </form>

            <script>
            jQuery(document).ready(function($) {
                // Live preview functionality
                function updatePreview() {
                    if (tinymce.activeEditor) {
                        var content = tinymce.activeEditor.getContent();
                        content = content.replace('[username]', 'John Doe')
                                       .replace('[title]', 'March 2024')
                                       .replace('[email]', 'john@example.com')
                                       .replace('[date]', new Date().toLocaleDateString())
                                       .replace('[total_days]', '15')
                                       .replace('[attendance_table]', '<table style="width:100%;border-collapse:collapse"><tr><th>Date</th><th>Time</th></tr><tr><td>Mar 1</td><td>09:00 AM</td></tr><tr><td>Mar 2</td><td>08:55 AM</td></tr></table>');
                        $('#template-preview').html(content);
                    }
                }

                if (typeof tinymce !== 'undefined') {
                    tinymce.on('addeditor', function(e) {
                        e.editor.on('change', updatePreview);
                    });
                }

                // Initial preview
                setTimeout(updatePreview, 1000);
            });
            </script>
        </div>
        <?php
    }
}
