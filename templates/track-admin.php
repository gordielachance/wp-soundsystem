<?php 
//
do_action( 'get_header', 'wpsstm' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
//
?>
<!DOCTYPE html>
<html class="no-js" <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" >
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <?php
    global $wpsstm_tracklist;
    global $wpsstm_track;
    $track_admin = get_query_var( WPSSTM_Core_Tracks::$qvar_track_admin );
    ?>
    <div id="wpsstm-track-admin" class="wpsstm-post-admin">
        <?php
        if ( $actions = $wpsstm_track->get_track_links($wpsstm_tracklist) ){
            $list = get_actions_list($actions,'track');
            echo $list;
        }

        $tab_content = null;

        switch ($track_admin){
            case 'playlists':
                ?>
                <div id="wpsstm-track-admin-playlists" class="wpsstm-track-admin">
                    <?php wpsstm_locate_template( 'track-admin-playlists.php',true );?>
                </div>
                <?php

            break;
            case 'trash':
                ?>
                <div id="wpsstm-track-admin-trash" class="wpsstm-track-admin">
                    trash
                </div>
                <?php
            break;
            default: //about
                $text_el = null;
                $bio = WPSSTM_LastFM::get_artist_bio($wpsstm_track->artist);

                //artist
                if ( !is_wp_error($bio) && isset($bio['summary']) ){
                    $artist_text = $bio['summary'];
                }else{
                    $artist_text = __('No data found for this artist','wpsstm');
                }

                ?>
                <div id="wpsstm-track-admin-about" class="wpsstm-track-admin">
                    <h2><?php echo $wpsstm_track->artist;?></h2>
                    <div><?php echo $artist_text;?></div>
                </div>
                <?php
            break;
        }

        ?>

    </div><!-- .wpsstm-post-admin -->
    <?php
    //
    do_action( 'get_footer', 'wpsstm' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
    //
    ?>
    <?php wp_footer(); ?>
</body>
</html>