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

/*
populate track
*/
if ( $url_track =  get_query_var( 'wpsstm_action_data' ) ){
    $track = new WPSSTM_Track();

    $track->from_array($url_track);
    $valid = $track->validate_track();
    if ( is_wp_error($valid) ){
        printf('<p class="wpsstm-notice">%s</p>',$valid->get_error_message());
    }else{
        global $wpsstm_track;
        $wpsstm_track = $track;
    }
}

//TOUFIX
$wpsstm_track = new WPSSTM_Track();
$wpsstm_track->artist = 'Thibault Cauvin';
$wpsstm_track->title = 'Danza Española No. 1: La Vida Breve';
$track_arr = $wpsstm_track->to_array();
$track_json = wp_json_encode($track_arr);
$track_attr = esc_attr($track_json);

?>

<!DOCTYPE html>
<html class="no-js" <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" >
    <?php wp_head(); ?>
</head>
<body <?php body_class($body_classes); ?>>  
    <?php

    if ( !get_current_user_id() ){ //not logge

        $action_link = $wpsstm_track->get_track_action_url('tracklists-selector');
        $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url($action_link),__('here','wpsstm'));
        $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
        printf('<p class="wpsstm-notice">%s</p>',$wp_auth_text);

    }else{

        ?>
        <form action="<?php echo WPSSTM_Core_Tracklists::get_tracklists_manager_url();?>" id="wpsstm-new-tracklist">
            <input name="wpsstm_action_data[tracklist_title]" type="text" placeholder="<?php _e('Type to filter playlists or to create a new one','wpsstm');?>" class="wpsstm-fullwidth" />

            <input name="wpsstm_action" type="hidden" value='new' />
            <input type="hidden" name="wpsstm_action_data[json_track]" value="<?php echo $track_attr;?>" />
            <button type="submit" class="button button-primary wpsstm-icon-button">
                <i class="fa fa-plus" aria-hidden="true"></i> <?php _e('New');?>
            </button>
        </form>
        <form action="<?php echo WPSSTM_Core_Tracklists::get_tracklists_manager_url();?>" id="wpsstm-toggle-tracklists" data-wpsstm-track-id="<?php echo $wpsstm_track->post_id;?>">
            <?php wpsstm_locate_template( 'tracklists-list.php', true, false );?>

            <input name="wpsstm_action" type="hidden" value='toggle-tracklists' />
            <input type="hidden" name="wpsstm_action_data[json_track]" value="<?php echo $track_attr;?>" />
            <button type="submit" class="button button-primary wpsstm-icon-button">
                <?php _e('Save');?>
            </button>
        </form>
        <?php
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