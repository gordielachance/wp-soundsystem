<?php

global $wpsstm_track;
$track = $wpsstm_track;

if ( $track->have_sources() ) { ?>
    <ul class="wpsstm-track-sources-list">
        <?php
        while ( $track->have_sources() ) {
            
            $track->the_source();
            global $wpsstm_source;

            if ( $source_link = $wpsstm_source->get_provider_link() ){
                ?>
                <li><?php echo $source_link;?></li>
                <?php
            }

        }
        ?>
    </ul>
    <?php 
}
