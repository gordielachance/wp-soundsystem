<?php
global $wpsstm_tracklist;
$has_player = wpsstm()->get_options('player_enabled');
?>
<div class="<?php echo implode(' ',$wpsstm_tracklist->get_tracklist_class('wpsstm-post-tracklist'));?>" <?php echo $wpsstm_tracklist->get_tracklist_attr();?>>
    <?php $wpsstm_tracklist->html_metas();?>
    <div class="tracklist-header tracklist-wpsstm_live_playlist top">
        <h3 class="wpsstm-tracklist-title" itemprop="name" title="<?php echo get_the_title();?>">
            <?php if ( $has_player ){ ?>
                <i class="wpsstm-tracklist-icon wpsstm-icon"></i>
            <?php } ?>
            <a target="_parent" href="<?php echo get_permalink($wpsstm_tracklist->post_id);?>"><?php echo get_the_title();?></a>
                <?php
                //live playlist icon
                if ($wpsstm_tracklist->tracklist_type == 'live'){
                    ?>
                    <i class="wpsstm-live-tracklist-icon fa fa-rss" aria-hidden="true" title="<?php _e("This is a live tracklist, it will auto-update!","wpsstm");?>"></i>
                    <?php
                }
                ?>
        </h3>
        <a href="#" class="tracklist-advanced-header-bt">
            <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
        </a>
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
    }else{ //no tracks
        ?>
        <p id="wpsstm-no-tracks">
            <?php _e('No tracks found.','wpsstm'); ?>
        </p>
        <?php
    }
    
    /*
    new subtrack
    */
    
    if ( $wpsstm_tracklist->user_can_reorder_tracks() ){
        ?>
        <form class="wpsstm-new-subtrack" action="<?php echo $wpsstm_tracklist->get_tracklist_action_url('queue');?>" method="post">
            <label><?php _e('New track','wpsstm');?></label>
            <p class="wpsstm-new-subtrack-fields">
            <input type="text" name="wpsstm_track_data[artist]" placeholder="<?php _e('Artist','wpsstm');?>"/>
            <input type="text" name="wpsstm_track_data[title]" placeholder="<?php _e('Title','wpsstm');?>"/>
            <input type="text" name="wpsstm_track_data[album]" placeholder="<?php _e('Album','wpsstm');?>"/>
            <button type="submit" class="button button-primary wpsstm-icon-button"><i class="fa fa-plus" aria-hidden="true"></i><span> <?php _e('Add subtrack','wpsstm');?></span></button>
            </p>
            <input type="hidden" name="tracklist_id" value="<?php echo $wpsstm_tracklist->post_id;?>"/>
        </form>
        <?php
    }

    ?>
</div>