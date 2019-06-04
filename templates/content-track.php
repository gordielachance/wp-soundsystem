<?php

global $wpsstm_track;
$wpsstm_track->local_track_lookup(); //check for this track in the database (if it has no ID) //TOUFIX TOUCHECK useful ?

?>
<wpsstm-track <?php echo $wpsstm_track->get_track_attr();?>>
    <div class="wpsstm-track-row">
        <div class="wpsstm-track-pre">
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
    //track links
    wpsstm_locate_template( 'content-track-links.php', true, false );

    
    ?>
</wpsstm-track>