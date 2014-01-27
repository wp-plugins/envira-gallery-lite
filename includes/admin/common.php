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

        // Update the checker limit.
        $this->update_limit( $id, true );

        // Flush necessary gallery caches to ensure trashed galleries are not showing.
        Envira_Gallery_Common_Lite::get_instance()->flush_gallery_caches( $id );

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

        // Update the checker limit.
        $this->update_limit( $id );

        // Flush necessary gallery caches to ensure untrashed galleries are showing.
        Envira_Gallery_Common_Lite::get_instance()->flush_gallery_caches( $id );

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
     * Runs limit checks to ensure only a certain amount of galleries can be
     * created in the Lite version.
     *
     * @since 1.0.0
     */
    public function limit() {

        // Update with the number of galleries created.
        $galleries = $this->base->get_galleries();
        $this->base->number = count( $galleries );
        if ( $this->base->number >= 5 )
            $this->base->limit = true;

    }

    /**
     * Updates the limit checker on save, trash, deletion and other change events.
     *
     * @since 1.0.0
     *
     * @param int $post_id The current post ID.
     * @param bool $unset  Whether or not to unset the ID from the limit checker.
     * @return null        Return early if no gallery is available for the ID.
     */
    public function update_limit( $post_id, $unset = false ) {

        // Return early if no gallery exists for the ID provided.
        if ( ! $this->base->get_gallery( $post_id ) )
            return;

        // Get the limit option.
        $limit = get_option( 'envira_gallery_lite_limit' );

        // If unsetting, unset and return.
        if ( $unset ) {
            if ( ( $key = array_search( $post_id, (array) $limit ) ) !== false )
                unset( $limit[$key] );

            update_option( 'envira_gallery_lite_limit', $limit );
        } else {
            if ( count( $limit ) >= 5 || in_array( $post_id, (array) $limit ) )
                return;

            $limit[] = $post_id;
            update_option( 'envira_gallery_lite_limit', $limit );
        }

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