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

    $track_action       = isset($_REQUEST['track_action']) ? $_REQUEST['track_action'] : null;
    $track_args = isset($_REQUEST['track']) ? $_REQUEST['track'] : null;
    $track_order        = isset($_REQUEST['track_order']) ? $_REQUEST['track_order'] : null;

    $track = new WP_SoundSystem_Subtrack($track_args);
    $result['track'] = $track;
    $success = false;
    
    switch($track_action){
        case 'save':
            if ( $post_id = $track->save_track() ){
                
                if ( is_wp_error($post_id) ){
                    $result['message'] = $post_id->get_error_message();
                }else{
                    wpsstm()->debug_log("ajax save:"); 
                    wpsstm()->debug_log($post_id); 

                    $tracklist = new WP_SoundSytem_Tracklist($track_args['tracklist_id']);
                    $tracklist->add(array($track));

                    require wpsstm()->plugin_dir . 'classes/wpsstm-tracklist-admin-table.php';
                    $entries_table = new WP_SoundSytem_TracksList_Admin_Table();
                    $entries_table->items = $tracklist->tracks;
                    $entries_table->prepare_items();

                    ob_start();
                    $item = end($entries_table->items);
                    $item->subtrack_order = $track_order;

                    $entries_table->single_row_columns( $item );
                    $result['new_html'] = ob_get_clean();

                    $result['success'] = true;
                    $result['post_id'] = $post_id;

                    $result['output'] = $success;
                }
                


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

function wpsstm_ajax_player_get_provider_sources(){
    $result = array(
        'input'     => $_POST,
        'message'   => null,
        'new_html'  => null,
        'success'   => false
    );
    
    $args = array(
        'title'     => ( isset($_POST['track']['title']) ) ? $_POST['track']['title'] : null,
        'artist'    => ( isset($_POST['track']['artist']) ) ? $_POST['track']['artist'] : null,
        'album'     => ( isset($_POST['track']['album']) ) ? $_POST['track']['album'] : null
    );

    $track = $result['track'] = new WP_SoundSystem_Track($args);
    
    if ( $bt = wpsstm_player()->get_track_button($track,true) ){
        $result['new_html'] = $bt;
        $result['success'] = true;
    }

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

//player
add_action('wp_ajax_wpsstm_player_get_provider_sources', 'wpsstm_ajax_player_get_provider_sources');
add_action('wp_ajax_nopriv_wpsstm_player_get_provider_sources', 'wpsstm_ajax_player_get_provider_sources');

?>