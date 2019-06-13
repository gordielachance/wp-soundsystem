<?php

global $wpsstm_track;
$wpsstm_track->populate_links();

//should we autoload links when the template is displayed ?
$init_autolink = ( !$wpsstm_track->have_links() && !wpsstm()->get_options('ajax_autolink') && !wp_doing_ajax() );

if ( $init_autolink ){
    $wpsstm_track->autolink();
}

if ( $wpsstm_track->have_links() ) { ?>
    <div class="wpsstm-track-links-list">
        <?php
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
        ?>
    </div>
    <?php 
}