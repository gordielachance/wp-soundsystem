<?php

global $wpsstm_track;
$wpsstm_track->local_track_lookup(); //check for this track in the database (if it has no ID)
$has_player = wpsstm()->get_options('player_enabled');

?>
<wpsstm-track class="<?php echo implode(' ',$wpsstm_track->get_track_class());?>" <?php echo $wpsstm_track->get_track_attr();?>>
    <div class="wpsstm-track-row">
        <div class="wpsstm-track-pre">
            <?php if ( $has_player ){ ?>
                <span class="wpsstm-track-play-bt">
                    <a class="wpsstm-track-icon wpsstm-icon" href="#"></a>
                </span>
            <?php } ?>
            <span class="wpsstm-track-position">
                <span itemprop="position"><?php echo $wpsstm_track->position;?></span>
            </span>
            <span class="wpsstm-track-image" itemprop="image">
                <?php 
                if ($wpsstm_track->image_url){
                    ?>
                    <img src="<?php echo $wpsstm_track->image_url;?>" />
                    <?php
                }
                ?>
            </span>
        </div>
        <div class="wpsstm-track-info">
            <span class="wpsstm-track-artist" itemprop="byArtist" title="<?php echo $wpsstm_track->artist;?>"><?php echo $wpsstm_track->artist;?></span>
            <span class="wpsstm-track-title" itemprop="name" title="<?php echo $wpsstm_track->title;?>"><?php echo $wpsstm_track->title;?></span>
            <span class="wpsstm-track-album" itemprop="inAlbum" title="<?php echo $wpsstm_track->album;?>"><?php echo $wpsstm_track->album;?></span>
        </div>
        <?php 

        //track actions
        if ( $actions = $wpsstm_track->get_track_links() ){
            echo get_actions_list($actions,'track');
        }
        
        ?>
    </div>
    <?php
    //track sources
    $wpsstm_track->populate_sources();
    wpsstm_locate_template( 'content-sources.php', true, false );
    ?>
</wpsstm-track>