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
                            'url' => $link_url,
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
    Get a list of pairs tracklist_id->subtrack_id based on the track id.
    */
    function get_subtrack_pairs(){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        $querystr = sprintf("SELECT `subtrack_id`,`tracklist_id` FROM `$subtracks_table` WHERE `track_id`=%d",$this->post_id );

        $results = $wpdb->get_results($querystr);
        if ( is_wp_error($results) || empty($results) ) return $results;

        $pairs = array();

        foreach((array)$results as $result){
          $pairs[$result->tracklist_id] = $result->subtrack_id;
        }

        return $pairs;

    }

    /*
    Get the ID of the subtrack matching this track within the tracklist of favorites tracks
    */

    function get_matching_favorites_id(){
      if ( !$tracklist_id = WPSSTM_Core_User::get_user_favtracks_playlist_id() ) return;
      $subtracks = $this->get_subtrack_pairs();
      if ( is_wp_error($subtracks) ) return $subtracks;
      return ( isset($subtracks[$tracklist_id]) ) ? $subtracks[$tracklist_id] : null;
    }

    /*
    Get the IDs of the parent tracklists for a track.
    */
    function get_in_tracklists_ids(){
      $subtracks = $this->get_subtrack_pairs();
      if ( is_wp_error($subtracks) ) return $subtracks;
      return array_keys($subtracks);
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
            array('subtrack_id'=>$this->subtrack_id)//where
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
        if ( !$tracklist_id = WPSSTM_Core_User::get_user_favtracks_playlist_id($user_id) ){
            remove_action('wpsstm_love_tracklist', array('WPSSTM_Core_BuddyPress','love_tracklist_activity') ); //no BP activity at tracklist creation / TOUFIX TOUCHECK
            $tracklist_id = WPSSTM_Core_User::create_user_favtracks_playlist($user_id);
            if ( is_wp_error($tracklist_id) ){
                return new WP_Error( 'wpsstm_missing_favorites_tracklist', __("Missing favorites tracklist ID.",'wpsstm') );
            }
        }

        $tracklist = new WPSSTM_Post_Tracklist($tracklist_id);

        if ($bool){
          $success = $tracklist->queue_track($this);
        }else{
          $favorite_id = $this->get_matching_favorites_id();
          if ( $favorite_id && !is_wp_error($favorite_id) ){
            $favorite_subtrack = new WPSSTM_Track();
            $favorite_subtrack->populate_subtrack_id($favorite_id);
            $success = $tracklist->dequeue_track($favorite_subtrack);
          }
        }

        $this->track_log(array('track'=>$this->to_array(),'do_love'=>$bool,'success'=>$success),"toggle favorite");

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

    function get_favoriters(){
        global $wpdb;

        //get subtracks
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        $ids = WPSSTM_Core_User::get_sitewide_favtracks_playlist_ids();

        if ( !$ids || is_wp_error($ids)  ){
            return $ids;
        }
        $ids_str = implode(',',$ids);

        $querystr = sprintf( "SELECT posts.post_author FROM $wpdb->posts posts INNER JOIN %s AS subtracks ON (subtracks.tracklist_id = posts.ID)  WHERE subtracks.tracklist_id IN(%s)", $subtracks_table, $ids_str);

        $querystr = $wpdb->prepare( $querystr ." AND subtracks.track_id = %d",$this->post_id );

        return $wpdb->get_col( $querystr);
    }

    function has_track_favoriters($user_id = null){
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return;

        $favoriters = $this->get_favoriters();
        return in_array($user_id,(array)$favoriters);
    }

    function get_favoriters_list(){
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

            if ( !$link_ids && !wpsstm()->get_options('ajax_autolink') ){
                $autolink_ids = $this->autolink();
                $link_ids = ( !is_wp_error($autolink_ids) ) ? $autolink_ids : null;
            }

            $this->add_links($link_ids);

        }else{
            $this->add_links($this->links); //so we're sure the links count is set
        }

        return true;

    }

    /*
    Check if a track has been autolinked recently
    */

    public function is_autolink_timelock(){

        if ( !$autolinked = get_post_meta( $this->post_id, WPSSTM_Core_Track_Links::$autolink_time_metakey, true ) ) return;

        $now = current_time( 'timestamp' );
        $seconds = $now - $autolinked;

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

        if ( !$force && ( $this->is_autolink_timelock() ) ){
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

        //limit to X links per host (we don't want 20 youtube links)
        if ( $limit_autolinks = (int)wpsstm()->get_options('limit_autolinks') ){

          $freq = []; // frequency table
          $new_links = array_filter($new_links, function($link) use($limit_autolinks,&$freq) {

            $host = parse_url($link->url, PHP_URL_HOST);
            $freq[$host] = ($freq[$host] ?? 0) + 1;
            return $freq[$host] <= $limit_autolinks;
          });

        }

        $new_links = apply_filters('wpsstm_track_autolinks',$new_links);

        $this->add_links($new_links);
        $new_ids = $this->batch_create_links();

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

    function get_track_context_menu_items(){

        $items = array();

        $tracklist_id =             $this->tracklist->post_id;
        $post_type_playlist =       $tracklist_id ? get_post_type($tracklist_id) : null;
        $tracklist_post_type_obj =  $post_type_playlist ? get_post_type_object($post_type_playlist) : null;
        $can_edit_tracklist =       ( $tracklist_post_type_obj && current_user_can($tracklist_post_type_obj->cap->edit_post,$tracklist_id) );
        $can_manage_playlists =     WPSSTM_Core_User::can_manage_playlists();
        $can_play_track =           wpsstm()->get_options('player_enabled') && $this->tracklist->get_options('playable');

        /*
        Track
        */
        $track_type_obj =           get_post_type_object(wpsstm()->post_type_track);
        $can_open_track =           ($this->post_id);
        $can_edit_track =           ( $this->post_id && current_user_can($track_type_obj->cap->edit_post,$this->post_id) );
        $can_delete_track =         ( $this->post_id && current_user_can($track_type_obj->cap->delete_posts) );

        $can_move_subtrack =        ( $this->subtrack_id && $can_edit_tracklist && ($this->tracklist->tracklist_type == 'static') );
        $can_dequeue_track =        ( $this->subtrack_id && $can_edit_tracklist && ($this->tracklist->tracklist_type == 'static') );

        //play
        if ( $can_play_track ){

          $items['play'] = sprintf(
            '<a %s><span>%s</span></a>',
            wpsstm_get_html_attr(
              array(
                'href' =>     '#',
                'class' =>    implode(' ',array(
                  'wpsstm-action',
                  'wpsstm-track-action',
                  'wpsstm-track-action-play'
                )),
                'title' =>    __('Play Track','wpsstm'),
                'rel' =>      'nofollow',
              )
            ),
            __('Play Track','wpsstm')
          );

        }


        /*
        toggle favorite
        */
        if ( wpsstm()->get_options('playlists_manager') && ( !get_current_user_id() || $can_manage_playlists ) ){

          //favorite
          $url_favorite = $this->get_track_action_url('favorite');
          $items['favorite'] = sprintf(
            '<a %s><span>%s</span></a>',
            wpsstm_get_html_attr(
              array(
                'href' =>     get_current_user_id() ? $url_favorite : wp_login_url($url_favorite),
                'class' =>    implode(' ',array(
                  'wpsstm-action',
                  'wpsstm-track-action',
                  'wpsstm-track-action-favorite',
                  'action-favorite'
                )),
                'title' =>    __('Add track to favorites','wpsstm'),
                'rel' =>      'nofollow',
              )
            ),
            __('Favorite','wpsstm')
          );

          //unfavorite
          $url_unfavorite = $this->get_track_action_url('unfavorite');
          $items['unfavorite'] = sprintf(
            '<a %s><span>%s</span></a>',
            wpsstm_get_html_attr(
              array(
                'href' =>     get_current_user_id() ? $url_unfavorite : wp_login_url($url_unfavorite),
                'class' =>    implode(' ',array(
                  'wpsstm-action',
                  'wpsstm-track-action',
                  'wpsstm-track-action-unfavorite',
                  'action-unfavorite'
                )),
                'title' =>    __('Remove track from favorites','wpsstm'),
                'rel' =>      'nofollow',
              )
            ),
            __('Favorite','wpsstm')
          );

        }

        /*
        Subtracks
        */

        if ($this->tracklist->tracklist_type == 'static'){

          $items['share'] = sprintf(
            '<a %s><span>%s</span></a>',
            wpsstm_get_html_attr(
              array(
                'href'=>    $this->get_track_action_url('share'),
                'class'=>   implode(' ',array(
                  'wpsstm-action',
                  'wpsstm-track-action',
                  'wpsstm-track-action-share',
                  'no-link-icon'
                )),
                'title'=>   __('Share track','wpsstm'),
                'rel'=>     'nofollow',
                'target'=>  '_blank',
              )
            ),
            __('Share'),
          );

        }

        if ($can_dequeue_track){

          $items['dequeue'] = sprintf(
            '<a %s><span>%s</span></a>',
            wpsstm_get_html_attr(
              array(
                'href'=>    $this->get_track_action_url('dequeue'),
                'class'=>   implode(' ',array(
                  'wpsstm-action',
                  'wpsstm-track-action',
                  'wpsstm-track-action-remove',
                  'wpsstm-advanced-action'
                )),
                'title'=>   __('Remove from tracklist','wpsstm'),
                'rel'=>     'nofollow',
              )
            ),
            __('Remove'),
          );

        }

        if ($can_move_subtrack){

          $items['move'] = sprintf(
            '<a %s><span>%s</span></a>',
            wpsstm_get_html_attr(
              array(
                'href'=>    $this->get_track_action_url('move'),
                'class'=>   implode(' ',array(
                  'wpsstm-action',
                  'wpsstm-track-action',
                  'wpsstm-track-action-move',
                  'wpsstm-advanced-action'
                )),
                'title'=>   __('Drag to move track in tracklist', 'wpsstm'),
                'rel'=>     'nofollow',
              )
            ),
            __('Move', 'wpsstm'),
          );

        }

        //playlists manager
        if ( wpsstm()->get_options('playlists_manager') ){

            $url = $this->get_track_action_url('manage');

            $items['tracklists'] = sprintf(
              '<a %s><span>%s</span></a>',
              wpsstm_get_html_attr(
                array(
                  'href'=>    get_current_user_id() ? $url : wp_login_url($url),
                  'class'=>   implode(' ',array(
                    'wpsstm-action',
                    'wpsstm-track-action',
                    'wpsstm-track-action-tracklists',
                    'wpsstm-action-popup',
                    ( get_current_user_id() && !$can_manage_playlists ) ? 'wpsstm-freeze' : null,
                  )),
                  'title'=>   __('Playlists manager','wpsstm'),
                  'rel'=>     'nofollow',
                )
              ),
              __('Playlists manager','wpsstm'),
            );

        }

        //delete track
        if ($can_delete_track){

          $items['trash'] = sprintf(
            '<a %s><span>%s</span></a>',
            wpsstm_get_html_attr(
              array(
                'href'=>    $this->get_track_action_url('trash'),
                'class'=>   implode(' ',array(
                  'wpsstm-action',
                  'wpsstm-track-action',
                  'wpsstm-track-action-trash',
                  ( get_post_type($this->post_id) === 'trash' ) ? 'wpsstm-freeze' : null,
                )),
                'title'=>   __('Trash'),
                'rel'=>     'nofollow',
              )
            ),
            __('Trash'),
          );


        }

        //backend
        if ($can_edit_track){

          $items['edit'] = sprintf(
            '<a %s><span>%s</span></a>',
            wpsstm_get_html_attr(
              array(
                'href'=>    get_edit_post_link( $this->post_id ),
                'class'=>   implode(' ',array(
                  'wpsstm-action',
                  'wpsstm-track-action',
                  'wpsstm-track-action-edit',
                  'wpsstm-advanced-action',
                  'wpsstm-action-popup'
                )),
                'title'=>   __('Edit'),
                'rel'=>     'nofollow',
              )
            ),
            __('Edit'),
          );

        }

        $items['links'] = sprintf(
          '<a %s><span>%s</span></a>',
          wpsstm_get_html_attr(
            array(
              'href'=>    '#',
              'class'=>   implode(' ',array(
                'wpsstm-action',
                'wpsstm-track-action',
                'wpsstm-track-action-links',
              )),
              'title'=>   __('Links','wpsstm'),
              'rel'=>     'nofollow',
            )
          ),
          __('Links','wpsstm'),
        );

        return apply_filters('wpsstm_track_context_menu_items',$items);

    }

    function get_track_attr($args=array()){

        $attr = array(
            'itemscope' =>                      true,
            'itemtype' =>                       "http://schema.org/MusicRecording",
            'itemprop' =>                       'track',
            'class' =>                          implode( ' ',$this->get_track_classes() ),
            'data-wpsstm-subtrack-id' =>        $this->subtrack_id,
            'data-wpsstm-subtrack-position' =>  $this->position,
            'data-wpsstm-track-id' =>           $this->post_id,
            'can-autolink' =>                   !$this->is_autolink_timelock(),
            'wpsstm-playable' =>                wpsstm()->get_options('player_enabled'),
        );

        return wpsstm_get_html_attr($attr);
    }

    private function get_track_classes(){

        $add_classes = array(
            ( $this->has_track_favoriters() ) ? 'favorited-track' : null,
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

    function populate_subtrack_id($subtrack_id){

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
        return $this->populate_track_post($post);
    }

}
