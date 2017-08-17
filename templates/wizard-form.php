<?php

global $post;
global $wpsstm_tracklist;
$wpsstm_tracklist->populate_tracks(array('posts_per_page'=>-1)); //we must have the tracks populated before we output the notices

wpsstm_wizard()->wizard_settings_init();
$is_wizard_disabled = $wpsstm_tracklist->is_wizard_disabled();
$post_type = get_post_type();

$classes = array();
$classes[]  = ( wpsstm_wizard()->is_advanced ) ? 'wizard-wrapper-advanced' : 'wizard-wrapper-simple';
$classes[]  = ( is_admin() ) ? 'wizard-wrapper-backend' : 'wizard-wrapper-frontend';
?>

<div id="wizard-wrapper" <?php echo wpsstm_get_classes_attr($classes);?>>
    <?php

    if(!$is_wizard_disabled){

        //wizard notices
        if ( $notices_el = $wpsstm_tracklist->get_notices_output('wizard-header') ){
            echo $notices_el;
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
        
    }else{
        ?>
        <p id="wpsstm-wizard-preview-feed-url">
            <?php printf($wpsstm_tracklist->feed_url);?>
        </p>
        <?php
        
    }
    
    //load URL (frontend)
    if ( !wpsstm_is_backend() ){
        wpsstm_wizard()->submit_button(__('Load URL','wpsstm'),'primary','wpsstm_wizard[load-url]');
    }
    
    //convert
    if ( !$is_wizard_disabled && $wpsstm_tracklist->feed_url && $wpsstm_tracklist->track_count ){
        ?>
        <span id="wpsstm-wizard-convert-tracklist">
            <?php
            if ( wpsstm_is_backend() ){
                if ( $post_type == wpsstm()->post_type_live_playlist ){
                    wpsstm_wizard()->submit_button(__('Convert to static playlist','wpsstm'),'primary','wpsstm_wizard[save-playlist][type][static]');
                }
            }else{
                if ( get_current_user_id() ){

                    //save live
                    $live_tracklist_obj =   get_post_type_object(wpsstm()->post_type_live_playlist);
                    $can_edit_cap =         $live_tracklist_obj->cap->edit_posts;
                    $can_save_live =        current_user_can($can_edit_cap);

                    if ($can_save_live){
                        wpsstm_wizard()->submit_button(__('Save as live playlist','wpsstm'),'primary','wpsstm_wizard[save-playlist][type][live]');
                    }

                    //save static
                    $static_tracklist_obj =   get_post_type_object(wpsstm()->post_type_playlist);
                    $can_edit_cap =         $static_tracklist_obj->cap->edit_posts;
                    $can_save_static =        current_user_can($can_edit_cap);

                    if ($can_save_static){
                        wpsstm_wizard()->submit_button(__('Save as static playlist','wpsstm'),'primary','wpsstm_wizard[save-playlist][type][static]');
                    }

                }else{

                    $wp_auth_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
                    $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(),__('here','wpsstm'));
                    $wp_auth_text = sprintf(__('You could save this playlist if you were logged.  Login or subscribe %s.','wpsstm'),$wp_auth_link);
                    printf('<p class="wpsstm-notice">%s %s</p>',$wp_auth_icon,$wp_auth_text);

                }

            }
            ?>
        </span>
        <?php
    }

    if ( wpsstm_is_backend() ){
        //import tracks
        
        
        if ( !$is_wizard_disabled && in_array($post_type,wpsstm_tracklists()->static_tracklist_post_types) && $wpsstm_tracklist->track_count ){
            wpsstm_wizard()->submit_button(__('Import Tracks','wpsstm'),'primary','wpsstm_wizard[import-tracks]');
        }

        //toggle wizard
        if ( get_post_status() != 'auto-draft' ){
            if( $is_wizard_disabled ){
                wpsstm_wizard()->submit_button(__('Open Wizard','wpsstm'),'primary','wpsstm_wizard[toggle-wizard][enable]');
            }else{
                wpsstm_wizard()->submit_button(__('Close Wizard','wpsstm'),'primary','wpsstm_wizard[toggle-wizard][disable]');
            }
        }
        
        //save wizard
        if( !$is_wizard_disabled ){
            if ($wpsstm_tracklist->feed_url){
                wpsstm_wizard()->submit_button(__('Save Changes'),'primary','wpsstm_wizard[save-wizard]');
            }else{
                wpsstm_wizard()->submit_button(__('Load URL','wpsstm'),'primary','wpsstm_wizard[save-wizard]');
            }
        }
    }

    //save settings

    wp_nonce_field('wpsstm_save_scraper_wizard','wpsstm_scraper_wizard_nonce',false);
    
    ?>
</div>