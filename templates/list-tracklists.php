<?php
global $tracklist_manager_query;
$query = $tracklist_manager_query;

if ( $query->have_posts() ) {
    ?>
    <ul id="tracklists-manager">
        <?php
        while ( $query->have_posts() ) {

            $query->the_post();
            global $wpsstm_tracklist;

            $wpsstm_tracklist->options['can_play'] = false;
            $wpsstm_tracklist->options['autoload'] = false;
            $wpsstm_tracklist->options['autoplay'] = false;
            
            //TO FIX TO CHECK or use get_tracklist_class() here ?
            $row_classes = array('tracklist-row','wpsstm-tracklist');
            if ($wpsstm_tracklist->tracklist_type=='live') $row_classes[] = 'wpsstm-live-tracklist';

            ?>
            <li class="<?php echo implode(' ',$row_classes);?>">
                <?php do_action('wpsstm_before_tracklist_row',$wpsstm_tracklist);?>
                <span class="wpsstm-tracklist-title" itemprop="name" title="<?php echo $tracklist->get_title();?>">
                    <a href="<?php echo get_permalink($wpsstm_tracklist->post_id);?>"><?php echo $tracklist->get_title();?></a>
                <?php
                    $post_status = get_post_status();
                    ?>
                    <strong class="wpsstm-tracklist-post-state wpsstm-tracklist-post-state-<?php echo $post_status;?>">
                        <?php
                            $post_status_obj = get_post_status_object( get_post_status() );
                            echo ' — ' . $post_status_obj->label;
                        ?>
                    </strong>
                    <?php
                ?>
                </span>
                <span class="wpsstm-tracklist-actions">
                    <?php
                    if ( $actions = $wpsstm_tracklist->get_tracklist_links('page') ){
                        echo get_actions_list($actions,'tracklist');
                    }
                    ?>
                </span>
            </li>
            <?php
        }
        ?>
    </ul>
    <?php
    wp_reset_postdata();
}else{
    ?>
    <p><?php _e( 'Sorry, no tracklists matching those criteria.','wpsstm' ); ?></p>
    <?php
}
?>