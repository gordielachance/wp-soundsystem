<?php

class WPSSTM_Track{
    public $post_id = null;
    
    public $title;
    public $artist;
    public $album;
    public $duration; //in milliseconds
    public $musicbrainz_id = null;
    public $spotify_id = null;
    
    public $image_url; //remote image URL
    public $autolinked; //last time autolinked
    public $classes = array('wpsstm-track');
    
    var $link;
    public $links = array();
    var $current_link = -1;
    var $link_count = 0;
    var $in_link_loop = false;
    
    var $tracklist;
    
    ///
    public $subtrack_id = null;
    public $position = 0;
    public $subtrack_time = null;
    public $subtrack_author = null;
    public $from_tracklist = null;

    public $notices = array();
    
    function __construct( $post = null, $tracklist = null ){
        
        $this->tracklist = new WPSSTM_Post_Tracklist();

        /*
        Track
        */

        $this->populate_track_post($post);

        /*
        Tracklist
        */
        if ($tracklist){
            if ( is_a($tracklist,'WPSSTM_Post_Tracklist') ){
                $this->tracklist = $tracklist;
            }else{
                $this->tracklist = new WPSSTM_Post_Tracklist($tracklist);
            }
        }
    }

    function from_array( $args ){
        
        if ( !is_array($args) ) return;

        //set properties from args input
        foreach ($args as $key=>$value){

            switch($key){
                case 'tracklist_id';
                    $this->tracklist = new WPSSTM_Post_Tracklist($value);
                break;
                case 'link_urls':
                    
                    $links = array();
                    foreach((array)$value as $link_url){
                        $link = array(
                            'permalink_url' => $link_url,
                        );
                        $links[] = $link;
                    }
                    
                    $this->add_links($links);
                break;
                case 'album';
                   if ($value == '_') break;
                default:
                    if ( !property_exists($this,$key) )  break;
                    if ( !isset($args[$key]) ) break; //value has not been set
                    $this->$key = $value;
                break;
            }

        }

        //subtrack or track id ?
        if ($this->subtrack_id){
            return $this->populate_subtrack_id($this->subtrack_id);
        }elseif ( $this->post_id ){
            return $this->populate_track_post($this->post_id);
        }
    }
    
    /*
    Query tracks (IDs) that have the same artist + title (+album if set)
    */
    private function get_track_duplicates(){
 
        $valid = $this->validate_track();
        if ( is_wp_error($valid) ) return $valid;

        $query_args = array(
            'post_type' =>      wpsstm()->post_type_track,
            'post_status' =>    'any',
            'posts_per_page'=>  -1,
            'fields' =>         'ids',
            'tax_query' =>      array(
                'relation' => 'AND',
                array(
                    'taxonomy' => WPSSTM_Core_Tracks::$artist_taxonomy,
                    'field'    => 'slug',
                    'terms'    => $this->artist,
                ),
                array(
                    'taxonomy' => WPSSTM_Core_Tracks::$track_taxonomy,
                    'field'    => 'slug',
                    'terms'    => $this->title,
                )
            )
        );
        
        /*
        if($this->album){
            $query_args['tax_query'][] = array(
                    'taxonomy' => WPSSTM_Core_Tracks::$album_taxonomy,
                    'field'    => 'slug',
                    'terms'    => $this->album,
                );
        }
        */

        if ($this->post_id){
            $query_args['post__not_in'] = array($this->post_id);
        }

        $query = new WP_Query( $query_args );

        return $query->posts;

    }
    
    /*
    Get the post ID for this track if it already exists in the database; and populate its data
    */
    
    public function local_track_lookup(){
        if ( $this->post_id ) return;

        $duplicates = $this->get_track_duplicates();
        if ( is_wp_error($duplicates) ) return $duplicates;
        
        if ( !empty($duplicates) ){
            $this->post_id = $duplicates[0];
            return $this->post_id;
        }
        
        return false;

    }

    /*
    Get IDs of the parent tracklists (albums / playlists / radios) for a track.
    */
    function get_in_tracklists_ids(){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        $querystr = sprintf("SELECT `tracklist_id` FROM `$subtracks_table` WHERE `track_id`=%d",$this->post_id );

        $tracklist_ids = $wpdb->get_col($querystr);
        
        return $tracklist_ids;

    }

