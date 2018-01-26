<?php 

$can_wizard = WP_SoundSystem_Core_Wizard::can_frontend_wizard();

if ( !$can_wizard ){

    $wp_auth_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
    $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(get_permalink()),__('here','wpsstm'));
    $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
    printf('<p class="wpsstm-notice">%s %s</p>',$wp_auth_icon,$wp_auth_text);

}else{
    
    global $wpsstm_tracklist;

    ?>

    <div id="wizard-wrapper" <?php echo wpsstm_get_classes_attr('wizard-wrapper-frontend');?>>

        <?php
    
        //we must have the tracks populated before we output the notices
        $wpsstm_tracklist->populate_subtracks();
    
        //wizard notices
        if ( $notices_el = $wpsstm_tracklist->get_notices_output('wizard-header') ){
            echo $notices_el;
        }
    
        //we requested something through the wizard form
        if ( $wztr_id = get_query_var(WP_SoundSystem_Core_Wizard::$qvar_tracklist_wizard) ){
            echo $wpsstm_tracklist->get_tracklist_html();
        }
        ?>

        <form action="<?php the_permalink();?>" method="POST">
            <div id="wpsstm-wizard-step-source-content" class="wpsstm-wizard-step-content">
                <?php 
                WP_SoundSystem_Core_Wizard::feed_url_callback();
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
//recent
if ( wpsstm()->get_options('recent_wizard_entries') ) {
    /*check that no tracklist is loaded by the wizard*/
    if ( !$has_wizard_id = get_query_var(WP_SoundSystem_Core_Wizard::$qvar_tracklist_wizard) ) {
        wpsstm_locate_template( 'wizard-recent-entries.php', true, false );
    }
}
?>