<?php
global $wpsstm_tracklist;
$tracklist = $wpsstm_tracklist;

//TO FIX move at a smarter place ?
if ( $wpsstm_tracklist->get_options('can_play') ){
    do_action('init_playable_tracklist'); //used to know if we must load the player stuff (scripts/styles/html...)
}

?>

<div itemscope class="<?php echo implode(' ',$tracklist->get_tracklist_class('wpsstm-tracklist-table') );?>" data-wpsstm-tracklist-id="<?php the_ID(); ?>" data-wpsstm-tracklist-idx="<?php echo $tracklist->position;?>" data-wpsstm-tracklist-type="<?php echo $tracklist->tracklist_type;?>" data-wpsstm-tracklist-options="<?php echo $tracklist->get_tracklist_options_attr();?>" data-tracks-count="<?php echo $tracklist->track_count;?>" itemtype="http://schema.org/MusicPlaylist" data-wpsstm-expire-time="<?php echo $tracklist->get_expire_time();?>">
    <meta itemprop="numTracks" content="<?php echo $tracklist->track_count;?>" />
    <div class="tracklist-nav tracklist-wpsstm_live_playlist top">
        <div>
            <strong class="wpsstm-tracklist-title" itemprop="name">
                <i class="wpsstm-tracklist-loading-icon fa fa-circle-o-notch fa-spin fa-fw"></i>
                <a href="<?php the_permalink();?>"><?php echo $tracklist->title;?></a>
            </strong>
            <small class="wpsstm-tracklist-time">
                <time class="wpsstm-tracklist-published"><i class="fa fa-clock-o" aria-hidden="true"></i> <?php echo wpsstm_get_datetime( $tracklist->updated_time );?></time>
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
    if ( $notices_el = $tracklist->get_notices_output('tracklist-header') ){
        echo $notices_el;
    }
    ?>
    <?php
    if ( $tracklist->have_tracks() ) {
    ?>
        <ul class="wpsstm-tracklist-entries">
            <?php
            while ( $tracklist->have_tracks() ) {
                $tracklist->the_track();
                wpsstm_locate_template( 'content-track-table.php', true, false );
            } 
            ?>
       </ul>
    <?php 
    }else{
        ?>
        <p id="wpsstm-notice-empty-tracklist" class="wpsstm-notice">
            <?php echo $tracklist->empty_tracks_msg();?>
        </p>
        <?php
    }

    ?>
</div>