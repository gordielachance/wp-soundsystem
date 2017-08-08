<?php

global $wpsstm_track;
$track = $wpsstm_track;

//track sources
$sources_query = $track->query_sources();
if ( $sources_query->have_posts() ) { ?>
    <ul class="wpsstm-track-sources-list">
        <?php
        $source_position = 0;
        while ( $sources_query->have_posts() ) { 
            $sources_query->the_post();
            
            global $wpsstm_source;
            
            $provider = $wpsstm_source->populate_provider();
            if ( !$wpsstm_source->src ) continue;
            
            $source_position++;
            $wpsstm_source->position = $source_position;
            $source = $wpsstm_source;
            
            
            

            ?>
            <li class="<?php echo implode( ' ',$source->get_source_class() );?>" data-wpsstm-source-id="<?php the_ID(); ?>" data-wpsstm-source-idx="<?php echo ($source->position - 1);?>" data-wpsstm-source-type="<?php echo $source->type;?>" data-wpsstm-source-src="<?php echo $source->get_source_url();?>" data-wpsstm-auto-source="1">
                <a class="wpsstm-source-provider-link wpsstm-icon-link" href="<?php echo $source->get_source_url(true);?>" target="_blank" title="<?php the_title();?>">
                    <span class="wpsstm-provider-icon"><?php echo $provider->icon;?></span>
                    <span class="wpsstm-source-title"><?php the_title();?></span>
                </a>
            </li>
            <?php
        }
        ?>
    </ul>
    <?php 
}