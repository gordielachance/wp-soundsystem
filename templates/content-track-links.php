<?php

global $wpsstm_track;
$wpsstm_track->populate_links();

if ( !wpsstm()->get_options('ajax_load_tracklists') && !$wpsstm_track->have_links() ){
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
                <i class="wpsstm-link-icon wpsstm-icon"></i>
                <?php
                if ( $actions = $wpsstm_link->get_link_links('page') ){
                    echo get_actions_list($actions,'track-link');
                }
                ?>
                <label class="wpsstm-link-title wpsstm-can-click"><?php echo $wpsstm_link->get_link_title();?></label>
            </wpsstm-track-link>
            <?php

        }
        ?>
    </div>
    <?php 
}