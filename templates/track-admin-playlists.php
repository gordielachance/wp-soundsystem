<?php

global $wpsstm_track;
$track_type_obj = get_post_type_object(wpsstm()->post_type_track);
$can_edit_track = current_user_can($track_type_obj->cap->edit_post,$wpsstm_track->post_id);

$playlist_type_obj = get_post_type_object(wpsstm()->post_type_playlist);
$labels = get_post_type_labels($playlist_type_obj);

//capability check
$create_playlist_cap = $playlist_type_obj->cap->create_posts;

if ( !current_user_can($create_playlist_cap) ){
    
    $action_link = $wpsstm_track->$this->get_track_action_url('playlists');
    $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url($action_link),__('here','wpsstm'));
    $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
    printf('<p class="wpsstm-notice">%s</p>',$wp_auth_text);
    
}else{
    
    ?>
    <div id="wpsstm-track-tracklists" data-wpsstm-track-id="<?php echo $wpsstm_track->post_id;?>">
        <p id="wpsstm-playlists-filter" class="wpsstm-icon-input">
            <input type="text" placeholder="<?php _e('Type to filter playlists or to create a new one','wpsstm');?>" class="wpsstm-fullwidth" />
            <button type="submit" id="wpsstm-new-playlist-add" class="button button-primary wpsstm-icon-button">
                <i class="fa fa-plus" aria-hidden="true"></i>
            </button>
            <?php wp_nonce_field( 'wpsstm_admin_track_gui_playlists_'.$wpsstm_track->post_id, 'wpsstm_admin_track_gui_playlists_nonce', true );?>
        </p>
        <?php echo $wpsstm_track->get_subtrack_playlist_manager_list(); ?>
    </div>
    <?php
}
