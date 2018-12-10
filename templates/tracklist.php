<?php
global $wpsstm_tracklist;
$action = get_query_var( 'wpsstm_action' );

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
    if (have_posts()){
        while (have_posts()) {
            
            the_post();
            
            ///
            
            $tab_content = null;

            /*
            Notices
            */

            //wizard temporary tracklist notice
            //TO FIX should be in populate_wizard_tracklist() ?
            if ( !wpsstm_is_backend() && $wpsstm_tracklist->user_can_get_tracklist_autorship() ){
                $autorship_url = $wpsstm_tracklist->get_tracklist_action_url('get-autorship');
                $autorship_link = sprintf('<a href="%s">%s</a>',$autorship_url,__("add it to your profile","wpsstm"));
                $message = __("This is a temporary playlist.","wpsstm");
                $message .= '  '.sprintf(__("Would you like to %s?","wpsstm"),$autorship_link);
                $wpsstm_tracklist->add_notice('get-autorship', $message );

            }
            ?>
    
            <div class="wpsstm-tracklist-notices">
                <?php
                /*
                Notices
                */
                if ( $notices_el = WP_SoundSystem::get_notices_output($wpsstm_tracklist->notices) ){
                    echo $notices_el;
                }
                ?>
                
            </div>
            <?php
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

                    wpsstm_locate_template( 'content-tracklist.php', true, false );
                break;
            }
        } 
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