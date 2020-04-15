<?php
global $wpsstm_tracklist;

$wpsstm_tracklist = new WPSSTM_Post_Tracklist(get_the_ID());

$load_tracklist = isset($_GET['wpsstm_load_tracklist']);

?>
<!--remote url-->
<div id="wpsstm-importer-step-feed-url" class="wpsstm-importer-section">
    <h3 class="wpsstm-importer-section-label"><?php _e('Feed URL','wpsstm');?> <small><a href="#" id="feed-url-help"><?php _e('help','wpsstm');?></a></small></h3>
    <?php
    if ( !WPSSTM_Core_API::is_premium() ){
        $xspf_link = sprintf('<a href="%s" target="_blank">%s</a>','http://xspf.org','.xspf');
        $notice = sprintf(__("Tracklist URL. Since you are not premium, it can only be a local file with a %s extension.  Bangs and remote URLs won't work !",'wpsstm'),$xspf_link);
        printf('<div class="notice notice-warning inline"><p>%s</p></div>',$notice);
    }
    ?>
    <p>
        <input type="text" name="wpsstm_importer[feed_url]" value="<?php echo esc_attr($wpsstm_tracklist->feed_url);?>" class="wpsstm-fullwidth" placeholder="<?php _e('Enter a tracklist URL or type a bang (eg. artist:Gorillaz)','wpsstm');?>" />
    </p>
    <?php

    //importers
    wpsstm_locate_template( 'importers-list.php', true, false);
    ?>
    <h3 class="wpsstm-importer-section-label"><?php _e('Website URL','wpsstm');?></h3>
    <?php _e("URL of the radio that will be displayed on the playlist.  If empty, the Feed URL will be used.",'wpsstm');?>
    <p>
        <input type="text" name="wpsstm_importer[website_url]" value="<?php echo esc_url($wpsstm_tracklist->website_url);?>" class="wpsstm-fullwidth" />
    </p>
</div>

<!--parser-->
<div id="wpsstm-importer-step-parser" class="wpsstm-importer-section wpsstm-importer-section-advanced">
  <h3><?php _e('Importer settings');?></h3>

  <?php
  $notice = __('Some extra settings are required to retrieve your tracklist.','wpsstm');
  printf('<div class="notice notice-warning inline"><p>%s</p></div>',$notice);

  ?>

  <div>
      <?php

      $xpath_selectors_link = sprintf('<a href="https://en.wikipedia.org/wiki/XPath" target="_blank">%s</a>',__('XPath selectors','wpsstm'));
      $jquery_selectors_link = sprintf('<a href="http://www.w3schools.com/jquery/jquery_ref_selectors.asp" target="_blank">%s</a>',__('jQuery selectors','wpsstm'));
      $regexes_link = sprintf('<a href="http://regex101.com" target="_blank">%s</a>',__('regular expressions','wpsstm'));

      printf(__('Depending of the document, use %s or %s to target the data for each track.','wpsstm'),$xpath_selectors_link,$jquery_selectors_link);
      echo"<br/>";
      printf(__('It is also possible to target the attribute of an element or to filter the data with a %s by using %s advanced settings for each item.','wpsstm'),$regexes_link,'<i class="fa fa-cog" aria-hidden="true"></i>');

      ?>
  </div>
    <h4 class="wpsstm-importer-section-label"><?php _e('Playlist','wpsstm');?></h4>
    <!--tracks selector-->
    <div id="wpsstm-importer-selectors-playlist">
      <div id="wpsstm-importer-selectors-playlist-tracks" class="wpsstm-importer-row">
          <h4 class="wpsstm-importer-row-label"><?php _e('Tracks Selector','wpsstm');?></h4>
          <div class="wpsstm-importer-row-content"><?php WPSSTM_Core_Importer::css_selector_block(array('playlist','tracks'));?></div>
      </div>
    </div>
    <div class="wpsstm-importer-section-label">
        <h4><?php _e('Single Track','wpsstm');?></h4>
    </div>
    <div id="wpsstm-importer-selectors-track">
        <div id="wpsstm-importer-selectors-track-artist" class="wpsstm-importer-row">
            <h5 class="wpsstm-importer-row-label"><?php _e('Artist Selector','wpsstm'); echo WPSSTM_Core_Importer::advanced_selectors_link()?></h5>
            <div class="wpsstm-importer-row-content"><?php WPSSTM_Core_Importer::css_selector_block(array('track','artist'));?></div>
        </div>
        <div id="wpsstm-importer-selectors-track-title" class="wpsstm-importer-row">
            <h5 class="wpsstm-importer-row-label"><?php _e('Title Selector','wpsstm'); echo WPSSTM_Core_Importer::advanced_selectors_link()?></h5>
            <div class="wpsstm-importer-row-content"><?php WPSSTM_Core_Importer::css_selector_block(array('track','title'));?></div>
        </div>
        <div id="wpsstm-importer-selectors-track-album" class="wpsstm-importer-row">
            <h5 class="wpsstm-importer-row-label"><?php _e('Album Selector','wpsstm'); echo WPSSTM_Core_Importer::advanced_selectors_link()?></h5>
            <div class="wpsstm-importer-row-content"><?php WPSSTM_Core_Importer::css_selector_block(array('track','album'));?></div>
        </div>
        <div id="wpsstm-importer-selectors-track-image" class="wpsstm-importer-row">
            <h5 class="wpsstm-importer-row-label"><?php _e('Image Selector','wpsstm'); echo WPSSTM_Core_Importer::advanced_selectors_link()?></h5>
            <div class="wpsstm-importer-row-content"><?php WPSSTM_Core_Importer::css_selector_block(array('track','image'));?></div>
        </div>
        <div id="wpsstm-importer-selectors-track-links" class="wpsstm-importer-row">
            <h5 class="wpsstm-importer-row-label"><?php _e('Links Selector','wpsstm'); echo WPSSTM_Core_Importer::advanced_selectors_link()?></h5>
            <div class="wpsstm-importer-row-content"><?php WPSSTM_Core_Importer::css_selector_block(array('track','link'));?></div>
        </div>
    </div>
</div>

<?php
wp_nonce_field( 'wpsstm_tracklist_importer_meta_box', 'wpsstm_tracklist_importer_meta_box_nonce' );
?>
