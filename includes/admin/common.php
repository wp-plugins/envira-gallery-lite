<?php
/**
 * Common admin class.
 *
 * @since 1.0.0
 *
 * @package Envira_Gallery_Lite
 * @author  Thomas Griffin
 */
class Envira_Gallery_Common_Admin_Lite {

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
     * Holds any plugin error messages.
     *
     * @since 1.0.0
     *
     * @var array
     */
    public $errors = array();

    /**
     * Holds the base class object.
     *
     * @since 1.0.0
     *
     * @var object
     */
    public $base;

    /**
     * Holds the submenu pagehook.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public $hook;

    /**
     * Primary class constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {

        // Load the base class object.
        $this->base = Envira_Gallery_Lite::get_instance();

        // Prepare to import/export galleries.
        $this->import_gallery();
        $this->export_gallery();

        // Add custom settings submenu.
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );

        // Add callbacks for settings tabs.
        add_action( 'envira_gallery_tab_settings_general', array( $this, 'settings_general_tab' ) );

        // Add the settings menu item to the Plugins table.
        add_filter( 'plugin_action_links_' . plugin_basename( plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'envira-gallery.php' ), array( $this, 'settings_link' ) );

        // Remove quick editing from the Envira post type row actions.
        add_filter( 'post_row_actions', array( $this, 'row_actions' ) );

        // Manage post type columns.
        add_filter( 'manage_edit-envira_columns', array( $this, 'envira_columns' ) );
        add_filter( 'manage_envira_posts_custom_column', array( $this, 'envira_custom_columns' ), 10, 2 );

        // Update post type messages.
        add_filter( 'post_updated_messages', array( $this, 'messages' ) );

        // Delete any gallery association on attachment deletion. Also delete any extra cropped images.
        add_action( 'delete_attachment', array( $this, 'delete_gallery_association' ) );
        add_action( 'delete_attachment', array( $this, 'delete_cropped_image' ) );

        // Ensure gallery display is correct when trashing/untrashing galleries.
        add_action( 'wp_trash_post', array( $this, 'trash_gallery' ) );
        add_action( 'untrash_post', array( $this, 'untrash_gallery' ) );

        // Force the menu icon to be scaled to proper size (for Retina displays).
        add_action( 'admin_head', array( $this, 'menu_icon' ) );

        // Add potential admin notices for actions around the admin.
        add_action( 'admin_notices', array( $this, 'notices' ) );

    }

    /**
     * Imports an Envira gallery.
     *
     * @since 1.0.0
     *
     * @return null Return early if gallery is to be imported or nonce is invalid.
     */
    public function import_gallery() {

        if ( empty( $_POST ) || empty( $_POST['envira_import'] ) )
            return;

        if ( ! wp_verify_nonce( $_POST['envira-gallery-import'], 'envira-gallery-import' ) )
            return;

        // If the post ID provided is a revision, return early.
        if ( wp_is_post_revision( $_POST['envira_post_id'] ) )
            return;

        // If the user does not have the proper permissions to manage options, return early.
        if ( ! apply_filters( 'envira_gallery_import_cap', current_user_can( 'manage_options' ) ) )
            return;

        // If there have been no files uploaded, return.
        if ( empty( $_FILES['envira_import_gallery']['name'] ) || empty( $_FILES['envira_import_gallery']['tmp_name'] ) )
            return;

        // If the filename does not begin with "envira-gallery", die.
        if ( ! preg_match( '#^envira-gallery#i', $_FILES['envira_import_gallery']['name'] ) )
            wp_die( 'You have attempted to upload a file with an incompatible filename. Envira Gallery import files must begin with "envira-gallery". <a href="' . get_admin_url() . '">Click here to return to the Dashboard</a>.' );

        // If the extension is not correct, die.
        $file_array = explode( '.', $_FILES['envira_import_gallery']['name'] );
        $extension  = end( $file_array );
        if ( 'json' !== $extension )
            wp_die( 'Envira Gallery import files must be in <code>.json</code> format. <a href="' . get_admin_url() . '">Click here to return to the Dashboard</a>.' );

        // Retrieve the JSON contents of the file. If that fails, die.
        $file     = $_FILES['envira_import_gallery']['tmp_name'];
        $contents = @file_get_contents( $file );
        if ( ! $contents )
            wp_die( 'Sorry, but there was an error retrieving the contents of the gallery export file. Please try again. <a href="' . get_admin_url() . '">Click here to return to the Dashboard</a>.' );

        // Decode the settings and start processing.
        $data    = json_decode( $contents, true );
        $post_id = absint( $_POST['envira_post_id'] );
        $gallery = false;
        $i       = 0;

        // Delete any previous gallery data (if any) from the post that is receiving the new gallery.
        delete_post_meta( $post_id, '_eg_gallery_data' );
        delete_post_meta( $post_id, '_eg_in_gallery' );

        // Update the ID in the gallery data to point to the new post.
        $data['id'] = $post_id;

        // If the wp_generate_attachment_metadata function does not exist, load it into memory because we will need it.
        if ( ! function_exists( 'wp_generate_attachment_metadata' ) )
            require_once ABSPATH . 'wp-admin/includes/image.php';

        // Loop through each item in the gallery and add association to that image for the new gallery.
        set_time_limit( 0 );
        wp_suspend_cache_invalidation( true );
        foreach ( (array) $data['gallery'] as $id => $item ) {
            // If just starting, use the base data imported. Otherwise, use the updated data after each import.
            if ( 0 === $i )
                $gallery = $this->import_gallery_item( $id, $item, $data, $post_id );
            else
                $gallery = $this->import_gallery_item( $id, $item, $gallery, $post_id );

            // Increment the iterator.
            $i++;
        }
        wp_suspend_cache_invalidation( false );
        wp_cache_flush();

        // Update the in_gallery checker for the post that is receiving the gallery.
        update_post_meta( $post_id, '_eg_in_gallery', $gallery['in_gallery'] );

        // Unset any unncessary data from the final gallery holder.
        unset( $gallery['in_gallery'] );

        // Update the meta for the post that is receiving the gallery.
        update_post_meta( $post_id, '_eg_gallery_data', $gallery );

    }

