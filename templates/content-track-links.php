<?php
global $wpsstm_track;

/*
List links
Render container even if there is no links, as it is used by JS.
*/

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
                if ( $items = $wpsstm_link->get_track_link_context_menu_items('page') ){
                    echo get_context_menu($items,'track-link');
                }
                ?>
                <a class="wpsstm-link-title" href="<?php echo $wpsstm_link->url;?>" target="_blank"><?php echo $wpsstm_link->get_link_title();?></a>
            </wpsstm-track-link>
            <?php

        }
    }

    ?>
</div>
<?php
