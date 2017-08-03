<?php

global $wpsstm_track;
$track = $wpsstm_track;

//track sources
$sources_query = $track->query_track_sources(array('posts_per_page'=>-1));
if ( $sources_query->have_posts() ) { ?>
    <?php
        //current source
        while ( $sources_query->have_posts() ) { 
            $sources_query->the_post();
            global $wpsstm_source;
            ?>
            <span class="wpsstm-source wpsstm-current-source">
                CACA
                <?php //wpsstm_locate_template( 'content-source.php', true, false ); ?>
            </span>
            <?php
            break;
        }
    ?>
    <ul class="wpsstm-track-sources-list">
        <?php
        $source_position = 0;
        while ( $sources_query->have_posts() ) { 
            $sources_query->the_post();

            global $wpsstm_source;
            $source = $wpsstm_source;
            $provider = wpsstm_get_source_provider();

            $source_position++;
            $wpsstm_source->position = $source_position;

            ?>
            <li class="wpsstm-source" data-wpsstm-source-id="<?php the_ID(); ?>" data-wpsstm-source-idx="<?php echo ($source->position - 1);?>" data-wpsstm-source-type="<?php echo wpsstm_get_source_mime();?>" data-wpsstm-source-src="<?php echo wpsstm_get_source_url();?>" data-wpsstm-auto-source="1">
                <a class="wpsstm-source-provider-link wpsstm-icon-link" href="<?php echo wpsstm_get_source_url(true);?>" target="_blank" title="<?php the_title();?>">
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