<?php
global $wpsstm_track;
global $tracklist_manager_query;

$action = get_query_var( 'wpsstm_action' );

add_filter( 'show_admin_bar','__return_false'); //hide admin bar
do_action( 'wpsstm-iframe' );
do_action( 'wpsstm-tracklists-manager-iframe' );
do_action( 'get_header', 'wpsstm-tracklists-manager-iframe' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
//

$body_classes = array(
    'wpsstm-iframe',
    'wpsstm-tracklist-manager-iframe'
);

$track_type_obj = get_post_type_object(wpsstm()->post_type_track);
$can_edit_track = current_user_can($track_type_obj->cap->edit_post,$wpsstm_track->post_id);

$playlist_type_obj = get_post_type_object(wpsstm()->post_type_playlist);
$labels = get_post_type_labels($playlist_type_obj);

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
    if ( !get_current_user_id() ){

        $action_link = $wpsstm_track->get_track_action_url('tracklists-selector');
        $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url($action_link),__('here','wpsstm'));
        $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
        printf('<p class="wpsstm-notice">%s</p>',$wp_auth_text);

    }else{

        ?>
        <form action="<?php echo get_permalink($wpsstm_track->post_id);?>" id="wpsstm-new-tracklist">
            <input name="wpstm-new-tracklist-title" type="text" placeholder="<?php _e('Type to filter playlists or to create a new one','wpsstm');?>" class="wpsstm-fullwidth" />

            <input name="wpsstm_action" type="hidden" value='new-tracklist' />
            <input type="hidden" name="wpsstm_action_data[from_tracklist]" value="<?php echo $wpsstm_track->from_tracklist;?>" />
            <button type="submit" class="button button-primary wpsstm-icon-button">
                <i class="fa fa-plus" aria-hidden="true"></i> <?php _e('New');?>
            </button>
        </form>
        <form action="<?php echo get_permalink($wpsstm_track->post_id);?>" id="wpsstm-toggle-tracklists" data-wpsstm-track-id="<?php echo $wpsstm_track->post_id;?>">
            <?php

            //handle checkbox
            add_filter('wpsstm_before_tracklist_row',array('WPSSTM_Track','tracklists_manager_track_checkbox'));

            //get logged user static playlists
            $args = array(
                'post_type' =>      wpsstm()->post_type_playlist,
                'author' =>         get_current_user_id(), //TOFIX TO CHECK WHAT IF NOT LOGGED ?
                'post_status' =>    array('publish','private','future','pending','draft'),
                'posts_per_page' => -1,
                'orderby' =>        'title',
                'order'=>           'ASC'
            );

            $tracklist_manager_query = new WP_Query( $args );
            wpsstm_locate_template( 'tracklists-list.php', true, false );
            
            ?>

            <input name="wpsstm_action" type="hidden" value='toggle-tracklists' />
            <input type="hidden" name="wpsstm_action_data[from_tracklist]" value="<?php echo $wpsstm_track->from_tracklist;?>" />
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