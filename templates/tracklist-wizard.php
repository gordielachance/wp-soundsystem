<?php
global $wpsstm_tracklist;
$action = get_query_var( 'wpsstm_action' );

add_filter( 'show_admin_bar','__return_false'); //hide admin bar
do_action( 'wpsstm-iframe' );
do_action( 'wpsstm-wizard-iframe' );
do_action( 'get_header', 'wpsstm-tracklist-iframe' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
//

$body_classes = array(
    'wpsstm-iframe',
    'wpsstm-tracklist-iframe',
    ($action) ? sprintf('wpsstm-tracklist-action-%s',$action) : null,
);

?>

<!DOCTYPE html>
<html class="no-js" <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" >
    <?php wp_head(); ?>
</head>
<body <?php body_class($body_classes); ?>>  
    <?php 
    if (have_posts()){
        while (have_posts()) {
            
            the_post();
            $wpsstm_tracklist->populate_preset();

            ?>

            <form id="wizard-wrapper" <?php echo wpsstm_get_classes_attr('wizard-wrapper-frontend');?> action="<?php echo $wpsstm_tracklist->get_tracklist_action_url('wizard');?>" method="post">
                
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

                                $desc_text = sprintf(
                                    '<input type="radio" name="%1$s[tracks_order]" value="desc" %2$s /><span class="wizard-field-desc">%3$s</span>',
                                    'wpsstm_wizard',
                                    checked($option, 'desc', false),
                                    __('Descending','spiff')
                                );

                                $asc_text = sprintf(
                                    '<input type="radio" name="%1$s[tracks_order]" value="asc" %2$s /><span class="wizard-field-desc">%3$s</span>',
                                    'wpsstm_wizard',
                                    checked($option, 'asc', false),
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

                                $desc[] = __('If set, posts will be created for each track when the remote playlist is retrieved.','wpsstm');
                                $desc[] = __("They will be flushed after the cache time has expired; if the track does not belong to another playlist or user's likes.",'wpsstm');
                                $desc[] = __("This can be useful if you have a lot of traffic - there will be less remote requests ans track sources will be searched only once.",'wpsstm');
                                $desc = implode("<br/>",$desc);

                                printf(
                                    '<input type="number" name="%s[remote_delay_min]" size="4" min="0" value="%s" /> %s<br/><small>%s</small>',
                                    'wpsstm_wizard',
                                    $option,
                                    __('minutes','spiff'),
                                    $desc
                                );
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="button button-primary wpsstm-icon-button"><?php _e('Save');?></button>
            </form>
        <?php

        }
    }
?>