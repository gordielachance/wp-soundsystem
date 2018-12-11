<?php

global $wpsstm_track;
$wpsstm_track->local_track_lookup(); //check for this track in the database (if it has no ID)
$can_play_track = $wpsstm_track->can_play_track();

?>
<li class="<?php echo implode(' ',$wpsstm_track->get_track_class());?>" <?php echo $wpsstm_track->get_track_attr();?>>
    <div class="wpsstm-track-row">
        <div class="wpsstm-track-pre">
            <?php if ( $can_play_track && !is_wp_error($can_play_track) ){ ?>
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
        <?php 
        
        //track header
        wpsstm_locate_template( 'track-header.php', true, false );
        
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
</li>