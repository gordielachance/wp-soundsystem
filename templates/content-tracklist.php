<?php

global $wpsstm_tracklist;
$wpsstm_tracklist->populate_subtracks();

//imported tracklist notice
//TOUFIX is this the right place ?
$notice = $wpsstm_tracklist->autorship_notice();
$notice = $wpsstm_tracklist->no_tracks_notice();
$notice = $wpsstm_tracklist->importer_notice();

?>
<wpsstm-tracklist class="<?php echo implode(' ',$wpsstm_tracklist->classes);?>" <?php echo $wpsstm_tracklist->get_tracklist_attr();?>>
    <?php
    wpsstm_locate_template( 'content-tracklist-header.php', true, false );
    
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
            <div id="wpsstm-queue-tracks">
                <p class="wpsstm-new-track">
                    <input type="text" name="wpsstm_track_data[artist]" placeholder="<?php _e('Artist','wpsstm');?>"/>
                    <input type="text" name="wpsstm_track_data[title]" placeholder="<?php _e('Title','wpsstm');?>"/>
                    <input type="text" name="wpsstm_track_data[album]" placeholder="<?php _e('Album','wpsstm');?>"/>
                    <button type="submit" class="button button-primary wpsstm-icon-button wpsstm-remove-new-track-row"><i class="fa fa-minus" aria-hidden="true"></i></button>
                </p>
                <p>
                    <button type="submit" id="wpsstm-queue-tracks-submit" class="button button-primary"><span> <?php _e('Add tracks','wpsstm');?></span></button>
                    <a href="#" id="wpsstm-queue-more-tracks"><?php _e('Add row','wpsstm');?></a>
                    <input type="hidden" name="tracklist_id" value="<?php echo $wpsstm_tracklist->post_id;?>"/>
                </p>
            </div>
            <?php
        }
        ?>
    </section>
</wpsstm-tracklist>