<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
    <body <?php body_class('wpsstm-popup'); ?>>
        <?php if ( have_posts() ) { ?>

            <?php
            // Start the loop.
            while ( have_posts() ) {
                the_post();

                ?>
                <article id="post-<?php echo get_the_ID() ?>" <?php post_class(); ?>>

                    <header class="post-header">
                        <h1 class="post-title"><?php the_title();?></h1>
                    </header><!-- .entry-header -->
                    <div class="post-content">
                        <?php the_content();?>
                    </div>
                </article><!-- #post-## -->

                <?php
            }
        }else{
            //TO FIX output error ?
        }
        ?>
        <?php wp_footer(); ?>
    </body>
</html>
