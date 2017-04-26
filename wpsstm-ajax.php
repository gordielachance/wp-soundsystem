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

function wpsstm_ajax_tracklist_save_row(){
    $result = array(
        'input'     => $_REQUEST,
        'tracks'    => null,
        'output'    => null,
        'message'   => null,
        'success'   => false
    );
    
    $tracks = array();
    $tracklist_id = isset($_REQUEST['uri_args']['post']) ? $_REQUEST['uri_args']['post'] : null;
    $tracks[] = array(
        'post_id'           => isset($_REQUEST['uri_args']['subtrack_id']) ? $_REQUEST['uri_args']['subtrack_id'] : null,
        'track_order'       => isset($_REQUEST['order']) ? $_REQUEST['order'] : null,
        'artist'            => isset($_REQUEST['artist']) ? $_REQUEST['artist'] : null,
        'title'             => isset($_REQUEST['track']) ? $_REQUEST['track'] : null,
        'album'             => isset($_REQUEST['album']) ? $_REQUEST['album'] : null,
        'mbid'              => isset($_REQUEST['mbid']) ? $_REQUEST['mbid'] : null,
    );

    $tracklist = new WP_SoundSytem_Tracklist($tracklist_id);
    $tracklist->add($tracks);
    
    $result['tracks'] = $tracklist->tracks; //for debug

    if ( count($tracklist->tracks) ){

        require wpsstm()->plugin_dir . 'classes/wpsstm-tracklist-admin-table.php';
        $entries_table = new WP_SoundSytem_TracksList_Admin_Table();
        $entries_table->items = $tracklist->tracks;
        $entries_table->prepare_items();

        ob_start();
        $item = end($entries_table->items);
        $entries_table->single_row_columns( $item );
        $result['output'] = ob_get_clean();

        $result['success'] = true;
        
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

        $result['success'] = $tracklist->set_subtrack_ids($subtracks_order);
        
    }
    
    header('Content-type: application/json');
    echo json_encode($result);
    die(); 
}

//artist
add_action('wp_ajax_wpsstm_artist_lookup', 'wpsstm_ajax_artist_lookup');
add_action('wp_ajax_nopriv_wpsstm_artist_lookup', 'wpsstm_ajax_artist_lookup');

//tracklist
add_action('wp_ajax_wpsstm_tracklist_save_row', 'wpsstm_ajax_tracklist_save_row');
add_action('wp_ajax_nopriv_wpsstm_tracklist_save_row', 'wpsstm_ajax_tracklist_save_row');

add_action('wp_ajax_wpsstm_tracklist_update_order', 'wpsstm_ajax_tracklist_update_order');
add_action('wp_ajax_nopriv_wpsstm_tracklist_update_order', 'wpsstm_ajax_tracklist_update_order');


?>