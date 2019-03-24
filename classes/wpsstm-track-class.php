<?php


class WPSSTM_Track{
    public $post_id = null;
    
    public $title;
    public $artist;
    public $album;
    public $duration; //in seconds
    public $mbid = null; //set 'null' so we can check later (by setting it to false) if it has been requested
    public $spotify_id = null;
    
    public $image_url;
    public $location;
    
    var $source;
    public $sources = null; //set 'null' so we can check later (by setting it to false) it has been populated
    var $current_source = -1;
    var $source_count = 0;
    var $in_source_loop = false;
    
    var $tracklist;
    
    ///
    public $subtrack_id = null;
    public $position = 0;
    public $subtrack_time = null;
    public $from_tracklist = null;
    
    public $notices = array();
    
    public $autosourced = null;
    
    function __construct( $post_id = null, $tracklist = null ){
        
        /*
        Tracklist
        */
        $this->tracklist = new WPSSTM_Post_Tracklist();

        //has track ID
        if ( $track_id = intval($post_id) ) {
            $this->populate_track_post($track_id);
        }
        
        if ($tracklist){
            if ( is_a($tracklist,'WPSSTM_Post_Tracklist') ){
                $this->tracklist = $tracklist;
            }elseif( $tracklist_id = intval($tracklist) ){
                $this->tracklist = new WPSSTM_Post_Tracklist($tracklist_id);
            }
        }

    }

    function populate_track_post($track_id){
        
        if ( get_post_type($track_id) != wpsstm()->post_type_track ){
            return new WP_Error( 'wpsstm_invalid_track_entry', __("This is not a valid track entry.",'wpsstm') );
        }
        $this->post_id      = $track_id;
        $this->title        = wpsstm_get_post_track($this->post_id);
        $this->artist       = wpsstm_get_post_artist($this->post_id);
        $this->album        = wpsstm_get_post_album($this->post_id);
        $this->image_url    = wpsstm_get_post_image_url($this->post_id);
        $this->duration     = wpsstm_get_post_length($this->post_id);
        $this->autosourced  = get_post_meta( $this->post_id, WPSSTM_Core_Sources::$autosource_time_metakey, true );
        
        //TOUFIX this should be hooked ?
        $this->mbid         = wpsstm_get_post_mbid($this->post_id);
        $this->spotify_id   = wpsstm_get_post_spotify_id($this->post_id);
        
    }
    
    function populate_subtrack($subtrack_id){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        $query = $wpdb->prepare("SELECT * FROM `$subtracks_table` WHERE ID = %s",$subtrack_id);
        $subtrack = $wpdb->get_row($query);
        if (!$subtrack) return new WP_Error( 'wpsstm_invalid_subtrack_entry', __("This is not a valid subtrack entry.",'wpsstm') );

        //track
        if ($track_id = $subtrack->track_id){
            $success = $this->populate_track_post($track_id);
            if ( is_wp_error($success) ) return $success;
        }else{
            $this->title =  $subtrack->title;
            $this->artist = $subtrack->artist;
            $this->album =  $subtrack->album;
        }
        
        //tracklist
        if ($tracklist_id = $subtrack->tracklist_id){
            $this->tracklist = new WPSSTM_Post_Tracklist($tracklist_id);
        }

        //subtrack-specific
        $this->subtrack_id =    $subtrack->ID;
        $this->subtrack_time =  $subtrack->time;
        $this->from_tracklist = $subtrack->tracklist_id;
        $this->position =       $subtrack->track_order;
    }
    
    function from_array( $args ){
        
        if ( !is_array($args) ) return;

        //set properties from args input
        foreach ($args as $key=>$value){

            switch($key){
                case 'tracklist_id';
                    $this->tracklist = new WPSSTM_Post_Tracklist($value);
                break;
                case 'source_urls':
                    
                    $sources = array();
                    foreach((array)$value as $source_url){
                        $source = array(
                            'permalink_url' => $source_url,
                        );
                        $sources[] = $source;
                    }
                    
                    $this->add_sources($sources);
                break;
                case 'album';
                   if ($value == '_') continue;
                default:
                    if ( !property_exists($this,$key) )  continue;
                    if ( !isset($args[$key]) ) continue; //value has not been set
                    $this->$key = $value;
                break;
            }

        }

        //subtrack or track id ?
        if ($this->subtrack_id){
            return $this->populate_subtrack($this->subtrack_id);
        }elseif ( $this->post_id ){
            return $this->populate_track_post($this->post_id);
        }
    }
    
    /*
    Query tracks (IDs) that have the same artist + title (+album if set)
    */
    
    function get_track_duplicates(){
        
        $valid = $this->validate_track();
        if ( is_wp_error($valid) ) return $valid;
        
        $query_args = array(
            'post_type' =>      wpsstm()->post_type_track,
            'post_status' =>    'any',
            'posts_per_page'=>  -1,
            'fields' =>         'ids',
            'lookup_artist' =>  $this->artist,
            'lookup_track' =>   $this->title,
            'lookup_album' =>   $this->album
        );

        if ($this->post_id){
            $query_args['post__not_in'] = array($this->post_id);
        }

        $query = new WP_Query( $query_args );        
        return $query->posts;

    }
    
