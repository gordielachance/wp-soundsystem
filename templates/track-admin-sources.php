<?php
global $wpsstm_track;
$track_type_obj = get_post_type_object(wpsstm()->post_type_track);
$can_edit_track = current_user_can($track_type_obj->cap->edit_post,$wpsstm_track->post_id);

if ( !$can_edit_track ){
    $action_link = $wpsstm_track->get_track_admin_url('playlists');
    $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url($action_link),__('here','wpsstm'));
    $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
    printf('<p class="wpsstm-notice">%s</p>',$wp_auth_text);
}else{
    
    ?>
    <p>
        <?php _e('Add sources to this track.  It could be a local audio file or a link to a music service.','wpsstm');?>
    </p>
    <p>
        <?php _e("If no sources are set and that the 'Auto-Source' setting is enabled, We'll try to find a source automatically when the tracklist is played.",'wpsstm');?>
    </p>
    <form action="<?php echo esc_url($wpsstm_track->get_track_admin_url('sources-manager'));?>" method="POST">
        <?php

        //track sources
        $wpsstm_track->populate_sources();
        wpsstm_locate_template( 'track-sources.php', true, false );

        ?>
        <p class="wpsstm-submit-wrapper">
            <input id="wpsstm-autosource-bt" type="submit" name="wpsstm_sources[action][autosource]" class="button" value="<?php _e('Autosource','wpsstm');?>">
            <input class="wpsstm-backend-toggle" type="submit" name="wpsstm_sources[action][backend]" class="button" value="<?php _e('Backend listing','wpsstm');?>">
        </p>
        <p class="wpsstm-icon-input" id="wpsstm-new-source">
            <input type="text" name="wpsstm_sources[source-url]" value="" class="wpsstm-fullwidth" placeholder="<?php _e('Enter a source URL','wpsstm');?>">
            <button type="submit" name="wpsstm_sources[action][new-source]" class="button button-primary wpsstm-icon-button">
                <i class="fa fa-plus" aria-hidden="true"></i>
            </button>
        </p>
        <input type="hidden" name="wpsstm-track-popup-action" value="sources-manager" />
        <input type="hidden" name="wpsstm-track-id" value="<?php echo $wpsstm_track->post_id;?>" />

        <?php wp_nonce_field( sprintf('wpsstm_track_%s_new_source_nonce',$wpsstm_track->post_id), 'wpsstm_track_new_source_nonce', true );?>
    </form>
    <?php
}