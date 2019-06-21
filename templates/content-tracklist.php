<?php

global $wpsstm_tracklist;
$wpsstm_tracklist->populate_subtracks();
$wpsstm_tracklist->classes[] = 'wpsstm-post-tracklist';

//imported tracklist notice
//TOUFIX is this the right place ?
$notice = $wpsstm_tracklist->autorship_notice();
$notice = $wpsstm_tracklist->no_tracks_notice();
$notice = $wpsstm_tracklist->importer_notice();

?>
<wpsstm-tracklist class="<?php echo implode(' ',$wpsstm_tracklist->classes);?>" <?php echo $wpsstm_tracklist->get_tracklist_attr();?>>
    <?php
    wpsstm_locate_template( 'content-tracklist-header.php', true, false );

    //actions
    if ( $actions = $wpsstm_tracklist->get_tracklist_actions() ){
        $list = get_actions_list($actions,'tracklist');
        echo $list;
    }
    
    /*
    Notices
    */
    if ( $notices_el = WP_SoundSystem::get_notices_output($wpsstm_tracklist->notices) ){
        ?>
        <ul class="wpsstm-tracklist-notices">
            <?php echo $notices_el; ?>
        </ul>
        <?php
    }
    
    /*
    tracks list
    */

    if ( $wpsstm_tracklist->have_subtracks() ) {
    ?>
        <div class="wpsstm-tracks-list">
            <?php

            while ( $wpsstm_tracklist->have_subtracks() ) {
                $wpsstm_tracklist->the_subtrack();
                global $wpsstm_track;
                echo $wpsstm_track->get_track_html();
            }
            ?>
       </div>
    <?php
    }
    
    /*
    new subtrack
    */
    
    if ( $wpsstm_tracklist->user_can_reorder_tracks() ){
        ?>
        <div class="wpsstm-new-subtrack">
            <label><?php _e('New track','wpsstm');?></label>
            <p class="wpsstm-new-subtrack-fields">
            <input type="text" name="wpsstm_track_data[artist]" placeholder="<?php _e('Artist','wpsstm');?>"/>
            <input type="text" name="wpsstm_track_data[title]" placeholder="<?php _e('Title','wpsstm');?>"/>
            <input type="text" name="wpsstm_track_data[album]" placeholder="<?php _e('Album','wpsstm');?>"/>
            <button type="submit" class="button button-primary wpsstm-icon-button"><i class="fa fa-plus" aria-hidden="true"></i><span> <?php _e('Add subtrack','wpsstm');?></span></button>
            </p>
            <input type="hidden" name="tracklist_id" value="<?php echo $wpsstm_tracklist->post_id;?>"/>
        </div>
        <?php
    }

    ?>
</wpsstm-tracklist>