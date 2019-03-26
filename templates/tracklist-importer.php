<?php
global $wpsstm_tracklist;
$wpsstm_tracklist->populate_preset();

$is_debug = isset($_GET['wpsstm_tracklist_debug']);

if ($is_debug){
    $wpsstm_tracklist->is_expired = true;
    $wpsstm_tracklist->populate_subtracks();
}


?>
<div id="wpsstm-importer">
    <ul id="wpsstm-importer-tabs">
        <li><a href="#wpsstm-importer-step-feed-url"><?php _e('Feed URL','wpsstm');?></a></li>
        <li><a href="#wpsstm-importer-step-tracks"><?php _e('Tracks','wpsstm');?></a></li>
        <li><a href="#wpsstm-importer-step-single-track"><?php _e('Details','wpsstm');?></a></li>
        <li><a href="#wpsstm-importer-step-options"><?php _e('Options','wpsstm');?></a></li>
        <li><a href="#wpsstm-importer-step-debug"><?php _e('Debug','wpsstm');?></a></li>
    </ul>

    <!--remote url-->
    <div id="wpsstm-importer-step-feed-url" class="wpsstm-importer-section">
        <h3 class="wpsstm-importer-section-label"><?php _e('Feed URL','wpsstm');?></h3>
        <div>
            <input type="text" name="wpsstm_wizard[feed_url]" value="<?php echo $wpsstm_tracklist->feed_url;?>" class="wpsstm-fullwidth" placeholder="<?php _e('Type something or enter a tracklist URL','wpsstm');?>" />
        </div>
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
                $forced_option = $wpsstm_tracklist->preset->get_preset_options('tracks_order');
                $option = ($forced_option) ? $forced_option : $wpsstm_tracklist->preset->get_options('tracks_order');
                $disabled = disabled( (bool)$forced_option, true, false );

                $desc_text = sprintf(
                    '<input type="radio" name="%1s[tracks_order]" value="desc" %s %s /><span class="wizard-field-desc">%s</span>',
                    'wpsstm_wizard',
                    checked($option, 'desc', false),
                    $disabled,
                    __('Descending','spiff')
                );

                $asc_text = sprintf(
                    '<input type="radio" name="%s[tracks_order]" value="asc" %s %s /><span class="wizard-field-desc">%s</span>',
                    'wpsstm_wizard',
                    checked($option, 'asc', false),
                    $disabled,
                    __('Ascending','spiff')
                );

                echo $desc_text." ".$asc_text;
                ?>
            </div>
        </div>
    </div>

    <!--track details-->
    <div id="wpsstm-importer-step-single-track" class="wpsstm-importer-section  wpsstm-importer-section-advanced">
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
                <li><a href="#wpsstm-importer-single-track-sources"><?php _e('Sources','wpsstm');?></a></li>
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
            <div id="wpsstm-importer-single-track-sources" class="wpsstm-importer-row">
                <h4 class="wpsstm-importer-row-label"><?php _e('Source URLs Selector','wpsstm'); echo WPSSTM_Core_Importer::regex_link()?></h4>
                <div class="wpsstm-importer-row-content"><?php WPSSTM_Core_Importer::css_selector_block('track_source_urls');?></div>
            </div>
        </div>
    </div>
    
    <!--options-->
    <div id="wpsstm-importer-step-options" class="wpsstm-importer-section wpsstm-importer-section-advanced">
        <div class="wpsstm-importer-section-label">
            <h3><?php _e('Tracklist options','wpsstm');?></h3>
        </div>
        <div class="wpsstm-importer-row">
            <h4 class="wpsstm-importer-row-label"><?php _e('Cache duration','wpsstm');?></h4>
            <div class="wpsstm-importer-row-content">
                <?php

                $forced_option = $wpsstm_tracklist->preset->get_preset_options('remote_delay_min');
                $option = ($forced_option) ? $forced_option : $wpsstm_tracklist->preset->get_options('remote_delay_min');
                $disabled = disabled( (bool)$forced_option, true, false );

                $desc[] = __('If set, posts will be created for each track when the remote playlist is retrieved.','wpsstm');
                $desc[] = __("They will be flushed after the cache time has expired; if the track does not belong to another playlist or user's likes.",'wpsstm');
                $desc[] = __("This can be useful if you have a lot of traffic - there will be less remote requests ans track sources will be searched only once.",'wpsstm');
                $desc = implode("<br/>",$desc);

                printf(
                    '<input type="number" name="%s[remote_delay_min]" size="4" min="0" value="%s" %s /> %s<br/><small>%s</small>',
                    'wpsstm_wizard',
                    $option,
                    $disabled,
                    __('minutes','spiff'),
                    $desc
                );
                ?>
            </div>
        </div>
    </div>
    
    <!--debug-->
    <div id="wpsstm-importer-step-debug" class="wpsstm-importer-section wpsstm-importer-section-advanced">
        <div class="wpsstm-importer-section-label">
            <h3><?php _e('Tracklist Debug','wpsstm');?></h3>
        </div>
        <?php
        if (!$is_debug){
            ?>
                <div class="wpsstm-block-notice">
                    <span>
                        <?php 
                        $url = get_edit_post_link();
                        $url = add_query_arg(array('wpsstm_tracklist_debug'=>true),$url) . '#wpsstm-importer-step-debug';
                        $link = sprintf('<a href="%s">%s</a>',$url,__('Reload tracklist','wpsstm'));
                        printf(__('%s to display the feedback','wpsstm'),$link);
                        ?>
                        </span>
                </div>
            <?php
        }else{
            ?>
            <!--preset-->
             <div class="wpsstm-importer-row">
                <h4 class="wpsstm-importer-row-label"><?php _e('Preset','wpsstm');?></h4>
                <div class="wpsstm-importer-row-content">
                    <?php
                    WPSSTM_Core_Importer::feedback_preset();
                    ?>
                </div>
            </div>
            <!--data type-->
             <div class="wpsstm-importer-row">
                <h4 class="wpsstm-importer-row-label"><?php _e('Data','wpsstm');?></h4>
                <div class="wpsstm-importer-row-content">
                    <?php
                    WPSSTM_Core_Importer::feedback_data_type_callback();
                    ?>
                </div>
            </div>
             <!--tracks-->
             <div class="wpsstm-importer-row">
                <h4 class="wpsstm-importer-row-label"><?php _e('Tracklist','wpsstm');?></h4>
                <div class="wpsstm-importer-row-content">
                    <?php
                    WPSSTM_Core_Importer::feedback_source_content_callback();
                    ?>
                </div>
            </div>
             <!--tracks-->
             <div class="wpsstm-importer-row">
                <h4 class="wpsstm-importer-row-label"><?php _e('Tracks','wpsstm');?></h4>
                <div class="wpsstm-importer-row-content">
                    <?php
                    WPSSTM_Core_Importer::feedback_tracks_callback();
                    ?>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
    
    <?php
    wp_nonce_field( 'wpsstm_tracklist_importer_meta_box', 'wpsstm_tracklist_importer_meta_box_nonce' );
    ?>
</div>