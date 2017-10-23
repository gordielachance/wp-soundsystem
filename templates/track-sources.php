<?php

global $wpsstm_track;
$track = $wpsstm_track;

if ( $track->have_sources() ) { ?>
    <ul class="wpsstm-track-sources-list">
        <?php
        while ( $track->have_sources() ) {
            
            $track->the_source();
            global $wpsstm_source;
            
            $wpsstm_source->populate_source_provider();
            if ( ($wpsstm_source->provider->slug == 'default') ) continue;//we cannot play this source

            //TO FIX TO CHECK required ?  Does a source not always have a track ?
            if(!$wpsstm_track){
                $wpsstm_track = new WP_SoundSystem_Track($wpsstm_source->track_id);
            }
            ?>
            <li <?php echo wpsstm_get_html_attr($wpsstm_source->get_single_source_attributes());?> >
                <label class="wpsstm-source-title"><?php echo $wpsstm_source->title;?></label>
                <a class="wpsstm-source-provider" href="<?php echo $wpsstm_source->url;?>" target="_blank" title="<?php echo $wpsstm_source->title;?>">
                    <?php echo $wpsstm_source->provider->icon;?>
                </a>
            </li>
            <?php

        }
        ?>
    </ul>
    <?php 
}