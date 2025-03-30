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
        $('#template-preview-body').html('<div class="updating">Updating preview...</div>');
        
        // Get subject and body content
        let subject = $('#pbda_email_subject').val() || pbdaDefaults.subject;
        let bodyContent = tinymce.get('pbda_email_template') ? 
            tinymce.get('pbda_email_template').getContent() : 
            $('#pbda_email_template').val() || pbdaDefaults.template;

        // Simple key-value replacements
        const shortcodes = {
            '[title]': 'March 2024',
            '[username]': 'John Doe',
            '[email]': 'john@example.com',
            '[date]': new Date().toLocaleDateString(),
            '[total_days]': '15',
            '[attendance_table]': getExampleTable()
        };

        // Process replacements
        Object.keys(shortcodes).forEach(key => {
            const regex = new RegExp(escapeRegex(key), 'g');
            subject = subject.replace(regex, shortcodes[key]);
            bodyContent = bodyContent.replace(regex, shortcodes[key]);
        });
        
        // Update preview
        $('#template-preview-subject span').text(subject);
        $('#template-preview-body').html(bodyContent);
    }

    // Helper function to escape regex special characters
    function escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // Manual preview update button
    $('#update-preview').on('click', function() {
        updatePreview();
        $(this).find('.dashicons').addClass('spin');
        setTimeout(() => {
            $(this).find('.dashicons').removeClass('spin');
        }, 500);
    });

    // Initial preview after a short delay
    setTimeout(updatePreview, 1000);

    // Add spin animation
    $('<style>')
        .text('@keyframes spin { 100% { transform: rotate(360deg); } }' +
              '.spin { animation: spin 0.5s linear; }' +
              '.updating { padding: 20px; text-align: center; color: #666; }')
        .appendTo('head');
});
