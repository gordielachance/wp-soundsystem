<?php
global $post;
global $wpsstm_track;
global $wpsstm_tracklist;
$tracklist_admin = get_query_var( WPSSTM_Core_Tracklists::$qvar_tracklist_admin );
?>

<div id="wpsstm-tracklist-admin" class="wpsstm-post-admin">
    <?php 
    if ( $actions = $wpsstm_tracklist->get_tracklist_links() ){
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
    }

    if ($tab_content){
        printf('<div id="wpsstm-tracklist-admin-%s" class="wpsstm-tracklist-admin">%s</div>',$tracklist_admin,$tab_content);
    }

    ?>

</div><!-- .wpsstm-post-admin -->