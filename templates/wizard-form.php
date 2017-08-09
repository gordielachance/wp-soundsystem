<?php

global $post;
global $wpsstm_tracklist;

wpsstm_wizard()->wizard_settings_init();

$is_wizard_disabled = $wpsstm_tracklist->is_wizard_disabled();


$classes = array();
$classes[]  = ( wpsstm_wizard()->is_advanced ) ? 'wizard-wrapper-advanced' : 'wizard-wrapper-simple';
$classes[]  = ( is_admin() ) ? 'wizard-wrapper-backend' : 'wizard-wrapper-frontend';
?>

<div id="wizard-wrapper" <?php echo wpsstm_get_classes_attr($classes);?>>
    <?php
    
    if (!$is_wizard_disabled){
        
        $reset_checked = false;

        $wpsstm_tracklist->output_notices('wizard-header');

        if ( wpsstm_wizard()->is_advanced ){
            $wpsstm_tracklist->output_notices('wizard-header-advanced');
        }

        ?>
        <div id="wpsstm-wizard-tabs">

            <?php 
            if ( wpsstm_wizard()->is_advanced ){ 
                ?>
                <ul id="wpsstm-wizard-tabs-header">
                    <?php wpsstm_wizard()->wizard_tabs(); ?>
                </ul>
                <?php
            }
            ?>

            <div id="wpsstm-wizard-step-source-content" class="wpsstm-wizard-step-content">
                <?php wpsstm_wizard()->do_wizard_sections( 'wpsstm-wizard-step-source' );?>
            </div>

            <?php if ( wpsstm_wizard()->is_advanced ){ ?>

                <div id="wpsstm-wizard-step-tracks-content" class="wpsstm-wizard-step-content">
                    <?php wpsstm_wizard()->do_wizard_sections( 'wpsstm-wizard-step-tracks' );?>
                </div>

                <div id="wpsstm-wizard-step-single-track-content" class="wpsstm-wizard-step-content">
                    <?php wpsstm_wizard()->do_wizard_sections( 'wpsstm-wizard-step-single-track' );?>
                </div>

                <div id="wpsstm-wizard-step-options" class="wpsstm-wizard-step-content">
                    <?php wpsstm_wizard()->do_wizard_sections( 'wpsstm-wizard-step-options' );?>
                </div>
            <?php } ?>
        </div>
        <?php

        if ( wpsstm_is_backend() ){
            $reset_checked = false;
            //import tracks
            $post_type = get_post_type();
            if ( ($post_type != wpsstm()->post_type_live_playlist ) && ($wpsstm_tracklist->tracks) ){
                wpsstm_wizard()->submit_button(__('Import Tracks','wpsstm'),'primary','wpsstm_wizard[import-tracks]');

            }
            //advanced wizard
            if ( $wpsstm_tracklist->feed_url && !isset($_REQUEST['advanced_wizard']) ){
                $advanced_wizard_url = get_edit_post_link();
                $advanced_wizard_url = add_query_arg(array('advanced_wizard'=>true),$advanced_wizard_url);
                echo '<p><a href="'.$advanced_wizard_url.'">' . __('Advanced Settings','wpsstm') . '</a></p>';
            }
            
        }else{ //frontend
            if ( is_page(wpsstm_wizard()->frontend_wizard_page_id) ){
                wpsstm_wizard()->submit_button(__('Load URL','wpsstm'),'primary','wpsstm_wizard[load-url]');
            }

        }
        
        if ( wpsstm_wizard()->is_advanced ){
            ?>
            <input type="hidden" name="advanced_wizard" value="1" />
            <?php
        }
        
    }else{
        ?>
        <p class="wpsstm-notice"><?php _e("Uncheck 'disabled wizard' and save to open settings.",'wpsstm');?></p>
        <?php
    }

    //toggle disable wizard
    if ( wpsstm_is_backend() ){
        
        $is_wizard_disabled = $wpsstm_tracklist->is_wizard_disabled();

        $input_id = 'wpsstm_wizard_disable';

        printf(
            '<small><input id="%s" type="checkbox" name="%s[disable]" value="on" %s /><label for="%s" class="wizard-field-desc">%s</label></small>',
            $input_id,
            'wpsstm_wizard',
            checked($is_wizard_disabled, true, false),
            $input_id,
            __('Disable wizard','wpsstm')
        );
        
        wpsstm_wizard()->submit_button(__('Save Changes'),'primary','wpsstm_wizard[save-wizard]');
    }

    //save settings

    wp_nonce_field('wpsstm_save_scraper_wizard','wpsstm_scraper_wizard_nonce',false);
    
    ?>
</div>