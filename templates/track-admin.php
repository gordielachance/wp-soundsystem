<?php get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<?php if ( have_posts() ) { ?>

			<?php
			// Start the loop.
			while ( have_posts() ) { 
                
                the_post();
                
                $post_type = get_post_type();
                $tracklist = wpsstm_get_post_tracklist(get_the_ID());

                $track_admin_action =  get_query_var( wpsstm_tracks()->qvar_track_admin );

                /*
                Capability check
                */
                //TO FIX to improve
                $playlist_type_obj =    get_post_type_object(wpsstm()->post_type_playlist);
                $create_playlist_cap =  $playlist_type_obj->cap->edit_posts;

                //TO FIX TO CHECK WHY FOR ?
                if ( $post_type == wpsstm()->post_type_track ){
                    $track = new WP_SoundSystem_Track(get_the_ID());
                }else{
                    $track = new WP_SoundSystem_Track();
                }

                $track_type_obj =       get_post_type_object(wpsstm()->post_type_track);
                
                ?>
                <article id="post-<?php echo $track->post_id; ?>" <?php post_class('wpsstm-track-admin'); ?> data-wpsstm-track-id="<?php echo $track->post_id;?>">

                    <header class="entry-header">
                        <h1 class="entry-title"><?php echo $track->title;?></h1>
                        <?php if ($track_admin_action == 'new-subtrack'){ //TO FIX NOT WORKING
                            printf('<h2>%s</h2>',$track_type_obj->labels->add_new_item);
                        }

                        ?>

                        <?php 
                        if ( $loved_list = $track->get_loved_by_list() ){
                            ?>
                            <div class="wpsstm-track-loved-by">
                                <strong><?php _e('Loved by:','wpsstm');?></strong>
                                <?php echo $loved_list; ?>
                            </div>
                            <?php
                        }
                
                        if ( $playlists_list = $track->get_parents_list() ){
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
                        if ( $actions = $track->get_track_actions($tracklist,'admin') ){
                            $list = output_tracklist_actions($actions,'track');
                            echo $list;
                        }
                
                        $tab_content = null;
                
                        switch ($track_admin_action){
                            case 'new-subtrack':
                            case 'edit':
                                ?>
                                <div id="wpsstm-track-admin-edit" class="wpsstm-track-admin">
                                    <form action="<?php echo esc_url($track->get_track_admin_gui_url($track_admin_action));?>" method="POST">

                                        <div id="track-admin-artist">
                                            <h3><?php _e('Artist','wpsstm');?></h3>
                                            <input name="wpsstm_track_artist" value="<?php echo $track->artist;?>" class="wpsstm-fullwidth" />
                                        </div>

                                        <div id="track-admin-title">
                                            <h3><?php _e('Title','wpsstm');?></h3>
                                            <input name="wpsstm_track_title" value="<?php echo $track->title;?>" class="wpsstm-fullwidth" />
                                        </div>

                                        <div id="track-admin-album">
                                            <h3><?php _e('Album','wpsstm');?></h3>
                                            <input name="wpsstm_track_album" value="<?php echo $track->album;?>" class="wpsstm-fullwidth" />
                                        </div>

                                        <div id="track-admin-mbid">
                                            <h3><?php _e('Musicbrainz ID','wpsstm');?></h3>
                                            <input name="wpsstm_track_mbid" value="<?php echo $track->mbid;?>" class="wpsstm-fullwidth" />
                                        </div>

                                        <p class="wpsstm-submit-wrapper">
                                            <?php 
                                            if ($track_admin_action=='new-subtrack'){
                                                $tracklist_id = isset($_REQUEST['tracklist_id']) ? $_REQUEST['tracklist_id'] : null;
                                                ?>
                                                <input type="hidden" name="tracklist_id" value="<?php echo $tracklist_id;?>" />
                                                <input type="hidden" name="wpsstm-admin-tracklist-action" value="new-subtrack" />
                                                <?php wp_nonce_field( 'wpsstm_admin_new_tracklist_subtrack_'.$tracklist_id, 'wpsstm_admin_new_tracklist_subtrack_nonce', true );?>
                                                <?php
                                            }else{
                                                ?>
                                                <input type="hidden" name="wpsstm-admin-track-action" value="edit" />
                                                <?php wp_nonce_field( 'wpsstm_admin_track_gui_edit_'.$track->post_id, 'wpsstm_admin_track_gui_edit_nonce', true );?>
                                                <?php
                                            }
                                            ?>
                                            <input id="wpsstm-update-track-bt" type="submit" value="<?php _e('Save');?>" />
                                        </p>

                                    </form>
                                </div>
                                <?php
                                
                            break;
                            case 'playlists':
                                
                                $playlist_type_obj = get_post_type_object(wpsstm()->post_type_playlist);
                                $labels = get_post_type_labels($playlist_type_obj);
                                
                                ?>
                                <div id="wpsstm-track-admin-playlists" class="wpsstm-track-admin">
                                    <div id="wpsstm-tracklist-chooser-list" data-wpsstm-track-id="<?php echo $tracklist->post_id;?>">
                                        <div id="wpsstm-filter-playlists">
                                            <p>
                                                <input id="wpsstm-playlists-filter" type="text" placeholder="<?php _e('Type to filter playlists or to create a new one','wpsstm');?>" />
                                            </p>
                                            <?php echo wpsstm_get_user_playlists_list(array('checked_ids'=>$track->get_parent_ids()));?>
                                            <p id="wpsstm-new-playlist-add">
                                                <input type="submit" value="<?php echo $labels->add_new_item;?>"/>
                                                <?php wp_nonce_field( 'wpsstm_admin_track_gui_playlists_'.$track->post_id, 'wpsstm_admin_track_gui_playlists_nonce', true );?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php
                                
                            break;
                            case 'sources':
                                
                                $track->populate_sources();
                                
                                ?>
                                <div id="wpsstm-track-admin-sources" class="wpsstm-track-admin">
                                    <p>
                                        <?php _e('Add sources to this track.  It could be a local audio file or a link to a music service.','wpsstm');?>
                                    </p>
                                    <p>
                                        <?php _e("If no sources are set and that the 'Auto-Source' setting is enabled, We'll try to find a source automatically when the tracklist is played.",'wpsstm');?>
                                    </p>
                                    <form action="<?php echo esc_url($track->get_track_admin_gui_url('sources'));?>" method="post">
                                        <div class="wpsstm-sources-edit-list-user wpsstm-sources-edit-list">
                                            <?php
                                            $track_source_ids = array();
                                            foreach((array)$track->sources as $source){
                                                $track_source_ids[] = $source->post_id;
                                            }
                                            echo wpsstm_sources()->get_sources_form($track_source_ids,true);
                                            ?>
                                        </div>
                                        <p class="wpsstm-submit-wrapper">
                                            <input id="wpsstm-suggest-sources-bt" type="submit" value="<?php _e('Suggest sources','wpsstm');?>" />
                                        </p>
                                        <p class="wpsstm-submit-wrapper">
                                            <input id="wpsstm-update-sources-bt" type="submit" value="<?php _e('Save');?>" />
                                            <input type="hidden" name="wpsstm-admin-track-action" value="sources">
                                            <input type="hidden" name="wpsstm-admin-track-id" value="<?php echo $track->post_id;?>">
                                            <?php wp_nonce_field( 'wpsstm_admin_track_gui_sources_'.$track->post_id, 'wpsstm_admin_track_gui_sources_nonce', true );?>
                                        </p>
                                    </form>
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
                                $bio = wpsstm_lastfm()->get_artist_bio($track->artist);

                                //artist
                                if ( !is_wp_error($bio) && isset($bio['summary']) ){
                                    $artist_text = $bio['summary'];
                                }else{
                                    $artist_text = __('No data found for this artist','wpsstm');
                                }
                                
                                ?>
                                <div id="wpsstm-track-admin-about" class="wpsstm-track-admin">
                                    <h2><?php echo $track->artist;?></h2>
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