<?php

class WP_SoundSystem_Track{
    public $post_id = 0;

    public $title;
    public $artist;
    public $album;
    public $image;
    public $location;
    public $mbid = null; //set 'null' so we can check later (by setting it to false) if it has been requested
    public $duration; //in seconds
    public $sources = null; //set 'null' so we can check later (by setting it to false) it has been populated

    function __construct( $args = array() ){
        
        //has track ID
        if ( isset($args['post_id'] ) ){
            $track_id = $args['post_id'];
            if ( $post = get_post($track_id) ){
                $this->post_id = $track_id;
                $this->title = wpsstm_get_post_track($track_id);
                $this->artist = wpsstm_get_post_artist($track_id);
                $this->album = wpsstm_get_post_album($track_id);
                $this->mbid = wpsstm_get_post_mbid($track_id);
            }
        }elseif ( $this->artist && $this->title ){ //no track ID, try to auto-guess
            $this->post_id = wpsstm_get_post_id_by('track',$this->artist,$this->album,$this->title);
        }

        $args_default = $this->get_default();
        $args = wp_parse_args($args,$args_default);

        //only for keys that exists in $args_default
        foreach ((array)$args_default as $param=>$dummy){
            if ( !$args[$param] ) continue; //empty value
            $this->$param = $args[$param];
        }

    }
    
    function get_default(){
        return array(
            'post_id'       =>null,
            'title'         =>null,
            'artist'        =>null,
            'album'         =>null,
            'image'         =>null,
            'location'      =>null,
            'mbid'          =>null,
            'duration'      =>null,
            'sources'       =>null
        );
    }

    //TO FIX do we need this ?
    function array_export(){
        $defaults = $this->get_default();
        $export = array();
        foreach ((array)$defaults as $param=>$dummy){
            $export[$param] = $this->$param;
        }
        return array_filter($export);
    }
    
    /**
    http://www.xspf.org/xspf-v1.html#rfc.section.4.1.1.2.14.1.1
    */
    
    function get_array_for_xspf(){
        $xspf_track = array(
            'identifier'    => ( $this->mbid ) ? sprintf('https://musicbrainz.org/recording/%s',$this->mbid) : null,
            'title'         => $this->title,
            'creator'       => $this->artist,
            'album'         => $this->album
        );
        
        $xspf_track = array_filter($xspf_track);
        
        return apply_filters('wpsstm_get_track_xspf',$xspf_track,$this);
    }
    
    /**
    Used before inserting a new track
    Check if we can find a track that has the same track informations so we can avoid duplicates.
    Returns existing track post ID if found.
    **/
    
    function get_existing_track_id(){
        
        $post_id = null;
        
        $meta_query = array(
            //artist
            array(
                'key'   => wpsstm_artists()->metakey,
                'value' => $this->artist
            ),
            //track
            array(
                'key'   => wpsstm_tracks()->metakey,
                'value' => $this->title
            )
        );

        if ($this->album){
            $meta_query[] = array(
                'key'   => wpsstm_albums()->metakey,
                'value' => $this->album
            );
        }
        
        //TO FIX should we check for MBID ?
        if ($this->mbid){
            $meta_query[] = array(
                'key'   => wpsstm_mb()->mb_id_meta_name,
                'value' => $this->mbid
            );
        }
        
        $args = array(
            'post_type'     => wpsstm()->post_type_track,
            'post_status'   => 'any',
            'posts_per_page'    => 1,
            'fields'            => 'ids',
            'meta_query'        => $meta_query
        );
        
        $query = new WP_Query( $args );

        if ( count($query->posts) ){
            $post_id = $query->posts[0];
            wpsstm()->debug_log( array('post_id'=>$post_id,'meta_query'=>json_encode($meta_query)), "WP_SoundSystem_Track::get_existing_track_id()"); 
        }

        return $post_id;
        
    }
    
    function validate_track($strict = true){

        if ($strict){
            //keep only tracks having artist AND title
            $valid = ($this->artist && $this->title);
            //artists & title would probably not be equal
            if ( $valid && ($this->artist == $this->title) ) {
                $valid = false;
            }
        }else{
            //keep only tracks having artist OR title (Wizard)
            $valid = ($this->artist || $this->title);
        }

        return $valid;

    }

