<?php
global $post;
get_header();
$tracklist = wpsstm_get_post_tracklist(get_the_ID());

?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<?php if ( have_posts() ) { ?>

			<?php
			// Start the loop.
			while ( have_posts() ) { 
                the_post();
                
                $action = $wp_query->get(wpsstm_tracklists()->qvar_tracklist_action);
                
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('wpsstm-tracklist-admin'); ?>>
                    <header class="entry-header">
                        <?php
                            printf('<h1>%s</h1>',$tracklist->title);
                        ?>
                    </header><!-- .entry-header -->

                    <div id="tracklist-popup-tabs" class="entry-content">
                        <?php 
                        if ( $actions = $tracklist->get_tracklist_links('admin') ){
                            $list = output_tracklist_actions($actions,'tracklist');
                            echo $list;
                        }
                
                        $tab_content = null;
                
                        switch($action){
                            case 'share':

                                $text = __("Use this link to share this playlist:","wpsstm");
                                $link = get_permalink($this->post_id);
                                $tab_content = sprintf('<div><p>%s</p><p class="wpsstm-notice">%s</p></div>',$text,$link);
                                
                            break;
                        }
                
                        if ($tab_content){
                            printf('<div id="wpsstm-tracklist-admin-%s" class="wpsstm-tracklist-admin">%s</div>',$action,$tab_content);
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