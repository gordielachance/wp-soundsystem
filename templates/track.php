<?php
global $post;
global $wpsstm_tracklist;
global $wpsstm_track;
$track_action = get_query_var( WPSSTM_Core_Tracks::$qvar_track_action );
//
add_filter( 'show_admin_bar','__return_false'); //hide admin bar
do_action( 'wpsstm-iframe' );
do_action( 'wpsstm-track-iframe' );
do_action( 'get_header', 'wpsstm-track-iframe' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
//
?>

<!DOCTYPE html>
<html class="no-js" <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" >
    <?php wp_head(); ?>
</head>
<body <?php body_class('wpsstm-iframe wpsstm-track-iframe'); ?>>
    <?php
    
    //single track tracklist
    $wpsstm_tracklist = new WPSSTM_Post_Tracklist();
    $track = new WPSSTM_Track( $post->ID );
    $wpsstm_tracklist->add_tracks($track);
    wpsstm_locate_template( 'content-tracklist.php', true, false );
    
    //track in playlists
    if ( $playlists_list = $wpsstm_track->get_parents_list() ){

        ?>
        <div class="wpsstm-track-playlists">
            <strong><?php _e('In playlists:','wpsstm');?></strong>
            <?php echo $playlists_list; ?>
        </div>
        <?php
    }
    //track loved by
    if ( $loved_list = $wpsstm_track->get_loved_by_list() ){
        ?>
        <div class="wpsstm-track-loved-by">
            <strong><?php _e('Loved by:','wpsstm');?></strong>
            <?php echo $loved_list; ?>
        </div>
        <?php
    }
    ?>
    <?php
    //tracklist
    echo $wpsstm_tracklist->get_tracklist_html();
    ?>
    <div id="wpsstm-track-admin" class="wpsstm-post-admin">
        <?php
        if ( $actions = $wpsstm_track->get_track_links($wpsstm_tracklist) ){
            $list = get_actions_list($actions,'track');
            echo $list;
        }

        $tab_content = null;

        switch ($track_action){
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
            case 'about':
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
</body>
<?php
//
do_action( 'get_footer', 'wpsstm-track-iframe' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
wp_footer();
//
?>
</html>