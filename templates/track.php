<?php
global $post;
global $wpsstm_tracklist;
global $wpsstm_track;
$action = get_query_var( 'wpsstm_action' );
//
add_filter( 'show_admin_bar','__return_false'); //hide admin bar
do_action( 'wpsstm-iframe' );
do_action( 'wpsstm-track-iframe' );
do_action( 'get_header', 'wpsstm-track-iframe' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
//

$body_classes = array(
    'wpsstm-iframe',
    'wpsstm-track-iframe',
    ($action) ? sprintf('wpsstm-track-action-%s',$action) : null,
);

?>

<!DOCTYPE html>
<html class="no-js wpsstm-iframe-content" <?php language_attributes(); ?>>
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
            wpsstm_locate_template( 'track-header.php', true, false );
            ?>
            
                <div>
                    <?php

                    /*
                    Content
                    */

                    switch ($action){
                        case 'trash':
                            ?>
                            <div id="wpsstm-track-admin-trash">
                                trash
                            </div>
                            <?php
                        break;
                        case 'about':
                            global $wpsstm_lastfm;
                            $text_el = null;
                            $bio = $wpsstm_lastfm->get_artist_bio($wpsstm_track->artist);

                            //artist
                            if ( !is_wp_error($bio) && isset($bio['summary']) ){
                                $artist_text = $bio['summary'];
                            }else{
                                $artist_text = __('No data found for this artist','wpsstm');
                            }

                            ?>
                            <div id="wpsstm-track-admin-about">
                                <h2><?php echo $wpsstm_track->artist;?></h2>
                                <div><?php echo $artist_text;?></div>
                            </div>
                            <?php
                        break;
                    }
                ?>
                </div>
            <?php
            
        }
    }
    ?>
</body>
<?php
//
do_action( 'get_footer', 'wpsstm-track-iframe' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
wp_footer();
//
?>
</html>