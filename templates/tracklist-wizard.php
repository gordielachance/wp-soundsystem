<?php
global $wpsstm_tracklist;
$wpsstm_tracklist->populate_preset();
?>

<!--remote url-->
<div class="wpsstm-wizard-section">
    <h3 class="wpsstm-wizard-section-label"><?php _e('Remote URL','wpsstm');?></h3>
    <div>

        <input type="text" name="wpsstm_wizard[feed_url]" value="<?php echo $wpsstm_tracklist->feed_url;?>" class="wpsstm-fullwidth" placeholder="<?php _e('Type something or enter a tracklist URL','wpsstm');?>" />
    </div>
</div>
<div class="wpsstm-advanced-wizard">
    <!--track-->
    <div class="wpsstm-wizard-section">
        <h3 class="wpsstm-wizard-section-label"><?php _e('Tracks','wpsstm');?></h3>
        <!--tracks selector-->
        <div class="wpsstm-wizard-row">
            <h4 class="wpsstm-wizard-row-label"><?php _e('Selector','wpsstm');?></h4>
            <div class="wpsstm-wizard-row-content">
                <?php WPSSTM_Core_Wizard::css_selector_block('tracks');?>
                <small>
                    <?php 
                    printf(__('Enter a <a href="%s" target="_blank">jQuery selector</a> to target each track item from the tracklist page, for example: %s.','wpsstm'),'http://www.w3schools.com/jquery/jquery_ref_selectors.asp','<code>#content #tracklist .track</code>');
                    ?>
                </small>
            </div>

        </div>
         <!--tracks order-->
         <div class="wpsstm-wizard-row">
            <h4 class="wpsstm-wizard-row-label"><?php _e('Order','wpsstm');?></h4>
            <div class="wpsstm-wizard-row-content">
                <?php
                $option = $wpsstm_tracklist->preset->get_options('tracks_order');
                $default_option = wpsstm_get_array_value('tracks_order',$wpsstm_tracklist->preset->default_options);
                $disabled = ($default_option) ? disabled( $option, $default_option, false ) : null;

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
    <div class="wpsstm-wizard-section">
        <div class="wpsstm-wizard-section-label">
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
        <div class="wpsstm-wizard-row">
            <h4 class="wpsstm-wizard-row-label"><?php _e('Artist Selector','wpsstm'); echo WPSSTM_Core_Wizard::regex_link()?></h4>
            <div class="wpsstm-wizard-row-content"><?php WPSSTM_Core_Wizard::css_selector_block('track_artist');?></div>
        </div>
        <div class="wpsstm-wizard-row">
            <h4 class="wpsstm-wizard-row-label"><?php _e('Title Selector','wpsstm'); echo WPSSTM_Core_Wizard::regex_link()?></h4>
            <div class="wpsstm-wizard-row-content"><?php WPSSTM_Core_Wizard::css_selector_block('track_title');?></div>
        </div>
        <div class="wpsstm-wizard-row">
            <h4 class="wpsstm-wizard-row-label"><?php _e('Album Selector','wpsstm'); echo WPSSTM_Core_Wizard::regex_link()?></h4>
            <div class="wpsstm-wizard-row-content"><?php WPSSTM_Core_Wizard::css_selector_block('track_album');?></div>
        </div>
        <div class="wpsstm-wizard-row">
            <h4 class="wpsstm-wizard-row-label"><?php _e('Image Selector','wpsstm'); echo WPSSTM_Core_Wizard::regex_link()?></h4>
            <div class="wpsstm-wizard-row-content"><?php WPSSTM_Core_Wizard::css_selector_block('track_image');?></div>
        </div>
        <div class="wpsstm-wizard-row">
            <h4 class="wpsstm-wizard-row-label"><?php _e('Source URLs Selector','wpsstm'); echo WPSSTM_Core_Wizard::regex_link()?></h4>
            <div class="wpsstm-wizard-row-content"><?php WPSSTM_Core_Wizard::css_selector_block('track_source_urls');?></div>
        </div>
    </div>

    <!--options-->
    <div class="wpsstm-wizard-section">
        <div class="wpsstm-wizard-section-label">
            <h3><?php _e('Tracklist options','wpsstm');?></h3>
        </div>
        <div class="wpsstm-wizard-row">
            <h4 class="wpsstm-wizard-row-label"><?php _e('Cache duration','wpsstm');?></h4>
            <div class="wpsstm-wizard-row-content">
                <?php

                $option = $wpsstm_tracklist->get_options('remote_delay_min');
                $default_option = wpsstm_get_array_value('remote_delay_min',$wpsstm_tracklist->preset->default_options);
                $disabled = ($default_option) ? disabled( true, true, false ) : null;

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
</div>
<?php
wp_nonce_field( 'wpsstm_scraper_wizard_meta_box', 'wpsstm_scraper_wizard_meta_box_nonce' );
?>