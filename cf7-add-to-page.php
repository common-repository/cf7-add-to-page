<?php
/**+
 * Plugin Name: Contact Form 7: Add to Page
 * Description: A plugin that provides a dropdown of selectable forms for easy attachment to pages.
 * Version: 1.0.1
 * Author: Philip Rabbett
 * Text Domain: cf7-add-to-page
 */

class CF7AddToPage {
    /**
     * __construct
     *
     * @since 1.0.0
     */
    function __construct()
    {
        if ( is_admin() ) :
            //display add to page
            add_action( 'add_meta_boxes_page', array( $this, 'add_meta_box' ) );
            //save contact form selection
            add_action( 'save_post', array( $this, 'save' ), 10, 2 );
        else :
            //append contact form to end of page
            add_filter( 'the_content', array( $this, 'get_contact_form_shortcode' ) );
        endif;
    }

    /**
     * used to register new metabox for contact form
     *
     * @since 1.0.0
     */
    function add_meta_box()
    {
        add_meta_box( 'CF7_meta_box', __( 'Add Contact Form', 'cf7-add-to-page' ), array(
            $this,
            'display'
        ), 'page', 'side', 'low' );
    }

    /**
     * used to display contact form metabox
     *
     * @since 1.0.0
     */
    function display()
    {
        $sContactFormId = $this->get_contact_form_id();
        $oContactForms  = $this->get_CF7_dropdown( $sContactFormId );
        if ( ! empty( $oContactForms ) ) : ?>

          <p class="post-attributes-label-wrapper">
            <label class="post-attributes-label" for="CF7_id"><?php _e( 'Contact Form', 'cf7-add-to-page' ); ?></label>
          </p>

            <?php
            wp_nonce_field( plugin_basename( __FILE__ ), '_add_contact_form_wpnonce', false, true );
            echo $oContactForms;
        endif; // end empty contact forms check
    }

    /**
     * used to save contact form ID field
     *
     * @since 2.0
     */
    function save( $post_id, $post )
    {
        if ( ! user_can_save( $post_id, $post, array(
            'name'   => '_add_contact_form_wpnonce',
            'action' => plugin_basename( __FILE__ )
        ) ) ) :
            return $post_id;
        endif;

        $old = $this->get_contact_form_id( get_queried_object_id( $post_id ) );
        $new = intval( $_POST['CF7_ID'] );
        if ( $new && $new != $old ) :
            update_post_meta( $post_id, '_CF7_ID', $new );
        elseif ( '' == $new && $old ) :
            delete_post_meta( $post_id, '_CF7_ID', $old );
        endif;
    }

    /**
     * used to create shortcode and append to end of page if contact form id exists in page meta
     *
     * @since 1.0.0
     */
    function get_contact_form_shortcode( $sContent )
    {
        if ( is_singular() && ( $sContactFormId = $this->get_contact_form_id( get_queried_object_id() ) ) ) :
            $sTitle     = get_the_title( $sContactFormId );
            $sShortcode = '[contact-form-7 id="' . $sContactFormId . '" title="' . $sTitle . '"]';

            /**
             * Filters the contact form 7 shortcode.
             *
             * @since 1.0.1
             *
             * @param string $sShortcode Contact Form 7 shortcode.
             */
            $sContent .= apply_filters( 'atp_append_contact_form', do_shortcode( $sShortcode ) );
        endif;

        return $sContent;
    }

    /**
     * used to get the contact form ID
     *
     * @since 1.0.0
     */
    function get_contact_form_id( $postid = NULL )
    {
        if ( $id = intval( get_post_meta( ( $postid ) ? $postid : get_the_ID(), '_CF7_ID', true ) ) )
            return $id;

        return false;
    }

    /**
     * used to get the downdown of various contact forms that exist
     *
     * @since 1.0.0
     */
    function get_CF7_dropdown( $selected = 0 )
    {
        $aContactForms = array(
            'post_type'        => 'wpcf7_contact_form',
            'post_status'      => 'publish',
            'orderby'          => 'id',
            'suppress_filters' => false,
            'posts_per_page'   => - 1
        );
        $oContactForms = get_posts( $aContactForms );
        if ( ! empty( $oContactForms ) ) :
            $sContactForms = '<select name="CF7_ID" id="CF7_ID">';
            $sContactForms .= sprintf( '<option value="">%s</option>', __( '(no contact form)', 'cf7-add-to-page' ) );
            foreach ( $oContactForms as $oContactForm ) :
                $sSelected     = ( $selected == $oContactForm->ID ) ? ' selected="selected"' : '';
                $sContactForms .= sprintf('<option value="%d"%s>%s</option>', $oContactForm->ID, $sSelected,
                    $oContactForm->post_title );
            endforeach;
            $sContactForms .= '</select>';
        else :
            $sContactForms = '';
        endif;

        return $sContactForms;
    }
}

$CF7AddToPage = new CF7AddToPage();

if ( ! function_exists( 'get_current_post_type' ) ) :
    /**
     * gets the current post type in the WordPress Admin
     */
    function get_current_post_type()
    {
        global $post, $typenow, $current_screen;

        //we have a post so we can just get the post type from that
        if ( $post && $post->post_type )
            return $post->post_type;

        //check the global $typenow - set in admin.php
        elseif ( $typenow )
            return $typenow;

        //check the global $current_screen object - set in screen.php
        elseif ( $current_screen && $current_screen->post_type )
            return $current_screen->post_type;

        //lastly check the post_type querystring
        elseif ( isset( $_REQUEST['post_type'] ) )
            return sanitize_key( $_REQUEST['post_type'] );

        //we do not know the post type!
        return NULL;
    }
endif;

if ( ! function_exists( 'user_can_save' ) ) :
    /**
     * Check if the current user can save metadata
     *
     * @param   int    $post_id The post ID.
     * @param   post   $post    The post object.
     * @param   string $nonce   The post nonce.
     *
     * @return  bool
     */
    function user_can_save( $post_id, $post, $nonce )
    {
        $is_autosave    = wp_is_post_autosave( $post_id );
        $is_revision    = wp_is_post_revision( $post_id );
        $is_valid_nonce = ( isset( $_POST[ $nonce['name'] ] ) && wp_verify_nonce( $_POST[ $nonce['name'] ], $nonce['action'] ) );

        $can_edit = true;
        if ( 'page' == $post->post_type ) :
            if ( ! current_user_can( 'edit_page', $post_id ) ) :
                $can_edit = false;
            endif;
        elseif ( ! current_user_can( 'edit_post', $post_id ) ) :
            $can_edit = false;
        endif;

        return ! ( $is_autosave || $is_revision ) && $is_valid_nonce && $can_edit;
    }
endif;