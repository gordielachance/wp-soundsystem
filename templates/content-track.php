<?php
global $wpsstm_track;
$wait_for_ajax = ( wpsstm()->get_options('ajax_tracks') && !wp_doing_ajax() );

if (!$wait_for_ajax){
    $wpsstm_track->populate_track_metas();
}


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
            <?php 
            if ($wpsstm_track->album) {
                ?>
                <span class="wpsstm-track-album" itemprop="inAlbum" title="<?php echo $wpsstm_track->album;?>"><?php echo $wpsstm_track->album;?></span>
                <?php
            }
            ?>
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
    
    if ( !$wait_for_ajax ) { //load links now ?
        wpsstm_locate_template( 'content-track-links.php', true, false );
    }

    ?>
</wpsstm-track>