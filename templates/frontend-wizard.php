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

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<?php
		// Start the loop.
		while ( have_posts() ) { 
            the_post();
            
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

                <header class="entry-header">
                    <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
                </header><!-- .entry-header -->

                <div class="entry-content">
                    <?php the_content(); ?>
                    
                    <?php 
            
                    global $wpsstm_tracklist;

                    $feed_url = isset( $_POST['wpsstm_wizard']['feed_url'] ) ? $_POST['wpsstm_wizard']['feed_url'] : null;
                    $wpsstm_tracklist = wpsstm_get_live_tracklist_preset($feed_url);
                    $wpsstm_tracklist->populate_remote_tracklist();

                    $visitors_wizard = ( wpsstm()->get_options('visitors_wizard') == 'on' );
                    $can_wizard = ( !get_current_user_id() && !$visitors_wizard );

                    if ( $can_wizard ){

                        $wp_auth_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
                        $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(),__('here','wpsstm'));
                        $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
                        $form = sprintf('<p class="wpsstm-notice">%s %s</p>',$wp_auth_icon,$wp_auth_text);

                    }else{
                        ?>
                        <form action="<?php the_permalink();?>" method="POST">
                            <?php
                            wpsstm_locate_template( 'wizard-form.php', true );
                            ?>
                        </form>
                        <?php
                        


                        //TO FIX move at a smarter place ?
                        if ( $wpsstm_tracklist->get_options('can_play') ){
                            do_action('init_playable_tracklist'); //used to know if we must load the player stuff (scripts/styles/html...)
                        }
                        wpsstm_locate_template( 'content-tracklist-table.php', true, false );
                        
                        wpsstm_locate_template( 'wizard-last-entries.php', true );
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