    function save_track(){
        
        if ( !$this->validate_track() ) return;

        $post_track_id = null;
        
        $meta_input = array(
            wpsstm_artists()->metakey           => $this->artist,
            wpsstm_tracks()->metakey            => $this->title,
            wpsstm_albums()->metakey            => $this->album,
            wpsstm_sources()->sources_metakey   => $this->sources,
        );
        
        if ( wpsstm()->get_options('musicbrainz_enabled') == 'on' ){		
            $meta_input[wpsstm_mb()->mb_id_meta_name] = $this->mbid;		
        }

        $meta_input = array_filter($meta_input);

        $post_track_args = array('meta_input' => $meta_input);

        if (!$this->post_id){ //not a track update

            //TO FIX should this rather be in WP_SoundSystem_Subtrack ?
            if ( $existing_track_id = $this->get_existing_track_id() ){ //we found a track that has the same details
                
                $post_track_id = $existing_track_id;

            }else{ //insert new track
                
                $post_track_new_args = array(
                    'post_type'     => wpsstm()->post_type_track,
                    'post_status'   => 'publish',
                    'post_author'   => get_current_user_id()
                );

                $post_track_new_args = wp_parse_args($post_track_new_args,$post_track_args);

                $post_track_id = wp_insert_post( $post_track_new_args );
                wpsstm()->debug_log( array('post_id'=>$post_track_id,'args'=>json_encode($post_track_new_args)), "WP_SoundSystem_Track::save_track() - post track inserted"); 
                
            }

        }else{ //is a track update
            
            $post_track_update_args = array(
                'ID'            => $this->post_id
            );
            
            $post_track_update_args = wp_parse_args($post_track_update_args,$post_track_args);
            
            $post_track_id = wp_update_post( $post_track_update_args );
            
            wpsstm()->debug_log( array('post_id'=>$post_track_id,'args'=>json_encode($post_track_update_args)), "WP_SoundSystem_Track::save_track() - post track updated"); 
        }

        if ( is_wp_error($post_track_id) ) return $post_track_id;
        
        $this->post_id = $post_track_id;

        return $this->post_id;
        
    }
    
    function delete_track($force_delete = false){
        $deleted = wp_delete_post( $this->post_id, $force_delete );
        if($deleted === false) return false;
        
        wpsstm()->debug_log( array('post_id',$this->post_id,'force_delete'=>$force_delete), "WP_SoundSystem_Track::delete_track()"); 
        return $deleted;
    }

    
    function musicbrainz(){
        //abord
        if( !$this->artist || !$this->title ) return;
        if( $this->mbid !== null ) return;
        
        //query
        $mzb_args = '"'.rawurlencode($this->title).'"';
        $mzb_args .= rawurlencode(' AND artist:');
        $mzb_args .= '"'.rawurlencode($this->artist).'"';

        //TO FIX album is ignored for the moment.
        /*
        if(!empty($this->album)){
            $mzb_args .= rawurlencode(' AND release:');
            $mzb_args .= '"'.rawurlencode($this->album).'"';
        }
        */
        $api_type = wpsstm_tracks()->mbtype;
        $api_response = wpsstm_mb()->get_musicbrainz_api_entry($api_type,null,$mzb_args);

        if (is_wp_error($api_response)) return;

        if ( $api_response['count'] && ($match = wpsstm_get_array_value(array('recordings',0),$api_response) ) ){ //WE'VE GOT A MATCH !!!

            //check score is high enough
            if($match['score']=70){

                $this->mbid = $match['id'];
                $this->title = $match['title'];
                $this->duration = wpsstm_get_array_value('length',$match);

                //artist
                $artists = wpsstm_get_array_value('artist-credit',$match);
                $artists_names_arr = array();

                foreach((array)$artists as $artist){
                    $obj = $artist['artist'];
                    $artists_names_arr[]=$obj['name'];
                }
                $this->artist = implode(' & ',$artists_names_arr);

                //album
                if ( $album = wpsstm_get_array_value('releases',0,$match) ){
                    $this->album = $album['title'];
                }

            }

        }
        
        return $api_response;
        
    }
    
    //useful eg. for transients
    function get_unique_id($prefix = null){
        $title = sanitize_title($prefix . $this->artist . $this->title . $this->album);
        return md5( $title );
    }

}

/**
For tracks that are attached to a tracklist (albums / playlists / live playlists)
**/

class WP_SoundSystem_Subtrack extends WP_SoundSystem_Track{

    public $tracklist_id = 0;
    public $subtrack_order = 0;
    
    function __construct( $args = array() ){

        //has parent ID
        if ( isset($args['tracklist_id']) ){
            $this->tracklist_id = $args['tracklist_id'];
        }

        parent::__construct($args);

    }
    
    function get_default(){
        $track_defaults = parent::get_default();
        
        $default = array(
            'tracklist_id'      => null,
            'subtrack_order'    => null
        );
        
        return wp_parse_args($default,$track_defaults);
    }
    
    function save_track(){
        
        if ( !$this->tracklist_id || !get_post($this->tracklist_id) ) return new WP_Error('tracklist_id',__("Tracklist ID not defined or does not exists",'wpsstm'));

        //save track
        $track_id = parent::save_track();
        if (!$track_id) return false;
        if ( is_wp_error($track_id) ) return $track_id;
        
        //save tracklist
        $tracklist = new WP_SoundSytem_Tracklist($this->tracklist_id);
        $subtrack_ids = $tracklist->get_subtracks_ids();
        $subtrack_ids[] = $track_id;
        $tracklist->set_subtrack_ids($subtrack_ids);

        //return track ID to match parent::save_track;
        return $track_id; 
        
    }

    function remove_subtrack(){
        global $wpdb;
        if (!$this->post_id) return;
        
        //get current subtrack IDs
        $tracklist = new WP_SoundSytem_Tracklist($this->tracklist_id);
        $subtrack_ids = $tracklist->get_subtracks_ids();

        //remove current value
        if(($key = array_search($this->post_id, $subtrack_ids)) !== false) {
            unset($subtrack_ids[$key]);
        }
        
        return $tracklist->set_subtrack_ids($subtrack_ids);
    }    
}
