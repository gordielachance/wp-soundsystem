<?php
global $wpdb;
global $wpsstm_tracklist;
global $wpsstm_track;

//tracklists manager query
$args = array(
    'post_type' =>      wpsstm()->post_type_playlist,
    'author' =>         get_current_user_id(),
    'posts_per_page' => -1,
    'orderby' =>        'title',
    'order'=>           'ASC'
);

//self playlists, allow any post stati
//TOUFIX TOUCHECK move in filter ?
if ( isset($args['author']) && ( $args['author'] === get_current_user_id() ) ){
    $args['post_status'] = array('publish','private','future','pending','draft');
}

$args = apply_filters('wpsstm_tracklist_list_query',$args);
$tracklist_query = new WP_Query( $args );

if ( $tracklist_query->have_posts() ) {

    ?>
    <ul class="tracklist-list">
        <?php
        while ( $tracklist_query->have_posts() ) {

            $tracklist_query->the_post();
            $wpsstm_tracklist->classes[] = 'tracklist-row';

            ?>
            <li class="<?php echo implode(' ',$wpsstm_tracklist->classes);?>">
                <span class="tracklist-row-action">
                    <?php

                    if ( $wpsstm_track->validate_track() === true ){ //track toggle action
                        $checked_playlist_ids = $wpsstm_track->get_in_tracklists_ids();
                        $checked = in_array($wpsstm_tracklist->post_id,(array)$checked_playlist_ids);
                        $old_value = ($checked) ? 1 : -1;
                        $checked_str = checked($checked,true,false);
                        ?>
                        <input name="wpsstm_manager_data[new_tids][<?php echo $wpsstm_tracklist->post_id;?>]" type="checkbox" value="1" <?php checked($checked,true);?> />
                        <input name="wpsstm_manager_data[old_tids][<?php echo $wpsstm_tracklist->post_id;?>]" type="hidden" value="<?php echo $old_value;?>" />
                        <?php
                    }
            
                    ?>
                </span>
                <span class="wpsstm-tracklist-title" itemprop="name" title="<?php echo get_the_title();?>">
                    <a href="<?php echo get_permalink($wpsstm_tracklist->post_id);?>"><?php echo get_the_title();?></a>
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
                    if ( $actions = $wpsstm_tracklist->get_tracklist_actions() ){
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