<?php
global $wpsstm_tracklist;

$wpsstm_tracklist = new WPSSTM_Post_Tracklist(get_the_ID());

$load_tracklist = isset($_GET['wpsstm_load_tracklist']);

?>
<div id="wpsstm-importer" data-wpsstm-tracklist-id="<?php echo get_the_ID();?>">
    <ul id="wpsstm-importer-tabs">
        <li><a href="#wpsstm-importer-step-feed-url"><?php _e('URLs','wpsstm');?></a></li>
        <li><a href="#wpsstm-importer-step-parser"><?php _e('Custom Parser','wpsstm');?></a></li>
        <li><a href="#wpsstm-importer-step-debug" class="wpsstm-debug-log-bt" target="_blank"><span class="wpsstm-debug-log-icon"></span><?php _e('Debug log','wpsstm');?></a></li>
    </ul>

    <!--remote url-->
    <div id="wpsstm-importer-step-feed-url" class="wpsstm-importer-section">
        <h3 class="wpsstm-importer-section-label"><?php _e('Feed URL','wpsstm');?></h3>
        <?php 
        if ( !WPSSTM_Core_API::is_premium() ){
            $xspf_link = sprintf('<a href="%s" target="_blank">%s</a>','http://xspf.org','.xspf');
            $notice = sprintf(__('Tracklist URL. Since you are not premium, it should be a local file with a %s extension.','wpsstm'),$xspf_link);
            printf('<div class="notice notice-warning inline is-dismissible"><p>%s</p></div>',$notice);
        }
        ?>
        <p>
            <input type="text" name="wpsstm_importer[feed_url]" value="<?php echo $wpsstm_tracklist->feed_url;?>" class="wpsstm-fullwidth" placeholder="<?php _e('Enter a tracklist URL or type a bang (eg. artist:Gorillaz)','wpsstm');?>" />
        </p>
        <?php

        //supported services
        $title = __('Supported services','wpsstm');
        if ( !WPSSTM_Core_API::is_premium() ) $title.= sprintf(' <small>(%s)</small>',__('Requires an API key','wpsstm'));
        printf('<h4>%s</h4>',$title);
        
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
        
        printf('<em>%s</em>',implode(', ',$items));
        
        //bangs
        $title = __('Supported Bangs','wpsstm');
        if ( !WPSSTM_Core_API::is_premium() ) $title.= sprintf(' <small>(%s)</small>',__('Requires an API key','wpsstm'));
        printf('<h4>%s</h4>',$title);
        
        $items = array();
        $services = WPSSTM_Core_Importer::get_import_bangs();

        if ( !is_wp_error($services) ){
            foreach((array)$services as $service){
                $items[] = sprintf('<code>%s</code>',$service['code']);
            }
        }
        
        printf('<em>%s</em>',implode(', ',$items));
        
        printf('<h4>%s</h4>',__('...Or build a Custom Parser!','wpsstm'));
        

        ?>
        <h3 class="wpsstm-importer-section-label"><?php _e('Website URL','wpsstm');?></h3>
        <?php _e("URL of the radio that will be displayed on the playlist.  If empty, the Feed URL will be used.",'wpsstm');?>
        <p>
            <input type="text" name="wpsstm_importer[website_url]" value="<?php echo $wpsstm_tracklist->website_url;?>" class="wpsstm-fullwidth" />
        </p>
    </div>
    
    <!--parser-->
    <div id="wpsstm-importer-step-parser" class="wpsstm-importer-section wpsstm-importer-section-advanced">
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
   <div id="wpsstm-importer-step-debug" class="wpsstm-importer-section  wpsstm-importer-section-advanced">
       <?php
        $notice = __("This is the last debug log.  Click on the tab title to update it once you have refreshed the tracklist.",'wpsstm');
        printf('<div class="notice notice-warning inline is-dismissible"><p>%s</p></div>',$notice);
       ?>
       <div id="wpsstm-debug-json"><!--ajax filled--></div>
    </div>
    
    <?php
    wp_nonce_field( 'wpsstm_tracklist_importer_meta_box', 'wpsstm_tracklist_importer_meta_box_nonce' );
    ?>
</div>