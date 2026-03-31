/* global msdAdmin, wp */
(function ($) {
    'use strict';

    // Handle upload buttons
    $(document).on('click', '.msd-upload-btn', function (e) {
        e.preventDefault();

        var btn       = $(this);
        var targetId  = btn.data('target');
        var previewId = btn.data('preview');
        var type      = btn.data('type'); // 'logo' or 'banner'
        var title     = type === 'banner' ? msdAdmin.bannerTitle : msdAdmin.logoTitle;

        var frame = wp.media({
            title:    title,
            button:   { text: msdAdmin.useText },
            multiple: false,
            library:  { type: 'image' }
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var url        = attachment.url;

            // Set the hidden input
            $('#' + targetId).val(url);

            // Update the preview
            var preview = $('#' + previewId);
            preview.find('img').remove();
            preview.append($('<img>').attr('src', url));

            // Show the remove button
            btn.siblings('.msd-remove-btn').show();
        });

        frame.open();
    });

    // Handle remove buttons
    $(document).on('click', '.msd-remove-btn', function (e) {
        e.preventDefault();

        var btn       = $(this);
        var targetId  = btn.data('target');
        var previewId = btn.data('preview');

        $('#' + targetId).val('');
        $('#' + previewId).find('img').remove();
        btn.hide();
    });

}(jQuery));