    function get_parents_list(){

        $tracklist_ids = $this->get_in_tracklists_ids();
        //TOUFIX filter with viewable tracklists (regular WP_Query);
        $links = array();

        foreach((array)$tracklist_ids as $tracklist_id){

            $tracklist_post_type = get_post_type($tracklist_id);

            $playlist_url = ( is_admin() ) ? get_edit_post_link($tracklist_id) : get_permalink($tracklist_id);
            $title = get_the_title($tracklist_id);
            $title_short = wpsstm_shorten_text($title);

            $links[] = sprintf('<li><a href="%s" title="%s">%s</a></li>',$playlist_url,$title,$title_short);
        }
        
        if ($links){
            return sprintf('<ul class="wpsstm-track-parents">%s</ul>',implode("\n",$links));
        }
    }
    
    /*
    Return one level array
    */

    function to_array(){

        $arr = array(
            'post_id' =>            $this->post_id,
            'title' =>              $this->title,
            'artist' =>             $this->artist,
            'album' =>              $this->album,
            'tracklist_id' =>       $this->tracklist->post_id,
            'from_tracklist' =>     $this->from_tracklist,
            'subtrack_time' =>      $this->subtrack_time,
            'subtrack_author' =>    $this->subtrack_author,
            'subtrack_id' =>        $this->subtrack_id,
            'position' =>           $this->position,
            'duration' =>           $this->duration,
        );
        
        return array_filter($arr);

    }

    /**
    http://www.xspf.org/xspf-v1.html#rfc.section.4.1.1.2.14.1.1
    */
    
    function to_xspf_array(){
        $output = array(
            'title'         => $this->title,
            'creator'       => $this->artist,
            'album'         => $this->album
        );
        
        $output = apply_filters('wpsstm_get_track_xspf',$output,$this);
        return array_filter($output);
    }
    
    function get_track_html(){
        global $wpsstm_track;
        $old_track = $wpsstm_track; //store temp
        $wpsstm_track = $this;
        $wpsstm_track->local_track_lookup(); //check for this track in the database (if it has no ID) //TOUFIX TOUCHECK useful ?
        
        ob_start();
        wpsstm_locate_template( 'content-track.php', true, false );
        $content = ob_get_clean();
        
        $wpsstm_track = $old_track; //restore global
        
        return $content;
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
        $new_pos = filter_var($new_pos, FILTER_VALIDATE_INT); //cast to int
        $tracklist_id = $this->tracklist->post_id;
        $last_pos = $this->tracklist->get_last_subtrack_pos();
        
        
        if ( !$this->subtrack_id ){
            return new WP_Error( 'wpsstm_missing_subtrack_id', __("Required subtrack ID missing.",'wpsstm') );
        }
        
        if ( !$tracklist_id ){
            return new WP_Error( 'wpsstm_missing_subtrack_id', __("Required tracklist ID missing.",'wpsstm') );
        }
        
        if ( !is_int($new_pos) || ($new_pos < 1) || ($new_pos > $last_pos) ){
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
            $querystr = $wpdb->prepare( "UPDATE $subtracks_table SET subtrack_order = subtrack_order + 1 WHERE tracklist_id = %d AND subtrack_order < %d AND subtrack_order >= %d",$tracklist_id,$old_pos,$new_pos);
            $result = $wpdb->get_results ( $querystr );
        }else{
            $querystr = $wpdb->prepare( "UPDATE $subtracks_table SET subtrack_order = subtrack_order - 1 WHERE tracklist_id = %d AND subtrack_order > %d AND subtrack_order <= %d",$tracklist_id,$old_pos,$new_pos);
            $result = $wpdb->get_results ( $querystr );
        }
        
        if ( is_wp_error($result) ) return $result;
 
        /*
        https://developer.wordpress.org/reference/classes/wpdb/update/
        returns false if errors, or the number of rows affected if successful.
        */

        $result = $wpdb->update( 
            $subtracks_table, //table
            array('subtrack_order'=>$new_pos),//data
            array('ID'=>$this->subtrack_id)//where
        );

        if ( !$result ){
            $message = __('Error while moving subtrack.','wpsstm');
            return new WP_Error( 'wpsstm_subtrack_position_failed', $message );
        }

        $this->position = $new_pos;
        $this->track_log(array('subtrack_id'=>$this->subtrack_id,'new_position'=>$new_pos,'old_position'=>$old_pos),"moved subtrack");
        
        return $result;

    }