    /**
     * Imports an individual item into a gallery.
     *
     * @since 1.0.0
     *
     * @param int $id        The image attachment ID from the import file.
     * @param array $item    Data for the item being imported.
     * @param array $gallery Array of gallery data being imported.
     * @param int $post_id   The post ID the gallery is being imported to.
     * @return array $data   Modified gallery data based on import status of image.
     */
    public function import_gallery_item( $id, $item, $data, $post_id ) {

        // If no image data was found, the image doesn't exist on the server.
        $image = wp_get_attachment_image_src( $id );
        if ( ! $image ) {
            // We need to stream our image from a remote source.
            if ( empty( $item['src'] ) ) {
                $this->errors[] = __( 'No valid URL found for the image ID #' . $id . '.', 'envira-gallery' );

                // Unset it from the gallery data for meta saving.
                unset( $data['gallery'][$id] );
                if ( ( $key = array_search( $id, (array) $data['in_gallery'] ) ) !== false )
                    unset( $data['in_gallery'][$key] );
            } else {
                // Stream the image from a remote URL.
                $new_image  = $item['src'];
                $stream     = wp_remote_get( $new_image, array( 'timeout' => 60 ) );
                $type       = wp_remote_retrieve_header( $stream, 'content-type' );

                // If we cannot get the image or determine the type, skip over the image.
                if ( is_wp_error( $stream ) || ! $type ) {
                    $this->errors[] = __( 'Could not retrieve a valid image from the URL ' . $item['src'] . '.', 'envira-gallery' );

                    // Unset it from the gallery data for meta saving.
                    unset( $data['gallery'][$id] );
                    if ( ( $key = array_search( $id, (array) $data['in_gallery'] ) ) !== false )
                        unset( $data['in_gallery'][$key] );
                } else {
                    // It is an image. Stream the image.
                    $mirror = wp_upload_bits( basename( $new_image ), null, wp_remote_retrieve_body( $stream ) );

                    // If there is an error, bail.
                    if ( ! empty( $mirror['error'] ) ) {
                        $this->errors[] = $mirror['error'];

                        // Unset it from the gallery data for meta saving.
                        unset( $data['gallery'][$id] );
                        if ( ( $key = array_search( $id, (array) $data['in_gallery'] ) ) !== false )
                            unset( $data['in_gallery'][$key] );
                    } else {
                        $attachment = array(
                            'post_title'     => basename( $new_image ),
                            'post_mime_type' => $type
                        );
                        $attach_id  = wp_insert_attachment( $attachment, $mirror['file'] );

                        // Generate and update attachment metadata.
                        $attach_data = wp_generate_attachment_metadata( $attach_id, $mirror['file'] );
                        wp_update_attachment_metadata( $attach_id, $attach_data );

                        // Unset it from the gallery data for meta saving now that we have a new image in its place.
                        unset( $data['gallery'][$id] );
                        if ( ( $key = array_search( $id, (array) $data['in_gallery'] ) ) !== false )
                            unset( $data['in_gallery'][$key] );

                        // Add the new attachment ID to the in_gallery checker.
                        $data['in_gallery'][] = $attach_id;

                        // Now update the attachment post meta and gallery meta fields.
                        $has_gallery = get_post_meta( $attach_id, '_eg_has_gallery', true );
                        if ( empty( $has_gallery ) )
                            $has_gallery = array();

                        $has_gallery[] = $post_id;
                        update_post_meta( $attach_id, '_eg_has_gallery', $has_gallery );

                        // Add the new attachment to the gallery.
                        $attachment = get_post( $attach_id );
                        $url        = wp_get_attachment_image_src( $attach_id, 'full' );
                        $alt_text   = get_post_meta( $attach_id, '_wp_attachment_image_alt', true );
                        $data['gallery'][$attach_id] = array(
                            'status'  => 'pending',
                            'src'     => isset( $url[0] ) ? esc_url( $url[0] ) : '',
                            'title'   => get_the_title( $attach_id ),
                            'caption' => isset( $attachment->post_excerpt ) ? $attachment->post_excerpt : '',
                            'link'    => isset( $url[0] ) ? esc_url( $url[0] ) : '',
                            'alt'     => ! empty( $alt_text ) ? $alt_text : ''
                        );
                    }
                }
            }
        } else {
            // The image already exists. If the URLs don't match, stream the image into the gallery.
            if ( $image[0] !== $item['src'] ) {
                // Stream the image from a remote URL.
                $new_image  = $item['src'];
                $stream     = wp_remote_get( $new_image );
                $type       = wp_remote_retrieve_header( $stream, 'content-type' );

                // If we cannot determine the type, skip over the image.
                if ( ! $type ) {
                    $this->errors[] = __( 'Could not retrieve a valid image from the URL ' . $item['src'] . '.', 'envira-gallery' );

                    // Unset it from the gallery data for meta saving.
                    unset( $data['gallery'][$id] );
                    if ( ( $key = array_search( $id, (array) $data['in_gallery'] ) ) !== false )
                        unset( $data['in_gallery'][$key] );

                    // Return the gallery data.
                    return $data;
                } else {
                    // It is an image. Stream the image.
                    $mirror = wp_upload_bits( basename( $new_image ), null, wp_remote_retrieve_body( $stream ) );

                    // If there is an error, bail.
                    if ( ! empty( $mirror['error'] ) ) {
                        $this->errors[] = $mirror['error'];

                        // Unset it from the gallery data for meta saving.
                        unset( $data['gallery'][$id] );
                        if ( ( $key = array_search( $id, (array) $data['in_gallery'] ) ) !== false )
                            unset( $data['in_gallery'][$key] );
                    } else {
                        $attachment = array(
                            'post_title'     => basename( $new_image ),
                            'post_mime_type' => $type
                        );
                        $attach_id  = wp_insert_attachment( $attachment, $mirror['file'] );

                        // Generate and update attachment metadata.
                        $attach_data = wp_generate_attachment_metadata( $attach_id, $mirror['file'] );
                        wp_update_attachment_metadata( $attach_id, $attach_data );

                        // Unset it from the gallery data for meta saving now that we have a new image in its place.
                        unset( $data['gallery'][$id] );
                        if ( ( $key = array_search( $id, (array) $data['in_gallery'] ) ) !== false )
                            unset( $data['in_gallery'][$key] );

                        // Add the new attachment ID to the in_gallery checker.
                        $data['in_gallery'][] = $attach_id;

                        // Now update the attachment post meta and gallery meta fields.
                        $has_gallery = get_post_meta( $attach_id, '_eg_has_gallery', true );
                        if ( empty( $has_gallery ) )
                            $has_gallery = array();

                        $has_gallery[] = $post_id;
                        update_post_meta( $attach_id, '_eg_has_gallery', $has_gallery );

                        // Add the new attachment to the gallery.
                        $attachment = get_post( $attach_id );
                        $url        = wp_get_attachment_image_src( $attach_id, 'full' );
                        $alt_text   = get_post_meta( $attach_id, '_wp_attachment_image_alt', true );
                        $data['gallery'][$attach_id] = array(
                            'status'  => 'pending',
                            'src'     => isset( $url[0] ) ? esc_url( $url[0] ) : '',
                            'title'   => get_the_title( $attach_id ),
                            'caption' => isset( $attachment->post_excerpt ) ? $attachment->post_excerpt : '',
                            'link'    => isset( $url[0] ) ? esc_url( $url[0] ) : '',
                            'alt'     => ! empty( $alt_text ) ? $alt_text : ''
                        );
                    }
                }
            } else {
                // The URLs match. We can simply update data and continue.
                $has_gallery = get_post_meta( $id, '_eg_has_gallery', true );
                if ( empty( $has_gallery ) )
                    $has_gallery = array();

                $has_gallery[] = $post_id;
                update_post_meta( $id, '_eg_has_gallery', $has_gallery );
            }
        }

        // Return the modified gallery data.
        return apply_filters( 'envira_gallery_imported_image_data', $data, $id, $item, $post_id );

    }

