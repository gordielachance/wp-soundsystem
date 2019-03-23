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
    <?php $wpsstm_tracklist->html_metas();?>
    <div class="tracklist-header tracklist-wpsstm_live_playlist top">
        <h3 class="wpsstm-tracklist-title" itemprop="name" title="<?php echo $wpsstm_tracklist->title;?>">
            <i class="wpsstm-tracks-container-icon wpsstm-icon"></i>
            <a target="_parent" href="<?php echo get_permalink($wpsstm_tracklist->post_id);?>"><?php echo $wpsstm_tracklist->title;?></a>
                <?php
                //radio icon
                if ($wpsstm_tracklist->tracklist_type == 'live'){
                    ?>
                    <span class="wpsstm-live-tracklist-icon wpsstm-reload-bt" title="<?php _e("This is a live tracklist, it will auto-update!","wpsstm");?>">
                        <i class="fa fa-rss" aria-hidden="true"></i>
                    </span>
                    <?php
                }
                ?>
        </h3>
        <div class="tracklist-advanced-header">
            <p class="wpsstm-tracklist-time">
                <?php
                //updated
                if ($updated = $wpsstm_tracklist->updated_time){
                    ?>
                    <time class="wpsstm-tracklist-updated">
                        <i class="fa fa-clock-o" aria-hidden="true"></i> 
                        <?php echo wpsstm_get_datetime( $updated );?>
                    </time>
                    <?php 
                }
                //refreshed
                if ( ($wpsstm_tracklist->tracklist_type == 'live') && ( $rate = $wpsstm_tracklist->get_human_next_refresh_time() ) ){
                    ?>
                    <time class="wpsstm-tracklist-refresh-time">
                        <i class="fa fa-rss" aria-hidden="true"></i> 
                        <?php printf(__('cached for %s','wpsstm'),$rate);?>
                    </time>
                    <?php
                }
                ?>
            </p>
            <?php
                //original link
                if ( ($wpsstm_tracklist->tracklist_type == 'live') && ($wpsstm_tracklist_url = $wpsstm_tracklist->feed_url) ){

                    //$wpsstm_tracklist_url = substr($wpsstm_tracklist_url, 0, strrpos($wpsstm_tracklist_url, ' ')) . " ...";

                    ?>
                    <p>
                        <a class="wpsstm-live-tracklist-link" target="_blank" href="<?php echo $wpsstm_tracklist_url;?>">
                            <i class="fa fa-link" aria-hidden="true"></i> 
                            <?php echo wpsstm_shorten_text($wpsstm_tracklist_url);?>
                        </a>
                    </p>

                    <?php
                }

                //actions
                if ( $actions = $wpsstm_tracklist->get_tracklist_actions() ){
                    $list = get_actions_list($actions,'tracklist');
                    echo $list;
                }
            ?>
        </div>
    </div>
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
    tracks list
    */

    if ( $wpsstm_tracklist->have_subtracks() ) {
    ?>
        <ul class="wpsstm-tracks-list">
            <?php

            while ( $wpsstm_tracklist->have_subtracks() ) {
                $wpsstm_tracklist->the_subtrack();
                wpsstm_locate_template( 'content-track.php', true, false );
            }
            ?>
       </ul>
    <?php
    }
    
    /*
    new subtrack
    */
    
    if ( $wpsstm_tracklist->user_can_reorder_tracks() ){
        ?>
        <div class="wpsstm-new-subtrack" action="<?php echo $wpsstm_tracklist->get_tracklist_action_url('queue');?>" method="post">
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