<?php
global $post;
global $wpsstm_track;
global $wpsstm_tracklist;
$tracklist_admin = get_query_var( WPSSTM_Core_Tracklists::$qvar_tracklist_admin );
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
        case 'debug':
            
            if ( isset($_REQUEST['delete_log']) ){
                $wpsstm_tracklist->delete_log();
            }
            
            $log_file = $wpsstm_tracklist->get_tracklist_log_path();
            
            if ( file_exists($log_file) ){
                printf('<p><small>%s</small></p>',$log_file);

                //delete log
                $url = add_query_arg(array('delete_log'=>1),$wpsstm_tracklist->get_tracklist_admin_url('debug'));
                printf('<p><a class="button" href="%s">%s</a></p>',$url,__('Delete log','wpsstm'));

                $debug = file_get_contents($log_file);
                printf('<xmp>%s</xmp>',$debug);
            }else{
                
                $debug = __('No log yet for this tracklist.','wpsstm');
                printf('<xmp>%s</xmp>',$debug);
                
            }
            


        break;
    }

    if ($tab_content){
        printf('<div id="wpsstm-tracklist-admin-%s" class="wpsstm-tracklist-admin">%s</div>',$tracklist_admin,$tab_content);
    }

    ?>

</div><!-- .wpsstm-post-admin -->