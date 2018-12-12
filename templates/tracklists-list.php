<?php
global $wpdb;
global $wpsstm_tracklist;
global $wpsstm_track;

//get logged user static playlists
$args = array(
    'post_type' =>      wpsstm()->post_type_playlist,
    'author' =>         get_current_user_id(),
    'posts_per_page' => -1,
    'orderby' =>        'title',
    'order'=>           'ASC'
);

//self playlists, allow any post stati
if ( isset($args['author']) && ( $args['author'] == get_current_user_id() ) ){
    $args['post_status'] = array('publish','private','future','pending','draft');
}

$args = apply_filters('wpsstm_tracklists_manager_query',$args);
$manager_query = new WP_Query( $args );

if ( $manager_query->have_posts() ) {
    ?>
    <ul id="tracklists-manager">
        <?php
        while ( $manager_query->have_posts() ) {

            $manager_query->the_post();
            
            //TO FIX TO CHECK or use get_tracklist_class() here ?
            $row_classes = array('tracklist-row','wpsstm-tracklist');
            if ($wpsstm_tracklist->tracklist_type=='live') $row_classes[] = 'wpsstm-live-tracklist';

            ?>
            <li class="<?php echo implode(' ',$row_classes);?>">
                <span class="tracklist-row-action">
                    <?php

                    if ( $wpsstm_track->validate_track() ){ //track toggle action
                        $checked_playlist_ids = $wpsstm_track->get_in_tracklists_ids();
                        $checked = in_array($wpsstm_tracklist->post_id,(array)$checked_playlist_ids);
                        $checked_str = checked($checked,true,false);
                        ?>
                        <input name="wpsstm_tracklists_manager_batch[<?php echo $wpsstm_tracklist->post_id;?>]" type="radio" value="1" <?php checked($checked,true);?> /><label>+</label>
                        <input name="wpsstm_tracklists_manager_batch[<?php echo $wpsstm_tracklist->post_id;?>]" type="radio" value="-1" /><label>-</label>
                        <?php
                    }
            
                    ?>
                </span>
                <span class="wpsstm-tracklist-title" itemprop="name" title="<?php echo $wpsstm_tracklist->get_title();?>">
                    <a href="<?php echo get_permalink($wpsstm_tracklist->post_id);?>"><?php echo $wpsstm_tracklist->get_title();?></a>
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
                    if ( $actions = $wpsstm_tracklist->get_tracklist_links() ){
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
}else{
    ?>
    <p class="wpsstm-notice"><?php _e( 'Sorry, no tracklists matching those criteria.','wpsstm' ); ?></p>
    <?php
}
wp_reset_postdata();
?>