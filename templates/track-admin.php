<?php

get_header(); ?>

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
                    $track = new WP_SoundSystem_Track( array('post_id'=>get_the_ID()) );
                    $tracks = array($track);
                    $tracklist = new WP_SoundSystem_Tracklist();
                    $tracklist->add($tracks);
                    $tracklist_table = $tracklist->get_tracklist_table(array('can_play'=>false));
                
                    $admin_action = $wp_query->get(wpsstm_tracks()->qvar_admin);
                
                    ?>

                    <header class="entry-header">
                        <?php
                            printf('<div id="wpsstm-track-popup-header">%s</div>',$tracklist_table);
                        ?>
                    </header><!-- .entry-header -->

                    <div id="track-popup-tabs" class="entry-content">
                        <ul>
                            <li class="<?php if ($admin_action == 'track_info') echo 'active';?>"><a href="#track-popup-info"><i class="fa fa-address-card-o" aria-hidden="true"></i> <?php _e('Details','wpsstm');?></a></li>
                            <li class="<?php if ($admin_action == 'track_details') echo 'active';?>"><a href="#track-popup-edit"><i class="fa fa-pencil" aria-hidden="true"></i> <?php _e('Edit');?></a></li>
                            <li class="<?php if ($admin_action == 'playlists_manger') echo 'active';?>"><a href="#track-popup-append"><i class="fa fa-list" aria-hidden="true"></i> <?php _e('Playlists manager','wpsstm');?></a></li>
                            <li class="<?php if ($admin_action == 'sources_manager') echo 'active';?>"><a href="#track-popup-sources"><i class="fa fa-cloud" aria-hidden="true"></i> <?php _e('Sources manager','wpsstm');?></a></li>
                            <li class="<?php if ($admin_action == 'track_info') echo 'active';?>"><a href="#track-popup-delete"><i class="fa fa-trash" aria-hidden="true"></i> <?php _e('Delete');?></a></li>
                        </ul>
                        <!--track infos-->
                        <div id="track-popup-info">
                            <?php print_r($track);
                            
                            $text_el = null;
                            $bio = wpsstm_lastfm()->get_artist_bio($track->artist);

                            //artist
                            if ( $bio['summary'] ){
                                $artist_text = $bio['summary'];
                            }else{
                                $artist_text = __('No data found for this artist','wpsstm');
                            }


                            $title_el = sprintf('<h2>%s</h2>',$track->artist);
                            printf('<div>%s%s</div>',$title_el,$artist_text);
                            
                            ?>
                        </div>
                        <!--track edit-->
                        <div id="track-popup-edit">
                            <?php 
                            echo $track->track_admin_details();
                            ?>
                        </div>
                        <!--track append-->
                        <div id="track-popup-append">
                            <?php echo $track->track_admin_playlists();?>
                        </div>
                        <!--track sources-->
                        <div id="track-popup-sources">
                            <?php echo $track->track_admin_sources();?>
                        </div>
                        <!--track delete-->
                        <div id="track-popup-delete">
                            delete
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