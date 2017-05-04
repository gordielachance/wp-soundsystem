<?php

function wpsstm_ajax_artist_lookup(){
    
    if ( !isset($_REQUEST['q']) ) return;
    $search = trim($_REQUEST['q']);
    
    $artists_wp = wpsstm_get_post_id_by('artist',$search);
    $artists = array();
    foreach((array)$artists_wp as $post){
        $artists[] = wpsstm_get_post_artist($post->ID);
    }

}

function wpsstm_ajax_tracklist_row_action(){
    
    $result = array(
        'input'     => $_REQUEST,
        'message'   => null,
        'new_html'  => null,
        'success'   => false
    );
    
    $result['tracklist_id'] =   $tracklist_id =     isset($_REQUEST['tracklist_id']) ? $_REQUEST['tracklist_id'] : null;
    $result['post_id'] =        $post_id =          isset($_REQUEST['track_id']) ? $_REQUEST['track_id'] : null;
    $result['action'] =         $action =           isset($_REQUEST['track_action']) ? $_REQUEST['track_action'] : null;
    $result['subtrack_order'] = $subtrack_order =   isset($_REQUEST['subtrack_order']) ? $_REQUEST['subtrack_order'] : null;

    $track_args = array(
        'tracklist_id'      => $tracklist_id,
        'post_id'           => $post_id,
        'artist'            => isset($_REQUEST['artist']) ? $_REQUEST['artist'] : null,
        'title'             => isset($_REQUEST['track']) ? $_REQUEST['track'] : null,
        'album'             => isset($_REQUEST['album']) ? $_REQUEST['album'] : null,
        'mbid'              => isset($_REQUEST['mbid']) ? $_REQUEST['mbid'] : null,
        'source_urls'       => isset($_REQUEST['source_urls']) ? $_REQUEST['source_urls'] : null,
    );
    
    $track = new WP_SoundSystem_Subtrack($track_args);
    $result['track'] = $track;
    $success = false;
    
    switch($action){
        case 'save':
            if ( $post_id = $track->save_track() ){

                $tracklist = new WP_SoundSytem_Tracklist($tracklist_id);
                $tracklist->add(array($track));

                require wpsstm()->plugin_dir . 'classes/wpsstm-tracklist-admin-table.php';
                $entries_table = new WP_SoundSytem_TracksList_Admin_Table();
                $entries_table->items = $tracklist->tracks;
                $entries_table->prepare_items();

                ob_start();
                $item = end($entries_table->items);
                $item->subtrack_order = $subtrack_order;
                
                $entries_table->single_row_columns( $item );
                $result['new_html'] = ob_get_clean();

                $result['success'] = true;
                $result['post_id'] = $post_id;
                
                $result['output'] = $success;

            }
        break;
        case 'remove':
            if ( $success = $track->remove_subtrack() ){
                $result['success'] = true;
                $result['output'] = $success;
            }
        break;
        case 'delete':
            if ( $success = $track->delete_track() ){
                $result['success'] = true;
                $result['output'] = $success;
            }
        break;
    }

    echo json_encode($result);
    die();

}

function wpsstm_ajax_tracklist_update_order(){
    $result = array(
        'message'   => null,
        'success'   => false,
        'input'     => $_POST
    );
    
    $result['post_id']  =           $post_id =          ( isset($_POST['post_id']) ) ? $_POST['post_id'] : null;
    $result['subtracks_order']   =  $subtracks_order =  ( isset($_POST['subtracks_order']) ) ? $_POST['subtracks_order'] : null;

    if ( $subtracks_order && $post_id ){
        
        //populate a tracklist with the selected tracks
        $tracklist = new WP_SoundSytem_Tracklist($post_id);
        $tracklist->load_subtracks();
        $result['tracklist'] = $tracklist;

        //TO FIX TO REMOVE $result['success'] = $tracklist->set_subtrack_ids($subtracks_order);
        
    }
    
    header('Content-type: application/json');
    echo json_encode($result);
    die(); 
}

//artist
add_action('wp_ajax_wpsstm_artist_lookup', 'wpsstm_ajax_artist_lookup');
add_action('wp_ajax_nopriv_wpsstm_artist_lookup', 'wpsstm_ajax_artist_lookup');

//rows
add_action('wp_ajax_wpsstm_tracklist_row_action', 'wpsstm_ajax_tracklist_row_action');
add_action('wp_ajax_nopriv_wpsstm_tracklist_row_action', 'wpsstm_ajax_tracklist_row_action');

//order
//add_action('wp_ajax_wpsstm_tracklist_update_order', 'wpsstm_ajax_tracklist_update_order');
//add_action('wp_ajax_nopriv_wpsstm_tracklist_update_order', 'wpsstm_ajax_tracklist_update_order');


?>