<?php
global $wpsstm_track;
?>
<wpsstm-track <?php echo $wpsstm_track->get_track_attr();?>>
  <div class="wpsstm-track-row">
    <span class="wpsstm-track-position">
      <span itemprop="position"><?php echo $wpsstm_track->position;?></span>
    </span>
    <span class="wpsstm-track-image" itemprop="image">
      <?php
      if ($image_url = wpsstm_get_post_image_url($wpsstm_track->post_id) ){
        ?>
        <img src="<?php echo $image_url;?>" />
        <?php
      }
      ?>
    </span>
    <span class="wpsstm-track-info">
      <span class="wpsstm-track-artist" itemprop="byArtist" title="<?php echo $wpsstm_track->artist;?>"><?php echo $wpsstm_track->artist;?></span>
      <span class="wpsstm-track-title" itemprop="name" title="<?php echo $wpsstm_track->title;?>"><?php echo $wpsstm_track->title;?></span>
      <?php
      if ($wpsstm_track->album) {
        ?>
        <span class="wpsstm-track-album" itemprop="inAlbum" title="<?php echo $wpsstm_track->album;?>"><?php echo $wpsstm_track->album;?></span>
        <?php
      }
      ?>
    </span>
    <?php
    //track actions
    if ( $actions = $wpsstm_track->get_track_links() ){
      echo get_actions_list($actions,'track');
    }
    ?>
  </div>
  <?php
  //track links
  $wpsstm_track->populate_links();
  wpsstm_locate_template( 'content-track-links.php', true, false );
  ?>
</wpsstm-track>
