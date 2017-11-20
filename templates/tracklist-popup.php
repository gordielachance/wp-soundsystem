<?php
global $post;
get_header();
$tracklist = wpsstm_get_post_tracklist(get_the_ID());
$popup_action = isset($_REQUEST['popup-action']) ? $_REQUEST['popup-action'] : null;

?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<?php if ( have_posts() ) { ?>

			<?php
			// Start the loop.
			while ( have_posts() ) { 
                the_post();
                

                
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('wpsstm-tracklist-admin'); ?>>
                    <header class="entry-header">
                        <?php
                            printf('<h1>%s</h1>',$tracklist->title);
                        ?>
                    </header><!-- .entry-header -->

                    <div id="tracklist-popup-tabs" class="entry-content">
                        <?php 
                        if ( $actions = $tracklist->get_tracklist_links('popup') ){
                            $list = get_actions_list($actions,'tracklist');
                            echo $list;
                        }
                
                        $tab_content = null;
                
                        switch($popup_action){
                            case 'share':

                                $text = __("Use this link to share this playlist:","wpsstm");
                                $link = get_permalink($tracklist->post_id);
                                $tab_content = sprintf('<div><p>%s</p><p class="wpsstm-notice">%s</p></div>',$text,$link);
                                
                            break;
                            case 'new-subtrack':
                            default:
                                global $wpsstm_track;
                                $wpsstm_track = new WP_SoundSystem_Track();
                                ?>
                                <form action="<?php echo esc_url($tracklist->get_tracklist_popup_url($popup_action));?>" method="POST">
                                    <?php wpsstm_locate_template( 'track-popup-edit.php',true );?>
                                    <input type="hidden" name="wpsstm-tracklist-popup-action" value="<?php echo $popup_action;?>" />
                                    <input type="hidden" name="wpsstm-tracklist-id" value="<?php echo $tracklist->post_id;?>" />
                                    <?php wp_nonce_field( sprintf('wpsstm_tracklist_%s_new_track_nonce',$tracklist->post_id), 'wpsstm_tracklist_new_track_nonce', true );?>
                                </form>
                                <?php
                            break;
                        }
                
                        if ($tab_content){
                            printf('<div id="wpsstm-tracklist-admin-%s" class="wpsstm-tracklist-admin">%s</div>',$popup_action,$tab_content);
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