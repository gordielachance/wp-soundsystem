<?php
global $wpsstm_track;
?>
<div id="wpsstm-track-admin-edit" class="wpsstm-track-admin">
    <div id="track-admin-artist">
        <h3><?php _e('Artist','wpsstm');?></h3>
        <input name="wpsstm_track_artist" type="text" value="<?php echo $wpsstm_track->artist;?>" class="wpsstm_search_artists wpsstm-fullwidth" />
    </div>

    <div id="track-admin-title">
        <h3><?php _e('Title','wpsstm');?></h3>
        <input name="wpsstm_track_title" type="text" value="<?php echo $wpsstm_track->title;?>" class="wpsstm-fullwidth" />
    </div>

    <div id="track-admin-album">
        <h3><?php _e('Album','wpsstm');?></h3>
        <input name="wpsstm_track_album" type="text" value="<?php echo $wpsstm_track->album;?>" class="wpsstm-fullwidth" />
    </div>

    <div id="track-admin-mbid">
        <h3><?php _e('MusicBrainz ID','wpsstm');?></h3>
        <input name="wpsstm_track_mbid" type="text" value="<?php echo $wpsstm_track->mbid;?>" class="wpsstm-fullwidth" />
    </div>

    <p class="wpsstm-submit-wrapper">
        <input id="wpsstm-update-track-bt" type="submit" value="<?php _e('Save');?>" />
    </p>
</div>