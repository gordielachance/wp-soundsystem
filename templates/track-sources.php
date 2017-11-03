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
            $source_type_obj = get_post_type_object(wpsstm()->post_type_source);
            
            $wpsstm_source->populate_source_provider();
            if ( ($wpsstm_source->provider->slug == 'default') ) continue;//we cannot play this source

            //TO FIX TO CHECK required ?  Does a source not always have a track ?
            if(!$wpsstm_track){
                $wpsstm_track = new WP_SoundSystem_Track($wpsstm_source->track_id);
            }
            ?>
            <li <?php echo wpsstm_get_html_attr($wpsstm_source->get_single_source_attributes());?> >
                <span class="wpsstm-source-links">
                    <a class="wpsstm-source-provider" href="<?php echo $wpsstm_source->url;?>" target="_blank" title="<?php echo $wpsstm_source->title;?>">
                        <?php echo $wpsstm_source->provider->icon;?>
                    </a>
                    <?php

                    //delete source
                    $can_delete_source = current_user_can($source_type_obj->cap->delete_post,$wpsstm_source->post_id);

                    if ($can_delete_source){
                        ?>
                        <a class="wpsstm-source-action wpsstm-source-delete-action" href="#" title="<?php _e('Delete this source','wpsstm');?>"><i class="fa fa-trash" aria-hidden="true"></i></a>
                        <?php
                    }

                    if ( $wpsstm_track->user_can_reorder_sources() ){
                        ?>
                        <a class="wpsstm-source-action wpsstm-source-reorder-action" href="#" title="<?php _e('Reorder track','wpsstm');?>"><i class="fa fa-arrows-v" aria-hidden="true"></i></a>
                        <?php
                    }

                    ?>
                </span>
                <label class="wpsstm-source-title wpsstm-can-click"><?php echo $wpsstm_source->title;?></label>
            </li>
            <?php

        }
        ?>
    </ul>
    <?php 
}