    /**
     * Exports an Envira gallery.
     *
     * @since 1.0.0
     *
     * @return null Return early if gallery is to be exported or nonce is invalid.
     */
    public function export_gallery() {

        if ( empty( $_POST ) || empty( $_POST['envira_export'] ) )
            return;

        if ( ! wp_verify_nonce( $_POST['envira-gallery-export'], 'envira-gallery-export' ) )
            return;

        // If the user does not have the proper permissions to manage options, return early.
        if ( ! apply_filters( 'envira_gallery_export_cap', current_user_can( 'manage_options' ) ) )
            return;

        // Ignore the user aborting the action.
        ignore_user_abort( true );

        // Grab the proper data.
        $post_id = absint( $_POST['envira_post_id'] );
        $data    = get_post_meta( $post_id, '_eg_gallery_data', true );

        // Append the in_gallery data checker to the data array.
        $data['in_gallery'] = get_post_meta( $post_id, '_eg_in_gallery', true );

        // Set the proper headers.
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=envira-gallery-' . $post_id . '-' . date( 'm-d-Y' ) . '.json' );
        header( 'Expires: 0' );

        // Make the settings downloadable to a JSON file and die.
        die( json_encode( $data ) );

    }

    /**
     * Register the Settings submenu item for Envira.
     *
     * @since 1.0.0
     */
    public function admin_menu() {

        // Register the submenu.
        $this->hook = add_submenu_page(
            'edit.php?post_type=envira',
            __( 'Envira Gallery Settings', 'envira-gallery' ),
            __( 'Settings', 'envira-gallery' ),
            apply_filters( 'envira_gallery_menu_cap', 'manage_options' ),
            $this->base->plugin_slug . '-settings',
            array( $this, 'settings_page' )
        );

        // If successful, load admin assets only on that page.
        if ( $this->hook )
            add_action( 'load-' . $this->hook, array( $this, 'settings_page_assets' ) );

    }

