<?php

global $wpdb;
global $wpsstm_tracklist;
global $wpsstm_track;

add_filter( 'show_admin_bar','__return_false'); //hide admin bar
do_action( 'wpsstm-popup' );
do_action( 'wpsstm-tracklist-manager-popup' );
do_action( 'get_header', 'wpsstm-tracklist-manager-popup' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
//

$body_classes = array(
    'wpsstm-popup',
    'wpsstm-tracklist-manager-popup'
);

/*
playlists capabilities
*/
$post_type = wpsstm()->post_type_playlist;
$post_type_obj = get_post_type_object( $post_type );
$create_cap = $post_type_obj->cap->create_posts;
$edit_cap = $post_type_obj->cap->edit_posts;

?>

<!DOCTYPE html>
<html class="no-js" <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" >
    <?php wp_head(); ?>
</head>
<body <?php body_class($body_classes); ?>>  
    <?php

    if ( !get_current_user_id() ){ //not logge
        
        $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url($manager_redirect_url),__('here','wpsstm'));
        $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
        printf('<p class="wpsstm-notice">%s</p>',$wp_auth_text);

    }else{
        
        /*
        Track header if any
        */
        wpsstm_locate_template( 'track-header.php', true, false );

        if ( current_user_can($create_cap) ){

            ?>
            <form action="<?php echo $wpsstm_track->get_track_action_url('manage');?>" id="wpsstm-new-tracklist" method="post">
                <input name="wpsstm_manager_data[new_tracklist_title]" type="text" placeholder="<?php _e('Type to filter playlists or to create a new one','wpsstm');?>" class="wpsstm-fullwidth" />
                <?php echo $wpsstm_track->get_subtrack_hidden_form_fields();?>
                <input name="wpsstm_manager_action" type="hidden" value='init_tracklist' />
                <button type="submit" class="button button-primary wpsstm-icon-button">
                    <i class="fa fa-plus" aria-hidden="true"></i> <?php _e('New');?>
                </button>
            </form>
        <?php
        }
        
        if ( current_user_can($edit_cap) ){
            ?>
            <form action="<?php echo $wpsstm_track->get_track_action_url('manage');?>" id="wpsstm-toggle-tracklists" method="post">
                <?php


                //tracklists manager query
                $args = array(
                    'post_type' =>      $post_type,
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

                $args = apply_filters('wpsstm_tracklist_manager_query',$args);
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
                <?php echo $wpsstm_track->get_subtrack_hidden_form_fields();?>
                <button type="submit" class="button button-primary wpsstm-icon-button">
                    <?php _e('Save');?>
                </button>
                <input name="wpsstm_manager_action" type="hidden" value='toggle_tracklists' />
            </form>
            <?php
        }
    }
    ?>
    <?php
    //
    do_action( 'get_footer', 'wpsstm-tracklist-iframe' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
    wp_footer();
    //
    ?>
</body>
</html>