<?php

global $wpsstm_tracklist;
$wpsstm_tracklist->populate_subtracks();

//imported tracklist notice
//TOUFIX is this the right place ?
$notice = $wpsstm_tracklist->autorship_notice();
$notice = $wpsstm_tracklist->no_tracks_notice();
$notice = $wpsstm_tracklist->importer_notice();

?>
<wpsstm-tracklist <?php echo $wpsstm_tracklist->get_tracklist_attr();?>>
    <?php

    if ( $wpsstm_tracklist->get_options('header') ){
        wpsstm_locate_template( 'content-tracklist-header.php', true, false );
    }

    /*
    Player
    */
    if ( $wpsstm_tracklist->get_options('playable') ){
        wpsstm_locate_template( 'player.php', true, false );
    }

    /*
    Queue
    */

    ?>
    <section class="wpsstm-tracklist-queue">
        <?php

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
        Queue
        Tracks list
        We DO output the container even if there is no tracks as it is required by some JS (to add a new track row).
        */

        ?>
        <div class="wpsstm-tracks-list">
          <?php
          if ( $wpsstm_tracklist->have_subtracks() ) {
            while ( $wpsstm_tracklist->have_subtracks() ) {
                $wpsstm_tracklist->the_subtrack();
                global $wpsstm_track;
                echo $wpsstm_track->get_track_html();
            }
          }
          ?>
        </div>
        <?php

        /*
        new subtrack
        */

        if ( $wpsstm_tracklist->user_can_reorder_tracks() ){
            ?>
            <div id="wpsstm-new-tracks">
                <div class="wpsstm-new-track">
                    <span class="wpsstm-new-track-data">
                        <input type="text" name="wpsstm_track_data[artist]" placeholder="<?php _e('Artist','wpsstm');?>"/>
                        <input type="text" name="wpsstm_track_data[title]" placeholder="<?php _e('Title','wpsstm');?>"/>
                        <input type="text" name="wpsstm_track_data[album]" placeholder="<?php _e('Album','wpsstm');?>"/>
                    </span>
                    <span class="wpsstm-new-track-actions">
                        <button type="submit" class="button button-primary wpsstm-icon-button wpsstm-save-new-track-row"><?php _e('Save','wpsstm');?></button>
                        <button type="submit" class="button button-secondary wpsstm-icon-button wpsstm-remove-new-track-row"><i class="fa fa-minus" aria-hidden="true"></i></button>
                    </span>
                </div>
                <button type="submit" id="wpsstm-add-new-track-row" class="button button-secondary"><?php _e('Add row','wpsstm');?></button>
                <input type="hidden" name="tracklist_id" value="<?php echo $wpsstm_tracklist->post_id;?>"/>
            </div>
            <?php
        }
        ?>
    </section>
</wpsstm-tracklist>
