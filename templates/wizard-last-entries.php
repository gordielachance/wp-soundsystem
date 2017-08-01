<?php

global $post;

$li_items = array();

$query_args = array(
    'post_type'     => wpsstm()->post_type_live_playlist,
    'meta_query' => array(
        array(
            'key'       => wpsstm_wizard()->frontend_wizard_meta_key,
            'compare'   => 'EXISTS'
        )
    )
);

query_posts( $query_args );

if ( have_posts() ){
    ?>
    <div id="wpsstm-wizard-last-entries"><h2><?php _e('Last Wizard Requests','wpsstm');?></h2>
        <ul>
        <?php
        while ( have_posts() ){
            the_post();
            ?>
            <li>
                <a href="<?php echo get_permalink();?>"><?php the_title();?></a><br/>
                <small><a href="<?php echo wpsstm_get_live_tracklist_url();?>"></a><?php echo wpsstm_get_live_tracklist_url();?></small>
            </li>
            <?php
        }
        ?>
        </ul>
    </div>
    <?php
}else{
    //TO FIX
    ?>
    <?php
}

wp_reset_query();