<?php
/**
 * The template for displaying pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages and that
 * other "pages" on your WordPress site will use a different template.
 *
 * @package WordPress
 * @subpackage Twenty_Fifteen
 * @since Twenty Fifteen 1.0
 */

global $wpsstm_tracklist;
get_header(); 
?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<?php
		// Start the loop.
		while ( have_posts() ) { 
            the_post();
            
            ?>
            <article id="wpsstm-frontend-wizard" <?php post_class(); ?>>

                <header class="entry-header">
                    <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
                </header><!-- .entry-header -->

                <div class="entry-content">
                    <?php the_content(); ?>
                    
                    <?php

                    $can_wizard = wpsstm_wizard()->can_frontend_wizard();

                    if ( !$can_wizard ){

                        $wp_auth_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
                        $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(),__('here','wpsstm'));
                        $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
                        printf('<p class="wpsstm-notice">%s %s</p>',$wp_auth_icon,$wp_auth_text);

                    }else{
                        ?>
                        <form action="<?php the_permalink();?>" method="GET">
                            <?php
                            wpsstm_locate_template( 'wizard-form.php', true );
                        
                            if ($wpsstm_tracklist->feed_url){
                                echo $wpsstm_tracklist->get_tracklist_html();
                            }
                        
                            ?>
                        </form>
                        <?php
                        
                    }
                    
                    ?>
                    
                </div><!-- .entry-content -->

            </article><!-- #post-## -->
            <?php
            
            // If comments are open or we have at least one comment, load up the comment template.
            if ( comments_open() || get_comments_number() ) {
                comments_template();
            }

		// End the loop.
        }
		?>

		</main><!-- .site-main -->
	</div><!-- .content-area -->

<?php get_footer(); ?>
