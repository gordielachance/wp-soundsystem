<?php
global $wpsstm_tracklist;
$tracklist = $wpsstm_tracklist;

//subtracks query
$subtracks_query = $tracklist->query_subtracks(array('posts_per_page'=>-1));

?>


<div itemscope class="<?php echo implode(' ',$tracklist->get_tracklist_class() );?>" data-wpsstm-tracklist-id="<?php the_ID(); ?>" data-wpsstm-tracklist-type="<?php echo $tracklist->tracklist_type;?>" data-wpsstm-autosource="<?php echo (int)$tracklist->get_options('autosource');?>" data-wpsstm-autoplay="<?php echo (int)$tracklist->get_options('autoplay');?>" data-tracks-count="<?php echo $subtracks_query->post_count;?>" itemtype="http://schema.org/MusicPlaylist" data-wpsstm-expire-time="<?php echo $tracklist->get_expire_time();?>">
    <meta itemprop="numTracks" content="<?php echo $subtracks_query->post_count;?>" />
    <div class="tracklist-nav tracklist-wpsstm_live_playlist top">
        <div>
            <strong class="wpsstm-tracklist-title" itemprop="name">
                <i class="wpsstm-tracklist-loading-icon fa fa-circle-o-notch fa-spin fa-fw"></i>
                <a href="<?php the_permalink();?>"><?php the_title();?></a>
            </strong>

            <small class="wpsstm-tracklist-time">
                <time class="wpsstm-tracklist-published"><i class="fa fa-clock-o" aria-hidden="true"></i> <?php echo wpsstm_get_datetime( get_post_modified_time('U') );?></time>
                <?php 
                if ( $rate = $tracklist->get_refresh_rate() ){
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
            <?php _e( 'No tracks found.','wpsstm');?>
        </p>
        <?php
    }
    
    //clear query
    wp_reset_query();
    
    ?>
</div>