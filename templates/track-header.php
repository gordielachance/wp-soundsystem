<?php
global $wpsstm_track;
?>
<header>
<div class="wpsstm-track-info">
    <h1 class="wpsstm-track-artist" itemprop="byArtist" title="<?php echo $wpsstm_track->artist;?>"><?php echo $wpsstm_track->artist;?></h1>
    <h2 class="wpsstm-track-title" itemprop="name" title="<?php echo $wpsstm_track->title;?>"><?php echo $wpsstm_track->title;?></h2>
    <h3 class="wpsstm-track-album" itemprop="inAlbum" title="<?php echo $wpsstm_track->album;?>"><?php echo $wpsstm_track->album;?></h3>
</div>
    <?php 
    /*
if ($wpsstm_track->from_tracklist){
    ?>
    <div class="wpsstm-from-tracklist">
        <label><?php _e('From:','wpsstm');?></label>
        <a target="_parent" href="<?php echo get_permalink($wpsstm_track->from_tracklist);?>"><?php echo get_the_title($wpsstm_track->from_tracklist);?></a>
    </div>
    <?php
}
*/
/*
Parent playlists
*/
if ( $playlists_list = $wpsstm_track->get_parents_list() ){

    ?>
    <div class="wpsstm-track-tracklists">
        <label><?php _e('In tracklists:','wpsstm');?></label>
        <?php echo $playlists_list; ?>
    </div>
    <?php
}
/*
Favorited by
*/
if ( $loved_list = $wpsstm_track->get_favorited_by_list() ){
    ?>
    <div class="wpsstm-track-loved-by">
        <label><?php _e('Loved by:','wpsstm');?></label>
        <?php echo $loved_list; ?>
    </div>
    <?php
}
?>
</header>


    <?php
    /*
    Notices
    */
    if ( $notices_el = WP_SoundSystem::get_notices_output($wpsstm_track->notices) ){
        ?>
        <div class="wpsstm-track-notices"><?php echo $notices_el;?></div>
        <?php
    }
    ?>