    /**
     * Loads assets for the settings page.
     *
     * @since 1.0.0
     */
    public function settings_page_assets() {

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

    }

    /**
     * Register and enqueue settings page specific CSS.
     *
     * @since 1.0.0
     */
    public function enqueue_admin_styles() {

        wp_register_style( $this->base->plugin_slug . '-settings-style', plugins_url( 'assets/css/settings.css', $this->base->file ), array(), $this->base->version );
        wp_enqueue_style( $this->base->plugin_slug . '-settings-style' );

        // Run a hook to load in custom styles.
        do_action( 'envira_gallery_settings_styles' );

    }

    /**
     * Register and enqueue settings page specific JS.
     *
     * @since 1.0.0
     */
    public function enqueue_admin_scripts() {

        wp_enqueue_script( 'jquery-ui-tabs' );
        wp_register_script( $this->base->plugin_slug . '-settings-script', plugins_url( 'assets/js/settings.js', $this->base->file ), array( 'jquery', 'jquery-ui-tabs' ), $this->base->version, true );
        wp_enqueue_script( $this->base->plugin_slug . '-settings-script' );

        // Run a hook to load in custom scripts.
        do_action( 'envira_gallery_settings_scripts' );

    }

    /**
     * Callback to output the Envira settings page.
     *
     * @since 1.0.0
     */
    public function settings_page() {

        ?>
        <div id="envira-gallery-settings" class="wrap">
            <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
            <div class="envira-gallery envira-clear">
                <div id="envira-tabs" class="envira-clear">
                    <ul id="envira-tabs-nav" class="envira-clear">
                        <?php foreach ( (array) $this->get_envira_settings_tab_nav() as $id => $title ) : ?>
                            <li><a href="#envira-tab-<?php echo $id; ?>" title="<?php echo $title; ?>"><?php echo $title; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php foreach ( (array) $this->get_envira_settings_tab_nav() as $id => $title ) : ?>
                        <div id="envira-tab-<?php echo $id; ?>" class="envira-tab envira-clear">
                            <?php do_action( 'envira_gallery_tab_settings_' . $id ); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php

    }

