<?php

global $wpsstm_track;
$track_type_obj = get_post_type_object(wpsstm()->post_type_track);
$can_edit_track = current_user_can($track_type_obj->cap->edit_post,$wpsstm_track->post_id);

$playlist_type_obj = get_post_type_object(wpsstm()->post_type_playlist);
$labels = get_post_type_labels($playlist_type_obj);

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
        <input type="hidden" name="wpsstm_item[from_tracklist]" value="<?php echo $wpsstm_track->from_tracklist;?>" />
        <button type="submit" class="button button-primary wpsstm-icon-button">
            <i class="fa fa-plus" aria-hidden="true"></i> <?php _e('New');?>
        </button>
    </form>
    <form action="<?php echo get_permalink($wpsstm_track->post_id);?>" id="wpsstm-toggle-tracklists" data-wpsstm-track-id="<?php echo $wpsstm_track->post_id;?>">
        <?php echo $wpsstm_track->get_subtrack_playlist_manager_list(); ?>

        <input name="wpsstm_action" type="hidden" value='toggle-tracklists' />
        <input type="hidden" name="wpsstm_item[from_tracklist]" value="<?php echo $wpsstm_track->from_tracklist;?>" />
        <button type="submit" class="button button-primary wpsstm-icon-button">
            <?php _e('Save');?>
        </button>
    </form>
    <?php
}
