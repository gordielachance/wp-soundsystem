<?php
add_filter( 'show_admin_bar','__return_false');



global $wpsstm_tracklist;

do_action( 'get_header', 'wpsstm' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
//
?>
<!DOCTYPE html>
<html class="no-js wpsstm-iframe" <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" >
    <?php wp_head(); ?>
</head>
<?php

$wpsstm_tracklist->populate_subtracks();


//TOUFIX TOCHECK should be elsewhere ?
if ( !$wpsstm_tracklist->get_options('ajax_autosource') ){
    $wpsstm_tracklist->tracklist_autosource();
}


//wizard notices
if ( $notices_el = $wpsstm_tracklist->get_notices_output('wizard-header') ){
    echo $notices_el;
}
?>
<?php wpsstm_locate_template( 'content-tracklist.php', true, false );?>
<?php
//
do_action( 'get_footer', 'wpsstm' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
//
?>
<?php wp_footer(); ?>
</body>
</html>