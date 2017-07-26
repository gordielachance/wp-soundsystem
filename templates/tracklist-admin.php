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
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <header class="entry-header">
                        <?php
                            printf('<h1>%s</h1>',$tracklist->title);
                        ?>
                    </header><!-- .entry-header -->

                    <div id="tracklist-popup-tabs" class="entry-content">
                        <?php 
                        if ( $actions = $tracklist->get_tracklist_popup_actions() ){
                            $list = wpsstm_get_actions_list($actions,'tracklist');
                            echo $list;
                        }
                
                        ?>
                        
                        <!--track infos-->
                        <?php if ( isset($actions['wizard']) ){
                            $form_action = $tracklist->get_tracklist_admin_gui_url('wizard');
                            ?>
                            <div id="tab-content-wizard">
                                <form method="post" action="<?php echo $form_action;?>">
                                <?php 
                                $file = 'wizard-form.php';
                                if ( file_exists( wpsstm_locate_template( $file ) ) ){
                                    $template = wpsstm_locate_template( $file );
                                    load_template( $template );
                                }
                                ?>
                                </form>
                                caca
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