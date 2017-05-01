<?php

class WP_SoundSytem_Tracklist{
    var $post_id = 0; //tracklist ID (can be an album, playlist or live playlist)
    var $tracks = array();
    var $tracks_count = 0;

    function __construct($post_id = null ){
        
        if ($post_id){
            $this->post_id = $post_id;
        }

    }
    
    function load_subtracks(){

        $post_type = get_post_type($this->post_id);
        $subtracks = array();
 
        //get tracklist metas
        $subtrack_ids = $this->get_subtracks_ids();

        foreach ($subtrack_ids as $subtrack_id){
            $subtrack = array(
                'post_id'  => $subtrack_id
            );
            $subtracks[] = $subtrack;
        }
        
        $this->add($subtracks);
    }
    
    
    /*
    Return the subtracks IDs for a tracklist.
    */

    function get_subtracks_ids(){
        global $wpdb;

        $ordered_ids = get_post_meta($this->post_id,'wpsstm_subtrack_ids',true);
        $ordered_ids = array_unique((array)$ordered_ids);
        
        if ( empty($ordered_ids) ) return;

        //validate those IDs, we must be sure they are tracks.
        $args = array(
            'post_type'         => array(wpsstm()->post_type_track),
            'post_status'       => 'any',
            'posts_per_page'    => -1,
            'fields'            => 'ids',
            'post__in'          => $ordered_ids
        );

        $query = new WP_Query( $args );
        $post_ids = $query->posts;
        
        foreach($ordered_ids as $key=>$ordered_id){
            if (!in_array($ordered_id,$post_ids)) unset($ordered_ids[$key]);
        }

        return $ordered_ids;
        
    }
    
    function set_subtrack_ids($ordered_ids){

        $ordered_ids = array_filter($ordered_ids, function($var){return !is_null($var);} ); //remove nuls if any
        $ordered_ids = array_unique($ordered_ids);
        return update_post_meta($this->post_id,'wpsstm_subtrack_ids',$ordered_ids);
    }
    
    function add($tracks){
        
        //force array
        if ( !is_array($tracks) ){
            $tracks = array($tracks);
        }

        foreach ($tracks as $track){
            
            if ( !is_a($track, 'WP_SoundSystem_Subtrack') ){
                if ( is_array($track) ){
                    $track = new WP_SoundSystem_Subtrack($track);
                }
            }
            
            //set tracklist ID
            $track->tracklist_id = $this->post_id;
            
            //increment count
            $this->tracks_count++;
            $track->subtrack_order = $this->tracks_count;
            
            $this->tracks[] = $track;
        }

    }

    function validate_tracks($strict = true){
        
        //array unique
        $pending_tracks = array_unique($this->tracks, SORT_REGULAR);
        $valid_tracks = array();
        
        foreach($pending_tracks as $track){
            if ( !$track->validate_track($strict) ) continue;
            $valid_tracks[] = $track;
        }
        
        $this->tracks = $valid_tracks;

    }

    //TO FIX do we need this ?
    function array_export(){
        $export = array();
        foreach ($this->tracks as $track){
            $export[] = $track->array_export();
        }

        return array_filter($export);
    }
    
    function save_subtracks(){
        foreach($this->tracks as $key=>$track){
            $saved = $track->save_track();
        }
    }
    
    function remove_subtracks(){
        foreach($this->tracks as $key=>$track){
            $track->remove_subtrack();
        }
    }
    
    function delete_subtracks(){
        foreach($this->tracks as $key=>$track){
            if ( $track->delete_track() ){
                unset($this->tracks[$key]);
            }
        }
    }
    
    /**
    Read-only tracklist table
    **/
    function get_tracklist_table($admin = false){
        
        if ($admin){
            require wpsstm()->plugin_dir . 'classes/wpsstm-tracklist-admin-table.php';
            $tracklist_table = new WP_SoundSytem_TracksList_Admin_Table();
            $tracklist_table->items = $this->tracks;
        }else{
            require_once wpsstm()->plugin_dir . 'classes/wpsstm-tracklist-table.php';
            $tracklist_table = new WP_SoundSytem_TracksList_Table($this);
        }

        ob_start();
        $tracklist_table->prepare_items();
        $tracklist_table->display();
        return ob_get_clean();
    }


}