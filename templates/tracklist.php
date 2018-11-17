<?php
global $wpsstm_tracklist;
$action = get_query_var( WPSSTM_Core_Tracklists::$qvar_tracklist_action );

//
add_filter( 'show_admin_bar','__return_false'); //hide admin bar
do_action( 'wpsstm-iframe' );
do_action( 'wpsstm-tracklist-iframe' );
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

    $tab_content = null;

    switch($action){
        case 'share':
            $text = __("Use this link to share this playlist:","wpsstm");
            $link = get_permalink($wpsstm_tracklist->post_id);
            printf('<div><p>%s</p><p class="wpsstm-notice">%s</p></div>',$text,$link);
        break;
        default: //'render'
            $wpsstm_tracklist->populate_subtracks();

            //TOUFIX TOCHECK should be elsewhere ?
            if ( !$wpsstm_tracklist->get_options('ajax_autosource') ){
                $wpsstm_tracklist->tracklist_autosource();
            }

            //wizard notices
            //TOUFIX TOCHECK good place for this ?
            if ( $notices_el = $wpsstm_tracklist->get_notices_output('wizard-header') ){
                echo $notices_el;
            }
            wpsstm_locate_template( 'content-tracklist.php', true, false );
        break;
    }

    ?>

    <?php
    //
    do_action( 'get_footer', 'wpsstm-tracklist-iframe' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
    wp_footer();
    //
    ?>
</body>
</html>