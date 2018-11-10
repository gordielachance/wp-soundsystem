<?php

global $wpsstm_track;
$track = $wpsstm_track;
$track->local_track_lookup(); //check for this track in the database (if it has no ID)
$track->populate_sources();

?>
<li class="<?php echo implode(' ',$track->get_track_class());?>" <?php echo $track->get_track_attr();?>>
    <div class="wpsstm-track-row">
        <div class="wpsstm-track-pre">
            <?php if ( $track->tracklist->get_options('can_play') ){ ?>
                <span class="wpsstm-track-play-bt">
                    <a class="wpsstm-track-icon wpsstm-icon" href="#"></a>
                </span>
            <?php } ?>
            <span class="wpsstm-track-position">
                <span itemprop="position"><?php echo $track->index + 1;?></span>
            </span>
            <span class="wpsstm-track-image" itemprop="image">
                <?php 
                if ($track->image_url){
                    ?>
                    <img src="<?php echo $track->image_url;?>" />
                    <?php
                }
                ?>
            </span>
        </div>
        <div class="wpsstm-track-info">
            <span class="wpsstm-track-artist" itemprop="byArtist" title="<?php echo $track->artist;?>"><?php echo $track->artist;?></span>
            <span class="wpsstm-track-title" itemprop="name" title="<?php echo $track->title;?>"><?php echo $track->title;?></span>
            <span class="wpsstm-track-album" itemprop="inAlbum" title="<?php echo $track->album;?>"><?php echo $track->album;?></span>
        </div>
        <?php
        if ( $actions = $track->get_track_links() ){
            echo get_actions_list($actions,'track');
        }
        ?>
    </div>
    <div class="wpsstm-track-row wpsstm-track-sources wpsstm-sources-toggle">
        <?php
        //track sources
        wpsstm_locate_template( 'content-source.php', true, false );
        ?>
        <span class="wpsstm-expand-sources">
            <a href="#"><span><?php _e('Source Switch','wpsstm');?></span></a>
        </span>
    </div>
</li>