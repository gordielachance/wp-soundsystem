<?php
global $wpsstm_tracklist;
$tracklist_admin = get_query_var( WPSSTM_Core_Tracklists::$qvar_tracklist_action );

//
add_filter( 'show_admin_bar','__return_false'); //hide admin bar
do_action( 'wpsstm-iframe' );
do_action( 'wpsstm-tracklist-iframe' );
do_action( 'get_header', 'wpsstm-tracklist-iframe' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
//
?>

<!DOCTYPE html>
<html class="no-js" <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" >
    <?php wp_head(); ?>
</head>
<body <?php body_class('wpsstm-iframe wpsstm-tracklist-iframe'); ?>>  
    <div id="wpsstm-tracklist-admin" class="wpsstm-post-admin">
        <?php 
        if ( $actions = $wpsstm_tracklist->get_tracklist_links() ){
            $list = get_actions_list($actions,'tracklist');
            echo $list;
        }

        $tab_content = null;

        switch($tracklist_admin){
            case 'share':
                $text = __("Use this link to share this playlist:","wpsstm");
                $link = get_permalink($wpsstm_tracklist->post_id);
                $tab_content = sprintf('<div><p>%s</p><p class="wpsstm-notice">%s</p></div>',$text,$link);
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

        if ($tab_content){
            printf('<div id="wpsstm-tracklist-admin-%s" class="wpsstm-tracklist-admin">%s</div>',$tracklist_admin,$tab_content);
        }

        ?>

    </div><!-- .wpsstm-post-admin -->
    <?php
    //
    do_action( 'get_footer', 'wpsstm-tracklist-iframe' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
    wp_footer();
    //
    ?>
</body>
</html>