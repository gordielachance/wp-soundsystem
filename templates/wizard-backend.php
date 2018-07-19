<?php

global $post;
global $wpsstm_tracklist;

$post_type = get_post_type();
?>

<div id="wizard-wrapper" <?php echo wpsstm_get_classes_attr('wizard-wrapper-backend');?>>

    <div id="wpsstm-wizard-sections">

        <ul id="wpsstm-wizard-sections-header">
            <?php WPSSTM_Core_Wizard::wizard_tabs(); ?>
        </ul>

        <div id="wpsstm-wizard-step-input-content" class="wpsstm-wizard-step-content">
            <?php do_settings_sections( 'wpsstm-wizard-step-input' );?>
        </div>



        <?php if ( WPSSTM_Core_Wizard::is_advanced_wizard() ){ ?>
        
            <div id="wpsstm-wizard-step-profile-content" class="wpsstm-wizard-step-content">
                <?php do_settings_sections( 'wpsstm-wizard-step-profile' );?>
            </div>
        
            <div id="wpsstm-wizard-step-options-content" class="wpsstm-wizard-step-content">
                <?php do_settings_sections( 'wpsstm-wizard-step-options' );?>
            </div>
        <?php } ?>

        <div id="wpsstm-wizard-step-results-content" class="wpsstm-wizard-step-content">
            <?php do_settings_sections( 'wpsstm-wizard-step-results' );?>
        </div>
        
        <?php if ( WPSSTM_Core_Wizard::is_advanced_wizard() ){ ?>

            <div id="wpsstm-wizard-step-debug-content" class="wpsstm-wizard-step-content">
                <?php do_settings_sections( 'wpsstm-wizard-step-debug' );?>
            </div>
        
        <?php } ?>

    </div>

    <?php


    //import tracks
    if ( in_array($post_type,wpsstm()->static_tracklist_post_types) && $wpsstm_tracklist->track_count ){
        submit_button(__('Import Tracks','wpsstm'),'primary','wpsstm_wizard[import-tracks]');
    }

    //save wizard
    submit_button(__('Save Changes'),'primary','wpsstm_wizard[save-wizard]');

    //save settings
    
    //post ID
    if ($wpsstm_tracklist->post_id){
        ?>
        <input type="hidden" name="wpsstm_wizard[post_id]" value="<?php echo $wpsstm_tracklist->post_id;?>" />
        <?php
    }

    wp_nonce_field('wpsstm_save_scraper_wizard','wpsstm_scraper_wizard_nonce',false);
    
    ?>
</div>