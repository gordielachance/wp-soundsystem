<?php
global $wpsstm_tracklist;
$tracklist = $wpsstm_tracklist;

if ($track_count){

    ?>


    <div itemscope class="<?php echo implode(' ',$tracklist->get_tracklist_class() );?>" data-wpsstm-tracklist-id="<?php the_ID(); ?>" data-wpsstm-tracklist-idx="<?php echo $tracklist->position;?>" data-wpsstm-tracklist-type="<?php echo $tracklist->tracklist_type;?>" data-wpsstm-tracklist-options="<?php echo $tracklist->get_tracklist_options_attr();?>" data-tracks-count="<?php echo $track_count;?>" itemtype="http://schema.org/MusicPlaylist" data-wpsstm-expire-time="<?php echo $tracklist->get_expire_time();?>">
        <meta itemprop="numTracks" content="<?php echo $track_count;?>" />
        <?php 
        if ( $->have_tracks() ) { 
        ?>
            <ol class="wpsstm-tracklist-entries">
                <?php
                $track_position = 0;
                while ( $->have_tracks() ) {
                    $the_track();
                    global $wpsstm_track;
                    $track_position++;
                    $wpsstm_track->position = $track_position;
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
        }

        //clear query
        wp_reset_query();

        ?>
    </div>
    <?php
}