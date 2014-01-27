/* ==========================================================
 * metabox.js
 * http://enviragallery.com/
 * ==========================================================
 * Copyright 2013 Thomas Griffin.
 *
 * Licensed under the GPL License, Version 2.0 or later (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ========================================================== */
;(function($){
    $(function(){
        // Initialize the gallery tabs.
        var envira_tabs = $('#envira-tabs');
        envira_tabs.tabs();

        // Place a custom progress bar directly underneath the image dropzone.
        $('#envira-gallery .drag-drop-inside').append('<div class="envira-progress-bar"><div></div></div>');

        // Append a link to use images from the user's media library.
        $('#envira-gallery .max-upload-size').append(' <a class="envira-media-library" href="#" title="' + envira_gallery_metabox.gallery + '">' + envira_gallery_metabox.gallery + '</a>');

        // Attach to when files are being uploaded - uploader is the global variable that will handle the uploading process.
        var envira_uploader = uploader,
            envira_bar      = $('#envira-gallery .envira-progress-bar'),
            envira_progress = $('#envira-gallery .envira-progress-bar div'),
            envira_output   = $('#envira-gallery-output');

        // Bind to the FilesAdded event to show the progess bar.
        envira_uploader.bind('FilesAdded', function(){
            $(envira_bar).show().css('display', 'block');
        });

        // Bind to the UploadProgress event to manipulate the progress bar.
        envira_uploader.bind('UploadProgress', function(up, file){
            $(envira_progress).css('width', up.total.percent + '%');
        });

        // Bind to the FileUploaded event to set proper UI display for gallery.
        envira_uploader.bind('FileUploaded', function(up, file, info){
            // Make an ajax request to generate and output the image in the gallery UI.
            $.post(
                envira_gallery_metabox.ajax,
                {
                    action:  'envira_gallery_load_image',
                    nonce:   envira_gallery_metabox.load_image,
                    id:      info.response,
                    post_id: envira_gallery_metabox.id
                },
                function(res){
                    $(envira_output).append(res);
                },
                'json'
            );
        });

        // Bind to the UploadComplete event to hide and reset the progress bar.
        envira_uploader.bind('UploadComplete', function(){
            $(envira_bar).hide().css('display', 'none');
            $(envira_progress).removeAttr('style');
        });

        // Conditionally show the cropping/mobile image sizes if the option is selected.
        var envira_crop_option   = $('#envira-config-crop'),
            envira_mobile_option = $('#envira-config-mobile');
        if ( envira_crop_option.is(':checked') )
            $('#envira-config-crop-size-box').fadeIn(300);
        envira_crop_option.on('change', function(){
            if ( $(this).is(':checked') )
               $('#envira-config-crop-size-box').fadeIn(300);
            else
                $('#envira-config-crop-size-box').fadeOut(300);
        });
        if ( envira_mobile_option.is(':checked') )
            $('#envira-config-mobile-size-box').fadeIn(300);
        envira_mobile_option.on('change', function(){
            if ( $(this).is(':checked') )
               $('#envira-config-mobile-size-box').fadeIn(300);
            else
                $('#envira-config-mobile-size-box').fadeOut(300);
        });

        // Open up the media manager modal.
        $(document).on('click', '.envira-media-library', function(e){
            e.preventDefault();

            // Show the modal.
            main_frame = true;
            $('#envira-gallery-upload-ui').appendTo('body').show();
        });

        // Add the selected state to images when selected from the library view.
        $('.envira-gallery-gallery').on('click', '.thumbnail, .check, .media-modal-icon', function(e){
            e.preventDefault();
            if ( $(this).parent().parent().hasClass('envira-gallery-in-gallery') )
                return;
            if ( $(this).parent().parent().hasClass('selected') )
                $(this).parent().parent().removeClass('details selected');
            else
                $(this).parent().parent().addClass('details selected');
        });

        // Load more images into the library view.
        $('.envira-gallery-load-library').on('click', function(e){
            e.preventDefault();
            var $this = $(this);
            $this.after('<span class="envira-gallery-waiting" style="display: inline-block; margin-top: 16px;"><img class="envira-gallery-spinner" src="' + envira_gallery_metabox.spinner + '" width="16px" height="16px" style="margin: -1px 5px 0; vertical-align: middle;" />' + envira_gallery_metabox.loading + '</span>');

            // Prepare our data to be sent via Ajax.
            var load = {
                action:     'envira_gallery_load_library',
                offset:     parseInt($this.attr('data-envira-gallery-offset')),
                id:         envira_gallery_metabox.id,
                nonce:      envira_gallery_metabox.load_gallery
            };

            // Process the Ajax response and output all the necessary data.
            $.post(
                envira_gallery_metabox.ajax,
                load,
                function(response) {
                    $this.attr('data-envira-gallery-offset', parseInt($this.attr('data-envira-gallery-offset')) + 20);

                    // Append the response data.
                    if ( response && response.html && $this.hasClass('has-search') ) {
                        $('.envira-gallery-gallery').html(response.html);
                        $this.removeClass('has-search');
                    } else {
                        $('.envira-gallery-gallery').append(response.html);
                    }

                    // Remove the spinner and loading message/
                    $('.envira-gallery-waiting').fadeOut('normal', function() {
                        $(this).remove();
                    });
                },
                'json'
            );
        });

        // Load images related to the search term specified
        $(document).on('keyup keydown', '#envira-gallery-gallery-search', function(){
            var $this = $(this);
            // Ensure loading icon has been removed before outputting again.
            $('.envira-waiting').remove();
            $this.before('<span class="envira-waiting" style="display: inline-block; margin-top: 16px; margin-right: 10px;"><img class="envira-spinner" src="' + envira_gallery_metabox.spinner + '" width="16px" height="16px" style="margin: -1px 5px 0; vertical-align: middle;" />' + envira_gallery_metabox.searching + '</span>');

            var text        = $(this).val();
            var search      = {
                action:     'envira_gallery_library_search',
                nonce:      envira_gallery_metabox.library_search,
                post_id:    envira_gallery_metabox.id,
                search:     text
            };

            // Send the ajax request with a delay (500ms after the user stops typing).
            delay(function() {
                // Process the Ajax response and output all the necessary data.
                $.post(
                    envira_gallery_metabox.ajax,
                    search,
                    function(response) {
                        // Notify the load button that we have entered a search and reset the offset counter.
                        $('.envira-load-library').addClass('has-search').attr('data-envira-offset', parseInt(0));

                        // Append the response data.
                        if ( response )
                            $('.envira-gallery-gallery').html(response.html);

                        // Remove the spinner and loading message.
                        $('.envira-waiting').fadeOut('normal', function() {
                            $(this).remove();
                        });
                    },
                    'json'
                );
            }, '500');
        });

        // Process inserting slides into slider when the Insert button is pressed.
        $(document).on('click', '.envira-gallery-media-insert', function(e){
            e.preventDefault();
            var $this = $(this),
                text  = $(this).text(),
                data  = {
                    action: 'envira_gallery_insert_images',
                    nonce:   envira_gallery_metabox.insert_nonce,
                    post_id: envira_gallery_metabox.id,
                    images:  {}
                },
                selected = false,
                insert_e = e;
            $this.text(envira_gallery_metabox.inserting);

            // Loop through potential data to send when inserting images.
            // First, we loop through the selected items and add them to the data var.
            $('.envira-gallery-media-frame').find('.attachment.selected:not(.envira-gallery-in-gallery)').each(function(i, el){
                data.images[i] = $(el).attr('data-attachment-id');
                selected       = true;
            });

            // Send the ajax request with our data to be processed.
            $.post(
                envira_gallery_metabox.ajax,
                data,
                function(response){
                    // Set small delay before closing modal.
                    setTimeout(function(){
                        // Re-append modal to correct spot and revert text back to default.
                        append_and_hide(insert_e);
                        $this.text(text);

                        // If we have selected items, be sure to properly load first images back into view.
                        if ( selected )
                            $('.envira-gallery-load-library').attr('data-envira-gallery-offset', 0).addClass('has-search').trigger('click');
                    }, 500);
                },
                'json'
            );

        });

        // Make gallery items sortable.
        var gallery = $('#envira-gallery-output');

        // Use ajax to make the images sortable.
        gallery.sortable({
            containment: '#envira-gallery-output',
            items: 'li',
            cursor: 'move',
            forcePlaceholderSize: true,
            placeholder: 'dropzone',
            update: function(event, ui) {
                // Make ajax request to sort out items.
                var opts = {
                    url:        envira_gallery_metabox.ajax,
                    type:       'post',
                    async:      true,
                    cache:      false,
                    dataType:   'json',
                    data: {
                        action:     'envira_gallery_sort_images',
                        order:      gallery.sortable('toArray').toString(),
                        post_id:    envira_gallery_metabox.id,
                        nonce:      envira_gallery_metabox.sort
                    },
                    success: function(response) {
                        return;
                    },
                    error: function(xhr, textStatus ,e) {
                        return;
                    }
                };
                $.ajax(opts);
            }
        });

        // Process image removal from a gallery.
        $('#envira-gallery').on('click', '.envira-gallery-remove-image', function(e){
            e.preventDefault();

            // Bail out if the user does not actually want to remove the image.
            var confirm_delete = confirm(envira_gallery_metabox.remove);
            if ( ! confirm_delete )
                return;

            // Prepare our data to be sent via Ajax.
            var attach_id = $(this).parent().attr('id'),
                remove = {
                    action:         'envira_gallery_remove_image',
                    attachment_id:  attach_id,
                    post_id:        envira_gallery_metabox.id,
                    nonce:          envira_gallery_metabox.remove_nonce
                };

            // Process the Ajax response and output all the necessary data.
            $.post(
                envira_gallery_metabox.ajax,
                remove,
                function(response) {
                    $('#' + attach_id).fadeOut('normal', function() {
                        $(this).remove();

                        // Refresh the modal view to ensure no items are still checked if they have been removed.
                        $('.envira-gallery-load-library').attr('data-envira-gallery-offset', 0).addClass('has-search').trigger('click');
                    });
                },
                'json'
            );
        });

        // Open up the media modal area for modifying gallery metadata.
        $('#envira-gallery').on('click.enviraModify', '.envira-gallery-modify-image', function(e){
            e.preventDefault();
            var attach_id = $(this).parent().data('envira-gallery-image'),
                formfield = 'envira-gallery-meta-' + attach_id;

            // Show the modal.
            $('#' + formfield).appendTo('body').show();

            // Close the modal window on user action
            var append_and_hide = function(e){
                e.preventDefault();
                $('#' + formfield).appendTo('#' + attach_id).hide();
            };
            $(document).on('click.enviraIframe', '.media-modal-close, .media-modal-backdrop', append_and_hide);
            $(document).on('keydown.enviraIframe', function(e){
                if ( 27 == e.keyCode )
                    append_and_hide(e);
            });
        });

        // Save the gallery metadata.
        $(document).on('click', '.envira-gallery-meta-submit', function(e){
            e.preventDefault();
            var $this     = $(this),
                default_t = $this.text(),
                attach_id = $this.data('envira-gallery-item'),
                formfield = 'envira-gallery-meta-' + attach_id,
                meta      = {};

            // Output saving text...
            $this.text(envira_gallery_metabox.saving);

            // Add the caption since it is a special field.
            meta.caption = $('#envira-gallery-meta-table-' + attach_id).find('textarea[name="_envira_gallery[meta_caption]"]').val();

            // Get all meta fields and values.
            $('#envira-gallery-meta-table-' + attach_id).find(':input').not('.ed_button').each(function(i, el){
                if ( $(this).data('envira-meta') )
                    meta[$(this).data('envira-meta')] = $(this).val();
            });

            // Prepare the data to be sent.
            var data = {
                action:    'envira_gallery_save_meta',
                nonce:     envira_gallery_metabox.save_nonce,
                attach_id: attach_id,
                id:        envira_gallery_metabox.id,
                meta:      meta
            };

            $.post(
                envira_gallery_metabox.ajax,
                data,
                function(res){
                    setTimeout(function(){
                        $('#' + formfield).appendTo('#' + attach_id).hide();
                        $this.text(default_t);
                    }, 500);
                },
                'json'
            );
        });

        // Append spinner when importing a gallery.
        $('#envira-gallery-import-submit').on('click', function(){
            $(this).after('<span class="envira-gallery-waiting" style="display: inline-block;"><img class="envira-gallery-spinner" src="' + envira_gallery_metabox.spinner + '" width="16px" height="16px" style="margin: -1px 5px 0; vertical-align: middle;" />');
        });

        // Polling function for typing and other user centric items.
        var delay = (function() {
            var timer = 0;
            return function(callback, ms) {
                clearTimeout(timer);
                timer = setTimeout(callback, ms);
            };
        })();

        // Close the modal window on user action.
        var main_frame = false;
        var append_and_hide = function(e){
            e.preventDefault();
            $('#envira-gallery-upload-ui').appendTo('#envira-gallery-upload-ui-wrapper').hide();
            enviraRefresh();
            main_frame = false;
        };
        $(document).on('click', '#envira-gallery-upload-ui .media-modal-close, #envira-gallery-upload-ui .media-modal-backdrop', append_and_hide);
        $(document).on('keydown', function(e){
            if ( 27 == e.keyCode && main_frame )
                append_and_hide(e);
        });

        // Function to refresh images in the gallery.
        function enviraRefresh(){
            var data = {
                action: 'envira_gallery_refresh',
                id:     envira_gallery_metabox.id,
                nonce:  envira_gallery_metabox.refresh_nonce
            };

            $('.envira-media-library').after('<span class="envira-gallery-waiting" style="display: inline-block;"><img class="envira-gallery-spinner" src="' + envira_gallery_metabox.spinner + '" width="16px" height="16px" style="margin: -1px 5px 0; vertical-align: middle;" />' + envira_gallery_metabox.refreshing + '</span>');

            $.post(
                envira_gallery_metabox.ajax,
                data,
                function(res){
                    if ( res && res.success ) {
                        $('#envira-gallery-output').html(res.success);
                        $('#envira-gallery-output').find('.wp-editor-wrap').each(function(i, el){
                            var id = $(el).attr('id').split('-')[4];
                            quicktags({id: 'envira-gallery-caption-' + id, buttons: 'strong,em,link,block,del,ins,img,ul,ol,li,code,close'});
                            QTags._buttonsInit(); // Force buttons to initialize.
                        });

                        // Trigger a custom event for 3rd party scripts.
                        $('#envira-gallery-output').trigger({ type: 'enviraRefreshed', html: res.success, id: envira_gallery_metabox.id });
                    }

                    $('.envira-gallery-waiting').fadeOut(300, function(){
                        $(this).remove();
                    });
                },
                'json'
            );
        }
    });
}(jQuery));