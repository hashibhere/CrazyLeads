jQuery(document).ready(function($) {
    $('.sgmls-save-lead').on('click', function() {
        var button = $(this);
        var data = {
            action: 'sgmls_save_lead',
            name: button.data('name'),
            address: button.data('address'),
            phone: button.data('phone'),
            website: button.data('website'),
            place_url: button.data('url')
        };

        $.post(sgmls_ajax.ajax_url, data, function(response) {
            if (response.success) {
                button.text('Saved').prop('disabled', true);
            } else {
                alert('Failed to save lead: ' + response.data.message);
            }
        }).fail(function() {
            alert('Failed to save lead: Server error');
        });
    });
});