    /*
    Get the post ID for this track if it already exists in the database; and populate its data
    */
    
    function local_track_lookup(){
        if ( $this->post_id ) return;
        if ( $this->validate_track() !== true ) return;

        if ( $duplicates = $this->get_track_duplicates() ){
            $this->post_id = $duplicates[0];
            //$this->track_log( json_encode($this->to_array(),JSON_UNESCAPED_UNICODE),'Track found in the local database');
        }
        
        return $this->post_id;

    }

    /*
    Get IDs of the parent tracklists (albums / playlists / radios) for a track.
    */
    function get_in_tracklists_ids(){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        $subtracks_ids = $this->get_subtrack_matches();

        if ( is_wp_error($subtracks_ids) ) return $subtracks_ids;
        if ( !$subtracks_ids ) return;

        $subtracks_ids_str = implode(',',$subtracks_ids);
        
        // !!! using wpb->prepare fucks up here, it wraps our IDs string with quotes and then the query fails.
        //$querystr = $wpdb->prepare( "SELECT `tracklist_id` FROM `$subtracks_table` WHERE `ID` IN (%s)",$subtracks_ids_str );
        $querystr = sprintf("SELECT `tracklist_id` FROM `$subtracks_table` WHERE `ID` IN (%s)",$subtracks_ids_str );
        
        $tracklist_ids = $wpdb->get_col($querystr);
        
        return array_unique($tracklist_ids);

    }

    function get_parents_list(){

        $tracklist_ids = $this->get_in_tracklists_ids();
        //TOUFIX filter with viewable tracklists (regular WP_Query);
        $links = array();

        foreach((array)$tracklist_ids as $tracklist_id){

            $tracklist_post_type = get_post_type($tracklist_id);

            $playlist_url = get_permalink($tracklist_id);
            $title = get_the_title($tracklist_id);
            $title_short = wpsstm_shorten_text($title);

            $links[] = sprintf('<li><a href="%s" title="%s">%s</a></li>',$playlist_url,$title,$title_short);
        }
        
        if ($links){
            return sprintf('<ul class="wpsstm-track-parents">%s</ul>',implode("\n",$links));
        }
    }

    function to_array(){

        $arr = array(
            'post_id' => $this->post_id,
            'title' => $this->title,
            'artist' => $this->artist,
            'album' => $this->album,
            'tracklist_id' => $this->tracklist->post_id,
            'from_tracklist' => $this->from_tracklist,
            'subtrack_id' => $this->subtrack_id,
            'position' => $this->position,
        );
        return array_filter($arr);
    }
    
    function to_url(){
        $arr = $this->to_array();
        
        if ($this->post_id || $this->subtrack_id){
            $removekeys = array('title','artist','album');
            $arr = array_diff_key($arr, array_flip($removekeys));
        }
        
        if ($this->subtrack_id){
            $removekeys = array('post_id','tracklist_id','position','from_tracklist');
            $arr = array_diff_key($arr, array_flip($removekeys));
        }
        
        return array_filter($arr);
    }
    
    /**
    http://www.xspf.org/xspf-v1.html#rfc.section.4.1.1.2.14.1.1
    */
    
    function to_xspf_array(){
        $xspf_track = array(
            'identifier'    => ( $this->mbid ) ? sprintf('https://musicbrainz.org/recording/%s',$this->mbid) : null,
            'title'         => $this->title,
            'creator'       => $this->artist,
            'album'         => $this->album
        );
        
        $xspf_track = array_filter($xspf_track);
        
        return apply_filters('wpsstm_get_track_xspf',$xspf_track,$this);
    }
    
    function validate_track($strict = true){

        if ($strict){
            if (!$this->artist){
                return new WP_Error( 'wpsstm_missing_track_artist', __("Missing track artist.",'wpsstm') );
            }
            if (!$this->title){
                return new WP_Error( 'wpsstm_missing_track_title', __("Missing track title.",'wpsstm') );
            }
        }else{ //wizard mode
            if ( !$this->artist || !$this->title ){
                return new WP_Error( 'wpsstm_missing_track_details', __("Missing track artist or title.",'wpsstm') );
            }
        }
        return true;
    }
    
