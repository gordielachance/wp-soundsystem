<?php
global $wpsstm_tracklist;
$wpsstm_tracklist->populate_tracks(array('posts_per_page'=>-1));

$tracklist = $wpsstm_tracklist;
?>

<div itemscope class="<?php echo implode(' ',$tracklist->get_tracklist_class('wpsstm-tracklist-list') );?>" data-wpsstm-tracklist-id="<?php echo $tracklist->post_id; ?>" data-wpsstm-tracklist-idx="<?php echo $tracklist->position;?>" data-wpsstm-tracklist-type="<?php echo $tracklist->tracklist_type;?>" data-wpsstm-tracklist-options="<?php echo $tracklist->get_tracklist_options_attr();?>" data-tracks-count="<?php echo $tracklist->track_count;?>" itemtype="http://schema.org/MusicPlaylist" data-wpsstm-expire-time="<?php echo $tracklist->get_expire_time();?>">
    <meta itemprop="numTracks" content="<?php echo $tracklist->track_count;?>" />
    <?php 
    if ( $tracklist->have_tracks() ) { 
    ?>
        <ol class="wpsstm-tracklist-entries">
            <?php
            $track_position = 0;
            while ( $tracklist->have_tracks() ) {
                $tracklist->the_track();
                global $wpsstm_track;
                $track = $wpsstm_track;
                ?>
                <li>
                    <strong class="wpsstm-track-artist" itemprop="byArtist"><?php echo $track->artist;?></strong>
                    <span class="wpsstm-track-title" itemprop="name"><?php echo $track->title;?></span>
                </li>
                <?php
            } 
            ?>
       </ol>
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
<?php
