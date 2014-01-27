<?php
/**
 * Metabox class.
 *
 * @since 1.0.0
 *
 * @package Envira_Gallery_Lite
 * @author  Thomas Griffin
 */
class Envira_Gallery_Metaboxes_Lite {

    /**
     * Holds the class object.
     *
     * @since 1.0.0
     *
     * @var object
     */
    public static $instance;

    /**
     * Path to the file.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public $file = __FILE__;

    /**
     * Holds the base class object.
     *
     * @since 1.0.0
     *
     * @var object
     */
    public $base;

    /**
     * Primary class constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {

        // Load the base class object.
        $this->base = Envira_Gallery_Lite::get_instance();

        // Load metabox assets.
        add_action( 'admin_enqueue_scripts', array( $this, 'meta_box_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'meta_box_scripts' ) );

        // Load the metabox hooks and filters.
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 100 );

        // Load all tabs.
        add_action( 'envira_gallery_tab_images', array( $this, 'images_tab' ) );
        add_action( 'envira_gallery_tab_config', array( $this, 'config_tab' ) );
        add_action( 'envira_gallery_tab_lightbox', array( $this, 'lightbox_tab' ) );
        add_action( 'envira_gallery_tab_thumbnails', array( $this, 'thumbnails_tab' ) );
        add_action( 'envira_gallery_tab_misc', array( $this, 'misc_tab' ) );

        // Add action to save metabox config options.
        add_action( 'save_post', array( $this, 'save_meta_boxes' ), 10, 2 );

    }

    /**
     * Loads styles for our metaboxes.
     *
     * @since 1.0.0
     *
     * @return null Return early if not on the proper screen.
     */
    public function meta_box_styles() {

        if ( 'post' !== get_current_screen()->base ) {
            return;
        }

        // Load necessary metabox styles.
        wp_register_style( $this->base->plugin_slug . '-metabox-style', plugins_url( 'assets/css/metabox.css', $this->base->file ), array(), $this->base->version );
        wp_enqueue_style( $this->base->plugin_slug . '-metabox-style' );

    }

    /**
     * Loads scripts for our metaboxes.
     *
     * @since 1.0.0
     *
     * @global int $id      The current post ID.
     * @global object $post The current post object..
     * @return null         Return early if not on the proper screen.
     */
    public function meta_box_scripts( $hook ) {

        global $id, $post;

        if ( isset( get_current_screen()->base ) && 'post' !== get_current_screen()->base ) {
            return;
        }

        // Set the post_id for localization.
        $post_id = ( null === $id ) ? $post->ID : $id;

        // Load WordPress necessary scripts.
        wp_enqueue_script( 'plupload-handlers' );
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_media( array( 'post' => $post_id ) );

        // Load necessary metabox scripts.
        wp_register_script( $this->base->plugin_slug . '-metabox-script', plugins_url( 'assets/js/metabox.js', $this->base->file ), array( 'jquery', 'plupload-handlers', 'quicktags', 'jquery-ui-sortable' ), $this->base->version, true );
        wp_enqueue_script( $this->base->plugin_slug . '-metabox-script' );
        wp_localize_script(
            $this->base->plugin_slug . '-metabox-script',
            'envira_gallery_metabox',
            array(
                'ajax'           => admin_url( 'admin-ajax.php' ),
                'gallery'        => esc_attr__( 'Click here to use images from your media library.', 'envira-gallery' ),
                'id'             => $post_id,
                'insert_nonce'   => wp_create_nonce( 'envira-gallery-insert-images' ),
                'inserting'      => __( 'Inserting...', 'envira-gallery' ),
                'library_search' => wp_create_nonce( 'envira-gallery-library-search' ),
                'load_image'     => wp_create_nonce( 'envira-gallery-load-image' ),
                'load_gallery'   => wp_create_nonce( 'envira-gallery-load-gallery' ),
                'refresh_nonce'  => wp_create_nonce( 'envira-gallery-refresh' ),
                'remove'         => __( 'Are you sure you want to remove this image from the gallery?', 'envira-gallery' ),
                'remove_nonce'   => wp_create_nonce( 'envira-gallery-remove-image' ),
                'save_nonce'     => wp_create_nonce( 'envira-gallery-save-meta' ),
                'saving'         => __( 'Saving...', 'envira-gallery' ),
                'sort'           => wp_create_nonce( 'envira-gallery-sort' ),
                'upgrade'        => __( 'This setting is not available to modify in the Lite version.', 'envira-gallery' ),
                'upgrade_btn'    => sprintf( __( '<a class="button button-primary button-small" href="%s" target="_blank">Click to Upgrade</a>', 'envira-gallery' ), 'http://enviragallery.com/lite/?utm_source=liteplugin&utm_medium=link&utm_campaign=WordPress' )
            )
        );

        // If on an Envira post type, add custom CSS for hiding specific things.
        if ( isset( get_current_screen()->post_type ) && 'envira' == get_current_screen()->post_type ) {
            add_action( 'admin_head', array( $this, 'meta_box_css' ) );
        }

    }

    /**
     * Hides unnecessary meta box items on Envira post type screens.
     *
     * @since 1.0.0
     */
    public function meta_box_css() {

        ?>
        <style type="text/css">.misc-pub-section:not(.misc-pub-post-status) { display: none; }</style>
        <?php

    }

    /**
     * Creates metaboxes for handling and managing galleries.
     *
     * @since 1.0.0
     */
    public function add_meta_boxes() {

        // Let's remove all of those dumb metaboxes from our post type screen to control the experience.
        $this->remove_all_the_metaboxes();

        // Get all public post types.
        $post_types = get_post_types( array( 'public' => true ) );

        // Splice the envira post type since it is not visible to the public by default.
        $post_types[] = 'envira';

        // Loops through the post types and add the metaboxes.
        foreach ( (array) $post_types as $post_type ) {
            // Don't output boxes on these post types.
            if ( in_array( $post_type, apply_filters( 'envira_gallery_skipped_posttypes', array( 'attachment', 'revision', 'nav_menu_item', 'soliloquy' ) ) ) ) {
                continue;
            }

            add_meta_box( 'envira-gallery', __( 'Envira Gallery Settings', 'envira-gallery' ), array( $this, 'meta_box_callback' ), $post_type, 'advanced', 'high' );
        }

    }