    /**
     * Callback for getting all of the settings tabs for Envira.
     *
     * @since 1.0.0
     *
     * @return array Array of tab information.
     */
    public function get_envira_settings_tab_nav() {

        $tabs = array(
            'general' => __( 'General', 'envira-gallery' ), // This tab is required. DO NOT REMOVE VIA FILTERING.
            'addons'  => __( 'Addons', 'envira-gallery' ),
        );
        $tabs = apply_filters( 'envira_gallery_settings_tab_nav', $tabs );

        return $tabs;

    }

    /**
     * Callback for displaying the UI for general settings tab.
     *
     * @since 1.0.0
     */
    public function settings_general_tab() {

        ?>
        <div id="envira-settins-general">
            <p class="envira-intro"><?php _e( 'The settings below adjust the basic configuration options for the gallery lightbox display.', 'envira-gallery' ); ?></p>
            <table class="form-table">
                <tbody>
                    <tr id="envira-config-columns-box">
                        <th scope="row">
                            <label for="envira-config-columns"><?php _e( 'Number of Gallery Columns', 'envira-gallery' ); ?></label>
                        </th>
                        <td>
                            <p class="description"><?php _e( 'Determines the number of columns in the gallery.', 'envira-gallery' ); ?></p>
                        </td>
                    </tr>
                    <?php do_action( 'envira_gallery_settings_general_box' ); ?>
                </tbody>
            </table>
        </div>
        <?php

    }

    /**
     * Add Settings page to plugin action links in the Plugins table.
     *
     * @since 1.0.0
     *
     * @param array $links  Default plugin action links.
     * @return array $links Amended plugin action links.
     */
    public function settings_link( $links ) {

        $settings_link = sprintf( '<a href="%s">%s</a>', add_query_arg( array( 'post_type' => 'envira', 'page' => 'envira-gallery-settings' ), admin_url( 'edit.php' ) ), __( 'Settings', 'envira-gallery' ) );
        array_unshift( $links, $settings_link );

        return $links;

    }

    /**
     * Customize the post columns for the Envira post type.
     *
     * @since 1.0.0
     *
     * @param array $columns  The default columns.
     * @return array $columns Amended columns.
     */
    public function envira_columns( $columns ) {

        $columns = array(
            'cb'        => '<input type="checkbox" />',
            'title'     => __( 'Title', 'envira-gallery' ),
            'shortcode' => __( 'Shortcode', 'envira-gallery' ),
            'template'  => __( 'Function', 'envira-gallery' ),
            'images'    => __( 'Number of Images', 'envira-gallery' ),
            'modified'  => __( 'Last Modified', 'envira-gallery' ),
            'date'      => __( 'Date', 'envira-gallery' )
        );

        return $columns;

    }

