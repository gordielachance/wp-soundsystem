<?php

global $wpsstm_tracklist;
$wpsstm_tracklist->populate_subtracks();

$tracklist = $wpsstm_tracklist;

//TO FIX move at a smarter place ?
if ( $wpsstm_tracklist->get_options('can_play') ){
    do_action('init_playable_tracklist'); //used to know if we must load the player stuff (scripts/styles/html...)
}

?>

<div class="<?php echo implode(' ',$tracklist->get_tracklist_class());?>" <?php echo $tracklist->get_tracklist_attr();?>>
    <meta itemprop="numTracks" content="<?php echo $tracklist->track_count;?>" />
    <div class="tracklist-header tracklist-wpsstm_live_playlist top">
        <i class="wpsstm-tracklist-icon wpsstm-icon"></i>
        <strong class="wpsstm-tracklist-title" itemprop="name">
            <a href="<?php echo get_permalink($tracklist->post_id);?>"><?php echo $tracklist->title;?></a>
        </strong>
        <small class="wpsstm-tracklist-time">
            <?php
            //updated
            if ($updated = $tracklist->updated_time){
                ?>
                <time class="wpsstm-tracklist-published">
                    <i class="fa fa-clock-o" aria-hidden="true"></i> 
                    <?php echo wpsstm_get_datetime( $updated );?>
                </time>
                <?php 
            }
            //refreshed
            if ( ($tracklist->tracklist_type == 'live') && ( $rate = $tracklist->get_time_before_refresh() ) ){
                ?>
                <time class="wpsstm-tracklist-refresh-time">
                    <i class="fa fa-rss" aria-hidden="true"></i> 
                    <?php printf(__('cached for %s','wpsstm'),$rate);?>
                </time>
                <?php
            }
            ?>
        </small>
        <?php
            //original link
            if ( ($tracklist->tracklist_type == 'live') && ($tracklist_url = $tracklist->feed_url) ){
                ?> 
                <a class="wpsstm-live-tracklist-link" target="_blank" href="<?php echo $tracklist_url;?>">
                    <i class="fa fa-link" aria-hidden="true"></i> 
                    <?php _e('Source','wpsstm');?>
                </a>
                <?php
            }
        ?>
        <?php 
            //tracklist actions
            if ( $actions = $tracklist->get_tracklist_links('page') ){
                echo get_actions_list($actions,'tracklist');
            }
        ?>
    </div>
    <?php
    //tracklist notices

    //wizard temporary tracklist notice
    //TO FIX should be in populate_wizard_tracklist() ?
    if ( !wpsstm_is_backend() && $tracklist->can_get_tracklist_authorship() ){
        $autorship_url = $tracklist->get_tracklist_action_url('get-autorship');
        $autorship_link = sprintf('<a href="%s">%s</a>',$autorship_url,__("add it to your profile","wpsstm"));
        $message = __("This is a temporary playlist.","wpsstm");
        $message .= '  '.sprintf(__("Would you like to %s?","wpsstm"),$autorship_link);
        $tracklist->add_notice( 'tracklist-header', 'get-autorship', $message );

    }
    
    if ( $tracklist->ajax_refresh ){
        /*
        REFRESH notice
        will be toggled using CSS
        */
        $tracklist->add_notice( 'tracklist-header', 'ajax-refresh', __('Refreshing...','wpsstm') );
    }else{
        
    }
    
    /*
    empty tracklist
    */
    if( $error = $tracklist->tracks_error ){
        $msg = sprintf( '<strong>%s</strong><br/><small>%s</small>',__('No tracks found.','wpsstm'),$error->get_error_message() );
        $tracklist->add_notice( 'tracklist-header', 'empty-tracklist', $msg );
    }

    if ( $notices_el = $tracklist->get_notices_output('tracklist-header') ){
        echo sprintf('<div class="wpsstm-tracklist-notices">%s</div>',$notices_el);
    }
    ?>

    <?php
    if ( $tracklist->have_tracks() ) {
    ?>
        <ul class="wpsstm-tracks-list">
            <?php
            while ( $tracklist->have_tracks() ) {
                $tracklist->the_track();
                global $wpsstm_track;
                $track = $wpsstm_track;
                $track->populate_sources();

                ?>
                    <li class="<?php echo implode(' ',$track->get_track_class());?>" <?php echo $track->get_track_attr();?>>
                        <span class="wpsstm-track-image" itemprop="image">
                            <?php 
                            if ($track->image_url){
                                ?>
                                <img src="<?php echo $track->image_url;?>" />
                                <?php
                            }
                            ?>
                        </span>
                        <?php if ( $wpsstm_tracklist->get_options('can_play') ){ ?>
                            <span class="wpsstm-track-play-bt">
                                <a class="wpsstm-track-icon wpsstm-icon" href="#"></a>
                            </span>
                        <?php } ?>
                        <span class="wpsstm-track-position">
                            <span itemprop="position"><?php echo $tracklist->current_track + 1;?></span>
                        </span>
                        <span class="wpsstm-track-info">
                            <span class="wpsstm-track-artist" itemprop="byArtist"><?php echo $track->artist;?></span>
                            <span class="wpsstm-track-title" itemprop="name"><?php echo $track->title;?></span>
                            <span class="wpsstm-track-album" itemprop="inAlbum"><?php echo $track->album;?></span>
                        </span>
                        <span class="wpsstm-track-actions">
                            <?php
                            if ( $actions = $track->get_track_links($tracklist,'page') ){
                                echo get_actions_list($actions,'track');
                            }
                            ?>
                        </span>
                        <span class="wpsstm-track-sources">
                            <?php
                            //track sources
                            wpsstm_locate_template( 'track-sources.php', true, false );
                            ?>
                        </span>
                    </li>
                <?php
            } 
            ?>
       </ul>
    <?php
        wp_reset_postdata(); //TOFIXTOCHECK useful ? Since we don't use the_post here...
    }

    ?>
</div>