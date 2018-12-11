<?php
global $wpsstm_tracklist;

//handle checkbox
add_filter('wpsstm_tracklists_manager_row_checked',array('WPSSTM_Track','tracklists_manager_track_checkbox'),10,2);

/*
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
$query = new WP_Query( $args );

print_r($args);
*/

if ( have_posts() ) {
    ?>
    <ul id="tracklists-manager">
        <?php
        while ( have_posts() ) {

            the_post();
            $wpsstm_tracklist = new WPSSTM_Post_Tracklist($post->ID);
            
            //TO FIX TO CHECK or use get_tracklist_class() here ?
            $row_classes = array('tracklist-row','wpsstm-tracklist');
            if ($wpsstm_tracklist->tracklist_type=='live') $row_classes[] = 'wpsstm-live-tracklist';

            ?>
            <li class="<?php echo implode(' ',$row_classes);?>">
                <span class="tracklist-row-action">
                    <?php
                    //checked
                    $checked = apply_filters('wpsstm_tracklists_manager_row_checked',false,$wpsstm_tracklist);
                    $checked_str = checked($checked,true,false);

                    printf('<input name="tracklists[%s]" type="checkbox" value="on" %s />',$wpsstm_tracklist->post_id,$checked_str);

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
?>