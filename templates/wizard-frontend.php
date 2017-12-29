<?php 

$can_wizard = wpsstm_wizard()->can_frontend_wizard();

if ( !$can_wizard ){

    $wp_auth_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
    $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(get_permalink()),__('here','wpsstm'));
    $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
    printf('<p class="wpsstm-notice">%s %s</p>',$wp_auth_icon,$wp_auth_text);

}else{
    
    global $wpsstm_tracklist;

    $wpsstm_tracklist->populate_subtracks(); //we must have the tracks populated before we output the notices

    ?>

    <div id="wizard-wrapper" <?php echo wpsstm_get_classes_attr('wizard-wrapper-frontend');?>>

        <?php
        if ($wpsstm_tracklist->feed_url){
            echo $wpsstm_tracklist->get_tracklist_html();
        }
        ?>

        <?php

        //wizard notices
        if ( $notices_el = $wpsstm_tracklist->get_notices_output('wizard-header') ){
            echo $notices_el;
        }
        ?>

        <form action="<?php the_permalink();?>" method="POST">
            <div id="wpsstm-wizard-step-source-content" class="wpsstm-wizard-step-content">
                <?php 
                wpsstm_wizard()->feed_url_callback();
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
    $has_wizard_id = get_query_var(wpsstm_wizard()->qvar_tracklist_wizard);
    if ( !$has_wizard_id ) {
        wpsstm_locate_template( 'recent-wizard-entries.php', true, false );
    }
}
?>