    function move_subtrack($new_pos){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        $old_pos = $this->position;
        $tracklist_id = $this->tracklist->post_id;
        $tracks_count = $this->tracklist->get_subtracks_count();
        $new_pos = intval($new_pos);
        
        if ( !$this->subtrack_id ){
            return new WP_Error( 'wpsstm_missing_subtrack_id', __("Required subtrack ID missing.",'wpsstm') );
        }
        
        if ( !$tracklist_id ){
            return new WP_Error( 'wpsstm_missing_subtrack_id', __("Required tracklist ID missing.",'wpsstm') );
        }
        
        if ( !is_int($new_pos) || ($new_pos < 1) || ($new_pos > $tracks_count) ){
            return new WP_Error( 'wpsstm_invalid_position', __("Invalid subtrack position.",'wpsstm') );
        }
        
        if ( !$this->tracklist->user_can_reorder_tracks() ){
            return new WP_Error( 'wpsstm_cannot_reorder', __("You don't have the capability required to reorder subtracks.",'wpsstm') );
        }

        if ($new_pos==$old_pos){
            return new WP_Error( 'wpsstm_not_needed', __("Same position ".$old_pos." -> ".$new_pos.": no update needed.",'wpsstm') );
        }

        //update tracks range
        $up = ($new_pos < $old_pos);
        if ($up){
            $querystr = $wpdb->prepare( "UPDATE $subtracks_table SET track_order = track_order + 1 WHERE tracklist_id = %d AND track_order < %d AND track_order >= %d",$tracklist_id,$old_pos,$new_pos);
            $result = $wpdb->get_results ( $querystr );
        }else{
            $querystr = $wpdb->prepare( "UPDATE $subtracks_table SET track_order = track_order - 1 WHERE tracklist_id = %d AND track_order > %d AND track_order <= %d",$tracklist_id,$old_pos,$new_pos);
            $result = $wpdb->get_results ( $querystr );
        }
        
        //update this subtrack
        if ( !is_wp_error($result) ){
            
            $result = $wpdb->update( 
                $subtracks_table, //table
                array('track_order'=>$new_pos), //data
                array('ID'=>$this->subtrack_id) //where
            );
            
        }

        if ( is_wp_error($result) ){
            $this->track_log(array('subtrack_id'=>$track->subtrack_id,'error'=>$result->get_error_message(),'new_position'=>$new_pos,'old_position'=>$old_pos),"error moving subtrack");
        }else{
            $this->position = $new_pos;
            $this->track_log(array('subtrack_id'=>$this->subtrack_id,'new_position'=>$new_pos,'old_position'=>$old_pos),"moved subtrack");
        }
        
        return $result;

    }

    function save_track_post($args = null){
        
        $valid = $this->validate_track();
        if ( is_wp_error( $valid ) ) return $valid;
        
        $post_id = null;

        $args_default = array(
            'post_status'   => 'publish',
            'post_author'   => get_current_user_id(),
        );
        
        $args = (!$args) ? $args_default : wp_parse_args($args,$args_default);
        
        $user_id = $args['post_author'];
        
        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_track);
        $required_cap = ($this->post_id) ? $post_type_obj->cap->edit_posts : $post_type_obj->cap->create_posts;

        if ( !user_can($user_id,$required_cap) ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to create a new track.",'wpsstm') );
        }
        
        //album
        if ($this->album != '_'){
            $this->album = null;
        }
        
        $meta_input = array(
            WPSSTM_Core_Tracks::$artist_metakey         => $this->artist,
            WPSSTM_Core_Tracks::$title_metakey          => $this->title,
            WPSSTM_Core_Tracks::$album_metakey          => $this->album,
            WPSSTM_Core_Tracks::$image_url_metakey      => $this->image_url,
        );
        
        //swap metas
        if ( isset($args['meta_input']) ){
            $meta_input = wp_parse_args($args['meta_input'],$meta_input);
        }

        $meta_input = array_filter($meta_input);

        $required_args = array(
            'post_title'    => (string)$this, // = __toString()
            'post_type'     => wpsstm()->post_type_track,
            'meta_input'    => $meta_input,
        );
        
        $args = wp_parse_args($required_args,$args);

        //check if this track already exists
        if (!$this->post_id){
            $this->local_track_lookup();
        }
        
        if (!$this->post_id){

            $post_id = wp_insert_post( $args, true );
            
        }else{ //is a track update
            
            $args['ID'] = $this->post_id;
            $post_id = wp_update_post( $args, true );
        }
        
        if ( is_wp_error($post_id) ){
            $error_msg = $post_id->get_error_message();
            $this->track_log($error_msg, "Error while saving track details" ); 
            return $post_id;
        } 

        //repopulate datas
        $this->populate_track_post($post_id);
        
        $this->track_log( array('post_id'=>$this->post_id,'args'=>json_encode($args)), "Saved track details" ); 

