<?php

add_filter( 'show_admin_bar','__return_false'); //hide admin bar
do_action( 'wpsstm-iframe' );
do_action( 'wpsstm-tracklists-manager-iframe' );
do_action( 'get_header', 'wpsstm-tracklists-manager-iframe' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
//

$body_classes = array(
    'wpsstm-iframe',
    'wpsstm-tracklist-manager-iframe'
);

$manager_redirect_url = WPSSTM_Core_Tracklists::get_manager_url();

/*
capabilities
*/
$post_type_obj = get_post_type_object( get_post_type() );
$create_cap = $post_type_obj->cap->create_posts;
$edit_cap = $post_type_obj->cap->edit_posts;

/*
populate track
*/
$track = new WPSSTM_Track();

if ( $url_track =  get_query_var( 'wpsstm_track_data' ) ){
    $track->from_array($url_track);
}
    
//init new subtrack
$subtrack = new WPSSTM_Track();
$subtrack->from_tracklist = $track->tracklist->post_id;
$subtrack->post_id = $track->post_id;
$subtrack->artist = $track->artist;
$subtrack->album = $track->album;
$subtrack->title = $track->title;

$valid = $subtrack->validate_track();

if ( !is_wp_error($valid) ){
    global $wpsstm_track;
    $wpsstm_track = $subtrack;
    $manager_redirect_url = $wpsstm_track->get_tracklists_manager_url();
}


?>

<!DOCTYPE html>
<html class="no-js wpsstm-no-admin-bar" <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" >
    <?php wp_head(); ?>
</head>
<body <?php body_class($body_classes); ?>>  
    <?php

    if ( !get_current_user_id() ){ //not logge
        
        $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url($manager_redirect_url),__('here','wpsstm'));
        $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
        printf('<p class="wpsstm-notice">%s</p>',$wp_auth_text);

    }else{
        
        /*
        Track header if any
        */
        if ( $subtrack->validate_track() === true ){
            wpsstm_locate_template( 'track-header.php', true, false );
        }

        
        if ( current_user_can($create_cap) ){

            ?>
            <form action="<?php echo WPSSTM_Core_Tracklists::get_manager_url();?>" id="wpsstm-new-tracklist" method="post">
                <input name="wpsstm_tracklist_data[title]" type="text" placeholder="<?php _e('Type to filter playlists or to create a new one','wpsstm');?>" class="wpsstm-fullwidth" />
                <?php echo $wpsstm_track->get_track_hidden_form_fields();?>
                <input name="wpsstm_action" type="hidden" value='new' />
                <button type="submit" class="button button-primary wpsstm-icon-button">
                    <i class="fa fa-plus" aria-hidden="true"></i> <?php _e('New');?>
                </button>
            </form>
        <?php
        }
        
        if ( current_user_can($edit_cap) ){
            ?>
            <form action="<?php echo WPSSTM_Core_Tracklists::get_manager_url();?>" id="wpsstm-toggle-tracklists" data-wpsstm-track-id="<?php echo $wpsstm_track->post_id;?>" method="post">
                <?php wpsstm_locate_template( 'tracklists-list.php', true, false );?>
                <?php echo $wpsstm_track->get_track_hidden_form_fields();?>
                <input name="wpsstm_action" type="hidden" value='toggle' />
                <button type="submit" class="button button-primary wpsstm-icon-button">
                    <?php _e('Save');?>
                </button>
            </form>
            <?php
        }
    }
    ?>
    <?php
    //
    do_action( 'get_footer', 'wpsstm-tracklist-iframe' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
    wp_footer();
    //
    ?>
</body>
</html>