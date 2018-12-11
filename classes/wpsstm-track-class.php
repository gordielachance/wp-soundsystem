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
    public $parent_ids = array();
    public $position = 0;
    public $subtrack_time = null;
    public $from_tracklist = null;
    
    public $notices = array();
    
    function __construct( $post_id = null, $tracklist = null ){
        
        /*
        Tracklist
        */
        $this->tracklist = new WPSSTM_Post_Tracklist();

        //has track ID
        if ( $track_id = intval($post_id) ) {
            $this->post_id = $track_id;
            $this->populate_track_post();
        }
        
        if ($tracklist){
            if ( is_a($tracklist,'WPSSTM_Post_Tracklist') ){
                $this->tracklist = $tracklist;
            }elseif( $tracklist_id = intval($tracklist) ){
                $this->tracklist = new WPSSTM_Post_Tracklist($tracklist_id);
            }
        }

        
    }
    
    function populate_track_post(){
        
        if ( !$this->post_id || ( get_post_type($this->post_id) != wpsstm()->post_type_track ) ){
            $this->track_log('Invalid track post');
            return;
        }
        
        $this->title        = wpsstm_get_post_track($this->post_id);
        $this->artist       = wpsstm_get_post_artist($this->post_id);
        $this->album        = wpsstm_get_post_album($this->post_id);
        $this->mbid         = wpsstm_get_post_mbid($this->post_id);
        $this->spotify_id   = wpsstm_get_post_spotify_id($this->post_id);
        $this->image_url    = wpsstm_get_post_image_url($this->post_id);
        $this->duration     = wpsstm_get_post_length($this->post_id);
    }
    
    function populate_subtrack($subtrack_id){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        $query = "SELECT * FROM $subtracks_table WHERE ID = $subtrack_id";
        $subtrack = $wpdb->get_row($query);

        if (!$subtrack) return;

        //track
        if ($track_id = $subtrack->track_id){
            $this->post_id = $track_id;
            $this->populate_track_post();
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
        $this->from_tracklist = $subtrack->from_tracklist;
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
                case 'album';
                   if ($value == '_') continue;
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
                default:
                    if ( !property_exists($this,$key) )  continue;
                    if ( !isset($args[$key]) ) continue; //value has not been set
                    $this->$key = $args[$key];
                break;
            }

        }

        //subtrack or track id ?
        if ($this->subtrack_id){
            $this->populate_subtrack($this->subtrack_id);
        }elseif ( $this->post_id ){
            $this->populate_track_post();
        }
        
        
    }
    
    /*
    Query tracks (IDs) that have the same artist + title (+album if set)
    */
    
    function get_track_duplicates(){
        
        if (!$this->artist || !$this->title) return;
        
        $query_args = array(
            'post_type' =>      wpsstm()->post_type_track,
            'post_status' =>    'any',
            'posts_per_page'=>  -1,
            'fields' =>         'ids',
            WPSSTM_Core_Artists::$qvar_artist_lookup => $this->artist,
            WPSSTM_Core_Tracks::$qvar_track_lookup =>   $this->title
        );

        if ($this->post_id){
            $query_args['post__not_in'] = array($this->post_id);
        }

        if ($this->album){
            $query_args[WPSSTM_Core_Albums::$qvar_album_lookup] = $this->album;
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
    Get IDs of the parent tracklists (albums / playlists / live playlists) for a subtrack.
    */
    function get_in_tracklists_ids($args = null){
        global $wpdb;

        //track ID is required
        if ( !$this->post_id ) return;//track does not exists in DB

        $default_args = array(
            'post_type' =>                              wpsstm()->tracklist_post_types,
            'post_status'  =>                           'any',
            'posts_per_page' =>                         -1,
            'fields'=>                                  'ids',
            'subtrack_id' =>                            $this->post_id,
        );

        $args = wp_parse_args((array)$args,$default_args);

        //$this->track_log($args,'WPSSTM_Track::get_in_tracklists_ids()');

        $query = new WP_Query( $args );

        $this->parent_ids = array_unique($query->posts);

        return $this->parent_ids;
    }

    function get_parents_list(){

        $tracklist_ids = $this->get_in_tracklists_ids();
        $links = array();

        foreach((array)$tracklist_ids as $tracklist_id){

            $tracklist_post_type = get_post_type($tracklist_id);

            $playlist_url = get_permalink($tracklist_id);
            $playlist_name = ( $title = get_the_title($tracklist_id) ) ? $title : sprintf('#%s',$tracklist_id);

            $links[] = sprintf('<li><a href="%s">%s</a></li>',$playlist_url,$playlist_name);
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
            $querystr = $wpdb->prepare( "UPDATE $subtracks_table SET track_order = %d WHERE ID = %d",$new_pos,$this->subtrack_id);
            $result = $wpdb->get_results ( $querystr );
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
            WPSSTM_Core_Artists::$artist_metakey    => $this->artist,
            WPSSTM_Core_Tracks::$title_metakey      => $this->title,
            WPSSTM_Core_Albums::$album_metakey      => $this->album,
            WPSSTM_MusicBrainz::$mbid_metakey       => $this->mbid,
            WPSSTM_Core_Tracks::$image_url_metakey  => $this->image_url,
        );
        
        $meta_input = array_filter($meta_input);
        
        $required_args = array(
            'post_type'     => wpsstm()->post_type_track,
            'meta_input'    => $meta_input,
        );
        
        $args = wp_parse_args($required_args,$args);

        //check if this track already exists
        if (!$this->post_id){
            $this->local_track_lookup();
        }
        
        if (!$this->post_id){
            
            $success = wp_insert_post( $args, true );
            
        }else{ //is a track update
            
            $args['ID'] = $this->post_id;
            $success = wp_update_post( $args, true );
        }
        
        if ( is_wp_error($success) ){
            $error_msg = $success->get_error_message();
            $this->track_log($error_msg, "Error while saving track details" ); 
            return $success;
        } 
        $this->post_id = $success;
        $this->track_log( array('post_id'=>$this->post_id,'args'=>json_encode($args)), "Saved track details" ); 

        return $this->post_id;
        
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
        
        if ( !$this->position ){
            return new WP_Error( 'wpsstm_missing_subtrack_position', __("Required subtrack position missing.",'wpsstm') );
        }

        //update this subtrack
        $querystr = $wpdb->prepare( "DELETE FROM $subtracks_table WHERE ID = '%s'", $this->subtrack_id );
        $result = $wpdb->get_results ( $querystr );

        //update tracks range
        if ( !is_wp_error($result) ){
            $querystr = $wpdb->prepare( "UPDATE $subtracks_table SET track_order = track_order - 1 WHERE tracklist_id = %d AND track_order > %d",$this->tracklist->post_id,$this->position);
            $range_success = $wpdb->get_results ( $querystr );
        }
        
        return $result;
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

    function love_track($do_love){
        
        if ( !$this->artist || !$this->title ) return new WP_Error('missing_love_track_data',__("Required track information missing",'wpsstm'));
        if ( !$user_id = get_current_user_id() ) return new WP_Error('no_user_id',__("User is not logged",'wpsstm'));

        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_playlist);
        $required_cap = $post_type_obj->cap->edit_posts;
        if ( !current_user_can($required_cap) ){
            return new WP_Error( 'wpsstm_track_no_edit_cap', __("You don't have the capability required to edit tracklists.",'wpsstm') );
        }
        
        //get tracklist
        $tracklist_id = WPSSTM_Core_Tracklists::get_user_favorites_id();
        
        if ( !$tracklist_id || is_wp_error($tracklist_id) ){
            return $tracklist_id;
        }
        
        $tracklist = new WPSSTM_Post_Tracklist($tracklist_id);

        if ($do_love){
            $new_subtrack = clone $this;
            $new_subtrack->subtrack_id = null;
            $new_subtrack->position = null;
            $success = $tracklist->save_subtrack($new_subtrack);
            do_action('wpsstm_love_track',$this->post_id,$this);
        }else{
            
            //find matche(s) from user's favorites

            $ids = $this->get_subtrack_matches($tracklist_id);
            
            foreach($ids as $subtrack_id){
                $subtrack = new WPSSTM_Track(); //default
                $subtrack->populate_subtrack($subtrack_id);
                $success = $subtrack->unlink_subtrack();
            }

            do_action('wpsstm_unlove_track',$this->post_id,$this);
        }

        return $success;
        
    }

    /*
    retrieve the subtracks IDs that matches a track in a playlist
    */
    function get_subtrack_matches($tracklist_id){
        global $wpdb;
        
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        if ($this->post_id){
            $querystr = $wpdb->prepare( "SELECT ID FROM $subtracks_table WHERE tracklist_id = %d AND track_id = %d", $tracklist_id, $this->post_id );
        }else{
            $querystr = $wpdb->prepare( "SELECT ID FROM $subtracks_table WHERE tracklist_id = %d AND artist = '%s' AND title = '%s' AND album = '%s'", $tracklist_id, $this->artist,$this->title,$this->album );
        }
        
        return $wpdb->get_col( $querystr);

    }
    
    function get_track_favorited_by(){
        global $wpdb;

        //get subtracks
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        $ids = WPSSTM_Core_Tracklists::get_all_favorite_tracklist_ids();

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
    }
    
    /*
    Retrieve autosources for a track
    */
    
    function autosource(){
        
        $autosources = array();

        if ( !wpsstm()->get_options('autosource') ){
            return new WP_Error( 'wpsstm_autosource_disabled', __("Track autosource is disabled.",'wpsstm') );
        }
        
        $can_autosource = WPSSTM_Core_Sources::can_autosource();
        if ( $can_autosource !== true ) return $can_autosource;

        if ( !$this->artist ){
            return new WP_Error( 'wpsstm_track_no_artist', __('Autosourcing requires track artist.','wpsstm') );
        }
        
        if ( !$this->title ){
            return new WP_Error( 'wpsstm_track_no_title', __('Autosourcing requires track title.','wpsstm') );
        }
        
        /*
        Create community post :
        if track does not exists yet, create it as a community track - we need a post ID to store various metadatas
        */

        if ( !$this->post_id ){
            $this->track_log('Creating community track','autosource');
            $tracks_args = array( //as community tracks	
                'post_author'   => wpsstm()->get_options('community_user_id'),	
            );	
            	
            $success = $this->save_track_post($tracks_args);	
            if ( is_wp_error($success) ){
                $error_msg = $success->get_error_message();
                $this->track_log($error_msg,'Error while creating community track');
                return $success;
            }else{
                $this->track_log($success,'Community track created');
            }
        }

        //$autosources_arr = WPSSTM_Tuneefy::get_track_autosources($this);
        $autosources_arr = WPSSTM_SongLink::get_track_autosources($this);
        
        if ( is_wp_error($autosources_arr) ) return $autosources_arr;

        foreach((array)$autosources_arr as $key=>$source_arr){
            $source = new WPSSTM_Source(null);
            $source->from_array($source_arr);
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

            $autosources[] = $source;

        }
        
        $autosources = apply_filters('wpsstm_pre_save_autosources',$autosources,$this);

        //limit autosource results
        $limit_autosources = (int)wpsstm()->get_options('limit_autosources');
        $autosources = array_slice($autosources, 0, $limit_autosources);
        $autosources = apply_filters('wpsstm_track_autosources',$autosources);
        
        //save autosourced time
        $now = current_time('timestamp');
        update_post_meta( $this->post_id, WPSSTM_Core_Sources::$autosource_time_metakey, $now );

        //save new sources
        $this->add_sources($autosources);
        $new_ids = $this->save_new_sources();
        
        $this->track_log(array('track_id'=>$this->post_id,'sources_found'=>count($autosources),'sources_saved'=>count($new_ids)),'autosource results');
        
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
        return $inserted;
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

    function get_track_action_url($action,$ajax = false){

        $url = $this->get_track_url();
        if (!$url) return false;
        
        $action_var = ($ajax) ? 'wpsstm_ajax_action' : 'wpsstm_action';
        $action_permavar = ($ajax) ? 'ajax' : 'action';
        

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
    
    function get_tracklists_manager_url(){
        $manager_url = WPSSTM_Core_Tracklists::get_tracklists_manager_url();
        
        $track_args = $this->to_url();
        $manager_url = add_query_arg(
            array('wpsstm_track_data'=>$track_args),
            $manager_url
        );
        return $manager_url;
    }

    function get_track_links(){
        
        $actions = array();

        /*
        Tracklist
        //TO FIX this should be reworked. Either tracks should have a ->in_playlist_id property, either filter the track actions, either have a tracklist_track_links() fn instead of this one, something like that.
        */
        $tracklist_id =             $this->tracklist->post_id;
        $post_type_playlist =       $tracklist_id ? get_post_type($tracklist_id) : null;
        $tracklist_post_type_obj =  $post_type_playlist ? get_post_type_object($post_type_playlist) : null;
        $can_edit_tracklist =       ( $tracklist_post_type_obj && current_user_can($tracklist_post_type_obj->cap->edit_post,$tracklist_id) );
        
        /*
        Track
        */
        $track_type_obj =           get_post_type_object(wpsstm()->post_type_track);
        $can_open_track =           ($this->post_id);
        $can_edit_track =           current_user_can($track_type_obj->cap->edit_post,$this->post_id);
        $can_delete_track =         ( $this->post_id && current_user_can($track_type_obj->cap->delete_posts) );
        $can_favorite_track =       true;//call to action
        $can_playlists_manager =    true;//call to action
        
        /*
        Subtrack
        */
        $can_move_subtrack =        ( $this->subtrack_id && $can_edit_tracklist && ($this->tracklist->tracklist_type == 'static') );
        $can_unlink_subtrack =      ( $this->subtrack_id && $can_edit_tracklist && ($this->tracklist->tracklist_type == 'static') );

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
                'ajax' =>       $this->get_track_action_url('favorite',true),
                'desc' =>       __('Add track to favorites','wpsstm'),
                'classes' =>    array('action-favorite'),
            );

            $actions['unfavorite'] = array(
                'text' =>      __('Favorite','wpsstm'),
                'href' =>       $this->get_track_action_url('unfavorite'),
                'ajax' =>       $this->get_track_action_url('unfavorite',true),
                'desc' =>       __('Remove track from favorites','wpsstm'),
                'classes' =>    array('action-unfavorite'),
            );

        }
        //track details
        if ($can_open_track){
            $actions['about'] = array(
                'text' =>      __('About', 'wpsstm'),
                'href' =>       $this->get_track_action_url('about'),
                'classes' =>    array('wpsstm-track-popup'),
            );
        }

        /*
        Subtracks
        */
        if ($can_unlink_subtrack){
            $actions['unlink'] = array(
                'text' =>      __('Remove'),
                'classes' =>    array('wpsstm-advanced-action'),
                'desc' =>       __('Remove from playlist','wpsstm'),
                'href' =>       $this->get_track_action_url('unlink'),
                'ajax' =>       $this->get_track_action_url('unlink',true),
            );
        }
        
        if ($can_move_subtrack){
            $actions['move'] = array(
                'text' =>      __('Move', 'wpsstm'),
                'desc' =>       __('Drag to move track in tracklist', 'wpsstm'),
                'classes' =>    array('wpsstm-advanced-action'),
                'href' =>       $this->get_track_action_url('move'),
                'ajax' =>       $this->get_track_action_url('move',true),
            );
        }
        
        //playlists manager
        if ($can_playlists_manager){
            $actions['toggle-tracklists'] = array(
                'text' =>      __('Playlists manager','wpsstm'),
                'href' =>       $this->get_tracklists_manager_url(),
                'classes' =>    array('wpsstm-track-popup'),
            );
        }

        //delete track
        if ($can_delete_track){
            $trash_action_url = $this->get_track_action_url('trash');
            $actions['trash'] = array(
                'text' =>      __('Trash'),
                'classes' =>    array('wpsstm-advanced-action'),
                'desc' =>       __('Trash this track','wpsstm'),
                'href' =>       $this->get_track_action_url('trash'),
                'ajax' =>       $this->get_track_action_url('trash',true),
            );
        }
        
        //backend
        if ($can_edit_track){
            $actions['edit-backend'] = array(
                'text' =>      __('Edit'),
                'classes' =>    array('wpsstm-advanced-action','wpsstm-track-popup'),
                'href' =>       get_edit_post_link( $this->post_id ),
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
            'data-wpsstm-subtrack-id' =>        $this->subtrack_id,
            'data-wpsstm-subtrack-position' =>  $this->position,
            'data-wpsstm-track-id' =>           $this->post_id,
            'data-wpsstm-sources-count' =>      $this->source_count,
            'data-wpsstm-autosource-time' =>    get_post_meta( $this->post_id, WPSSTM_Core_Sources::$autosource_time_metakey, true ),
        );

        return wpsstm_get_html_attr($attr);
    }
    
    function get_track_class(){

        $classes = array(
            'wpsstm-track',
            ( $this->is_track_favorited_by() ) ? 'favorited-track' : null,
        );
        
        $classes[] = is_wp_error( $this->validate_track() ) ? 'wpsstm-invalid-track' : null;

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
        $add_sources = apply_filters('wpsstm_input_sources',$add_sources,$this);

        $this->sources = array_merge((array)$this->sources,(array)$add_sources);
        $this->source_count = count($this->sources);

        return $add_sources;
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
    
    /*
    Checks the tracklists manager checkbox if the global track is part of the playlist displayed in the row
    */
    public static function tracklists_manager_track_checkbox($checked,$tracklist){
        global $wpsstm_track;
        
        if ( !$wpsstm_track->subtrack_id ) return $checked;
        
        $checked_playlist_ids = $wpsstm_track->get_in_tracklists_ids();
        $checked = in_array($tracklist->post_id,(array)$checked_playlist_ids);
        return $checked;
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
    
    function can_play_track(){
        if ( !wpsstm()->get_options('player_enabled') ){
            return new WP_Error( 'wpsstm_player_disabled', __("Player is disabled.",'wpsstm') );
        }
        return true;
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
    
    function do_track_action($action,$action_data = null){
        global $wp_query;
        
        $success = null;
        
        //action
        switch($action){
            case 'favorite':
            case 'unfavorite':
                $do_love = ( $action == 'favorite');
                $success = $this->love_track($do_love);
            break;

            case 'trash':
                $success = $this->trash_track();
            break;
            case 'append':
                $success = $this->tracklist->save_subtrack($this);
            break;
            case 'unlink':
                $success = $this->unlink_subtrack();
            break;
        }
        
        return $success;
    }
    
    function get_track_hidden_form_fields(){
        $fields = array();
        if ($this->post_id){
            $fields[] = sprintf('<input type="hidden" name="wpsstm_track_data[post_id]" value="%s" />',esc_attr($this->post_id));
        }
        if ($this->subtrack_id){
            $fields[] = sprintf('<input type="hidden" name="wpsstm_track_data[subtrack_id]" value="%s" />',esc_attr($this->subtrack_id));
        }
        if ($this->from_tracklist){
            $fields[] = sprintf('<input type="hidden" name="wpsstm_track_data[from_tracklist]" value="%s" />',esc_attr($this->from_tracklist));
        }
        if ($this->tracklist->post_id){
            $fields[] = sprintf('<input type="hidden" name="wpsstm_track_data[tracklist_id]" value="%s" />',esc_attr($this->tracklist->post_id));
        }
        if ($this->artist){
            $fields[] = sprintf('<input type="hidden" name="wpsstm_track_data[artist]" value="%s" />',esc_attr($this->artist));
        }
        if ($this->album){
            $fields[] = sprintf('<input type="hidden" name="wpsstm_track_data[album]" value="%s" />',esc_attr($this->album));
        }
        if ($this->title){
            $fields[] = sprintf('<input type="hidden" name="wpsstm_track_data[title]" value="%s" />',esc_attr($this->title));
        }
        return implode("\n",$fields);
    }
    
}