        return $this->post_id;
        
    }
    
    function toggle_favorite($bool){
        
        if ( !is_bool($bool) ){
            return new WP_Error( 'wpsstm_missing_bool', __("Missing valid bool.",'wpsstm') );
        }
        
        if ( !get_current_user_id() ){
            return new WP_Error( 'wpsstm_missing_user_id', __("Missing user ID.",'wpsstm') );
        }

        if (!$tracklist_id = wpsstm()->user->favorites_id){
            return new WP_Error( 'wpsstm_missing_favorites_tracklist', __("Missing favorites tracklist ID.",'wpsstm') );
        }
        
        ///
        
        $tracklist = new WPSSTM_Post_Tracklist($tracklist_id);

        if ($bool){
            $success = $tracklist->queue_track($this);
        }else{
            $success = $tracklist->dequeue_track($this);
        }
        
        $this->track_log(array('track'=>$this->to_array(),'do_love'=>$bool,'success'=>$success),"toggle_favorite");
        
        return $success;
        
    }
    
    function trash_track(){

        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_track);
        $can_delete_track = current_user_can($post_type_obj->cap->delete_post,$this->post_id);

        if ( !$can_delete_track ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to delete this track.",'wpsstm') );
        }
        
        $success = wp_trash_post($this->post_id);
        
        //$this->track_log( array('post_id',$this->post_id,'success'=>$success), "WPSSTM_Track::trash_track()"); 
        
        return $success;
        
    }

    
    function unlink_subtrack(){
        if ( !$this->subtrack_id ){
            return new WP_Error( 'wpsstm_missing_subtrack_id', __("Required subtrack ID missing.",'wpsstm') );
        }
        
        //capability check
        if ( !$this->tracklist->user_can_edit_tracklist() ){
            return new WP_Error( 'wpsstm_missing_capability', __("You don't have the capability required to edit this tracklist",'wpsstm') );
        }
        
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        $querystr = $wpdb->prepare( "DELETE FROM `$subtracks_table` WHERE ID = '%s'", $this->subtrack_id );
        $result = $wpdb->get_results ( $querystr );

        //update tracks range
        if ( !is_wp_error($result) ){
            $querystr = $wpdb->prepare( "UPDATE $subtracks_table SET track_order = track_order - 1 WHERE tracklist_id = %d AND track_order > %d",$this->tracklist->post_id,$this->position);
            $range_success = $wpdb->get_results ( $querystr );
             $this->track_log(array('subtrack_id'=>$this->subtrack_id,'tracklist'=>$this->tracklist->post_id),"dequeued subtrack");
            $this->subtrack_id = null;
        }
        
        return $result;
    }

    /*
    retrieve the subtracks IDs that matches a track, eventually filtered by tracklist ID
    */
    function get_subtrack_matches($tracklist_id = null){
        global $wpdb;
        
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        //check we have enough informations on this track
        if ( !$this->post_id || ( $this->validate_track() !== true) ) return false;

        if ($this->post_id){
            $querystr = $wpdb->prepare( "SELECT ID FROM `$subtracks_table` WHERE track_id = %d", $this->post_id );
            
        }else{
            $querystr = $wpdb->prepare( "SELECT ID FROM `$subtracks_table` WHERE artist = '%s' AND title = '%s' AND album = '%s'", $this->artist,$this->title,$this->album);
        }
        
        if($tracklist_id){
            $querystr.= $wpdb->prepare( " AND tracklist_id = %d",$tracklist_id);
        }

        return $wpdb->get_col( $querystr);

    }
    
    function get_track_favorited_by(){
        global $wpdb;

        //get subtracks
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        $ids = WPSSTM_Core_Tracklists::get_favorite_tracks_tracklist_ids();

        if ( !$ids || is_wp_error($ids)  ){
            return $ids;
        }
        $ids_str = implode(',',$ids);
        
        $querystr = sprintf( "SELECT posts.post_author FROM $wpdb->posts posts INNER JOIN %s AS subtracks ON (subtracks.tracklist_id = posts.ID)  WHERE subtracks.tracklist_id IN(%s)", $subtracks_table, $ids_str);

        if ($this->post_id){
            $querystr = $wpdb->prepare( $querystr ." AND subtracks.track_id = %d",$this->post_id );
        }else{
            $querystr = $wpdb->prepare( $querystr ." AND artist = '%s' AND title = '%s' AND album = '%s'",$this->artist,$this->title,$this->album );
        }

        return $wpdb->get_col( $querystr);
    }
    
    function is_track_favorited_by($user_id = null){
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return;
        
        $favorited_by = $this->get_track_favorited_by();
        return in_array($user_id,(array)$favorited_by);
    }
    
    function get_favorited_by_list(){
        $list = null;
        if ( !$user_ids = $this->get_track_favorited_by() ) return;
        
        foreach($user_ids as $user_id){
            $user_info = get_userdata($user_id);
            $link = sprintf('<li><a href="%s" target="_blank">%s</a></li>',get_author_posts_url($user_id),$user_info->user_login);
            $links[] = $link;
        }

        $list = sprintf('<ul class="wpsstm-track-loved-by-list">%s</ul>',implode("\n",$links));

        return $list;
    }
    
    function query_sources($args=null){
        $default_args = array(
            'post_status'       => 'publish',
            'posts_per_page'    => -1,
            'orderby'           => 'menu_order',
            'order'             => 'ASC',
        );

        $required_args = array(
            'post_type'     => wpsstm()->post_type_source,
            'post_parent'   => $this->post_id,
        );
        
        //we need a parent track or it will return all sources; so force return nothing
        if(!$this->post_id){
            $required_args['post__in'] = array(0);
        }
        
        
        $args = wp_parse_args((array)$args,$default_args);
        $args = wp_parse_args($required_args,$args);
        return new WP_Query($args);
    }
    
    function populate_sources(){

        if ($this->post_id){
            $query = $this->query_sources(array('fields'=>'ids'));
            $source_ids = $query->posts;
            $this->add_sources($source_ids);
        }else{
            $this->add_sources($this->sources); //so we're sure the sources count is set
        }
        
        return true;
        
    }
    
    function did_autosource(){

        /*
        Check if a track has been autosourced recently
        */

        $now = current_time( 'timestamp' );
        $seconds = $now - $this->autosourced;
        $hours = $seconds / HOUR_IN_SECONDS;
        
        return ($hours < 48);
 
    }

    /*
    Retrieve autosources for a track
    */
    
    function autosource(){
        global $wpsstm_spotify;

        $new_sources = array();

        if ( !wpsstm()->get_options('autosource') ){
            return new WP_Error( 'wpsstm_autosource_disabled', __("Track autosource is disabled.",'wpsstm') );
        }
        
        if ( $this->did_autosource() ) {
            return new WP_Error( 'wpsstm_autosource_disabled', __("Track has already been autosourced recently.",'wpsstm') );
        }
        
        $can_autosource = WPSSTM_Core_Sources::can_autosource();
        if ( $can_autosource !== true ) return $can_autosource;

        $valid = $this->validate_track();
        if ( is_wp_error($valid) ) return $valid;
        
        /*
        Get sources automatically - services should be hooked here to fetch results.
        */
        
        $sources_auto = array();
        
        /*
        Create community post if track does not exists yet, since we need to store some datas, including the autosource time.
        */
        if(!$this->post_id){

            $tracks_args = array( //as community tracks	
                'post_author'   => wpsstm()->get_options('community_user_id')
            );	

            $success = $this->save_track_post($tracks_args);

            if ( is_wp_error($success) ){
                $error_msg = $success->get_error_message();
                $this->track_log($error_msg,'Error while creating community track');
                return $success;
            }

        }
        
        //save autosourced time so we won't query autosources again too soon
        $now = current_time('timestamp');
        update_post_meta( $this->post_id, WPSSTM_Core_Sources::$autosource_time_metakey, $now );
        
        if (WPSSTM_Core_API::can_wpsstmapi() === true){
            
            if (!$this->spotify_id) {
                
                $sid = $wpsstm_spotify->get_spotify_id( $this->artist,$this->album,$this->title ); //maybe no post ID yet

                if ( is_wp_error($sid) ) return $sid;
                
                if ($sid){
                    
                    $this->spotify_id = $sid;
                    
                    if ( !$success = update_post_meta( $this->post_id, WPSSTM_Spotify::$spotify_id_meta_key, $this->spotify_id ) ){
                        $this->track_log($success,"Error while updated the track's Spotify ID");
                    }
                }
                
            }

            if ( !$this->spotify_id ){
                return new WP_Error( 'missing_spotify_id',__( 'Missing Spotify ID.', 'wpsstmapi' ));
            }

            $api_url = sprintf('track/autosource/spotify/%s',$this->spotify_id);
            $sources_auto = WPSSTM_Core_API::api_request($api_url);
            if ( is_wp_error($sources_auto) ) return $sources_auto;

        }

        /*
        Handle those sources
        */
        
        foreach((array)$sources_auto as $key=>$args){
            
            $source = new WPSSTM_Source();
            $source->from_array( $args );
            
            $source->track = $this;
            $source->is_community = true;
            
            //validate
            $valid_source = $source->validate_source();
            if ( is_wp_error($valid_source) ){
                    $code = $valid_source->get_error_code();
                    $error_msg = $valid_source->get_error_message($code);
                    $source->source_log($error_msg,__('Autosource rejected','wpsstm'));
                    continue;
            }

            $new_sources[] = $source;

        }

        /*
        Hook filter here to ignore some of the sources
        */

        $new_sources = apply_filters('wpsstm_autosources_input',$new_sources,$this);

        //limit autosource results
        $limit_autosources = (int)wpsstm()->get_options('limit_autosources');
        $new_sources = array_slice($new_sources, 0, $limit_autosources);
        $new_sources = apply_filters('wpsstm_track_autosources',$new_sources);

        $this->add_sources($new_sources);
        $new_ids = $this->save_new_sources();
        
        $this->track_log(array('track_id'=>$this->post_id,'sources_found'=>count($this->source_count),'sources_saved'=>count($new_ids)),'autosource results');
        
        return $new_ids;

    }
    
    public function save_new_sources(){

        if ( !$this->post_id ){
            return new WP_Error( 'wpsstm_track_no_id', __('Unable to store source: track ID missing.','wpsstm') );
        }

        //insert sources
        $inserted = array();
        foreach((array)$this->sources as $source){

            if ($source->post_id) continue;
            $source_id = $source->save_source();

            if ( is_wp_error($source_id) ){
                $code = $source_id->get_error_code();
                $error_msg = $source_id->get_error_message($code);
                $source->source_log( $error_msg,"store autosources - error while saving source");
                continue;
            }else{
                $inserted[] = $source_id;
            }

        }
        
        return count($inserted);
    }

    function get_track_url(){
        $url = home_url();
        
        $post_type_obj = get_post_type_object(wpsstm()->post_type_track);
        
        /*
        Subtrack
        */
        if ( $this->subtrack_id ){
            if ( !get_option('permalink_structure') ){
                $args = array(
                    'post_type' =>      wpsstm()->$post_type_track,
                    'subtrack_id' =>    $this->subtrack_id
                );
                $url = add_query_arg($args,$url);
            }else{
                $url .= sprintf('/%s/%s/%d/',WPSSTM_BASE_SLUG,WPSSTM_SUBTRACKS_SLUG,$this->subtrack_id);
            }
        /*
        Track
        */
        }elseif ( $this->post_id ){
            if ( !get_option('permalink_structure') ){
                $args = array(
                    'post_type' =>      wpsstm()->$post_type_track,
                    'p' =>              $this->post_id
                );
                $url = add_query_arg($args,$url);
            }else{
                $url .= sprintf('/%s/%d/',$post_type_obj->rewrite['slug'],$this->post_id);
            }
        }else{
            return false;
        }
        
        return $url;
    }

    function get_track_action_url($action){

        $url = $this->get_track_url();
        if (!$url) return false;
        
        $action_var = 'wpsstm_action';
        $action_permavar = 'action';
        
        if ( !get_option('permalink_structure') ){
            $args = array(
                $action_var =>     $action,
            );

            $url = add_query_arg($args,$url);
        }else{
            $url .= sprintf('%s/%s/',$action_permavar,$action);
        }

        return $url;
    }

    function get_track_links(){
        
        $actions = array();

        $tracklist_id =             $this->tracklist->post_id;
        $post_type_playlist =       $tracklist_id ? get_post_type($tracklist_id) : null;
        $tracklist_post_type_obj =  $post_type_playlist ? get_post_type_object($post_type_playlist) : null;
        $can_edit_tracklist =       ( $tracklist_post_type_obj && current_user_can($tracklist_post_type_obj->cap->edit_post,$tracklist_id) );
        
        /*
        Track
        */
        $track_type_obj =           get_post_type_object(wpsstm()->post_type_track);
        $can_open_track =           ($this->post_id);
        $can_create_track =         current_user_can($track_type_obj->cap->edit_posts);
        $can_edit_track =           ( $this->post_id && current_user_can($track_type_obj->cap->edit_post,$this->post_id) );
        $can_delete_track =         ( $this->post_id && current_user_can($track_type_obj->cap->delete_posts) );
        
        $can_favorite_track =       ( wpsstm()->user->can_subtracks && wpsstm()->user->favorites_id);
        $can_playlists_manager =    ( wpsstm()->user->can_subtracks );

        $can_move_subtrack =        ( $this->subtrack_id && $can_edit_tracklist && ($this->tracklist->tracklist_type == 'static') );
        $can_dequeue_track =      ( $this->subtrack_id && $can_edit_tracklist && ($this->tracklist->tracklist_type == 'static') );

        //share
        /*
        //TO FIX in playlist, etc.
        $actions['share'] = array(
            'text' =>       __('Share', 'wpsstm'),
            'href' =>       $this->get_track_action_url('share'),
        );
        */

        //favorite
        if ($can_favorite_track){
            
            $actions['favorite'] = array(
                'text' =>      __('Favorite','wpsstm'),
                'href' =>       $this->get_track_action_url('favorite'),
                'desc' =>       __('Add track to favorites','wpsstm'),
                'classes' =>    array('action-favorite'),
            );

            $actions['unfavorite'] = array(
                'text' =>      __('Favorite','wpsstm'),
                'href' =>       $this->get_track_action_url('unfavorite'),
                'desc' =>       __('Remove track from favorites','wpsstm'),
                'classes' =>    array('action-unfavorite'),
            );

        }else{
            if ( !get_current_user_id() ){ //call to action
                $actions['favorite'] = array(
                    'text' =>      __('Favorite','wpsstm'),
                    'href' =>       '#',
                    'desc' =>       __('This action requires you to be logged.','wpsstm'),
                    'classes' =>    array('action-favorite','wpsstm-tooltip','wpsstm-requires-login'),
                );
            }
        }

        /*
        Subtracks
        */
        if ($can_dequeue_track){
            $actions['dequeue'] = array(
                'text' =>      __('Remove'),
                'classes' =>    array('wpsstm-advanced-action'),
                'desc' =>       __('Remove from playlist','wpsstm'),
                'href' =>       $this->get_track_action_url('dequeue'),
            );
        }
        
        if ($can_move_subtrack){
            $actions['move'] = array(
                'text' =>      __('Move', 'wpsstm'),
                'desc' =>       __('Drag to move track in tracklist', 'wpsstm'),
                'classes' =>    array('wpsstm-advanced-action'),
                'href' =>       $this->get_track_action_url('move'),
            );
        }
        
        //playlists manager
        if ($can_playlists_manager){
            $actions['toggle-tracklists'] = array(
                'text' =>      __('Playlists manager','wpsstm'),
                'href' =>       $this->get_track_action_url('manage'),
                'classes' =>    array('wpsstm-track-popup'),
            );
        }else{
            if ( !get_current_user_id() ){ //call to action
                $actions['toggle-tracklists'] = array(
                    'text' =>      __('Playlists manager','wpsstm'),
                    'href' =>       '#',
                    'desc' =>       __('This action requires you to be logged.','wpsstm'),
                    'classes' =>    array('wpsstm-tooltip','wpsstm-requires-login'),
                );
            }
        }

        //delete track
        if ($can_delete_track){
            $actions['trash'] = array(
                'text' =>      __('Trash'),
                'classes' =>    array('wpsstm-advanced-action'),
                'desc' =>       __('Trash this track','wpsstm'),
                'href' =>       $this->get_track_action_url('trash'),
            );
        }
        
        //backend
        if ($can_edit_track){
            $actions['edit-backend'] = array(
                'text' =>      __('Edit Track','wpsstm'),
                'classes' =>    array('wpsstm-advanced-action','wpsstm-track-popup'),
                'href' =>       get_edit_post_link( $this->post_id ),
            );
        }else if($can_create_track){
            //TOUFIX handle track creation with redirection
            $actions['edit-backend'] = array(
                'text' =>      __('Edit Track','wpsstm'),
                'classes' =>    array('wpsstm-advanced-action','wpsstm-track-popup'),
                'href' =>       $this->get_track_action_url('create'),
            );
        }
        
        $actions['toggle-sources'] = array(
            'text' =>      __('Sources'),
            'href' =>       '#',
        );

        return apply_filters('wpsstm_track_actions',$actions);

    }
    
    function get_track_attr($args=array()){
        global $wpsstm_tracklist;

        $attr = array(
            'itemscope' =>                      true,
            'itemtype' =>                       "http://schema.org/MusicRecording",
            'itemprop' =>                       'track',
            'class' =>                          implode( ' ',$this->get_track_class() ),
            'data-wpsstm-subtrack-id' =>        $this->subtrack_id,
            'data-wpsstm-subtrack-position' =>  $this->position,
            'data-wpsstm-track-id' =>           $this->post_id,
            'data-wpsstm-sources-count' =>      $this->source_count,
        );

        return wpsstm_get_html_attr($attr);
    }
    
    function get_track_class(){

        $classes = array(
            'wpsstm-track',
            ( $this->is_track_favorited_by() ) ? 'favorited-track' : null,
            is_wp_error( $this->validate_track() ) ? 'wpsstm-invalid-track' : null,
            $this->did_autosource()  ? 'did-track-autosource' : null,

        );

        $classes = apply_filters('wpsstm_track_classes',$classes,$this);
        return array_filter(array_unique($classes));
    }
    
    /*
    $input_sources = array of sources objects or array of source IDs
    */
    
    function add_sources($input_sources){

        $add_sources = array();
        if(!$input_sources) return;

        
        foreach ((array)$input_sources as $source){

            if ( is_a($source, 'WPSSTM_Source') ){
                $source_obj = $source;
            }else{
                if ( is_array($source) ){
                    $source_args = $source;
                    $source_obj = new WPSSTM_Source(null);
                    $source_obj->from_array($source_args);
                }else{ //source ID
                    $source_id = $source;
                    //TO FIX check for int ?
                    $source_obj = new WPSSTM_Source($source_id);
                }
            }

            $valid = $source_obj->validate_source();

            if ( is_wp_error($valid) ){
                $code = $valid->get_error_code();
                $error_msg = $valid->get_error_message($code);
                $source_obj->source_log(array('error'=>$error_msg,'source'=>$source_obj),"Unable to add source");
                continue;
            }

            $source_obj->track = $this;
            $add_sources[] = $source_obj;
            
        }

        //allow users to alter the input sources.
        $add_sources = apply_filters('wpsstm_sources_input',$add_sources,$this);

        $this->sources = array_merge((array)$this->sources,(array)$add_sources);
        $this->source_count = count($this->sources);

        return $this->sources;
    }
    
    /**
	 * Set up the next source and iterate current source index.
	 * @return WP_Post Next source.
	 */
	public function next_source() {

		$this->current_source++;

		$this->source = $this->sources[$this->current_source];
		return $this->source;
	}

	/**
	 * Sets up the current source.
	 * Retrieves the next source, sets up the source, sets the 'in the loop'
	 * property to true.
	 * @global WP_Post $wpsstm_source
	 */
	public function the_source() {
		global $wpsstm_source;
		$this->in_source_loop = true;

		if ( $this->current_source == -1 ) // loop has just started
			/**
			 * Fires once the loop is started.
			 *
			 * @since 2.0.0
			 *
			 * @param WP_Query &$this The WP_Query instance (passed by reference).
			 */
			do_action_ref_array( 'wpsstm_sources_loop_start', array( &$this ) );

		$wpsstm_source = $this->next_source();
		//$this->setup_sourcedata( $wpsstm_source );
	}

	/**
	 * Determines whether there are more sources available in the loop.
	 * Calls the {@see 'wpsstm_sources_loop_end'} action when the loop is complete.
	 * @return bool True if sources are available, false if end of loop.
	 */
	public function have_sources() {
		if ( $this->current_source + 1 < $this->source_count ) {
			return true;
		} elseif ( $this->current_source + 1 == $this->source_count && $this->source_count > 0 ) {
			/**
			 * Fires once the loop has ended.
			 *
			 * @since 2.0.0
			 *
			 * @param WP_Query &$this The WP_Query instance (passed by reference).
			 */
			do_action_ref_array( 'wpsstm_sources_loop_end', array( &$this ) );
			// Do some cleaning up after the loop
			$this->rewind_sources();
		}

		$this->in_source_loop = false;
		return false;
	}

	/**
	 * Rewind the sources and reset source index.
	 * @access public
	 */
	public function rewind_sources() {
		$this->current_source = -1;
		if ( $this->source_count > 0 ) {
			$this->source = $this->sources[0];
		}
	}
    
    function user_can_reorder_sources(){
        $track_type_obj = get_post_type_object(wpsstm()->post_type_track);
        $required_cap = $track_type_obj->cap->edit_posts;
        $can_edit_track = current_user_can($required_cap,$this->post_id);

        $source_type_obj = get_post_type_object(wpsstm()->post_type_source);
        $can_edit_sources = current_user_can($source_type_obj->cap->edit_posts);
        
        return ($can_edit_track && $can_edit_sources);
    }
    
    function get_backend_sources_url(){
        $sources_url = admin_url('edit.php');
        $sources_url = add_query_arg( 
            array(
                'post_type'     => wpsstm()->post_type_source,
                'post_parent'   => $this->post_id,
                //'post_status' => 'publish'
            ),$sources_url 
        );
        return $sources_url;
    }

    function update_sources_order($source_ids){
        global $wpdb;
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_post_id', __("Missing track ID.",'wpsstm') );
        }

        if ( !$this->user_can_reorder_sources() ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to reorder sources.",'wpsstm') );
        }

        foreach((array)$source_ids as $order=>$post_id){
            
            $wpdb->update( 
                $wpdb->posts, //table
                array('menu_order'=>$order), //data
                array('ID'=>$post_id) //where
            );

        }

        return true;
    }
    
    function track_log($data,$title = null){

        if ($this->post_id){
            $title = sprintf('[track:%s] ',$this->post_id) . $title;
        }

        if ( $this->tracklist->post_id ){
            $this->tracklist->tracklist_log($data,$title);
        }else{
            wpsstm()->debug_log($data,$title);
        }

    }
    
    /*
    Use a custom function to display our notices since natice WP function add_settings_error() works only backend.
    */
    
    function add_notice($code,$message,$error = false){
        
        //$this->track_log(array('slug'=>$slug,'code'=>$code,'error'=>$error),'[WPSSTM_Post_Tracklist notice]: ' . $message ); 
        
        $this->notices[] = array(
            'code'      => $code,
            'message'   => $message,
            'error'     => $error
        );

    }

    function get_subtrack_hidden_form_fields(){
        $fields = array();
        if ($this->subtrack_id){
            $fields[] = sprintf('<input type="hidden" name="wpsstm_track_data[subtrack_id]" value="%s" />',esc_attr($this->subtrack_id));
        }
        if ($this->tracklist->post_id){
            $fields[] = sprintf('<input type="hidden" name="wpsstm_track_data[from_tracklist]" value="%s" />',esc_attr($this->tracklist->post_id));
        }
        return implode("\n",$fields);
    }
    
    /*
    Magic Method used among others by array_unique() for our tracks, be careful if you plan to change this fn.
    */
    
    public function __toString() {
        if ($this->album){
            $title = sprintf('%s - "%s" | %s',$this->artist,$this->title,$this->album);
        }else{
            $title = sprintf('%s - "%s"',$this->artist,$this->title);
        }
        return $title;
    }
    
}