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
        
        const subject = $('#pbda_email_subject').val() || pbdaDefaults.subject;
        let bodyContent = '';
        
        // Get content from TinyMCE or textarea
        if (tinymce.get('pbda_email_template')) {
            bodyContent = tinymce.get('pbda_email_template').getContent();
        } else {
            bodyContent = $('#pbda_email_template').val();
        }

        bodyContent = bodyContent || pbdaDefaults.template;

        // Replace shortcodes
        const replacements = {
            subject: {
                '[username]': 'John Doe',
                '[title]': 'March 2024',
                '[date]': new Date().toLocaleDateString()
            },
            body: {
                '[username]': 'John Doe',
                '[title]': 'March 2024', 
                '[email]': 'john@example.com',
                '[date]': new Date().toLocaleDateString(),
                '[total_days]': '15',
                '[attendance_table]': getExampleTable()
            }
        };

        let processedSubject = subject;
        let processedBody = bodyContent;

        Object.entries(replacements.subject).forEach(([key, value]) => {
            processedSubject = processedSubject.replace(new RegExp(key, 'g'), value);
        });

        Object.entries(replacements.body).forEach(([key, value]) => {
            processedBody = processedBody.replace(new RegExp(key, 'g'), value);
        });
        
        $('#template-preview-subject span').text(processedSubject);
        $('#template-preview-body').html(processedBody);
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
