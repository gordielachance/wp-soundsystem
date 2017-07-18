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

                    $admin_action = $wp_query->get(wpsstm_tracks()->qvar_track_admin);
                
                    /*
                    Capability check
                    */
                    //TO FIX to improve
                    $track_type_obj =       get_post_type_object(wpsstm()->post_type_track);
                    $can_edit_track =       current_user_can($track_type_obj->cap->edit_post,$track->post_id);
                    $can_delete_tracks =    current_user_can($post_type_obj->cap->delete_posts);
                
                    $can_tab = array(
                        'track_info' =>         ($track->title && $track->artist),
                        'track_details' =>      $can_edit_track,
                        'playlists_manager' =>  (bool)get_current_user_id(),
                        'sources_manager' =>    $can_edit_track,
                        'delete' =>             $can_delete_tracks
                    );

                    ?>

                    <header class="entry-header">
                        <?php
                            printf('<div id="wpsstm-track-popup-header">%s</div>',$tracklist_table);
                        ?>
                    </header><!-- .entry-header -->

                    <div id="track-popup-tabs" class="entry-content">
                        <ul>
                            <?php if ($can_tab['track_info']){?>
                                <li class="<?php if ($admin_action == 'track_info') echo 'active';?>"><a href="#track-infos"><i class="fa fa-address-card-o" aria-hidden="true"></i> <?php _e('Details','wpsstm');?></a></li>
                            <?php } ?>
                            <?php if ($can_tab['track_details']){?>
                                <li class="<?php if ($admin_action == 'track_details') echo 'active';?>"><a href="#admin-track-details"><i class="fa fa-pencil" aria-hidden="true"></i> <?php _e('Edit');?></a></li>
                            <?php } ?>
                            <?php if ($can_tab['playlists_manager']){?>
                                <li class="<?php if ($admin_action == 'playlists_manager') echo 'active';?>"><a href="#admin-track-playlists"><i class="fa fa-list" aria-hidden="true"></i> <?php _e('Playlists manager','wpsstm');?></a></li>
                            <?php } ?>
                            <?php if ($can_tab['sources_manager']){?>
                                <li class="<?php if ($admin_action == 'sources_manager') echo 'active';?>"><a href="#admin-track-sources"><i class="fa fa-cloud" aria-hidden="true"></i> <?php _e('Sources manager','wpsstm');?></a></li>
                            <?php } ?>
                            <?php if ($can_tab['delete']){?>
                                <li class="<?php if ($admin_action == 'track_info') echo 'active';?>"><a href="#admin-track-delete"><i class="fa fa-trash" aria-hidden="true"></i> <?php _e('Delete');?></a></li>
                            <?php } ?>
                        </ul>
                        <!--track infos-->
                        <?php if ($can_tab['track_info']){?>
                            <div id="track-infos">
                                <?php print_r($track);

                                $text_el = null;
                                $bio = wpsstm_lastfm()->get_artist_bio($track->artist);

                                //artist
                                if ( !is_wp_error($bio) && isset($bio['summary']) ){
                                    $artist_text = $bio['summary'];
                                }else{
                                    $artist_text = __('No data found for this artist','wpsstm');
                                }


                                $title_el = sprintf('<h2>%s</h2>',$track->artist);
                                printf('<div>%s%s</div>',$title_el,$artist_text);

                                ?>
                            </div>
                        <?php } ?>
                        <!--track edit-->
                        <?php if ($can_tab['track_details']){?>
                            <div id="admin-track-details">
                                <?php 
                                echo $track->track_admin_details();
                                ?>
                            </div>
                        <?php } ?>
                        <!--playlists manager-->
                        <?php if ($can_tab['playlists_manager']){?>
                            <div id="admin-track-playlists">
                                <?php echo $track->track_admin_playlists();?>
                            </div>
                        <?php } ?>
                        <!--sources manager-->
                        <?php if ($can_tab['sources_manager']){?>
                            <div id="admin-track-sources">
                                <?php echo $track->track_admin_sources();?>
                            </div>
                        <?php } ?>
                        <!--track delete-->
                        <?php if ($can_tab['delete']){?>
                            <div id="admin-track-delete">
                                delete
                            </div>
                        <?php } ?>
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