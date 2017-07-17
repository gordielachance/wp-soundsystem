<?php
global $post;
get_header(); 
?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<?php if ( have_posts() ) { ?>

			<?php
			// Start the loop.
			while ( have_posts() ) { 
                the_post();
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    
                    <?php
                    $tracklist = new WP_SoundSystem_Tracklist(get_the_ID());
                    $tracklist_action = $wp_query->get(wpsstm_tracklists()->qvar_admin);
                    $can_add_post_tracks = in_array($post->post_type,array(wpsstm()->post_type_album,wpsstm()->post_type_playlist) );
                
                    $track_obj = get_post_type_object(wpsstm()->post_type_track);
                    $add_track_text = $track_obj->labels->add_new_item;
                
                    ?>

                    <header class="entry-header">
                        <?php
                            printf('<h1>%s</h1>',$tracklist->title);
                        ?>
                    </header><!-- .entry-header -->

                    <div id="track-popup-tabs" class="entry-content">
                        <ul>
                            <?php 
                            if ( $can_add_post_tracks ){
                                ?>
                                <li class="<?php if ($tracklist_action == 'add_track') echo 'active';?>"><a href="#tracklist-popup-add-track"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo $add_track_text;?></a>
                                <?php
                            }
                            ?>
                            
                        </ul>
                        <!--add edit-->
                        <div id="tracklist-popup-add-track">
                            <?php
                            echo $tracklist->tracklist_admin_new_track_details();
                            ?>
                        </div>
                    </div><!-- .entry-content -->

                </article><!-- #post-## -->

                <?php
            }


		// If no content, include the "No posts found" template.
        }else{
			get_template_part( 'content', 'none' );

        }
		?>

		</main><!-- .site-main -->
	</div><!-- .content-area -->

<?php get_footer(); ?>