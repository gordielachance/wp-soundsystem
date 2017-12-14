<?php
global $list_tracklists_query;
$query = $list_tracklists_query;

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

            ?>
            <li class="tracklist-row">
                <?php do_action('wpsstm_before_tracklist_row',$wpsstm_tracklist);?>
                <span>
                    <span class="wpsstm-tracklist-title">
                        <?php 
                        $title = ( $wpsstm_tracklist->title ) ? $wpsstm_tracklist->title : sprintf(__('(playlist #%d)','wpsstm'),$wpsstm_tracklist->post_id);
                        ?>
                        <a href="<?php echo get_permalink($wpsstm_tracklist->post_id);?>"><?php echo $title;?></a>
                    </span>
                    <strong class="wpsstm-tracklist-post-state">
                        <?php
                            $post_status_obj = get_post_status_object( get_post_status() );
                            echo ' — ' . $post_status_obj->label;
                        ?>
                    </strong>
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
        <!-- put pagination functions here -->
        <?php wp_reset_postdata(); ?>
    </ul>
    <?php
}else{
    ?>
    <p><?php _e( 'Sorry, no tracklists matching those criteria.','wpsstm' ); ?></p>
    <?php
}
?>