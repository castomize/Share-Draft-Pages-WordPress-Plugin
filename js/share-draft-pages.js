jQuery(document).ready(function($) {
    $('#share-draft-pages').on('change', function() {
        var checkbox = $(this);
        var isChecked = checkbox.is(':checked');
        var postID = $('#post_ID').val();
        var nonce = $('#share_draft_pages_wpnonce').val();

        // Show a loading message while waiting for the AJAX response
        var statusElement = $('#share-draft-pages-ajax');
        statusElement.removeClass('mfpp-enabled mfpp-disabled').text('Loading...');

        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'share_draft_pages', // Action name matches PHP
                post_ID: postID,
                checked: isChecked ? 'true' : 'false',
                _wpnonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    if (isChecked) {
                        $('#share-draft-pages-link').removeClass('hidden').find('input').val(response.data.preview_url);
                        statusElement.removeClass('mfpp-disabled').addClass('mfpp-enabled').text('Enabled!');
                    } else {
                        $('#share-draft-pages-link').addClass('hidden').find('input').val('');
                        statusElement.removeClass('mfpp-enabled').addClass('mfpp-disabled').text('Disabled!');
                    }

                    // Hide the status message after 5 seconds
                    setTimeout(function() {
                        statusElement.fadeOut();
                    }, 3000);
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