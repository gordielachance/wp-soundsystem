<?php

global $wpsstm_track;
$track = $wpsstm_track;

//track sources
$sources_query = $track->query_sources();
if ( $sources_query->have_posts() ) { ?>
    <div class="wpsstm-track-sources-list">
        <?php
        $source_position = 0;
        while ( $sources_query->have_posts() ) {
            
            $sources_query->the_post();
            global $wpsstm_source;
            
            if ( !$source_link = $wpsstm_source->get_provider_link() ) continue;
            echo $source_link;
            
            ?>

            <?php
        }
        ?>
    </div>
    <?php 
}