    /**
     * Removes all the metaboxes except the ones I want on MY POST TYPE. RAGE.
     *
     * @since 1.0.0
     *
     * @global array $wp_meta_boxes Array of registered metaboxes.
     * @return smile $for_my_buyers Happy customers with no spammy metaboxes!
     */
    public function remove_all_the_metaboxes() {

        global $wp_meta_boxes;

        // This is the post type you want to target. Adjust it to match yours.
        $post_type  = 'envira';

        // These are the metabox IDs you want to pass over. They don't have to match exactly. preg_match will be run on them.
        $pass_over  = array( 'submitdiv', 'envira' );

        // All the metabox contexts you want to check.
        $contexts   = array( 'normal', 'advanced', 'side' );

        // All the priorities you want to check.
        $priorities = array( 'high', 'core', 'default', 'low' );

        // Loop through and target each context.
        foreach ( $contexts as $context ) {
            // Now loop through each priority and start the purging process.
            foreach ( $priorities as $priority ) {
                if ( isset( $wp_meta_boxes[$post_type][$context][$priority] ) ) {
                    foreach ( (array) $wp_meta_boxes[$post_type][$context][$priority] as $id => $metabox_data ) {
                        // If the metabox ID to pass over matches the ID given, remove it from the array and continue.
                        if ( in_array( $id, $pass_over ) ) {
                            unset( $pass_over[$id] );
                            continue;
                        }

                        // Otherwise, loop through the pass_over IDs and if we have a match, continue.
                        foreach ( $pass_over as $to_pass ) {
                            if ( preg_match( '#^' . $id . '#i', $to_pass ) ) {
                                continue;
                            }
                        }

                        // If we reach this point, remove the metabox completely.
                        unset( $wp_meta_boxes[$post_type][$context][$priority][$id] );
                    }
                }
            }
        }

    }

    /**
     * Callback for displaying content in the registered metabox.
     *
     * @since 1.0.0
     *
     * @param object $post The current post object.
     */
    public function meta_box_callback( $post ) {

        // Keep security first.
        wp_nonce_field( 'envira-gallery', 'envira-gallery' );

        // Run limit checks.
        Envira_Gallery_Common_Admin_Lite::get_instance()->limit();

        // If no more galleries can be made, return early.
        if ( $this->base->limit ) {
            if ( in_array( $post->ID, get_option( 'envira_gallery_lite_limit' ) ) ) {
                $this->base->upgrade( true );
            } else {
                return $this->base->upgrade();
            }
        } else {
            $this->base->remaining();
        }

        // Check for our meta overlay helper.
        $gallery_data = get_post_meta( $post->ID, '_eg_gallery_data', true );
        $helper       = get_post_meta( $post->ID, '_eg_just_published', true );
        $class        = '';
        if ( $helper ) {
            $class = 'envira-helper-needed';
        }

        ?>
        <div id="envira-tabs" class="envira-clear <?php echo $class; ?>">
            <?php $this->meta_helper( $post, $gallery_data ); ?>
            <ul id="envira-tabs-nav" class="envira-clear">
                <?php $i = 0; foreach ( (array) $this->get_envira_tab_nav() as $id => $title ) : $class = 0 === $i ? 'envira-active' : ''; ?>
                    <li class="<?php echo $class; ?>"><a href="#envira-tab-<?php echo $id; ?>" title="<?php echo $title; ?>"><?php echo $title; ?></a></li>
                <?php $i++; endforeach; ?>
            </ul>
            <?php $i = 0; foreach ( (array) $this->get_envira_tab_nav() as $id => $title ) : $class = 0 === $i ? 'envira-active' : ''; ?>
                <div id="envira-tab-<?php echo $id; ?>" class="envira-tab envira-clear <?php echo $class; ?>">
                    <?php do_action( 'envira_gallery_tab_' . $id, $post ); ?>
                </div>
            <?php $i++; endforeach; ?>
        </div>
        <?php

    }

    /**
     * Callback for getting all of the tabs for Envira galleries.
     *
     * @since 1.0.0
     *
     * @return array Array of tab information.
     */
    public function get_envira_tab_nav() {

        $tabs = array(
            'images'     => __( 'Images', 'envira-gallery' ),
            'config'     => __( 'Config', 'envira-gallery' ),
            'lightbox'   => __( 'Lightbox', 'envira-gallery' ),
            'thumbnails' => __( 'Thumbnails', 'envira-gallery' ),
        );
        $tabs = apply_filters( 'envira_gallery_tab_nav', $tabs );

        // "Misc" tab is required.
        $tabs['misc'] = __( 'Misc', 'envira-gallery' );

        return $tabs;

    }

    /**
     * Callback for displaying the UI for main images tab.
     *
     * @since 1.0.0
     *
     * @param object $post The current post object.
     */
    public function images_tab( $post ) {

        // Run a filter to contextualize the upload message.
        add_filter( 'gettext', array( $this, 'upload_context' ), 1, 3 );

        $gallery_data = get_post_meta( $post->ID, '_eg_gallery_data', true );
        media_upload_form();

        // Remove the contextual filter.
        remove_filter( 'gettext', array( $this, 'upload_context' ), 1, 3 );

        ?>
        <script type="text/javascript">var post_id = <?php echo $post->ID; ?>, shortform = 3;</script>
        <input type="hidden" name="post_id" id="post_id" value="<?php echo $post->ID; ?>" />
        <div id="media-items" class="hide-if-no-js media-upload-form" style="display:none;"></div>
        <ul id="envira-gallery-output" class="envira-clear">
            <?php if ( ! empty( $gallery_data['gallery'] ) ) : ?>
                <?php foreach ( $gallery_data['gallery'] as $id => $data ) : ?>
                    <?php echo $this->get_gallery_item( $id, $data, $post->ID ); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
        <?php $this->media_library( $post );

    }

    /**
     * Filter the media drag/drop upload text for better contextualization.
     *
     * @since 1.0.0
     *
     * @param string $translated_text  The translated text string.
     * @param string $source_text      The source text string (not yet translated).
     * @param string $domain           The textdomain for the text string.
     * @return string $translated_text Amended translated text.
     */
    public function upload_context( $translated_text, $source_text, $domain ) {

        if ( 'Drop files here' === $source_text ) {
            return __( 'Drop images here', 'envira-gallery' );
        }

        if ( 'Select Files' === $source_text ) {
            return __( 'Select Images', 'envira-gallery' );
        }

        return $translated_text;

    }

    /**
     * Inserts the meta icon for displaying useful gallery meta like shortcode and template tag.
     *
     * @since 1.0.0
     *
     * @param object $post        The current post object.
     * @param array $gallery_data Array of gallery data for the current post.
     * @return null               Return early if this is an auto-draft.
     */
    public function meta_helper( $post, $gallery_data ) {

        if ( isset( $post->post_status ) && 'auto-draft' == $post->post_status ) {
            return;
        }

        // Check for our meta overlay helper.
        $helper = get_post_meta( $post->ID, '_eg_just_published', true );
        $class  = '';
        if ( $helper ) {
            $class = 'envira-helper-active';
            delete_post_meta( $post->ID, '_eg_just_published' );
        }

        ?>
        <div class="envira-meta-helper <?php echo $class; ?>">
            <span class="envira-meta-close-text"><?php _e( '(click the icon to open and close the overlay dialog)', 'envira-gallery' ); ?></span>
            <a href="#" class="envira-meta-icon" title="<?php esc_attr__( 'Click here to view meta information about this gallery.', 'envira-gallery' ); ?>"></a>
            <div class="envira-meta-information">
                <p><?php _e( 'You can place this gallery anywhere into your posts, pages, custom post types or widgets by using the shortcode(s) below:', 'envira-gallery' ); ?></p>
                <code><?php echo '[envira-gallery id="' . $post->ID . '"]'; ?></code>
                <?php if ( ! empty( $gallery_data['config']['slug'] ) ) : ?>
                    <br><code><?php echo '[envira-gallery slug="' . $gallery_data['config']['slug'] . '"]'; ?></code>
                <?php endif; ?>
                <p><?php _e( 'You can also place this gallery into your template files by using the template tag(s) below:', 'envira-gallery' ); ?></p>
                <code><?php echo 'if ( function_exists( \'envira_gallery\' ) ) { envira_gallery( \'' . $post->ID . '\' ); }'; ?></code>
                <?php if ( ! empty( $gallery_data['config']['slug'] ) ) : ?>
                    <br><code><?php echo 'if ( function_exists( \'envira_gallery\' ) ) { envira_gallery( \'' . $gallery_data['config']['slug'] . '\', \'slug\' ); }'; ?></code>
                <?php endif; ?>
            </div>
        </div>
        <?php

    }

