jQuery(document).ready(function($) {
    $('#public-draft-page-preview').on('change', function() {
        var checkbox = $(this);
        var isChecked = checkbox.is(':checked');
        var postID = $('#post_ID').val();
        var nonce = $('#public_draft_page_preview_wpnonce').val();

        // Show a loading message while waiting for the AJAX response
        var statusElement = $('#public-draft-page-preview-ajax');
        statusElement.removeClass('mfpp-enabled mfpp-disabled').text('Loading...');

        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'public_draft_page_preview', // Action name matches PHP
                post_ID: postID,
                checked: isChecked ? 'true' : 'false',
                _wpnonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    if (isChecked) {
                        $('#public-draft-page-preview-link').removeClass('hidden').find('input').val(response.data.preview_url);
                        statusElement.removeClass('mfpp-disabled').addClass('mfpp-enabled').text('Enabled!');
                    } else {
                        $('#public-draft-page-preview-link').addClass('hidden').find('input').val('');
                        statusElement.removeClass('mfpp-enabled').addClass('mfpp-disabled').text('Disabled!');
                    }

                    // Hide the status message after 5 seconds
                    setTimeout(function() {
                        statusElement.fadeOut();
                    }, 1500);
                } else {
                    statusElement.removeClass('mfpp-enabled').addClass('mfpp-disabled').text('Error');
                }
            },
            error: function(xhr, status, error) {
                statusElement.removeClass('mfpp-enabled').addClass('mfpp-disabled').text('AJAX Error');
                console.log('Status: ' + status + '\nError: ' + error);
            }
        });

        // Reset the status display in case of another change
        statusElement.show();
    });
});
