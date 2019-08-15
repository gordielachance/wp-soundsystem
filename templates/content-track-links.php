<?php

global $wpsstm_track;
$wpsstm_track->populate_links();

//should we autoload links when the template is displayed ?
$init_autolink = ( !$wpsstm_track->have_links() && !wpsstm()->get_options('ajax_autolink') && !wp_doing_ajax() );

if ( $init_autolink ){
    $wpsstm_track->autolink();
}

if ( $wpsstm_track->have_links() && is_admin() ){
    ?>
    <p>
    <?php

    //edit links bt
    $post_links_url = admin_url(sprintf('edit.php?post_type=%s&post_parent=%s',wpsstm()->post_type_track_link,$wpsstm_track->post_id));
    printf('<a href="%s" class="button">%s</a>',$post_links_url,__('Edit links','wpsstm'));

    ?>
    </p>
    <?php
}
?>

<div class="wpsstm-track-links-list">
    <?php
    if ( $wpsstm_track->have_links() ) {
        while ( $wpsstm_track->have_links() ) {

            $wpsstm_track->the_track_link();
            global $wpsstm_link;
            ?>
            <wpsstm-track-link <?php echo wpsstm_get_html_attr($wpsstm_link->get_single_link_attributes());?> >
                <?php
                if ( $actions = $wpsstm_link->get_link_actions('page') ){
                    echo get_actions_list($actions,'track-link');
                }
                ?>
                <a class="wpsstm-link-title" href="<?php echo $wpsstm_link->permalink_url;?>" target="_blank"><?php echo $wpsstm_link->get_link_title();?></a>
            </wpsstm-track-link>
            <?php

        }
    }
    ?>
</div>
<?php 