    /**
     * Callback for displaying the UI for selecting images from the media library to insert.
     *
     * @since 1.0.0
     *
     * @param object $post The current post object.
     */
    public function media_library( $post ) {

        ?>
        <div id="envira-gallery-upload-ui-wrapper">
            <div id="envira-gallery-upload-ui" class="envira-gallery-image-meta" style="display: none;">
                <div class="media-modal wp-core-ui">
                    <a class="media-modal-close" href="#"><span class="media-modal-icon"></span></a>
                    <div class="media-modal-content">
                        <div class="media-frame envira-gallery-media-frame wp-core-ui hide-menu envira-gallery-meta-wrap">
                            <div class="media-frame-title">
                                <h1><?php _e( 'Insert Images into Gallery', 'envira-gallery' ); ?></h1>
                            </div>
                            <div class="media-frame-router">
                                <div class="media-router">
                                    <a href="#" class="media-menu-item active"><?php _e( 'Images', 'envira-gallery' ); ?></a>
                                    <?php do_action( 'envira_gallery_modal_router', $post ); ?>
                                </div><!-- end .media-router -->
                            </div><!-- end .media-frame-router -->
                            <!-- begin content for inserting slides from media library -->
                            <div id="envira-gallery-select-images">
                                <div class="media-frame-content">
                                    <div class="attachments-browser">
                                        <div class="media-toolbar envira-gallery-library-toolbar">
                                            <div class="media-toolbar-primary">
                                                <span class="spinner envira-gallery-spinner"></span><input type="search" placeholder="<?php esc_attr_e( 'Search', 'envira-gallery' ); ?>" id="envira-gallery-gallery-search" class="search" value="" />
                                            </div>
                                            <div class="media-toolbar-secondary">
                                                <a class="button media-button button-large button-secodary envira-gallery-load-library" href="#" data-envira-gallery-offset="20"><?php _e( 'Load More Images from Library', 'envira-gallery' ); ?></a></a><span class="spinner envira-gallery-spinner"></span>
                                            </div>
                                        </div>
                                        <?php $library = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'posts_per_page' => 20 ) ); ?>
                                        <?php if ( $library ) : ?>
                                        <ul class="attachments envira-gallery-gallery">
                                        <?php foreach ( (array) $library as $image ) :
                                            $has_gallery = get_post_meta( $image->ID, '_eg_has_gallery', true );
                                            $class       = $has_gallery && in_array( $post->ID, (array) $has_gallery ) ? ' selected envira-gallery-in-gallery' : ''; ?>
                                            <li class="attachment<?php echo $class; ?>" data-attachment-id="<?php echo absint( $image->ID ); ?>">
                                                <div class="attachment-preview landscape">
                                                    <div class="thumbnail">
                                                        <div class="centered">
                                                            <?php $src = wp_get_attachment_image_src( $image->ID, 'thumbnail' ); ?>
                                                            <img src="<?php echo esc_url( $src[0] ); ?>" />
                                                        </div>
                                                    </div>
                                                    <a class="check" href="#"><div class="media-modal-icon"></div></a>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                        </ul><!-- end .envira-gallery-meta -->
                                        <?php endif; ?>
                                        <div class="media-sidebar">
                                            <div class="envira-gallery-meta-sidebar">
                                                <h3><?php _e( 'Helpful Tips', 'envira-gallery' ); ?></h3>
                                                <strong><?php _e( 'Selecting Images', 'envira-gallery' ); ?></strong>
                                                <p><?php _e( 'You can insert any image from your Media Library into your gallery. If the image you want to insert is not shown on the screen, you can either click on the "Load More Images from Library" button to load more images or use the search box to find the images you are looking for.', 'envira-gallery' ); ?></p>
                                            </div><!-- end .envira-gallery-meta-sidebar -->
                                        </div><!-- end .media-sidebar -->
                                    </div><!-- end .attachments-browser -->
                                </div><!-- end .media-frame-content -->
                            </div><!-- end #envira-gallery-image-slides -->
                            <!-- end content for inserting slides from media library -->
                            <div class="media-frame-toolbar">
                                <div class="media-toolbar">
                                    <div class="media-toolbar-primary">
                                        <a href="#" class="envira-gallery-media-insert button media-button button-large button-primary media-button-insert" title="<?php esc_attr_e( 'Insert Images into Gallery', 'envira-gallery' ); ?>"><?php _e( 'Insert Images into Gallery', 'envira-gallery' ); ?></a>
                                    </div><!-- end .media-toolbar-primary -->
                                </div><!-- end .media-toolbar -->
                            </div><!-- end .media-frame-toolbar -->
                        </div><!-- end .media-frame -->
                    </div><!-- end .media-modal-content -->
                </div><!-- end .media-modal -->
                <div class="media-modal-backdrop"></div>
            </div><!-- end .envira-gallery-image-meta -->
        </div><!-- end #envira-gallery-upload-ui-wrapper-->
        <?php

    }

    /**
     * Callback for displaying the UI for setting gallery config options.
     *
     * @since 1.0.0
     *
     * @param object $post The current post object.
     */
    public function config_tab( $post ) {

        ?>
        <div id="envira-config">
            <p class="envira-intro"><?php _e( 'The settings below adjust the basic configuration options for the gallery lightbox display.', 'envira-gallery' ); ?></p>
            <table class="form-table">
                <tbody>
                    <tr id="envira-config-columns-box">
                        <th scope="row">
                            <label for="envira-config-columns"><?php _e( 'Number of Gallery Columns', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <select id="envira-config-columns" name="_envira_gallery[columns]">
                                <?php foreach ( (array) $this->get_columns() as $i => $data ) : ?>
                                    <option value="<?php echo $data['value']; ?>"<?php selected( $data['value'], $this->get_config( 'columns', $this->get_config_default( 'columns' ) ) ); ?>><?php echo $data['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'Determines the number of columns in the gallery.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <tr id="envira-config-gallery-theme-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-gallery-theme"><?php _e( 'Gallery Theme', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <select id="envira-config-gallery-theme" name="_envira_gallery[gallery_theme]">
                                <?php foreach ( (array) $this->get_gallery_themes() as $i => $data ) : ?>
                                    <option value="<?php echo $data['value']; ?>"<?php selected( $data['value'], $this->get_config( 'gallery_theme', $this->get_config_default( 'gallery_theme' ) ) ); ?>><?php echo $data['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'Sets the theme for the gallery display.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <tr id="envira-config-lightbox-theme-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-lightbox-theme"><?php _e( 'Gallery Lightbox Theme', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <select id="envira-config-lightbox-theme" name="_envira_gallery[lightbox_theme]">
                                <?php foreach ( (array) $this->get_lightbox_themes() as $i => $data ) : ?>
                                    <option value="<?php echo $data['value']; ?>"<?php selected( $data['value'], $this->get_config( 'lightbox_theme', $this->get_config_default( 'lightbox_theme' ) ) ); ?>><?php echo $data['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'Sets the theme for the gallery lightbox display.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <tr id="envira-config-gutter-box">
                        <th scope="row">
                            <label for="envira-config-gutter"><?php _e( 'Column Gutter Width', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-gutter" type="number" name="_envira_gallery[gutter]" value="<?php echo $this->get_config( 'gutter', $this->get_config_default( 'gutter' ) ); ?>" />
                            <p class="description"><?php _e( 'Sets the space between the columns (defaults to 10).', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <tr id="envira-config-margin-box">
                        <th scope="row">
                            <label for="envira-config-margin"><?php _e( 'Margin Below Each Image', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-margin" type="number" name="_envira_gallery[margin]" value="<?php echo $this->get_config( 'margin', $this->get_config_default( 'margin' ) ); ?>" />
                            <p class="description"><?php _e( 'Sets the space below each item in the gallery.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <tr id="envira-config-crop-box">
                        <th scope="row">
                            <label for="envira-config-crop"><?php _e( 'Crop Images in Gallery?', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-crop" type="checkbox" name="_envira_gallery[crop]" value="<?php echo $this->get_config( 'crop', $this->get_config_default( 'crop' ) ); ?>" <?php checked( $this->get_config( 'crop', $this->get_config_default( 'crop' ) ), 1 ); ?> />
                            <span class="description"><?php _e( 'Enables or disables image cropping for the main gallery images.', 'envira-gallery' ); ?></span>
                        </td>
                    </tr>
                    <tr id="envira-config-crop-size-box" style="display:none;">
                        <th scope="row">
                            <label for="envira-config-crop-width"><?php _e( 'Crop Dimensions', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-crop-width" type="number" name="_envira_gallery[crop_width]" value="<?php echo $this->get_config( 'crop_width', $this->get_config_default( 'crop_width' ) ); ?>" <?php checked( $this->get_config( 'crop_width', $this->get_config_default( 'crop_width' ) ), 1 ); ?> /> &#215; <input id="envira-config-crop-height" type="number" name="_envira_gallery[crop_height]" value="<?php echo $this->get_config( 'crop_height', $this->get_config_default( 'crop_height' ) ); ?>" <?php checked( $this->get_config( 'crop_height', $this->get_config_default( 'crop_height' ) ), 1 ); ?> />
                            <p class="description"><?php _e( 'You should adjust these dimensions based on the number of columns in your gallery.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <tr id="envira-config-mobile-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-mobile"><?php _e( 'Create Mobile Gallery Images?', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-mobile" type="checkbox" name="_envira_gallery[mobile]" value="<?php echo $this->get_config( 'mobile', $this->get_config_default( 'mobile' ) ); ?>" <?php checked( $this->get_config( 'mobile', $this->get_config_default( 'mobile' ) ), 1 ); ?> />
                            <span class="description"><?php _e( 'Enables or disables creating specific images for mobile devices.', 'envira-gallery' ); ?></span>
                        </td>
                    </tr>
                    <tr id="envira-config-mobile-size-box" class="envira-lite-disabled" style="display:none;">
                        <th scope="row">
                            <label for="envira-config-mobile-width"><?php _e( 'Mobile Dimensions', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-mobile-width" type="number" name="_envira_gallery[mobile_width]" value="<?php echo $this->get_config( 'mobile_width', $this->get_config_default( 'mobile_width' ) ); ?>" <?php checked( $this->get_config( 'mobile_width', $this->get_config_default( 'mobile_width' ) ), 1 ); ?> /> &#215; <input id="envira-config-mobile-height" type="number" name="_envira_gallery[mobile_height]" value="<?php echo $this->get_config( 'mobile_height', $this->get_config_default( 'mobile_height' ) ); ?>" <?php checked( $this->get_config( 'mobile_height', $this->get_config_default( 'mobile_height' ) ), 1 ); ?> />
                            <p class="description"><?php _e( 'These will be the sizes used for images displayed on mobile devices.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <?php do_action( 'envira_gallery_config_box', $post ); ?>
                </tbody>
            </table>
        </div>
        <?php

    }

    /**
     * Callback for displaying the UI for setting gallery lightbox options.
     *
     * @since 1.0.0
     *
     * @param object $post The current post object.
     */
    public function lightbox_tab( $post ) {

        ?>
        <div id="envira-lightbox">
            <p class="envira-intro"><?php _e( 'The settings below adjust the lightbox outputs and displays.', 'envira-gallery' ); ?></p>
            <table class="form-table">
                <tbody>
                    <tr id="envira-config-lightbox-title-display-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-lightbox-title-display"><?php _e( 'Gallery Title Position', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <select id="envira-config-lightbox-title-display" name="_envira_gallery[title_display]">
                                <?php foreach ( (array) $this->get_title_displays() as $i => $data ) : ?>
                                    <option value="<?php echo $data['value']; ?>"<?php selected( $data['value'], $this->get_config( 'title_display', $this->get_config_default( 'title_display' ) ) ); ?>><?php echo $data['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'Sets the display of the lightbox title.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <tr id="envira-config-lightbox-arrows-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-lightbox-arrows"><?php _e( 'Enable Gallery Arrows?', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-lightbox-arrows" type="checkbox" name="_envira_gallery[arrows]" value="<?php echo $this->get_config( 'arrows', $this->get_config_default( 'arrows' ) ); ?>" <?php checked( $this->get_config( 'arrows', $this->get_config_default( 'arrows' ) ), 1 ); ?> />
                            <span class="description"><?php _e( 'Enables or disables the gallery lightbox navigation arrows.', 'envira-gallery' ); ?></span>
                        </td>
                    </tr>
                    <tr id="envira-config-lightbox-keyboard-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-lightbox-keyboard"><?php _e( 'Enable Keyboard Navigation?', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-lightbox-keyboard" type="checkbox" name="_envira_gallery[keyboard]" value="<?php echo $this->get_config( 'keyboard', $this->get_config_default( 'keyboard' ) ); ?>" <?php checked( $this->get_config( 'keyboard', $this->get_config_default( 'keyboard' ) ), 1 ); ?> />
                            <span class="description"><?php _e( 'Enables or disables keyboard navigation in the gallery lightbox.', 'envira-gallery' ); ?></span>
                        </td>
                    </tr>
                    <tr id="envira-config-lightbox-mousewheel-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-lightbox-mousewheel"><?php _e( 'Enable Mousewheel Navigation?', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-lightbox-mousewheel" type="checkbox" name="_envira_gallery[mousewheel]" value="<?php echo $this->get_config( 'mousewheel', $this->get_config_default( 'mousewheel' ) ); ?>" <?php checked( $this->get_config( 'mousewheel', $this->get_config_default( 'mousewheel' ) ), 1 ); ?> />
                            <span class="description"><?php _e( 'Enables or disables mousewheel navigation in the gallery.', 'envira-gallery' ); ?></span>
                        </td>
                    </tr>
                    <tr id="envira-config-lightbox-toolbar-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-lightbox-toolbar"><?php _e( 'Enable Gallery Toolbar?', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-lightbox-toolbar" type="checkbox" name="_envira_gallery[toolbar]" value="<?php echo $this->get_config( 'toolbar', $this->get_config_default( 'toolbar' ) ); ?>" <?php checked( $this->get_config( 'toolbar', $this->get_config_default( 'toolbar' ) ), 1 ); ?> />
                            <span class="description"><?php _e( 'Enables or disables the gallery lightbox toolbar.', 'envira-gallery' ); ?></span>
                        </td>
                    </tr>
                    <tr id="envira-config-lightbox-toolbar-position-box" class="envira-lite-disabled" style="display:none;">
                        <th scope="row">
                            <label for="envira-config-lightbox-toolbar-position"><?php _e( 'Gallery Toolbar Position', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <select id="envira-config-lightbox-toolbar-position" name="_envira_gallery[toolbar_position]">
                                <?php foreach ( (array) $this->get_toolbar_positions() as $i => $data ) : ?>
                                    <option value="<?php echo $data['value']; ?>"<?php selected( $data['value'], $this->get_config( 'toolbar_position', $this->get_config_default( 'toolbar_position' ) ) ); ?>><?php echo $data['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'Sets the position of the lightbox toolbar.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <tr id="envira-config-lightbox-aspect-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-lightbox-aspect"><?php _e( 'Keep Aspect Ratio?', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-lightbox-toolbar" type="checkbox" name="_envira_gallery[aspect]" value="<?php echo $this->get_config( 'aspect', $this->get_config_default( 'aspect' ) ); ?>" <?php checked( $this->get_config( 'aspect', $this->get_config_default( 'aspect' ) ), 1 ); ?> />
                            <span class="description"><?php _e( 'If enabled, images will always resize based on the original aspect ratio.', 'envira-gallery' ); ?></span>
                        </td>
                    </tr>
                    <tr id="envira-config-lightbox-loop-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-lightbox-loop"><?php _e( 'Loop Gallery Navigation?', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-lightbox-loop" type="checkbox" name="_envira_gallery[loop]" value="<?php echo $this->get_config( 'loop', $this->get_config_default( 'loop' ) ); ?>" <?php checked( $this->get_config( 'loop', $this->get_config_default( 'loop' ) ), 1 ); ?> />
                            <span class="description"><?php _e( 'Enables or disables infinite navigation cycling of the lightbox gallery.', 'envira-gallery' ); ?></span>
                        </td>
                    </tr>
                    <?php do_action( 'envira_gallery_lightbox_box', $post ); ?>
                </tbody>
            </table>
        </div>
        <?php

    }

    /**
     * Callback for displaying the UI for setting gallery thumbnail options.
     *
     * @since 1.0.0
     *
     * @param object $post The current post object.
     */
    public function thumbnails_tab( $post ) {

        ?>
        <div id="envira-thumbnails">
            <p class="envira-intro"><?php _e( 'If enabled, thumbnails are generated automatically inside the lightbox. The settings below adjust the thumbnail views for the gallery lightbox display.', 'envira-gallery' ); ?></p>
            <table class="form-table">
                <tbody>
                    <tr id="envira-config-thumbnails-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-thumbnails"><?php _e( 'Enable Gallery Thumbnails?', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-thumbnails" type="checkbox" name="_envira_gallery[thumbnails]" value="<?php echo $this->get_config( 'thumbnails', $this->get_config_default( 'thumbnails' ) ); ?>" <?php checked( $this->get_config( 'thumbnails', $this->get_config_default( 'thumbnails' ) ), 1 ); ?> />
                            <span class="description"><?php _e( 'Enables or disables the gallery lightbox thumbnails.', 'envira-gallery' ); ?></span>
                        </td>
                    </tr>
                    <tr id="envira-config-thumbnails-width-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-thumbnails-width"><?php _e( 'Gallery Thumbnails Width', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-thumbnails-width" type="number" name="_envira_gallery[thumbnails_width]" value="<?php echo $this->get_config( 'thumbnails_width', $this->get_config_default( 'thumbnails_width' ) ); ?>" />
                            <p class="description"><?php _e( 'Sets the width of the lightbox thumbnails.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <tr id="envira-config-thumbnails-height-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-thumbnails-height"><?php _e( 'Gallery Thumbnails Height', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-thumbnails-height" type="number" name="_envira_gallery[thumbnails_height]" value="<?php echo $this->get_config( 'thumbnails_height', $this->get_config_default( 'thumbnails_height' ) ); ?>" />
                            <p class="description"><?php _e( 'Sets the height of the lightbox thumbnails.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <tr id="envira-config-thumbnails-position-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-thumbnails-position"><?php _e( 'Gallery Thumbnails Position', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <select id="envira-config-thumbnails-position" name="_envira_gallery[thumbnails_position]">
                                <?php foreach ( (array) $this->get_thumbnail_positions() as $i => $data ) : ?>
                                    <option value="<?php echo $data['value']; ?>"<?php selected( $data['value'], $this->get_config( 'thumbnails_position', $this->get_config_default( 'thumbnails_position' ) ) ); ?>><?php echo $data['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'Sets the position of the lightbox thumbnails.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <?php do_action( 'envira_gallery_thumbnails_box', $post ); ?>
                </tbody>
            </table>
        </div>
        <?php

    }

    /**
     * Callback for displaying the UI for setting gallery miscellaneous options.
     *
     * @since 1.0.0
     *
     * @param object $post The current post object.
     */
    public function misc_tab( $post ) {

        ?>
        <div id="envira-misc">
            <p class="envira-intro"><?php _e( 'The settings below adjust the miscellaneous settings for the gallery lightbox display.', 'envira-gallery' ); ?></p>
            <table class="form-table">
                <tbody>
                    <tr id="envira-config-title-box">
                        <th scope="row">
                            <label for="envira-config-title"><?php _e( 'Gallery Title', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-title" type="text" name="_envira_gallery[title]" value="<?php echo $this->get_config( 'title', $this->get_config_default( 'title' ) ); ?>" />
                            <p class="description"><?php _e( 'Internal gallery title for identification in the admin.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <tr id="envira-config-slug-box">
                        <th scope="row">
                            <label for="envira-config-slug"><?php _e( 'Gallery Slug', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <input id="envira-config-slug" type="text" name="_envira_gallery[slug]" value="<?php echo $this->get_config( 'slug', $this->get_config_default( 'slug' ) ); ?>" />
                            <p class="description"><?php _e( '<strong>Unique</strong> internal gallery slug for identification and advanced gallery queries.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <tr id="envira-config-classes-box">
                        <th scope="row">
                            <label for="envira-config-classes"><?php _e( 'Custom Gallery Classes', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <textarea id="envira-config-classes" rows="5" cols="75" name="_envira_gallery[classes]" placeholder="<?php _e( 'Enter custom gallery CSS classes here, one per line.', 'envira-gallery' ); ?>"><?php echo implode( "\n", (array) $this->get_config( 'classes', $this->get_config_default( 'classes' ) ) ); ?></textarea>
                            <p class="description"><?php _e( 'Adds custom CSS classes to this gallery. Enter one class per line.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <tr id="envira-config-import-export-box" class="envira-lite-disabled">
                        <th scope="row">
                            <label for="envira-config-import-gallery"><?php _e( 'Import/Export Gallery', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <form></form>
                            <?php $import_url = 'auto-draft' == $post->post_status ? add_query_arg( array( 'post' => $post->ID, 'action' => 'edit', 'envira-gallery-imported' => true ), admin_url( 'post.php' ) ) : add_query_arg( 'envira-gallery-imported', true ); ?>
                            <form action="<?php echo $import_url; ?>" id="envira-config-import-gallery-form" class="envira-gallery-import-form" method="post" enctype="multipart/form-data">
                                <input id="envira-config-import-gallery" type="file" name="envira_import_gallery" />
                                <input type="hidden" name="envira_import" value="1" />
                                <input type="hidden" name="envira_post_id" value="<?php echo $post->ID; ?>" />
                                <?php wp_nonce_field( 'envira-gallery-import', 'envira-gallery-import' ); ?>
                                <?php submit_button( __( 'Import Gallery', 'envira-gallery' ), 'secondary', 'envira-gallery-import-submit', false ); ?>
                                <span class="spinner envira-gallery-spinner"></span>
                            </form>
                            <form id="envira-config-export-gallery-form" method="post">
                                <input type="hidden" name="envira_export" value="1" />
                                <input type="hidden" name="envira_post_id" value="<?php echo $post->ID; ?>" />
                                <?php wp_nonce_field( 'envira-gallery-export', 'envira-gallery-export' ); ?>
                                <?php submit_button( __( 'Export Gallery', 'envira-gallery' ), 'secondary', 'envira-gallery-export-submit', false ); ?>
                            </form>
                        </td>
                    </tr>
                    <?php do_action( 'envira_tab_misc_box', $post ); ?>
                </tbody>
            </table>
        </div>
        <?php

    }

    /**
     * Callback for saving values from Envira metaboxes.
     *
     * @since 1.0.0
     *
     * @param int $post_id The current post ID.
     * @param object $post The current post object.
     */
    public function save_meta_boxes( $post_id, $post ) {

        // Bail out if we fail a security check.
        if ( ! isset( $_POST['envira-gallery'] ) || ! wp_verify_nonce( $_POST['envira-gallery'], 'envira-gallery' ) || ! isset( $_POST['_envira_gallery'] ) ) {
            return;
        }

        // Bail out if running an autosave, ajax, cron or revision.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Bail out if the user doesn't have the correct permissions to update the slider.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // If the post has just been published for the first time, set meta field for the gallery meta overlay helper.
        if ( isset( $post->post_date ) && isset( $post->post_modified ) && $post->post_date === $post->post_modified ) {
            update_post_meta( $post_id, '_eg_just_published', true );
        }

        // Sanitize all user inputs.
        $settings = get_post_meta( $post_id, '_eg_gallery_data', true );
        if ( empty( $settings ) ) {
            $settings = array();
        }

        // If the ID of the gallery is not set or is lost, replace it now.
        if ( empty( $settings['id'] ) || ! $settings['id'] ) {
            $settings['id'] = $post_id;
        }

        // Save the config settings.
        $settings['config']['columns']     = preg_replace( '#[^a-z0-9-_]#', '', $_POST['_envira_gallery']['columns'] );
        $settings['config']['gutter']      = absint( $_POST['_envira_gallery']['gutter'] );
        $settings['config']['margin']      = absint( $_POST['_envira_gallery']['margin'] );
        $settings['config']['crop']        = isset( $_POST['_envira_gallery']['crop'] ) ? 1 : 0;
        $settings['config']['crop_width']  = absint( $_POST['_envira_gallery']['crop_width'] );
        $settings['config']['crop_height'] = absint( $_POST['_envira_gallery']['crop_height'] );
        $settings['config']['classes']     = explode( "\n", $_POST['_envira_gallery']['classes'] );
        $settings['config']['title']       = trim( strip_tags( $_POST['_envira_gallery']['title'] ) );
        $settings['config']['slug']        = sanitize_text_field( $_POST['_envira_gallery']['slug'] );

        // If on an envira post type, map the title and slug of the post object to the custom fields if no value exists yet.
        if ( isset( $post->post_type ) && 'envira' == $post->post_type ) {
            if ( empty( $settings['config']['title'] ) ) {
                $settings['config']['title'] = trim( strip_tags( $post->post_title ) );
            }

            if ( empty( $settings['config']['slug'] ) ) {
                $settings['config']['slug'] = sanitize_text_field( $post->post_name );
            }
        }

        // Provide a filter to override settings.
        $settings = apply_filters( 'envira_gallery_save_settings', $settings, $post_id, $post );

        // Update the post meta.
        update_post_meta( $post_id, '_eg_gallery_data', $settings );

        // Change states of images in gallery from pending to active.
        $this->change_gallery_states( $post_id );

        // If the crop option is checked, crop images accordingly.
        if ( isset( $settings['config']['crop'] ) && $settings['config']['crop'] ) {
            $args = apply_filters( 'envira_gallery_crop_image_args',
                array(
                    'position' => 'c',
                    'width'    => $this->get_config( 'crop_width', $this->get_config_default( 'crop_width' ) ),
                    'height'   => $this->get_config( 'crop_height', $this->get_config_default( 'crop_height' ) ),
                    'quality'  => 100,
                    'retina'   => false
                )
            );
            $this->crop_images( $args, $post_id );
        }

        // Finally, flush all gallery caches to ensure everything is up to date.
        $this->flush_gallery_caches( $post_id, $settings['config']['slug'] );

        // Update the limit checker.
        Envira_Gallery_Common_Admin_Lite::get_instance()->update_limit( $post_id );

    }

    /**
     * Helper method for retrieving the gallery layout for an item in the admin.
     *
     * @since 1.0.0
     *
     * @param int $id The  ID of the item to retrieve.
     * @param array $data  Array of data for the item.
     * @param int $post_id The current post ID.
     * @return string The  HTML output for the gallery item.
     */
    public function get_gallery_item( $id, $data, $post_id = 0 ) {

        $thumbnail = wp_get_attachment_image_src( $id, 'thumbnail' ); ob_start(); ?>
        <li id="<?php echo $id; ?>" class="envira-gallery-image envira-gallery-status-<?php echo $data['status']; ?>" data-envira-gallery-image="<?php echo $id; ?>">
            <img src="<?php echo esc_url( $thumbnail[0] ); ?>" alt="<?php esc_attr_e( $data['alt'] ); ?>" />
            <a href="#" class="envira-gallery-remove-image" title="<?php esc_attr_e( 'Remove Image from Gallery?', 'envira-gallery' ); ?>"></a>
            <a href="#" class="envira-gallery-modify-image" title="<?php esc_attr_e( 'Modify Image', 'envira-gallery' ); ?>"></a>
            <?php echo $this->get_gallery_item_meta( $id, $data, $post_id ); ?>
        </li>
        <?php
        return ob_get_clean();

    }

    /**
     * Helper method for retrieving the gallery metadata editing modal.
     *
     * @since 1.0.0
     *
     * @param int $id      The ID of the item to retrieve.
     * @param array $data  Array of data for the item.
     * @param int $post_id The current post ID.
     * @return string      The HTML output for the gallery item.
     */
    public function get_gallery_item_meta( $id, $data, $post_id ) {

        ob_start(); ?>
        <div id="envira-gallery-meta-<?php echo $id; ?>" class="envira-gallery-meta-container" style="display:none;">
            <div class="media-modal wp-core-ui">
                <a class="media-modal-close" href="#"><span class="media-modal-icon"></span></a>
                <div class="media-modal-content">
                    <div class="media-frame envira-gallery-media-frame wp-core-ui hide-menu hide-router envira-gallery-meta-wrap">
                        <div class="media-frame-title">
                            <h1><?php _e( 'Edit Metadata', 'envira-gallery' ); ?></h1>
                        </div>
                        <div class="media-frame-content">
                            <div class="attachments-browser">
                                <div class="envira-gallery-meta attachments">
                                    <?php do_action( 'envira_gallery_before_meta_table', $id, $data, $post_id ); ?>
                                    <table id="envira-gallery-meta-table-<?php echo $id; ?>" class="form-table envira-gallery-meta-table" data-envira-meta-id="<?php echo $id; ?>">
                                        <tbody>
                                            <?php do_action( 'envira_gallery_before_meta_settings', $id, $data, $post_id ); ?>
                                            <tr id="envira-gallery-title-box-<?php echo $id; ?>" valign="middle">
                                                <th scope="row"><label for="envira-gallery-title-<?php echo $id; ?>"><?php _e( 'Image Title', 'envira-gallery' ); ?></label></th>
                                                <td>
                                                    <?php wp_editor( $data['title'], 'envira-gallery-title-' . $id, array( 'media_buttons' => false, 'tinymce' => false, 'textarea_name' => '_envira_gallery[meta_title]', 'quicktags' => array( 'buttons' => 'strong,em,link,ul,ol,li,close' ) ) ); ?>
                                                    <p class="description"><?php _e( 'Image titles can take any type of HTML.', 'envira-gallery' ); ?></p>
                                                </td>
                                            </tr>
                                            <tr id="envira-gallery-alt-box-<?php echo $id; ?>" valign="middle">
                                                <th scope="row"><label for="envira-gallery-alt-<?php echo $id; ?>"><?php _e( 'Image Alt Text', 'envira-gallery' ); ?></label></th>
                                                <td>
                                                    <input id="envira-gallery-alt-<?php echo $id; ?>" class="envira-gallery-alt" type="text" name="_envira_gallery[meta_alt]" value="<?php echo esc_attr( $data['alt'] ); ?>" data-envira-meta="alt" />
                                                    <p class="description"><?php _e( 'The image alt text is used for SEO. You should probably fill this one out!', 'envira-gallery' ); ?></p>
                                                </td>
                                            </tr>
                                            <tr id="envira-gallery-link-box-<?php echo $id; ?>" class="envira-gallery-link-cell" valign="middle">
                                                <th scope="row"><label for="envira-gallery-link-<?php echo $id; ?>"><?php _e( 'Image Hyperlink', 'envira-gallery' ); ?></label></th>
                                                <td>
                                                    <input id="envira-gallery-link-<?php echo $id; ?>" class="envira-gallery-link" type="text" name="_envira_gallery[meta_link]" value="<?php echo esc_url( $data['link'] ); ?>" data-envira-meta="link" />
                                                    <p class="description"><?php _e( 'The image hyperlink determines what opens up in the lightbox once the image is clicked. Defaults to a larger version of itself.', 'envira-gallery' ); ?></p>
                                                </td>
                                            </tr>
                                            <?php do_action( 'envira_gallery_after_meta_settings', $id, $data, $post_id ); ?>
                                        </tbody>
                                    </table>
                                    <?php do_action( 'envira_gallery_after_meta_table', $id, $data, $post_id ); ?>
                                </div><!-- end .envira-gallery-meta -->
                                <div class="media-sidebar">
                                    <div class="envira-gallery-meta-sidebar">
                                        <h3><?php _e( 'Helpful Tips', 'envira-gallery' ); ?></h3>
                                        <strong><?php _e( 'Image Titles', 'envira-gallery' ); ?></strong>
                                        <p><?php _e( 'Image titles can take any type of HTML. You can adjust the position of the titles in the main Lightbox settings.', 'envira-gallery' ); ?></p>
                                        <strong><?php _e( 'Image Hyperlinks', 'envira-gallery' ); ?></strong>
                                        <p><?php _e( 'The image hyperlink field is used when you click on an image in the gallery. It determines what is displayed in the lightbox view. It could be a larger version of the image, a video, or some other form of content.', 'envira-gallery' ); ?></p>
                                        <strong><?php _e( 'Saving and Exiting', 'envira-gallery' ); ?></strong>
                                        <p class="no-margin"><?php _e( 'Click on the blue button below to save your image metadata. You can close this window by either clicking on the "X" above or hitting the <code>esc</code> key on your keyboard.', 'envira-gallery' ); ?></p>
                                    </div><!-- end .envira-gallery-meta-sidebar -->
                                </div><!-- end .media-sidebar -->
                            </div><!-- end .attachments-browser -->
                        </div><!-- end .media-frame-content -->
                        <div class="media-frame-toolbar">
                            <div class="media-toolbar">
                                <div class="media-toolbar-primary">
                                    <a href="#" class="envira-gallery-meta-submit button media-button button-large button-primary media-button-insert" title="<?php esc_attr_e( 'Save Metadata', 'envira-gallery' ); ?>" data-envira-gallery-item="<?php echo $id; ?>"><?php _e( 'Save Metadata', 'envira-gallery' ); ?></a>
                                </div><!-- end .media-toolbar-primary -->
                            </div><!-- end .media-toolbar -->
                        </div><!-- end .media-frame-toolbar -->
                    </div><!-- end .media-frame -->
                </div><!-- end .media-modal-content -->
            </div><!-- end .media-modal -->
            <div class="media-modal-backdrop"></div>
        </div>
        <?php
        return ob_get_clean();

    }

    /**
     * Helper method to change a gallery state from pending to active. This is done
     * automatically on post save. For previewing galleries before publishing,
     * simply click the "Preview" button and Envira will load all the images present
     * in the gallery at that time.
     *
     * @since 1.0.0
     *
     * @param int $id The current post ID.
     */
    public function change_gallery_states( $post_id ) {

        $gallery_data = get_post_meta( $post_id, '_eg_gallery_data', true );
        if ( ! empty( $gallery_data['gallery'] ) ) {
            foreach ( (array) $gallery_data['gallery'] as $id => $item ) {
                $gallery_data['gallery'][$id]['status'] = 'active';
            }
        }

        update_post_meta( $post_id, '_eg_gallery_data', $gallery_data );

    }

    /**
     * Helper method to crop gallery images to the specified sizes.
     *
     * @since 1.0.0
     *
     * @param array $args  Array of args used when cropping the images.
     * @param int $post_id The current post ID.
     */
    public function crop_images( $args, $post_id ) {

        // Gather all available images to crop.
        $gallery_data = get_post_meta( $post_id, '_eg_gallery_data', true );
        $images       = ! empty( $gallery_data['gallery'] ) ? $gallery_data['gallery'] : false;
        $common       = Envira_Gallery_Common_Lite::get_instance();

        // Loop through the images and crop them.
        if ( $images ) {
            // Increase the time limit to account for large image sets and suspend cache invalidations.
            set_time_limit( 0 );
            wp_suspend_cache_invalidation( true );

            foreach ( $images as $id => $item ) {
                // Get the full image attachment. If it does not return the data we need, skip over it.
                $image = wp_get_attachment_image_src( $id, 'full' );
                if ( ! is_array( $image ) ) {
                    continue;
                }

                // Generate the cropped image.
                $cropped_image = $common->resize_image( $image[0], $args['width'], $args['height'], true, $args['position'], $args['quality'], $args['retina'] );

                // If there is an error, possibly output error message, otherwise woot!
                if ( is_wp_error( $cropped_image ) ) {
                    // If debugging is defined, print out the error.
                    if ( defined( 'ENVIRA_GALLERY_CROP_DEBUG' ) && ENVIRA_GALLERY_CROP_DEBUG ) {
                        echo '<pre>' . var_export( $cropped_image->get_error_message(), true ) . '</pre>';
                    }
                }
            }

            // Turn off cache suspension and flush the cache to remove any cache inconsistencies.
            wp_suspend_cache_invalidation( false );
            wp_cache_flush();
        }

    }

    /**
     * Helper method to flush gallery caches once a gallery is updated.
     *
     * @since 1.0.0
     *
     * @param int $post_id The current post ID.
     * @param string $slug The unique gallery slug.
     */
    public function flush_gallery_caches( $post_id, $slug ) {

        Envira_Gallery_Common_Lite::get_instance()->flush_gallery_caches( $post_id, $slug );

    }

    /**
     * Helper method for retrieving config values.
     *
     * @since 1.0.0
     *
     * @global int $id        The current post ID.
     * @global object $post   The current post object.
     * @param string $key     The config key to retrieve.
     * @param string $default A default value to use.
     * @return string         Key value on success, empty string on failure.
     */
    public function get_config( $key, $default = false ) {

        global $id, $post;

        // Get the current post ID.
        $post_id = ( null === $id ) ? $post->ID : $id;

        $settings = get_post_meta( $post_id, '_eg_gallery_data', true );
        if ( isset( $settings['config'][$key] ) ) {
            return $settings['config'][$key];
        } else {
            return $default ? $default : '';
        }

    }

    /**
     * Helper method for setting default config values.
     *
     * @since 1.0.0
     *
     * @param string $key The default config key to retrieve.
     * @return string Key value on success, false on failure.
     */
    public function get_config_default( $key ) {

        $instance = Envira_Gallery_Common_Lite::get_instance();
        return $instance->get_config_default( $key );

    }

    /**
     * Helper method for retrieving columns.
     *
     * @since 1.0.0
     *
     * @return array Array of column data.
     */
    public function get_columns() {

        $instance = Envira_Gallery_Common_Lite::get_instance();
        return $instance->get_columns();

    }

    /**
     * Helper method for retrieving gallery themes.
     *
     * @since 1.0.0
     *
     * @return array Array of gallery theme data.
     */
    public function get_gallery_themes() {

        $instance = Envira_Gallery_Common_Lite::get_instance();
        return $instance->get_gallery_themes();

    }

    /**
     * Helper method for retrieving lightbox themes.
     *
     * @since 1.0.0
     *
     * @return array Array of lightbox theme data.
     */
    public function get_lightbox_themes() {

        $instance = Envira_Gallery_Common_Lite::get_instance();
        return $instance->get_lightbox_themes();

    }

    /**
     * Helper method for retrieving title displays.
     *
     * @since 1.0.0
     *
     * @return array Array of title display data.
     */
    public function get_title_displays() {

        $instance = Envira_Gallery_Common_Lite::get_instance();
        return $instance->get_title_displays();

    }

    /**
     * Helper method for retrieving toolbar positions.
     *
     * @since 1.0.0
     *
     * @return array Array of toolbar position data.
     */
    public function get_toolbar_positions() {

        $instance = Envira_Gallery_Common_Lite::get_instance();
        return $instance->get_toolbar_positions();

    }

    /**
     * Helper method for retrieving thumbnail positions.
     *
     * @since 1.0.0
     *
     * @return array Array of thumbnail position data.
     */
    public function get_thumbnail_positions() {

        $instance = Envira_Gallery_Common_Lite::get_instance();
        return $instance->get_thumbnail_positions();

    }

    /**
     * Returns the singleton instance of the class.
     *
     * @since 1.0.0
     *
     * @return object The Envira_Gallery_Metaboxes_Lite object.
     */
    public static function get_instance() {

        if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Envira_Gallery_Metaboxes_Lite ) ) {
            self::$instance = new Envira_Gallery_Metaboxes_Lite();
        }

        return self::$instance;

    }

}

// Load the metabox class.
$envira_gallery_metaboxes_lite = Envira_Gallery_Metaboxes_Lite::get_instance();