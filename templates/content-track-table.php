<?php

global $wpsstm_tracklist;
$tracklist = $wpsstm_tracklist;

global $wpsstm_track;
$track = $wpsstm_track;

$sources_query = $track->query_sources();


?>
<li class="<?php echo implode(' ',$track->get_track_class() );?>" itemscope data-wpsstm-track-id="<?php the_ID(); ?>" data-wpsstm-sources-count="<?php echo $sources_query->post_count;?>" itemtype="http://schema.org/MusicRecording" itemprop="track">
    <span class="wpsstm-track-left">
        <span class="wpsstm-track-position">
            <i class="wpsstm-player-icon wpsstm-player-icon-loading fa fa-circle-o-notch fa-spin fa-fw"></i>
            <span class="wpsstm-reposition-track"><i class="fa fa-arrows-v" aria-hidden="true"></i></span>
            <span itemprop="position"><?php echo (int)$track->position;?></span>
        </span>
        <span class="wpsstm-track-play-bt">
            <a class="wpsstm-play-track wpsstm-icon-link" href="#">
            <i class="wpsstm-player-icon wpsstm-player-icon-error fa fa-exclamation-triangle" aria-hidden="true"></i>
            <i class="wpsstm-player-icon wpsstm-player-icon-pause fa fa-pause" aria-hidden="true"></i>
            <i class="wpsstm-player-icon wpsstm-player-icon-play fa fa-play" aria-hidden="true"></i>
            </a>
        </span>
    </span>
    <span class="wpsstm-track-main">
        <span class="wpsstm-track-info">
            <?php 
            if ( $track->image ){
                ?>
                <span class="wpsstm-track-image" itemprop="image">CACA<img src="<?php echo $track->image;?>" /></span>
                <?php
            }
            ?>
            <span class="wpsstm-track-artist" itemprop="byArtist"><?php echo $track->artist;?></span>
            <span class="wpsstm-track-title" itemprop="name"><?php echo $track->title;?></span>
            <?php 
            if ( $track->album ){
                ?>
                <span class="wpsstm-track-album" itemprop="inAlbum"><?php echo $track->album;?></span>
                <?php
            }
            ?>
        </span>
    </span>
    <?php
    //track actions
    if ( $actions = $track->get_track_actions($tracklist,'page') ){
        ?>
        <span class="wpsstm-track-right wpsstm-track-actions">
            <?php echo wpsstm_get_actions_list($actions,'track');?>
        </span>
        <?php
    }
    ?>
    <span class="wpsstm-track-right wpsstm-track-sources">
        <?php
        //track sources
        wpsstm_locate_template( 'track-sources.php', true, false );
        ?>
    </span>
</li>
<?php
