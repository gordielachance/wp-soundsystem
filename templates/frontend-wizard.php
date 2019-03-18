<?php
global $wpsstm_tracklist;
$can_wizard = WPSSTM_Core_Wizard::can_frontend_wizard();

if ( is_wp_error($can_wizard) ){ //TOUFIX TOUCHECK

    $wp_auth_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
    $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(get_permalink()),__('here','wpsstm'));
    $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
    $wp_auth_text = $can_wizard->get_error_message();
    printf('<p class="wpsstm-notice">%s %s</p>',$wp_auth_icon,$wp_auth_text);

}else{

    ?>

    <div id="frontend-wizard">

        <form action="<?php the_permalink();?>" method="POST">

            <div id="wpsstm-wizard-step-profile-content" class="wpsstm-wizard-section">
                <?php
                global $wpsstm_tracklist;

                $option = null;

                $text_input = sprintf(
                    '<input type="text" name="wpsstm_frontend_wizard_url" value="%s" class="wpsstm-fullwidth" placeholder="%s" />',
                    $option,
                    __('Enter a tracklist URL or type a bang (eg. artist:Gorillaz)','wpsstm')
                );

    
                $submit_input = '<button type="submit" name="wpsstm_wizard[action][load-url]" id="wpsstm_wizard[action][load-url]" class="button button-primary wpsstm-icon-button"><i class="fa fa-search" aria-hidden="true"></i></button>';


                printf('<p class="wpsstm-icon-input" id="wpsstm-wizard-search">%s%s</p>',$text_input,$submit_input);
                ?>
            </div>

            <?php
            //save settings

            //post ID
            if ($wpsstm_tracklist->post_id){
                ?>
                <input type="hidden" name="wpsstm_wizard[post_id]" value="<?php echo $wpsstm_tracklist->post_id;?>" />
                <?php
            }

            wp_nonce_field('wpsstm_save_scraper_wizard','wpsstm_scraper_wizard_nonce',false);

            ?>
        </form>

    </div>
<?php

}
?>
<?php

//services
wpsstm_locate_template( 'wizard-services.php', true, false); //we need $require_once = false here or Jetpack will fuck up

//bangs
wpsstm_locate_template( 'wizard-bangs.php', true, false); //we need $require_once = false here or Jetpack will fuck up

//recent
if ( wpsstm()->get_options('recent_wizard_entries') ) {
    wpsstm_locate_template( 'wizard-recent-entries.php', true, false );  //we need $require_once = false here or Jetpack will fuck up
}
