<?php

class WP_SoundSystem_Track{
    public $post_id = 0;

    public $title;
    public $artist;
    public $album;
    public $image;
    public $location;
    public $mbid = null;
    public $duration;
    public $did_lookup = false; // TO FIX
    
    function __construct( $args = array(), $track_id = null ){

        //has track ID
        if ( $track_id ){
            if ( $post = get_post($track_id) ){

                $this->post_id = $track_id;
                $this->title = wpsstm_get_post_track($track_id);
                $this->artist = wpsstm_get_post_artist($track_id);
                $this->album = wpsstm_get_post_album($track_id);
                $this->mbid = wpsstm_get_post_mbid($track_id);
            }
        }

        $args_default = $this->get_default();
        $args = wp_parse_args($args,$args_default);

        //only for keys that exists in $args_default
        foreach ((array)$args_default as $param=>$dummy){
            if ( !$args[$param] ) continue; //empty value
            $this->$param = apply_filters('wpsstm_get_track_'.$param,$args[$param]);
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
        }
        
        wpsstm()->debug_log( array('post_id'=>$post_id,'meta_query'=>json_encode($meta_query)), "WP_SoundSystem_Track::get_existing_track_id()"); 
        
        return $post_id;
        
    }

    function save_track(){

        $post_track_id = null;
        
        $meta_input = array(
            wpsstm_artists()->metakey       => $this->artist,
            wpsstm_tracks()->metakey        => $this->title,
            wpsstm_albums()->metakey        => $this->album
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
        if($deleted !== false){
            wpsstm()->debug_log( array('post_id',$this->post_id,'force_delete'=>$force_delete), "WP_SoundSystem_Track::delete_track()"); 
            unset($this->tracks[$key]);
        }
    }

    
    function musicbrainz(){
        //abord
        if( !$this->artist || !$this->title ) return;
        if( $this->mbid ) return;
        
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
        
        $this->did_lookup = true;

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

}

/**
For tracks that are attached to a tracklist (albums / playlists / live playlists)
**/

class WP_SoundSystem_Subtrack extends WP_SoundSystem_Track{
    /*
    $subtrack_id is the meta_id of the meta that contains informations related to the tracklist entry.
    - post_id of this meta is the post track ID
    - meta_key will contain the ID of the parent post (eg. wpsstm_tracklist_11180)
    - meta_value will be used to order the tracklist.  It can be either a number (for regular playlists) or a timestamp (for live playlists)
    */
    public $subtrack_id = 0;
    public $subtrack_parent = 0;
    public $subtrack_order = 0;
    
    function __construct( $args = array(), $subtrack_id = null, $tracklist_id = null ){
        
        $track_id = null;
        
        //has parent ID
        if ( $tracklist_id ){
            $this->subtrack_parent = $tracklist_id;
        }
        
        //has subtrack ID
        if ( $subtrack_id ){
            $subtrack_meta = get_metadata_by_mid( 'post', $subtrack_id );
            if ($subtrack_meta){
                
                $track_id = $subtrack_meta->post_id;
                
                $this->subtrack_id = $subtrack_meta->meta_id;
                $this->subtrack_parent = str_replace('wpsstm_tracklist_','',$subtrack_meta->meta_key);
                $this->subtrack_order = $subtrack_meta->meta_value;

            }
        }
        
        parent::__construct($args,$track_id);

    }
    
    function get_default(){
        $track_defaults = parent::get_default();
        
        $default = array(
            'subtrack_id'          => null,
            'subtrack_parent'      => null,
            'subtrack_order'       => null
        );
        
        return wp_parse_args($default,$track_defaults);
    }
    
    function save_track(){
        
        if ( !$this->subtrack_parent || !get_post($this->subtrack_parent) ) return new WP_Error('subtrack_parent',__("Subtrack parent not defined or does not exists",'wpsstm'));

        //save track
        $track_id = parent::save_track();
        if (!$track_id) return false;
        if ( is_wp_error($track_id) ) return $track_id;
        
        //save order
        $subtrack_id = $this->save_subtrack_order();
        if (!$subtrack_id) return false;
        if ( is_wp_error($subtrack_id) ) return $subtrack_id;

        //return track ID to match parent::save_track;
        return $track_id; 
        
    }
    
    /**
    Update or add the subtrack meta key.  
    Returns the meta ID.
    **/
    
    function save_subtrack_order(){
        global $wpdb;
        
        if (!$this->post_id) return new WP_Error('no_post_track',__("Track does not exists",'wpsstm'));
        
        if ( !$this->subtrack_parent || !get_post($this->subtrack_parent) ) return new WP_Error('subtrack_parent',__("Subtrack parent not defined or does not exists",'wpsstm'));

        $success = false;

        if ($this->subtrack_id){ //meta already exists
            
            $update_order = $wpdb->update( 
                $wpdb->postmeta, 
                array( //data
                    'meta_value' => $this->subtrack_order 
                ), 
                array( 'meta_id' => $this->subtrack_id ) //where
            );
            
            $success = ($update_order !== false);
            
            wpsstm()->debug_log( array('subtrack_id'=>$this->subtrack_id,'success'=>$success,'subtrack_order'=>$this->subtrack_order), "WP_SoundSystem_Subtrack::save_subtrack_order() - meta updated");
            
            if ($success) return $this->subtrack_id;
            
        }else{
            $subtrack_metakey = wpsstm_get_tracklist_entry_metakey($this->subtrack_parent);
            $new_meta_id = add_post_meta($this->post_id, $subtrack_metakey, $this->subtrack_order);
            if ($new_meta_id){
                $this->subtrack_id = $new_meta_id;
                
                wpsstm()->debug_log( array('post_id'=>$this->post_id,'subtrack_id'=>$this->subtrack_id,'subtrack_parent'=>$this->subtrack_parent,'subtrack_order'=>$this->subtrack_order), "WP_SoundSystem_Subtrack::save_subtrack_order() - meta inserted"); 
                
                return $this->subtrack_id;
                
            }
        }
        
    }
    
    function remove_subtrack(){
        global $wpdb;
        if (!$this->subtrack_id) return;
        return $wpdb->delete( $wpdb->postmeta, array( 'meta_id' => $this->subtrack_id ) );
    }
    
}

add_filter('wpsstm_get_track_artist','strip_tags');
add_filter('wpsstm_get_track_artist','urldecode');
add_filter('wpsstm_get_track_artist','htmlspecialchars_decode');
add_filter('wpsstm_get_track_artist','trim');

add_filter('wpsstm_get_track_title','strip_tags');
add_filter('wpsstm_get_track_title','urldecode');
add_filter('wpsstm_get_track_title','htmlspecialchars_decode');
add_filter('wpsstm_get_track_title','trim');

add_filter('wpsstm_get_track_album','strip_tags');
add_filter('wpsstm_get_track_album','urldecode');
add_filter('wpsstm_get_track_album','htmlspecialchars_decode');
add_filter('wpsstm_get_track_album','trim');

add_filter('wpsstm_get_track_image','strip_tags');
add_filter('wpsstm_get_track_image','urldecode');
add_filter('wpsstm_get_track_image','trim');

add_filter('wpsstm_get_track_location','strip_tags');
add_filter('wpsstm_get_track_location','urldecode');
add_filter('wpsstm_get_track_location','trim');