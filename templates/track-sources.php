<?php

global $wpsstm_track;
$track_type_obj = get_post_type_object(wpsstm()->post_type_track);
$can_edit_track = current_user_can($track_type_obj->cap->edit_post,$wpsstm_track->post_id);

if ( $wpsstm_track->have_sources() ) { ?>
    <ul class="wpsstm-track-sources-list">
        <?php
        while ( $wpsstm_track->have_sources() ) {
            
            $wpsstm_track->the_source();
            global $wpsstm_source;
            
            
            $wpsstm_source->populate_source_provider();
            if (!$wpsstm_source->url) continue;
            $title = ($title = $wpsstm_source->title ) ? $title : sprintf('<em>%s</em>',$wpsstm_source->url);

            //TO FIX TO CHECK required ?  Does a source not always have a track ?
            if(!$wpsstm_track){
                $wpsstm_track = new WP_SoundSystem_Track($wpsstm_source->track_id);
            }
            ?>
            <li <?php echo wpsstm_get_html_attr($wpsstm_source->get_single_source_attributes());?> >
                <i class="wpsstm-source-icon wpsstm-icon" href="#"></i>
                <?php
                if ( $actions = $wpsstm_source->get_source_links('page') ){
                    echo get_actions_list($actions,'source');
                }
                ?>
                <label class="wpsstm-source-title wpsstm-can-click"><?php echo $title;?></label>
            </li>
            <?php

        }
        ?>
    </ul>
    <?php 
}