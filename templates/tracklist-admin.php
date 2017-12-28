<?php
global $post;
global $wpsstm_track;
global $wpsstm_tracklist;
$tracklist_admin = get_query_var( wpsstm_tracklists()->qvar_tracklist_admin );
?>

<div id="wpsstm-tracklist-admin" class="wpsstm-post-admin">
    <?php 
    if ( $actions = $wpsstm_tracklist->get_tracklist_links('popup') ){
        $list = get_actions_list($actions,'tracklist');
        echo $list;
    }

    $tab_content = null;

    switch($tracklist_admin){
        case 'share':

            $text = __("Use this link to share this playlist:","wpsstm");
            $link = get_permalink($wpsstm_tracklist->post_id);
            $tab_content = sprintf('<div><p>%s</p><p class="wpsstm-notice">%s</p></div>',$text,$link);

        break;
        case 'new-subtrack':
            //TOFIXGGG : create subtrack then redirect to it ???
            ?>
            <form action="<?php echo esc_url($wpsstm_tracklist->get_tracklist_admin_url($tracklist_admin));?>" method="POST">
                <?php wpsstm_locate_template( 'track-admin-edit.php',true );?>
                <input type="hidden" name="wpsstm-tracklist-popup-action" value="<?php echo $tracklist_admin;?>" />
                <input type="hidden" name="wpsstm-tracklist-id" value="<?php echo $wpsstm_tracklist->post_id;?>" />
                <?php wp_nonce_field( sprintf('wpsstm_tracklist_%s_new_track_nonce',$wpsstm_tracklist->post_id), 'wpsstm_tracklist_new_track_nonce', true );?>
            </form>
            <?php
        break;
    }

    if ($tab_content){
        printf('<div id="wpsstm-tracklist-admin-%s" class="wpsstm-tracklist-admin">%s</div>',$tracklist_admin,$tab_content);
    }

    ?>

</div><!-- .wpsstm-post-admin -->