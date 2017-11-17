<?php

global $post;
global $wpsstm_tracklist;
$wpsstm_tracklist->populate_tracks(array('posts_per_page'=>-1)); //we must have the tracks populated before we output the notices

$is_wizard_disabled = $wpsstm_tracklist->is_wizard_disabled();
$post_type = get_post_type();
?>

<div id="wizard-wrapper" <?php echo wpsstm_get_classes_attr('wizard-wrapper-backend');?>>

    <?php
    if(!$is_wizard_disabled){

        //wizard notices
        if ( $notices_el = $wpsstm_tracklist->get_notices_output('wizard-header') ){
            echo $notices_el;
        }

        ?>
        <div id="wpsstm-wizard-sections">

            <?php 
            if ( wpsstm_wizard()->is_advanced ){ 
                ?>
                <ul id="wpsstm-wizard-sections-header">
                    <?php wpsstm_wizard()->wizard_tabs(); ?>
                </ul>
                <?php
            }
            ?>

            <div id="wpsstm-wizard-step-source-content" class="wpsstm-wizard-step-content">
                <?php do_settings_sections( 'wpsstm-wizard-step-source' );?>
            </div>

            <?php if ( wpsstm_wizard()->is_advanced ){ ?>

                <div id="wpsstm-wizard-step-tracks-content" class="wpsstm-wizard-step-content">
                    <?php do_settings_sections( 'wpsstm-wizard-step-tracks' );?>
                </div>

                <div id="wpsstm-wizard-step-single-track-content" class="wpsstm-wizard-step-content">
                    <?php do_settings_sections( 'wpsstm-wizard-step-single-track' );?>
                </div>

                <div id="wpsstm-wizard-step-options" class="wpsstm-wizard-step-content">
                    <?php do_settings_sections( 'wpsstm-wizard-step-options' );?>
                </div>
            <?php } ?>
        </div>
    
        <?php
        
    }else{
        ?>
        <p id="wpsstm-wizard-preview-feed-url">
            <?php printf($wpsstm_tracklist->feed_url);?>
        </p>
        <?php
        
    }

    //import tracks
    if ( !$is_wizard_disabled && in_array($post_type,wpsstm_tracklists()->static_tracklist_post_types) && $wpsstm_tracklist->track_count ){
        submit_button(__('Import Tracks','wpsstm'),'primary','wpsstm_wizard[import-tracks]');
    }

    //toggle wizard
    if ( get_post_status() != 'auto-draft' ){
        if( $is_wizard_disabled ){
            submit_button(__('Open Wizard','wpsstm'),'primary','wpsstm_wizard[toggle-wizard][enable]');
        }else{
            submit_button(__('Close Wizard','wpsstm'),'primary','wpsstm_wizard[toggle-wizard][disable]');
        }
    }

    //save wizard
    if( !$is_wizard_disabled ){
        if ($wpsstm_tracklist->feed_url){
            submit_button(__('Save Changes'),'primary','wpsstm_wizard[save-wizard]');
        }else{
            submit_button(__('Load URL','wpsstm'),'primary','wpsstm_wizard[save-wizard]');
        }
    }

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