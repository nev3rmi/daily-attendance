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
            array($this, 'render_settings_page'),
            100  // High priority to keep at bottom
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
                <div class="pbda-settings-layout">
                    <div class="pbda-settings-content">
                        <form action="options.php" method="post">
                            <?php 
                            settings_fields($this->option_group);
                            $this->render_section_info();
                            ?>
                            <div class="form-fields">
                                <div class="form-field">
                                    <label for="pbda_email_subject">Email Subject</label>
                                    <input type="text" 
                                        name="pbda_email_subject" 
                                        id="pbda_email_subject" 
                                        value="<?php echo esc_attr(get_option('pbda_email_subject', $this->get_default_subject())); ?>" 
                                        class="large-text">
                                </div>

                                <div class="form-field">
                                    <label for="pbda_email_template">Email Body Template</label>
                                    <?php wp_editor(get_option('pbda_email_template', $this->get_default_template()), 'pbda_email_template', array(
                                        'textarea_name' => 'pbda_email_template',
                                        'textarea_rows' => 20,
                                        'media_buttons' => true,
                                        'teeny' => false,
                                        'tinymce' => true
                                    )); ?>
                                </div>
                            </div>
                            <?php submit_button('Save Settings'); ?>
                        </form>
                    </div>

                    <div class="pbda-preview-controls">
                        <button type="button" id="update-preview" class="button button-secondary">
                            <span class="dashicons dashicons-update"></span> 
                            Update Preview
                        </button>
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
                .shortcode-info {
                    display: flex;
                    gap: 30px;
                    margin: 20px 0;
                }
                .shortcode-subject, .shortcode-body {
                    flex: 1;
                    background: #fff;
                    padding: 15px;
                    border-left: 4px solid #2271b1;
                }
                .pbda-settings-layout {
                    display: grid;
                    grid-template-columns: 1fr auto 1fr;
                    gap: 20px;
                    margin-top: 20px;
                }
                .pbda-preview-controls {
                    align-self: center;
                    padding: 20px;
                }
                #update-preview {
                    display: flex;
                    align-items: center;
                    gap: 5px;
                    padding: 10px 20px;
                }
                .shortcode-list {
                    list-style: none;
                    margin: 10px 0;
                    columns: 2;
                }
                .shortcode-list li {
                    margin-bottom: 8px;
                }
                .pbda-settings-container {
                    display: flex;
                    gap: 20px;
                    margin-top: 20px;
                    position: relative;
                }
                .pbda-settings-form {
                    flex: 1;
                    min-width: 500px;
                }
                .pbda-preview-controls {
                    position: sticky;
                    top: 32px;
                    z-index: 100;
                    background: #f0f0f1;
                    padding: 10px;
                    border-radius: 4px;
                    text-align: center;
                }
                #update-preview {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 5px;
                }
                #update-preview .dashicons {
                    margin-top: 3px;
                }
                .pbda-settings-preview {
                    flex: 1;
                    min-width: 400px;
                    position: sticky;
                    top: 100px;
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
