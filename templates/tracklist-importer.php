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

<?php
//advanced importer
if ( $importer = $wpsstm_tracklist->get_importer() ) {

  if ( is_wp_error($importer) ){
    printf('<div class="notice notice-warning inline"><p>%s</p></div>',$importer->get_error_message());
  }else{

    ?>
    <div id="wpsstm-importer-content-type" class="wpsstm-importer-section">
      <div id="wpsstm-importer-content-type" class="wpsstm-importer-row">
          <h4 class="wpsstm-importer-row-label"><?php _e('Content-Type','wpsstm');?></h4>
          <div class="wpsstm-importer-row-content"><?php echo wpsstm_get_array_value(array('infos','content_type'),$importer);?>
            <?php
            if ( $body_url = $wpsstm_tracklist->get_radio_content_url() ){
              ?>
              - <a href="<?php echo $body_url;?>" target="_blank"><?php _e('View content','wpsstm');?></a></div>
              <?php
            }
             ?>
      </div>
      <div id="wpsstm-importer-name" class="wpsstm-importer-row">
          <h4 class="wpsstm-importer-row-label"><?php _e('Importer','wpsstm');?></h4>
          <div class="wpsstm-importer-row-content"><?php echo wpsstm_get_array_value(array('infos','name'),$importer);?></div>
      </div>
    </div>
    <?php

    $schema = $wpsstm_tracklist->get_schema();
    $schema = WPSSTM_Core_Importer::instantiate_schema_references($schema);
    $selectors = wpsstm_get_array_value(array('properties','selectors'),$schema);

    if ( $advanced = WPSSTM_Core_Importer::parse_schema_node($schema,array('properties','selectors')) ){
      ?>
      <div id="wpsstm-importer-schema" class="wpsstm-importer-section">
        <h3><?php _e('Advanced settings','wpsstm');?></h3>
        <?php

        //notice
        $wiki_link = sprintf('<a href="https://github.com/gordielachance/wp-soundsystem/wiki/Tracklist-importer" target="_blank">%s</a>',__('wiki','wpsstm'));
        $notice = sprintf(__('Need some help there ? Read the %s.','wpsstm'),$wiki_link);
        printf('<div class="notice notice-warning inline"><p>%s</p></div>',$notice);

        //schema
        echo $advanced;
        ?>
      </div>
      <?php
    }
  }

}

wp_nonce_field( 'wpsstm_tracklist_importer_meta_box', 'wpsstm_tracklist_importer_meta_box_nonce' );
?>