    /**
     * Add data to the custom columns added to the Envira post type.
     *
     * @since 1.0.0
     *
     * @global object $post  The current post object
     * @param string $column The name of the custom column
     * @param int $post_id   The current post ID
     */
    public function envira_custom_columns( $column, $post_id ) {

        global $post;
        $post_id = absint( $post_id );

        switch ( $column ) {
            case 'shortcode' :
                echo '<code>[envira-gallery id="' . $post_id . '"]</code>';
                break;

            case 'template' :
                echo '<code>if ( function_exists( \'envira_gallery\' ) ) envira_gallery( \'' . $post_id . '\' );</code>';
                break;

            case 'images' :
                $gallery_data = get_post_meta( $post_id, '_eg_gallery_data', true );
                echo count( $gallery_data['gallery'] );
                break;

            case 'modified' :
                the_modified_date();
                break;
        }

    }

    /**
     * Filter out unnecessary row actions from the Envira post table.
     *
     * @since 1.0.0
     *
     * @param array $actions  Default row actions.
     * @return array $actions Amended row actions.
     */
    public function row_actions( $actions ) {

        if ( isset( get_current_screen()->post_type ) && 'envira' == get_current_screen()->post_type )
            unset( $actions['inline hide-if-no-js'] );

        return $actions;

    }

