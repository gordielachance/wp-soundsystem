<?php

//TOUFIX display only successful tracklists ? (or at least, tracklists that have a tracks count ?)
$recent_wizard_args = array(
    'post_type'         => wpsstm()->post_type_radio,
    'posts_per_page'    => (int)wpsstm()->get_options('recent_wizard_entries'),
    'meta_query'        => array(
        array( WPSSTM_Core_Importer::$is_wizard_tracklist_metakey => true ) //created with wizard
    )
);
$recent_wizard_q = new WP_Query( $recent_wizard_args );
?>
<?php if ( $recent_wizard_q->have_posts() ) {
    ?>
    <section id="wpsstm-frontend-importer-recent">
        <h3><?php _e('Recently');?></h3>
        <ul>
            <?php while ( $recent_wizard_q->have_posts() ) : $recent_wizard_q->the_post();
                $tracklist = new WPSSTM_Post_Tracklist(get_the_ID());
                ?>
                <li>
                    <a href="<?php echo get_permalink();?>">
                    <?php
                    if ( $title = get_the_title() ){
                        ?>
                        <strong><?php echo $title; ?></strong>
                        <?php
                    }
                    ?>
                    <span><?php echo wpsstm_shorten_text(esc_html($tracklist->feed_url));?></span>
                    </a>
                </li>
            <?php endwhile; ?><!-- end of the loop -->
        </ul>
    </section>
    <?php
    wp_reset_postdata();
}
?>
