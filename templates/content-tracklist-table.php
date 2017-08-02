<?php
global $wpsstm_tracklist;
$tracklist = $wpsstm_tracklist;

if ( $tracklist->get_options('can_play') ){
    do_action('init_playable_tracklist'); //used to know if we must load the player stuff (scripts/styles/html...)
}
?>


<div itemscope <?php wpsstm_tracklist_class();?> data-wpsstm-tracklist-id="<?php the_ID(); ?>" data-wpsstm-tracklist-type="<?php wpsstm_tracklist_type();?>" data-wpsstm-autosource="<?php echo (int)$tracklist->get_options('autosource');?>" data-wpsstm-autoplay="<?php echo (int)$tracklist->get_options('autoplay');?>" data-tracks-count="<?php echo (int)wpsstm_get_tracklist_substracks_count();?>" itemtype="http://schema.org/MusicPlaylist" data-wpsstm-expire-sec="<?php echo wpsstm_get_tracklist_remaining_cache_seconds();?>">
    <meta itemprop="numTracks" content="<?php echo (int)wpsstm_get_tracklist_substracks_count();?>" />
    <div class="tracklist-nav tracklist-wpsstm_live_playlist top">
        <div>
            <strong class="wpsstm-tracklist-title" itemprop="name">
                <i class="wpsstm-tracklist-loading-icon fa fa-circle-o-notch fa-spin fa-fw"></i>
                <a href="<?php the_permalink();?>"><?php the_title();?></a>
            </strong>

            <small class="wpsstm-tracklist-time">
                <time class="wpsstm-tracklist-published"><i class="fa fa-clock-o" aria-hidden="true"></i> <?php echo wpsstm_get_datetime( get_post_modified_time('U') );?></time>
                <?php 
                if ( $rate = wpsstm_get_tracklist_refresh_rate() ){
                    ?>
                    <time class="wpsstm-tracklist-refresh-time"><i class="fa fa-rss" aria-hidden="true"></i> <?php printf(__('every %s','wpsstm'),$rate);?></time>
                    <?php
                }
                ?>
            </small>
            <?php 
                //tracklist actions
                if ( $actions = $tracklist->get_tracklist_actions('page') ){
                    echo wpsstm_get_actions_list($actions,'tracklist');
                }
            ?>
        </div>
    </div>
            
    <?php 
        //tracklist notices
        if ( $notices_el = $tracklist->get_notices('tracklist-header') ){
            echo $notices_el;
        }
    ?>
    <?php 
    
    //get subtracks
    $subtrack_ids = $tracklist->get_subtrack_ids();
    $subtracks_args = array(
        'post__in' =>   $subtrack_ids,
        'post_type' =>  wpsstm()->post_type_track,
    );

    $subtracks_query = new WP_Query($subtracks_args);
    
    if ( $subtracks_query->have_posts() ) { ?>
        <ul class="wpsstm-tracklist-entries">
            <?php
            $track_position = 0;
            while ( $subtracks_query->have_posts() ) {
                $subtracks_query->the_post();
                global $wpsstm_track;
                $track_position++;
                $wpsstm_track->position = $track_position;
                wpsstm_locate_template( 'content-track-table.php', true, false );
            } 
            ?>
       </ul>
    <?php 
    }else{
        ?>
        <p class="wpsstm-notice">
            <?php 
            if ( ( wpsstm_get_tracklist_type() == 'live' ) && $tracklist->is_expired){
                _e("The tracklist cache has expired.","wpsstm"); 
            }else{
                _e( 'No tracks found.','wpsstm');
            }
            ?>
        </p>
        <?php
    }
    ?>
</div>