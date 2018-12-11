<?php
global $wpsstm_track;
?>
<div class="wpsstm-track-info">
    <span class="wpsstm-track-artist" itemprop="byArtist" title="<?php echo $wpsstm_track->artist;?>"><?php echo $wpsstm_track->artist;?></span>
    <span class="wpsstm-track-title" itemprop="name" title="<?php echo $wpsstm_track->title;?>"><?php echo $wpsstm_track->title;?></span>
    <span class="wpsstm-track-album" itemprop="inAlbum" title="<?php echo $wpsstm_track->album;?>"><?php echo $wpsstm_track->album;?></span>
    <?php 
    if ($wpsstm_track->from_tracklist){
        ?>
        <span class="wpsstm-from-tracklist">
            <?php _e('From:','wpsstm');?>
            <a target="_parent" href="<?php echo get_permalink($wpsstm_track->from_tracklist);?>"><?php echo get_the_title($wpsstm_track->from_tracklist);?></a>
        </span>
        <?php
    }
    ?>
</div>