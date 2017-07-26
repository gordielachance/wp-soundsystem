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
                
                $admin_action = $wp_query->get(wpsstm_tracklists()->qvar_tracklist_admin);
                
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <header class="entry-header">
                        <?php
                            printf('<h1>%s</h1>',$tracklist->title);
                        ?>
                    </header><!-- .entry-header -->

                    <div id="tracklist-popup-tabs" class="entry-content">
                        <?php 
                        if ( $actions = $tracklist->get_tracklist_actions('admin') ){
                            $list = wpsstm_get_actions_list($actions,'tracklist');
                            echo $list;
                        }
                
                        $tab_content = null;
                
                        switch($admin_action){
                            case 'share':

                                $text = __("Here's the link to this playlist:","wpsstm");
                                $tab_content = sprintf('<div><p>%s</p><p class="wpsstm-notice">%s</p></div>',$text,get_permalink());
                                
                            break;
                            case 'wizard':

                                ob_start();
                                $file = 'wizard-form.php';
                                if ( file_exists( wpsstm_locate_template( $file ) ) ){
                                    $template = wpsstm_locate_template( $file );
                                    load_template( $template );
                                }
                                $form_content = ob_get_clean();
                                
                                $form_action = $tracklist->get_tracklist_admin_gui_url('wizard');
                                $tab_content = sprintf('<form method="post" action="%s">%s</form>',$form_action,$form_content);

                            break;
                        }
                
                        if ($tab_content){
                            printf('<div class="wpsstm-tracklist-admin-%s">%s</div>',$admin_action,$tab_content);
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