    /**
     * Contextualizes the post updated messages.
     *
     * @since 1.0.0
     *
     * @global object $post    The current post object.
     * @param array $messages  Array of default post updated messages.
     * @return array $messages Amended array of post updated messages.
     */
    public function messages( $messages ) {

        global $post;

        // Contextualize the messages.
        $messages['envira'] = apply_filters( 'envira_gallery_messages',
            array(
                0  => '',
                1  => __( 'Envira gallery updated.', 'envira-gallery' ),
                2  => __( 'Envira gallery custom field updated.', 'envira-gallery' ),
                3  => __( 'Envira gallery custom field deleted.', 'envira-gallery' ),
                4  => __( 'Envira gallery updated.', 'envira-gallery' ),
                5  => isset( $_GET['revision'] ) ? sprintf( __( 'Envira gallery restored to revision from %s.', 'envira-gallery' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
                6  => __( 'Envira gallery published.', 'envira-gallery' ),
                7  => __( 'Envira gallery saved.', 'envira-gallery' ),
                8  => __( 'Envira gallery submitted.', 'envira-gallery' ),
                9  => sprintf( __( 'Envira gallery scheduled for: <strong>%1$s</strong>.', 'envira-gallery' ), date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ) ),
                10 => __( 'Envira gallery draft updated.', 'soliloquy' )
            )
        );

        return $messages;

    }

    /**
     * Deletes the Envira gallery association for the image being deleted.
     *
     * @since 1.0.0
     *
     * @param int $attach_id The attachment ID being deleted.
     */
    public function delete_gallery_association( $attach_id ) {

        $has_gallery = get_post_meta( $attach_id, '_eg_has_gallery', true );

        // Only proceed if the image is attached to any Envira galleries.
        if ( ! empty( $has_gallery ) ) {
            foreach ( $has_gallery as $post_id ) {
                // Remove the in_gallery association.
                $in_gallery = get_post_meta( $post_id, '_eg_in_gallery', true );
                if ( ! empty( $in_gallery ) )
                    if ( ( $key = array_search( $attach_id, (array) $in_gallery ) ) !== false )
                        unset( $in_gallery[$key] );

                update_post_meta( $post_id, '_eg_in_gallery', $in_gallery );

                // Remove the image from the gallery altogether.
                $gallery_data = get_post_meta( $post_id, '_eg_gallery_data', true );
                if ( ! empty( $gallery_data['gallery'] ) )
                    unset( $gallery_data['gallery'][$attach_id] );

                // Update the post meta for the gallery.
                update_post_meta( $post_id, '_eg_gallery_data', $gallery_data );

                // Flush necessary gallery caches.
                Envira_Gallery_Common_Lite::get_instance()->flush_gallery_caches( $post_id, $gallery_data['config']['slug'] );
            }
        }

    }

    /**
     * Removes any extra cropped images when an attachment is deleted.
     *
     * @since 1.0.0
     *
     * @param int $post_id The post ID
     * @return null        Return early if the appropriate metadata cannot be retrieved.
     */
    public function delete_cropped_image( $post_id ) {

        // Get attachment image metadata.
        $metadata = wp_get_attachment_metadata( $post_id );

        // Return if no metadata is found.
        if ( ! $metadata )
            return;

        // Return if we don't have the proper metadata.
        if ( ! isset( $metadata['file'] ) || ! isset( $metadata['image_meta']['resized_images'] ) )
            return;

        // Grab the necessary info to removed the cropped images.
        $wp_upload_dir  = wp_upload_dir();
        $pathinfo       = pathinfo( $metadata['file'] );
        $resized_images = $metadata['image_meta']['resized_images'];

        // Loop through and deleted and resized/cropped images.
        foreach ( $resized_images as $dims ) {
            // Get the resized images filename and delete the image.
            $file = $wp_upload_dir['basedir'] . '/' . $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '-' . $dims . '.' . $pathinfo['extension'];

            // Delete the resized image.
            if ( file_exists( $file ) )
                @unlink( $file );
        }

    }

    /**
     * Trash a gallery when the gallery post type is trashed.
     *
     * @since 1.0.0
     *
     * @param $id   The post ID being trashed.
     * @return null Return early if no gallery is found.
     */
    public function trash_gallery( $id ) {

        $gallery = get_post( $id );

        // Return early if not an Envira gallery.
        if ( 'envira' !== $gallery->post_type )
            return;

        // Set the gallery status to inactive.
        $gallery_data = get_post_meta( $id, '_eg_gallery_data', true );
        if ( empty( $gallery_data ) )
            return;

        $gallery_data['status'] = 'inactive';
        update_post_meta( $id, '_eg_gallery_data', $gallery_data );

    }

    /**
     * Untrash a gallery when the gallery post type is untrashed.
     *
     * @since 1.0.0
     *
     * @param $id   The post ID being untrashed.
     * @return null Return early if no gallery is found.
     */
    public function untrash_gallery( $id ) {

        $gallery = get_post( $id );

        // Return early if not an Envira gallery.
        if ( 'envira' !== $gallery->post_type )
            return;

        // Set the gallery status to inactive.
        $gallery_data = get_post_meta( $id, '_eg_gallery_data', true );
        if ( empty( $gallery_data ) )
            return;

        if ( isset( $gallery_data['status'] ) )
            unset( $gallery_data['status'] );

        update_post_meta( $id, '_eg_gallery_data', $gallery_data );

    }

    /**
     * Forces the Envira menu icon to width/height for Retina devices.
     *
     * @since 1.0.0
     */
    public function menu_icon() {

        ?>
        <style type="text/css">#menu-posts-envira .wp-menu-image img { width: 16px; height: 16px; }</style>
        <?php

    }

    /**
     * Outputs any notices generated by the class.
     *
     * @since 1.0.0
     */
    public function notices() {

        // If there are any errors, create a notice for them.
        if ( ! empty( $this->errors ) ) :
        ?>
        <div id="message" class="error">
            <p><?php echo implode( '<br>', $this->errors ); ?></p>
        </div>
        <?php
        endif;

        // If a gallery has been imported, create a notice for the import status.
        if ( isset( $_GET['envira-gallery-imported'] ) && $_GET['envira-gallery-imported'] ) :
        ?>
        <div id="message" class="updated">
            <p><?php _e( 'Envira gallery imported. Please check to ensure all images and data have been imported properly.', 'envira-gallery' ); ?></p>
        </div>
        <?php
        endif;

    }

    /**
     * Returns the singleton instance of the class.
     *
     * @since 1.0.0
     *
     * @return object The Envira_Gallery_Common_Admin_Lite object.
     */
    public static function get_instance() {

        if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Envira_Gallery_Common_Admin_Lite ) )
            self::$instance = new Envira_Gallery_Common_Admin_Lite();

        return self::$instance;

    }

}

// Load the common admin class.
$envira_gallery_common_admin_lite = Envira_Gallery_Common_Admin_Lite::get_instance();