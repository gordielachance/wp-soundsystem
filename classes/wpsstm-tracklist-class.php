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
                'subtrack_id'  => $subtrack_id
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

        $metakey = wpsstm_get_tracklist_entry_metakey($this->post_id);
        $query = $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE `meta_key` = '%s' ORDER BY $wpdb->postmeta.meta_value ASC", $metakey );
        return $wpdb->get_col( $query );
    }
    
    function add($tracks){
        
        //force array
        if ( !is_array($tracks) ){
            $tracks = array($tracks);
        }

        foreach ($tracks as $track){
            
            if ( !is_a($track, 'WP_SoundSystem_Subtrack') ){
                if ( is_array($track) ){
                    $subtrack_id = ( isset($track['subtrack_id']) ) ? $track['subtrack_id'] : null;
                    $track = new WP_SoundSystem_Subtrack($track,$subtrack_id,$this->post_id);
                }
            }
            
            //increment count
            $this->tracks_count++;
            $track->subtrack_order = $this->tracks_count;
            
            $this->tracks[] = $track;
        }

    }

    function validate_tracks($strict = true){
        
        //array unique
        $this->tracks = array_unique($this->tracks, SORT_REGULAR);
        
        if ($strict){
            //keep only tracks having artist AND title
            $this->tracks = array_filter(
                $this->tracks,
                function ($e) {
                    return ($e->artist && $e->title);
                }
            );
        }else{
            //keep only tracks having artist OR title (Wizard)
            $this->tracks = array_filter(
                $this->tracks,
                function ($e) {
                    return ($e->artist || $e->title);
                }
            );
        }

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
    
    function save_subtracks_order($ordered_ids = null){
        
        foreach( $this->tracks as $track ){
            
            if ($ordered_ids){
                $new_order = array_search($track->subtrack_id, $ordered_ids);
                $track->subtrack_order = $new_order + 1; //avoid null
            }

            $track->save_subtrack_order();
        }
        
        return true;
        
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