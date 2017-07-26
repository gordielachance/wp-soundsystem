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
                    $tracklist = wpsstm_get_post_tracklist(get_the_ID());
                    $tracklist_table = $tracklist->get_tracklist_table(array('can_play'=>false));

                    $admin_action = $wp_query->get(wpsstm_tracks()->qvar_track_admin);
                
                    /*
                    Capability check
                    */
                    //TO FIX to improve
                    $playlist_type_obj =    get_post_type_object(wpsstm()->post_type_playlist);
                    $create_playlist_cap =  $playlist_type_obj->cap->edit_posts;

                    $track =                new WP_SoundSystem_Track(get_the_ID());
                    $track_type_obj =       get_post_type_object(wpsstm()->post_type_track);
                    $can_edit_track =       current_user_can($track_type_obj->cap->edit_post,$track->post_id);
                    $can_delete_tracks =    current_user_can($playlist_type_obj->cap->delete_posts);

                    ?>

                    <header class="entry-header">
                        <?php
                            printf('<div id="wpsstm-track-popup-header">%s</div>',$tracklist_table);
                        ?>
                    </header><!-- .entry-header -->

                    <div id="track-popup-tabs" class="entry-content">
                        <?php
                        if ( $actions = $track->get_track_actions($tracklist,'admin') ){
                            $list = wpsstm_get_actions_list($actions,'track');
                            echo $list;
                        }
                
                        $tab_content = null;
                
                        switch ($admin_action){
                            case 'edit':
                                $tab_content = $track->track_admin_details();
                            break;
                            case 'playlists':
                                $tab_content = $track->track_admin_playlists();
                            break;
                            case 'sources':
                                $tab_content = $track->track_admin_sources();
                            break;
                            case 'delete':
                                $tab_content = "delete";
                            break;
                            default: //about
                                $text_el = null;
                                $bio = wpsstm_lastfm()->get_artist_bio($track->artist);

                                //artist
                                if ( !is_wp_error($bio) && isset($bio['summary']) ){
                                    $artist_text = $bio['summary'];
                                }else{
                                    $artist_text = __('No data found for this artist','wpsstm');
                                }

                                $title_el = sprintf('<h2>%s</h2>',$track->artist);
                                $tab_content = sprintf('<div>%s%s</div>',$title_el,$artist_text);
                            break;
                        }
                
                        if ($tab_content){
                            printf('<div id="wpsstm-track-admin-%s" class="wpsstm-track-admin>%s</div>',$admin_action,$tab_content);
                        }
                        
                
                        ?>

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