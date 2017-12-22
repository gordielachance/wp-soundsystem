<?php 

$can_wizard = wpsstm_wizard()->can_frontend_wizard();

if ( !$can_wizard ){

    $wp_auth_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
    $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(get_permalink()),__('here','wpsstm'));
    $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
    printf('<p class="wpsstm-notice">%s %s</p>',$wp_auth_icon,$wp_auth_text);

}else{
    wpsstm_locate_template( 'wizard-frontend.php', true );
}
?>
<?php
//recent
if ( wpsstm()->get_options('recent_wizard_entries') ) {
    $has_wizard_id = get_query_var(wpsstm_wizard()->qvar_tracklist_wizard);
    if ( !$has_wizard_id ) {
        wpsstm_locate_template( 'recent-wizard-entries.php', true );
    }
}
?>