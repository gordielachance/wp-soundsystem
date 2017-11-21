<?php 
get_header();
global $wpsstm_track;

$popup_action = isset($_REQUEST['popup-action']) ? $_REQUEST['popup-action'] : null;
?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<?php if ( have_posts() ) { ?>

			<?php
			// Start the loop.
			while ( have_posts() ) { 
                
                the_post();
                
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
                <article id="post-<?php echo $wpsstm_track->post_id; ?>" <?php post_class('wpsstm-track-admin'); ?> data-wpsstm-track-id="<?php echo $wpsstm_track->post_id;?>">

                    <header class="entry-header">
                        <h1 class="entry-title"><?php echo $wpsstm_track->title;?></h1>
                        <?php if ($popup_action == 'new-subtrack'){ //TO FIX NOT WORKING
                            printf('<h2>%s</h2>',$track_type_obj->labels->add_new_item);
                        }

                        ?>

                        <?php 
                        if ( $loved_list = $wpsstm_track->get_loved_by_list() ){
                            ?>
                            <div class="wpsstm-track-loved-by">
                                <strong><?php _e('Loved by:','wpsstm');?></strong>
                                <?php echo $loved_list; ?>
                            </div>
                            <?php
                        }
                
                        if ( $playlists_list = $wpsstm_track->get_parents_list() ){
                            ?>
                            <div class="wpsstm-track-playlists">
                                <strong><?php _e('In playlists:','wpsstm');?></strong>
                                <?php echo $playlists_list; ?>
                            </div>
                            <?php
                        }
                        ?>
                        
                    </header><!-- .entry-header -->

                    <div id="track-popup-tabs" class="entry-content">
                        <?php
                        if ( $actions = $wpsstm_track->get_track_links($tracklist,'popup') ){
                            $list = get_actions_list($actions,'track');
                            echo $list;
                        }
                
                        $tab_content = null;
 
                        switch ($popup_action){
                            case 'edit':
                                ?>
                                <form action="<?php echo esc_url($wpsstm_track->get_track_popup_url('edit'));?>" method="POST">
                                    <?php wpsstm_locate_template( 'track-popup-edit.php',true );?>
                                </form>
                                <?php
                            break;
                            case 'playlists':
                                ?>
                                <div id="wpsstm-track-admin-playlists" class="wpsstm-track-admin">
                                    <?php echo $wpsstm_track->get_playlists_manager();?>
                                </div>
                                <?php
                                
                            break;
                            case 'sources-manager':
                                ?>
                                <div id="wpsstm-track-admin-sources" class="wpsstm-track-admin">
                                    <p>
                                        <?php _e('Add sources to this track.  It could be a local audio file or a link to a music service.','wpsstm');?>
                                    </p>
                                    <p>
                                        <?php _e("If no sources are set and that the 'Auto-Source' setting is enabled, We'll try to find a source automatically when the tracklist is played.",'wpsstm');?>
                                    </p>
                                    <?php echo $wpsstm_track->get_sources_manager();?>
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
                                $bio = wpsstm_lastfm()->get_artist_bio($wpsstm_track->artist);

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