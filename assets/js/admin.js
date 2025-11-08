/**
 * TPWC Hebrew PDF Generator - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Media uploader for logo.
        var mediaUploader;

        $(document).on('click', '.tpwc-upload-media', function(e) {
            e.preventDefault();

            var button = $(this);
            var targetField = button.data('target');
            var $field = $('#' + targetField);
            var $container = button.closest('.tpwc-media-upload');

            // If the media frame already exists, reopen it.
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            // Create the media frame.
            mediaUploader = wp.media({
                title: 'Select Logo',
                button: {
                    text: 'Use this logo'
                },
                library: {
                    type: ['image/svg+xml', 'image/png', 'image/jpeg']
                },
                multiple: false
            });

            // When an image is selected, run a callback.
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();

                // Set the field value.
                $field.val(attachment.id);

                // Show preview.
                var $img = $container.find('img');
                if ($img.length) {
                    $img.attr('src', attachment.url);
                } else {
                    $('<img />')
                        .attr('src', attachment.url)
                        .css({
                            'max-width': '200px',
                            'display': 'block',
                            'margin-bottom': '10px'
                        })
                        .insertBefore(button);
                }

                // Show remove button.
                if (!$container.find('.tpwc-remove-media').length) {
                    $('<button type="button" class="button tpwc-remove-media" data-target="' + targetField + '">Remove</button>')
                        .insertAfter(button);
                }
            });

            // Open the media frame.
            mediaUploader.open();
        });

        // Remove media.
        $(document).on('click', '.tpwc-remove-media', function(e) {
            e.preventDefault();

            var button = $(this);
            var targetField = button.data('target');
            var $field = $('#' + targetField);
            var $container = button.closest('.tpwc-media-upload');

            // Clear the field.
            $field.val('');

            // Remove preview.
            $container.find('img').remove();

            // Remove the button itself.
            button.remove();
        });

        // Confirm bulk delete.
        $('select[name="action"], select[name="action2"]').on('change', function() {
            var action = $(this).val();
            if (action === 'delete') {
                $(this).closest('form').on('submit', function(e) {
                    var checkedCount = $('input[name="ids[]"]:checked').length;
                    if (checkedCount > 0) {
                        if (!confirm('Are you sure you want to delete ' + checkedCount + ' PDF(s)?')) {
                            e.preventDefault();
                            return false;
                        }
                    }
                });
            }
        });
    });

})(jQuery);
