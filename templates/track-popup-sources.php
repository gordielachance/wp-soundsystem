<?php
global $wpsstm_track;


//track sources
wpsstm_locate_template( 'track-sources.php', true, false );
?>
<p class="wpsstm-submit-wrapper">
    <input id="wpsstm-autosource-bt" type="submit" name="wpsstm_sources[action][autosource]" class="button" value="<?php _e('Autosource','wpsstm');?>">
    <input class="wpsstm-backend-toggle" type="submit" name="wpsstm_sources[action][backend]" class="button" value="<?php _e('Backend listing','wpsstm');?>">
</p>
<p class="wpsstm-icon-input" id="wpsstm-new-source">
    <input type="text" name="wpsstm_sources[source-url]" value="" class="wpsstm-fullwidth" placeholder="Enter a tracklist URL">
    <input type="submit" name="wpsstm_sources[action][new-source]" class="button button-primary" value="+">
</p>
<input type="hidden" name="wpsstm-track-popup-action" value="sources-manager" />
<input type="hidden" name="wpsstm-track-id" value="<?php echo $wpsstm_track->post_id;?>" />

<?php wp_nonce_field( sprintf('wpsstm_track_%s_new_source_nonce',$wpsstm_track->post_id), 'wpsstm_track_new_source_nonce', true );?>