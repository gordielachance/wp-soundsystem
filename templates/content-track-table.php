<?php

global $wpsstm_tracklist;
$tracklist = $wpsstm_tracklist;

global $wpsstm_track;
$track = $wpsstm_track;
?>
<li itemscope class="" data-wpsstm-track-id="<?php the_ID(); ?>" data-wpsstm-sources-count="<?php echo wpsstm_get_track_sources_count();?>" itemtype="http://schema.org/MusicRecording" itemprop="track">
    <span class="wpsstm-track-column trackitem_position column-trackitem_position">
        <i class="wpsstm-player-icon wpsstm-player-icon-buffering fa fa-circle-o-notch fa-spin fa-fw"></i>
        <span itemprop="position"><?php echo $track->position;?></span>
    </span>
    <span class="wpsstm-track-column trackitem_play_bt column-trackitem_play_bt">
        <a class="wpsstm-play-track wpsstm-icon-link" href="#">
        <i class="wpsstm-player-icon wpsstm-player-icon-error fa fa-exclamation-triangle" aria-hidden="true"></i>
        <i class="wpsstm-player-icon wpsstm-player-icon-pause fa fa-pause" aria-hidden="true"></i>
        <i class="wpsstm-player-icon wpsstm-player-icon-play fa fa-play" aria-hidden="true"></i>
        </a>
    </span>
    <?php 
    if ( $track->image ){
        ?>
        <span class="wpsstm-track-column trackitem_image column-trackitem_image" itemprop="image"><img src="<?php echo $track->image;?>" /></span>
        <?php
    }
    ?>
    <span class="wpsstm-track-column trackitem_artist column-trackitem_artist" itemprop="byArtist"><?php echo $track->artist;?></span>
    <span class="wpsstm-track-column trackitem_track column-trackitem_track" itemprop="name"><?php echo $track->title;?></span>
    <?php 
    if ( $track->album ){
        ?>
        <span class="wpsstm-track-column trackitem_album column-trackitem_album" itemprop="inAlbum"><img src="<?php echo $track->album;?>" /></span>
        <?php
    }
    ?>
    <span class="wpsstm-track-column trackitem_actions column-trackitem_actions">
        <?php 
            //tracklist actions
            if ( $actions = $track->get_track_actions($tracklist,'page') ){
                echo wpsstm_get_actions_list($actions,'track');
            }
        ?>
        </span>
        <span class="wpsstm-track-column trackitem_sources column-trackitem_sources">
            <?php
            //get track sources
            $source_ids = $track->get_track_source_ids();

            $source_args = array(
                'post__in' =>   $source_ids,
                'post_type' =>  wpsstm()->post_type_source,
            );

            $sources_query = new WP_Query($source_args);

            if ( $sources_query->have_posts() ) { ?>
                <ul class="wpsstm-track-sources-list">
                    <?php
                    $source_position = 0;
                    while ( $sources_query->have_posts() ) { 
                        $sources_query->the_post();
                        
                        global $wpsstm_source;
                        $source_position++;
                        $wpsstm_source->position = $source_position;
                        
                        wpsstm_locate_template( 'content-source.php', true, false );
                    }
                    ?>
                </ul>
            <?php 
            }

            ?>
    </span>
</li>