    public function insert_bot_track($args = null){
        
        $valid = $this->validate_track();
        if ( is_wp_error( $valid ) ) return $valid;
        
        //check bot user
        $bot_ready = wpsstm()->is_bot_ready();
        if ( is_wp_error($bot_ready) ) return $bot_ready;
        $bot_id = wpsstm()->get_options('bot_user_id');

        $post_id = null;

        $args_default = array(
            'post_status'   => 'publish',
            'post_author'   => $bot_id,
        );
        
        $args = (!$args) ? $args_default : wp_parse_args($args,$args_default);
        
        //album
        if ($this->album === '_'){
            $this->album = null;
        }

        $meta_input = array(
            WPSSTM_Core_Tracks::$image_url_metakey          => $this->image_url,
            sprintf('_wpsstm_details_%s_id','musicbrainz')  => $this->musicbrainz_id,
            sprintf('_wpsstm_details_%s_id','spotify')      => $this->spotify_id,
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
        $post_id = wp_insert_post( $args, true );
        
        if ( is_wp_error($post_id) ){
            $error_msg = $post_id->get_error_message();
            $this->track_log($error_msg, "Error while saving track details" ); 
            return $post_id;
        }
        
        /*
        taxonomies
        */
        
        wp_set_post_terms( $post_id,$this->artist, WPSSTM_Core_Tracks::$artist_taxonomy );
        wp_set_post_terms( $post_id,$this->title, WPSSTM_Core_Tracks::$track_taxonomy );
        wp_set_post_terms( $post_id,$this->album, WPSSTM_Core_Tracks::$album_taxonomy );

        //repopulate track
        $this->populate_track_post($post_id);

        //save track links from parser if any
        $new_ids = $this->batch_create_links();

        $this->track_log(
            array(
                'post_id'=>$this->post_id,
                'links_saved'=>sprintf( '%s/%s',count($new_ids),count($this->links) ),
                'track'=>json_encode($this->to_array())
            ), "Saved track details" ); 

        return $this->post_id;
        
    }
    
    function toggle_favorite($bool){
        
        if ( !is_bool($bool) ){
            return new WP_Error( 'wpsstm_missing_bool', __("Missing valid bool.",'wpsstm') );
        }
        
        if ( !$user_id = get_current_user_id() ){
            return new WP_Error( 'wpsstm_missing_user_id', __("Missing user ID.",'wpsstm') );
        }

        //create user favorite tracklist
        if ( !$tracklist_id = WPSSTM_Core_User::get_user_favorites_tracklist_id($user_id) ){
            remove_action('wpsstm_love_tracklist', array('WPSSTM_Core_BuddyPress','love_tracklist_activity') ); //no BP activity at tracklist creation / TOUFIX TOUCHECK
            $tracklist_id = WPSSTM_Core_User::create_user_favorites_tracklist($user_id);
            if ( is_wp_error($tracklist_id) ){
                return new WP_Error( 'wpsstm_missing_favorites_tracklist', __("Missing favorites tracklist ID.",'wpsstm') );
            }
        }
        
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
            return new WP_Error( 'wpsstm_missing_capability', __("You don't have the capability required to delete this track.",'wpsstm') );
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

        $querystr = $wpdb->prepare( "DELETE FROM `$subtracks_table` WHERE subtrack_id = '%s'", $this->subtrack_id );
        $result = $wpdb->get_results ( $querystr );

        //update tracks range
        if ( !is_wp_error($result) ){
            $querystr = $wpdb->prepare( "UPDATE $subtracks_table SET subtrack_order = subtrack_order - 1 WHERE tracklist_id = %d AND subtrack_order > %d",$this->tracklist->post_id,$this->position);
            $range_success = $wpdb->get_results ( $querystr );
             $this->track_log(array('subtrack_id'=>$this->subtrack_id,'tracklist'=>$this->tracklist->post_id),"dequeued subtrack");
            $this->subtrack_id = null;
        }
        
        return $result;
    }
    
    function get_favoriters(){
        global $wpdb;

        //get subtracks
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        $ids = WPSSTM_Core_User::get_sitewide_favorites_tracklist_ids();

        if ( !$ids || is_wp_error($ids)  ){
            return $ids;
        }
        $ids_str = implode(',',$ids);
        
        $querystr = sprintf( "SELECT posts.post_author FROM $wpdb->posts posts INNER JOIN %s AS subtracks ON (subtracks.tracklist_id = posts.ID)  WHERE subtracks.tracklist_id IN(%s)", $subtracks_table, $ids_str);

        $querystr = $wpdb->prepare( $querystr ." AND subtracks.track_id = %d",$this->post_id );

        return $wpdb->get_col( $querystr);
    }
    
    function is_track_favorited_by($user_id = null){
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return;
        
        $favorited_by = $this->get_favoriters();
        return in_array($user_id,(array)$favorited_by);
    }
    
    function get_favorited_by_list(){
        $list = null;
        if ( !$user_ids = $this->get_favoriters() ) return;
        
        foreach($user_ids as $user_id){
            $user_info = get_userdata($user_id);
            $link = sprintf('<li><a href="%s" target="_blank">%s</a></li>',get_author_posts_url($user_id),$user_info->user_login);
            $links[] = $link;
        }

        $list = sprintf('<ul class="wpsstm-track-loved-by-list">%s</ul>',implode("\n",$links));

        return $list;
    }
    
    function query_links($args=null){
        $default_args = array(
            'post_status'       => 'publish',
            'posts_per_page'    => -1,
            'orderby'           => 'menu_order',
            'order'             => 'ASC',
        );

        $required_args = array(
            'post_type'     =>      wpsstm()->post_type_track_link,
            'parent_track'  =>      $this->post_id,
            'no_excluded_hosts'=>   true,
        );
        
        //we need a parent track or it will return all links; so force return nothing
        if(!$this->post_id){
            $required_args['post__in'] = array(0);
        }
        
        
        $args = wp_parse_args((array)$args,$default_args);
        $args = wp_parse_args($required_args,$args);

        return new WP_Query($args);
    }
    
    public function populate_links(){
        
        //reset
        $this->links = array();
        $this->link_count = 0;

        if ($this->post_id){
            $args = array(
                'fields' =>             'ids',
            );
            $query = $this->query_links($args);

            $link_ids = $query->posts;
            $this->add_links($link_ids);
        }else{
            $this->add_links($this->links); //so we're sure the links count is set
        }

        return true;
        
    }
    
    /*
    Check if a track has been autolinked recently
    */
    
    private function did_autolink(){

        if (!$this->autolinked) return false;

        $now = current_time( 'timestamp' );
        $seconds = $now - $this->autolinked;

        $max_seconds = wpsstm()->get_options('autolink_timeout');
        return ($seconds < $max_seconds);

    }

    /*
    Retrieve autolinks for a track
    */
    
    function autolink($force = false){

        $new_links = array();
        $links_auto = array();

        $can_autolink = WPSSTM_Core_Track_Links::can_autolink();
        if ( $can_autolink !== true ) return $can_autolink;
        
        if ( !$force && ( $did_autolink = $this->did_autolink() ) ){
            return new WP_Error( 'wpsstm_autolink_disabled', __("Track has already been autolinkd recently.",'wpsstm') );
        }
        
        $this->track_log("start autolink...");

        $valid = $this->validate_track();
        if ( is_wp_error($valid) ) return $valid;

        /*
        save autolink time so we won't query autolinks again too soon
        */
        $now = current_time('timestamp');
        update_post_meta( $this->post_id, WPSSTM_Core_Track_Links::$autolink_time_metakey, $now );
        
        /*
        Hook filter here to add autolinks (array)
        */
        $links_auto = apply_filters('wpsstm_autolink_input',$links_auto,$this);
        if ( is_wp_error($links_auto) ) return $links_auto;

        foreach((array)$links_auto as $key=>$args){
            
            $link = new WPSSTM_Track_Link();
            $link->from_array( $args );
            
            $link->track = $this;
            $link->is_bot = true;

            $new_links[] = $link;

        }

        /*
        Hook filter here to ignore some of the links
        */

        $new_links = apply_filters('wpsstm_autolink_filtered',$new_links,$this);

        //limit autolink results
        $limit_autolinks = (int)wpsstm()->get_options('limit_autolinks');
        $new_links = array_slice($new_links, 0, $limit_autolinks);
        $new_links = apply_filters('wpsstm_track_autolinks',$new_links);

        $this->add_links($new_links);
        $new_ids = $this->batch_create_links();
        
        //repopulate links
        $this->populate_links();
        
        $this->track_log(array('track_id'=>$this->post_id,'links_found'=>$this->link_count,'links_saved'=>count($new_ids)),'autolink results');
        
        return $new_ids;

    }
    
    public function batch_create_links(){

        if ( !$this->post_id ){
            return new WP_Error( 'wpsstm_track_no_id', __('Unable to store link: track ID missing.','wpsstm') );
        }
        

        //insert links
        $inserted = array();
        

        foreach((array)$this->links as $link){

            $link_id = $link->create_track_link($this);
            
            //$link->link_log(array('response'=>$link_id,'track'=>(string)$this,'link'=>$link),"...creating link");
            
            if ( is_wp_error($link_id) ){
                $code = $link_id->get_error_code();
                $error_msg = $link_id->get_error_message($code);
                $link->link_log($error_msg,"...Unable to create link");
                continue;
            }

            $inserted[] = $link_id;

        }

        return $inserted;
    }

    private function get_track_url(){
        $url = home_url();
        
        $post_type_obj = get_post_type_object(wpsstm()->post_type_track);
        
        /*
        Subtrack
        */
        if ( $this->subtrack_id ){
            if ( !get_option('permalink_structure') ){
                $args = array(
                    'post_type' =>      wpsstm()->post_type_track,
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
                    'post_type' =>      wpsstm()->post_type_track,
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

        if ( !get_option('permalink_structure') ){
            $args = array(
                'wpsstm_action' =>     $action,
            );

            $url = add_query_arg($args,$url);
        }else{
            $url .= sprintf('%s/%s/','action',$action);
        }
        
        switch($action){
            //TOUFIX in the end, this should rather open a share popup, and we should not have a switch
            case 'share':
                if ( $url = get_permalink($this->tracklist->post_id) ){
                    $args = array(
                        'subtrack_autoplay' => $this->subtrack_id,
                    );
                    $url = add_query_arg($args,$url);
                }
            break;
        }

        return $url;
    }

    function get_track_links(){
        
        $actions = array();

        $tracklist_id =             $this->tracklist->post_id;
        $post_type_playlist =       $tracklist_id ? get_post_type($tracklist_id) : null;
        $tracklist_post_type_obj =  $post_type_playlist ? get_post_type_object($post_type_playlist) : null;
        $can_edit_tracklist =       ( $tracklist_post_type_obj && current_user_can($tracklist_post_type_obj->cap->edit_post,$tracklist_id) );
        $can_manage_playlists =     WPSSTM_Core_User::can_manage_playlists();
        
        /*
        Track
        */
        $track_type_obj =           get_post_type_object(wpsstm()->post_type_track);
        $can_open_track =           ($this->post_id);
        $can_edit_track =           ( $this->post_id && current_user_can($track_type_obj->cap->edit_post,$this->post_id) );
        $can_delete_track =         ( $this->post_id && current_user_can($track_type_obj->cap->delete_posts) );

        $can_move_subtrack =        ( $this->subtrack_id && $can_edit_tracklist && ($this->tracklist->tracklist_type == 'static') );
        $can_dequeue_track =      ( $this->subtrack_id && $can_edit_tracklist && ($this->tracklist->tracklist_type == 'static') );
        
        //play
        if ( wpsstm()->get_options('player_enabled') ){
            $actions['play'] = array(
                'text' =>      __('Play Track','wpsstm'),
                'href' =>       '#',
            );
        }

        //favorite
        if ( wpsstm()->get_options('playlists_manager') ){
            
            if ( get_current_user_id() ){
                
                if ( $can_manage_playlists ){
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
                    $actions['favorite'] = array(
                        'text' =>      __('Favorite','wpsstm'),
                        'href' =>       '#',
                        'desc' =>       __("Missing required capability.",'wpsstm'),
                        'classes' =>    array('action-favorite','wpsstm-disabled-action','wpsstm-tooltip'),
                    );
                }
                
            }else{
                
                $actions['favorite'] = array(
                    'text' =>      __('Favorite','wpsstm'),
                    'href' =>       '#',
                    'desc' =>       __('This action requires you to be logged.','wpsstm'),
                    'classes' =>    array('action-favorite','wpsstm-disabled-action','wpsstm-tooltip'),
                );
                
            }

        }

        /*
        Subtracks
        */
        
        if ($this->tracklist->tracklist_type == 'static'){
            $actions['share'] = array(
                'text' =>      __('Share'),
                'desc' =>       __('Share track','wpsstm'),
                'href' =>       $this->get_track_action_url('share'),
                'target' =>     '_blank',
                'classes' =>    array('no-link-icon'), //TOUFIX this is for gordo theme, should not be in this plugin
            );
        }
        
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
        if ( wpsstm()->get_options('playlists_manager') ){
            
            if ( get_current_user_id() ){
            
                if ( $can_manage_playlists ){ 
                    $actions['toggle-tracklists'] = array(
                        'text' =>      __('Playlists manager','wpsstm'),
                        'href' =>       $this->get_track_action_url('manage'),
                        'classes' =>    array('wpsstm-action-popup'),
                    );
                }else{
                    $actions['toggle-tracklists'] = array(
                        'text' =>      __('Favorite','wpsstm'),
                        'href' =>       '#',
                        'desc' =>       __("Missing required capability.",'wpsstm'),
                        'classes' =>    array('wpsstm-disabled-action','wpsstm-tooltip'),
                    );
                }
            }else{
                $actions['toggle-tracklists'] = array(
                    'text' =>      __('Playlists manager','wpsstm'),
                    'href' =>       '#',
                    'desc' =>       __('This action requires you to be logged.','wpsstm'),
                    'classes' =>    array('wpsstm-disabled-action','wpsstm-tooltip'),
                );
            }

        }else{

        }

        //delete track
        if ($can_delete_track){
            $actions['trash'] = array(
                'text' =>      __('Trash'),
                'classes' =>    array('wpsstm-advanced-action'),
                'desc' =>       __('Trash this track','wpsstm'),
                'href' =>       $this->get_track_action_url('trash'),
            );
            
            $is_trashed = ( get_post_type($this->post_id) === 'trash' );
            if ($is_trashed){
                $actions['trash']['classes'][] = 'wpsstm-freeze';
            }
            
        }
        
        //backend
        if ($can_edit_track){
            $actions['edit-backend'] = array(
                'text' =>      __('Edit Track','wpsstm'),
                'classes' =>    array('wpsstm-advanced-action','wpsstm-action-popup'),
                'href' =>       get_edit_post_link( $this->post_id ),
            );
        }
        
        $actions['toggle-links'] = array(
            'text' =>      __('Tracks Links'),
            'href' =>       '#',
        );

        return apply_filters('wpsstm_track_actions',$actions);

    }
    
    function get_track_attr($args=array()){
        global $wpsstm_tracklist;
        $can_autolink = ( WPSSTM_Core_Track_Links::can_autolink() === true);

        $attr = array(
            'itemscope' =>                      true,
            'itemtype' =>                       "http://schema.org/MusicRecording",
            'itemprop' =>                       'track',
            'class' =>                          implode( ' ',$this->get_track_class() ),
            'data-wpsstm-subtrack-id' =>        $this->subtrack_id,
            'data-wpsstm-subtrack-position' =>  $this->position,
            'data-wpsstm-track-id' =>           $this->post_id,
            'can-autolink' =>                   ( $can_autolink && !$this->did_autolink() ),
            'ajax-tracks' =>                    ( wpsstm()->get_options('ajax_tracks') && !wp_doing_ajax() ),
            'wpsstm-playable' =>                wpsstm()->get_options('player_enabled'),
        );

        return wpsstm_get_html_attr($attr);
    }
    
    function get_track_class(){

        $add_classes = array(
            ( $this->is_track_favorited_by() ) ? 'favorited-track' : null,
            is_wp_error( $this->validate_track() ) ? 'wpsstm-invalid-track' : null,//TOUFIX URGENT NEEDED ?
            ( ( $autoplay_id = wpsstm_get_array_value('subtrack_autoplay',$_GET) ) && ($autoplay_id == $this->subtrack_id) ) ? 'track-autoplay' : null,
        );

        $classes = array_merge($this->classes,$add_classes);
        $classes = array_filter(array_unique($classes));
        
        $classes = apply_filters('wpsstm_track_classes',$classes,$this);

        return $classes;
    }
    
    /*
    $input_links = array of links objects or array of link IDs
    */
    
    function add_links($input_links){

        $add_links = array();
        if(!$input_links) return;

        
        foreach ((array)$input_links as $link){

            if ( is_a($link, 'WPSSTM_Track_Link') ){
                $link_obj = $link;
            }else{
                if ( is_array($link) ){
                    $link_args = $link;
                    $link_obj = new WPSSTM_Track_Link(null);
                    $link_obj->from_array($link_args);
                }else{ //link ID
                    $link_id = $link;
                    //TO FIX check for int ?
                    $link_obj = new WPSSTM_Track_Link($link_id);
                }
            }

            $valid = $link_obj->validate_link();

            if ( is_wp_error($valid) ){
                $code = $valid->get_error_code();
                $error_msg = $valid->get_error_message($code);
                $link_obj->link_log(array('error'=>$error_msg,'link'=>$link_obj),"Unable to add link");
                continue;
            }

            $link_obj->track = $this;
            $add_links[] = $link_obj;
            
        }

        //allow users to alter the input links.
        $add_links = apply_filters('wpsstm_links_input',$add_links,$this);

        $this->links = array_merge((array)$this->links,(array)$add_links);
        $this->link_count = count($this->links);

        return $this->links;
    }
    
    /**
	 * Set up the next link and iterate current link index.
	 * @return WP_Post Next link.
	 */
	public function next_link() {

		$this->current_link++;

		$this->link = $this->links[$this->current_link];
		return $this->link;
	}

	/**
	 * Sets up the current link.
	 * Retrieves the next link, sets up the link, sets the 'in the loop'
	 * property to true.
	 * @global WP_Post $wpsstm_link
	 */
	public function the_track_link() {
		global $wpsstm_link;
		$this->in_link_loop = true;

		if ( $this->current_link == -1 ) // loop has just started
			/**
			 * Fires once the loop is started.
			 *
			 * @since 2.0.0
			 *
			 * @param WP_Query &$this The WP_Query instance (passed by reference).
			 */
			do_action_ref_array( 'wpsstm_links_loop_start', array( &$this ) );

		$wpsstm_link = $this->next_link();
		//$this->setup_linkdata( $wpsstm_link );
	}

	/**
	 * Determines whether there are more links available in the loop.
	 * Calls the {@see 'wpsstm_links_loop_end'} action when the loop is complete.
	 * @return bool True if links are available, false if end of loop.
	 */
	public function have_links() {
        
		if ( $this->current_link + 1 < $this->link_count ) {
			return true;
		} elseif ( $this->current_link + 1 == $this->link_count && $this->link_count > 0 ) {
			/**
			 * Fires once the loop has ended.
			 *
			 * @since 2.0.0
			 *
			 * @param WP_Query &$this The WP_Query instance (passed by reference).
			 */
			do_action_ref_array( 'wpsstm_links_loop_end', array( &$this ) );
			// Do some cleaning up after the loop
			$this->rewind_links();
		} elseif ( 0 === $this->link_count ) {
            do_action( 'links_loop_no_results', $this );
        }

		$this->in_link_loop = false;
		return false;
	}

	/**
	 * Rewind the links and reset link index.
	 * @access public
	 */
	public function rewind_links() {
		$this->current_link = -1;
		if ( $this->link_count > 0 ) {
			$this->link = $this->links[0];
		}
	}
    
    function user_can_reorder_links(){
        $track_type_obj = get_post_type_object(wpsstm()->post_type_track);
        $required_cap = $track_type_obj->cap->edit_posts;
        $can_edit_track = current_user_can($required_cap,$this->post_id);

        $link_type_obj = get_post_type_object(wpsstm()->post_type_track_link);
        $can_edit_links = current_user_can($link_type_obj->cap->edit_posts);
        
        return ($can_edit_track && $can_edit_links);
    }
    
    function get_backend_links_url(){
        $links_url = admin_url('edit.php');
        $links_url = add_query_arg( 
            array(
                'post_type'         => wpsstm()->post_type_track_link,
                'parent_track'      => $this->post_id,
                //'post_status' => 'publish'
            ),$links_url 
        );
        return $links_url;
    }

    function update_links_order($link_ids){
        global $wpdb;
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_post_id', __("Missing track ID.",'wpsstm') );
        }

        if ( !$this->user_can_reorder_links() ){
            return new WP_Error( 'wpsstm_missing_capability', __("You don't have the capability required to reorder links.",'wpsstm') );
        }

        foreach((array)$link_ids as $order=>$post_id){
            
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
            WP_SoundSystem::debug_log($data,$title);
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
    
    private function clear_now_playing(){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        $nowplaying_id = wpsstm()->get_options('nowplaying_id');
        if (!$nowplaying_id) return new WP_Error('missing_nowplaying_id','Missing Now Playing Radio ID');
        
        if ( !$delay = wpsstm()->get_options('play_history_timeout') ) return false;
        
        $now = current_time('timestamp');
        $limit = $now - $delay;
        $limit_datetime = date("Y-m-d H:i:s", $limit);
        
        $query = $wpdb->prepare(" DELETE FROM `$subtracks_table` WHERE tracklist_id = %s AND subtrack_time < %s",$nowplaying_id,$limit_datetime);
        //WP_SoundSystem::debug_log($query,"clear now playing");
        return $wpdb->query($query);

    }
    
    function insert_now_playing(){
        $nowplaying_id = wpsstm()->get_options('nowplaying_id');
        if (!$nowplaying_id) return new WP_Error('missing_nowplaying_id','Missing Now Playing Radio ID');
        
        $clear = $this->clear_now_playing();
        
        $now_track = clone $this;
        $now_track->subtrack_id = null; //reset subtrack
        $now_track->subtrack_author = get_current_user_id();
        
        $tracklist = new WPSSTM_Post_Tracklist($nowplaying_id);
        return $tracklist->insert_subtrack($now_track);
    }
    
    /*
    Populate the basic track informations
    Post : int|WP_Post|null
    */

    private function populate_track_post($post = null){

        $post = get_post($post);
        if ( get_post_type($post) != wpsstm()->post_type_track ) return;

        $this->post_id =    $post->ID;

        /*
        Get basic infos : artist, title & album.
        Since WP caches terms alongside with the query results, we can get those without hitting the DB
        https://wordpress.stackexchange.com/a/227450/70449
        */

        $this->artist =     wpsstm_get_post_artist($this->post_id);
        $this->title =      wpsstm_get_post_track($this->post_id);
        $this->album =      wpsstm_get_post_album($this->post_id);

        /*
        Subtrack
        */

        if ( isset($post->subtrack_id) ){
            $this->subtrack_id =        filter_var($post->subtrack_id, FILTER_VALIDATE_INT);
            $this->subtrack_time =      $post->subtrack_time;
            $this->subtrack_author =    filter_var($post->subtrack_author, FILTER_VALIDATE_INT);
            $this->position =           filter_var($post->subtrack_order, FILTER_VALIDATE_INT);
            $this->tracklist =          new WPSSTM_Post_Tracklist($post->tracklist_id);
            $this->from_tracklist =     filter_var($post->from_tracklist, FILTER_VALIDATE_INT);
        }
        
        return $this->post_id;

    }

    private function populate_subtrack_id($subtrack_id){
        
        //get post
        $track_args = array(
            'post_type' =>              wpsstm()->post_type_track,
            'subtrack_query' =>         true,
            'subtrack_id' =>            $subtrack_id
        );

        $query = new WP_Query( $track_args );
        $posts = $query->posts;
        
        $post = isset($posts[0]) ? $posts[0] : null;
        if (!$post) return;

        //populate post
        return $this->populate_subtrack_track_post($post);
    }

    /*
    Populate extended track informations, usually post metas
    */
    
    public function populate_track_metas(){
        if (!$this->post_id) return;
        $this->image_url        = wpsstm_get_post_image_url($this->post_id);
        $this->duration         = wpsstm_get_post_duration($this->post_id);
        $this->autolinked       = get_post_meta( $this->post_id, WPSSTM_Core_Track_Links::$autolink_time_metakey, true );
    }
    
}