<?php
$recent_wizard_args = array(
    'post_type'         => wpsstm()->post_type_live_playlist,
    'meta_query'        => array(
        array( wpsstm_wizard()->is_wizard_tracklist_metakey => true ) //created with wizard
    )
);
$recent_wizard_q = new WP_Query( $recent_wizard_args );
?>
<?php if ( $recent_wizard_q->have_posts() ) { 
    ?>
    <section id="wpsstm-frontend-wizard-recent">
        <h2><?php _e('Recently');?></h2>
        <ul>
            <?php while ( $recent_wizard_q->have_posts() ) : $recent_wizard_q->the_post(); ?>
                <li>
                    <a href="<?php echo get_permalink();?>">
                    <strong><?php the_title(); ?></strong>
                    <small><?php echo wpsstm_get_live_tracklist_url();?></small>
                    </a>
                </li>
            <?php endwhile; ?><!-- end of the loop -->
        </ul>
    </section>
    <?php
   wp_reset_query();
}
?>