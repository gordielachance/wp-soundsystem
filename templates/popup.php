<?php 
global $wpsstm_track;
$track_admin = get_query_var( wpsstm_tracks()->qvar_track_admin );
?>

<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
    <body <?php body_class('wpsstm-popup'); ?>>
        <?php if ( have_posts() ) { ?>

            <?php
            // Start the loop.
            while ( have_posts() ) {

                the_post();
                
                $wpsstm_track = new WP_SoundSystem_Track( get_the_ID() );

                $post_type = get_post_type();
                $tracklist = wpsstm_get_post_tracklist(get_the_ID());

                /*
                Capability check
                */
                //TO FIX to improve
                $playlist_type_obj =    get_post_type_object(wpsstm()->post_type_playlist);
                $create_playlist_cap =  $playlist_type_obj->cap->edit_posts;

                $track_type_obj =       get_post_type_object(wpsstm()->post_type_track);

                ?>
                <article id="post-<?php echo get_the_ID() ?>" <?php post_class(); ?>>

                    <header class="post-header">
                        <h1 class="post-title"><?php the_title();?></h1>
                    </header><!-- .entry-header -->
                    <div class="post-content">
                        <?php the_content();?>
                    </div>
                </article><!-- #post-## -->

                <?php
            }
        }else{
            //TO FIX output error ?
        }
        ?>
        <?php wp_footer(); ?>
    </body>
</html>
