<?php
global $wpsstm_tracklist;

$wpsstm_tracklist = new WPSSTM_Post_Tracklist(get_the_ID());

$load_tracklist = isset($_GET['wpsstm_load_tracklist']);

?>
<div id="wpsstm-importer">
    <ul id="wpsstm-importer-tabs">
        <li><a href="#wpsstm-importer-step-feed-url"><?php _e('URLs','wpsstm');?></a></li>
        <li><a href="#wpsstm-importer-step-tracks"><?php _e('Tracks','wpsstm');?></a></li>
        <li><a href="#wpsstm-importer-step-details"><?php _e('Details','wpsstm');?></a></li>
        <li><a href="#wpsstm-importer-step-feedback"><?php _e('Feedback','wpsstm');?></a></li>
    </ul>

    <!--remote url-->
    <div id="wpsstm-importer-step-feed-url" class="wpsstm-importer-section">
        <h3 class="wpsstm-importer-section-label"><?php _e('Feed URL','wpsstm');?></h3>
        <?php 
        $xspf_link = sprintf('<a href="%s" target="_blank">%s</a>','http://xspf.org','.xspf');
        printf(__('Tracklist URL. It should have an %s extension.','wpsstm'),$xspf_link);
        ?>
        <p>
            <input type="text" name="wpsstm_importer[feed_url]" value="<?php echo $wpsstm_tracklist->feed_url;?>" class="wpsstm-fullwidth" placeholder="<?php _e('Enter a tracklist URL or type a bang (eg. artist:Gorillaz)','wpsstm');?>" />
        </p>
        <?php
        $api_link = sprintf('<a href="%s" target="_blank">%s</a>',WPSSTM_API_REGISTER_URL,__('API key','wpsstm'));
        $items = array();
        $services = WPSSTM_Core_Importer::get_import_services();

        if ( !is_wp_error($services) ){
            foreach((array)$services as $service){
                $item = $service['name'];
                if ( $url = $service['url'] ){
                    $item = sprintf('<a target="_blank" href="%s">%s</a>',$url,$item);
                }
                $items[] = $item;
            }
        }

        $services_list = sprintf('<em>%s</em>',implode(', ',$items));
        printf(__('If you have an %s, you could also enter URL to those services: %s - or use your own custom import settings!','wpsstm'),$api_link,$services_list);
        ?>
        <h3 class="wpsstm-importer-section-label"><?php _e('Website URL','wpsstm');?></h3>
        <?php _e("URL of the radio that will be displayed on the playlist.  If empty, the Feed URL will be used.",'wpsstm');?>
        <p>
            <input type="text" name="wpsstm_importer[website_url]" value="<?php echo $wpsstm_tracklist->website_url;?>" class="wpsstm-fullwidth" />
        </p>
    </div>
    
    <!--track-->
    <div id="wpsstm-importer-step-tracks" class="wpsstm-importer-section wpsstm-importer-section-advanced">
        <h3 class="wpsstm-importer-section-label"><?php _e('Tracks','wpsstm');?></h3>
        <!--tracks selector-->
        <div class="wpsstm-importer-row">
            <h4 class="wpsstm-importer-row-label"><?php _e('Selector','wpsstm');?></h4>
            <div class="wpsstm-importer-row-content">
                <?php WPSSTM_Core_Importer::css_selector_block('tracks');?>
                <small>
                    <?php 
                    printf(__('Enter a <a href="%s" target="_blank">jQuery selector</a> to target each track item from the tracklist page, for example: %s.','wpsstm'),'http://www.w3schools.com/jquery/jquery_ref_selectors.asp','<code>#content #tracklist .track</code>');
                    ?>
                </small>
            </div>

        </div>
         <!--tracks order-->
         <div class="wpsstm-importer-row">
            <h4 class="wpsstm-importer-row-label"><?php _e('Order','wpsstm');?></h4>
            <div class="wpsstm-importer-row-content">
                <?php
                $forced_option = null;//TOUFIX$wpsstm_tracklist->preset->get_preset_options('tracks_order');
                $option = ($forced_option) ? $forced_option : $wpsstm_tracklist->get_importer_options('tracks_order');
                
                $disabled = disabled( (bool)$forced_option, true, false );

                $desc_text = sprintf(
                    '<input type="radio" name="%1s[tracks_order]" value="desc" %s %s /><span class="wizard-field-desc">%s</span>',
                    'wpsstm_importer',
                    checked($option, 'desc', false),
                    $disabled,
                    __('Descending','spiff')
                );

                $asc_text = sprintf(
                    '<input type="radio" name="%s[tracks_order]" value="asc" %s %s /><span class="wizard-field-desc">%s</span>',
                    'wpsstm_importer',
                    checked($option, 'asc', false),
                    $disabled,
                    __('Ascending','wpsstm')
                );

                echo $desc_text." ".$asc_text;
                ?>
            </div>
        </div>
    </div>

    <!--track details-->
    <div id="wpsstm-importer-step-details" class="wpsstm-importer-section  wpsstm-importer-section-advanced">
        <div class="wpsstm-importer-section-label">
            <h3><?php _e('Track details','wpsstm');?></h3>
            <small>
                <?php

                $jquery_selectors_link = sprintf('<a href="http://www.w3schools.com/jquery/jquery_ref_selectors.asp" target="_blank">%s</a>',__('jQuery selectors','wpsstm'));
                $regexes_link = sprintf('<a href="http://regex101.com" target="_blank">%s</a>',__('regular expressions','wpsstm'));

                printf(__('Enter a %s to extract the data for each track.','wpsstm'),$jquery_selectors_link);
                echo"<br/>";
                printf(__('It is also possible to target the attribute of an element or to filter the data with a %s by using %s advanced settings for each item.','wpsstm'),$regexes_link,'<i class="fa fa-cog" aria-hidden="true"></i>');

                ?>
            </small>
        </div>
        <div id="wpsstm-single-track-setup">
            <ul id="wpsstm-single-track-tabs">
                <li><a href="#wpsstm-importer-single-track-artist"><?php _e('Artist','wpsstm');?></a></li>
                <li><a href="#wpsstm-importer-single-track-title"><?php _e('Title','wpsstm');?></a></li>
                <li><a href="#wpsstm-importer-single-track-album"><?php _e('Album','wpsstm');?></a></li>
                <li><a href="#wpsstm-importer-single-track-image"><?php _e('Image','wpsstm');?></a></li>
                <li><a href="#wpsstm-importer-single-track-links"><?php _e('Tracks Links','wpsstm');?></a></li>
            </ul>
            <div id="wpsstm-importer-single-track-artist" class="wpsstm-importer-row">
                <h4 class="wpsstm-importer-row-label"><?php _e('Artist Selector','wpsstm'); echo WPSSTM_Core_Importer::regex_link()?></h4>
                <div class="wpsstm-importer-row-content"><?php WPSSTM_Core_Importer::css_selector_block('track_artist');?></div>
            </div>
            <div id="wpsstm-importer-single-track-title" class="wpsstm-importer-row">
                <h4 class="wpsstm-importer-row-label"><?php _e('Title Selector','wpsstm'); echo WPSSTM_Core_Importer::regex_link()?></h4>
                <div class="wpsstm-importer-row-content"><?php WPSSTM_Core_Importer::css_selector_block('track_title');?></div>
            </div>
            <div id="wpsstm-importer-single-track-album" class="wpsstm-importer-row">
                <h4 class="wpsstm-importer-row-label"><?php _e('Album Selector','wpsstm'); echo WPSSTM_Core_Importer::regex_link()?></h4>
                <div class="wpsstm-importer-row-content"><?php WPSSTM_Core_Importer::css_selector_block('track_album');?></div>
            </div>
            <div id="wpsstm-importer-single-track-image" class="wpsstm-importer-row">
                <h4 class="wpsstm-importer-row-label"><?php _e('Image Selector','wpsstm'); echo WPSSTM_Core_Importer::regex_link()?></h4>
                <div class="wpsstm-importer-row-content"><?php WPSSTM_Core_Importer::css_selector_block('track_image');?></div>
            </div>
            <div id="wpsstm-importer-single-track-links" class="wpsstm-importer-row">
                <h4 class="wpsstm-importer-row-label"><?php _e('Track Link URLs Selector','wpsstm'); echo WPSSTM_Core_Importer::regex_link()?></h4>
                <div class="wpsstm-importer-row-content"><?php WPSSTM_Core_Importer::css_selector_block('track_link_urls');?></div>
            </div>
        </div>
    </div>
   <div id="wpsstm-importer-step-feedback" class="wpsstm-importer-section  wpsstm-importer-section-advanced">
       <?php
       
            $importer_options = get_post_meta($wpsstm_tracklist->post_id, WPSSTM_Post_Tracklist::$importer_options_meta_name,true);
            $args = array(
                'input' =>      $wpsstm_tracklist->feed_url,
                'options'=>     $importer_options
            );

            $args = rawurlencode_deep( $args );
            $api_url = add_query_arg($args,'feedback');
            $feedback = WPSSTM_Core_API::api_request($api_url);
       
            if ( is_wp_error($feedback) ){
                $error_msg = $feedback->get_error_message();
                printf('<div class="wpsstm-notice">%s</div>',$error_msg);
            }else{
                echo wpsstm_get_json_viewer($feedback);
            }


       ?>
    </div>
    
    <?php
    wp_nonce_field( 'wpsstm_tracklist_importer_meta_box', 'wpsstm_tracklist_importer_meta_box_nonce' );
    ?>
</div>