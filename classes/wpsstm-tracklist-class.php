<?php

class WPSSTM_Post_Tracklist extends WPSSTM_Tracklist{
    
    var $post_id = 0; //tracklist ID (can be an album, playlist or radio)
    var $index = -1;
    var $tracklist_type = 'static';
    
    var $default_options = array();
    var $options = array();
    
    var $updated_time = null;
    public $is_expired = false;
    
    var $pagination = array(
        'total_pages'       => null,
        'per_page'          => null,
        'current_page'      => null,
    );

    var $paged_var = 'tracklist_page';

    //live
    static $scraper_meta_name = '_wpsstm_scraper_options';
    static $feed_url_meta_name = '_wpsstm_scraper_url';
    static $website_url_meta_name = '_wpsstm_website_url';
    private static $remote_title_meta_name = 'wpsstm_remote_title';
    public $feed_url = null;
    public $website_url = null;
    public $cache_min = null;
    
    var $preset;
    
    public $classes = array();

    function __construct($post_id = null ){
        
        $post_id = filter_var($post_id, FILTER_VALIDATE_INT); //cast to int
        
        $pagination_args = array(
            'per_page'      => 0, //TO FIX default option
            'current_page'  => ( isset($_REQUEST[$this->paged_var]) ) ? $_REQUEST[$this->paged_var] : 1
        );
        
        /*
        options
        */
        
        $can_autolink = ( WPSSTM_Core_Track_Links::can_autolink() === true);
        
        $this->default_options = array(
            'autolink'                => ( wpsstm()->get_options('autolink') && $can_autolink ),
            'cache_min'          => 15, //toufix broken if within the default options of WPSSTM_Remote_Tracklist
        );

        $this->set_tracklist_pagination($pagination_args);

        //has tracklist ID
        if ( is_int($post_id) ) {
            $this->post_id = $post_id;
            $this->populate_tracklist_post();
        }
    }
    
    static function get_tracklist_title($post_id){
        //title
        $title = get_post_field( 'post_title', $post_id );

        $post_type = get_post_type($post_id);
        
        //if no title has been set, use the cached title if any
        if ( !$title && in_array($post_type,wpsstm()->tracklist_post_types) ){
            $title = self::get_cached_title($post_id);
        }

        return $title;
    }
    
    static public function get_cached_title($post_id){
        return get_post_meta($post_id,self::$remote_title_meta_name,true);
    }
    
    function populate_tracklist_post(){
        
        $post_type = get_post_type($this->post_id);

        if ( !$this->post_id || ( !in_array($post_type,wpsstm()->tracklist_post_types) ) ){
            $this->tracklist_log('Invalid tracklist post');
            return;
        }
        
        //options
        $db_options = get_post_meta($this->post_id,self::$scraper_meta_name,true);
        $this->options = array_replace_recursive($this->default_options,(array)$db_options); //last one has priority

        //type        
        $this->tracklist_type = ($post_type == wpsstm()->post_type_live_playlist) ? 'live' : 'static';
        
        //title (will be filtered)
        $this->title = get_the_title($this->post_id);
        
        //author
        $post_author_id = get_post_field( 'post_author', $this->post_id );
        $this->author = get_the_author_meta( 'display_name', $post_author_id );

        //live
        $this->feed_url =       get_post_meta($this->post_id, self::$feed_url_meta_name, true );
        $this->website_url =    get_post_meta($this->post_id, self::$website_url_meta_name, true );
        $this->cache_min =      ($meta = get_post_meta($this->post_id,WPSSTM_Core_Live_Playlists::$cache_min_meta_name,true)) ? $meta : $this->options['cache_min'];
        
        //time updated
        //TOUFIX bad logic.  We should rather update the post time when an import is done.
        if ( $last_import_time = get_post_meta($this->post_id,WPSSTM_Core_Live_Playlists::$time_updated_meta_name,true) ){
            $this->updated_time = (int)$last_import_time;
        }else{
            $this->updated_time = (int)get_post_modified_time( 'U', true, $this->post_id, true );
        }
        
        if ( $this->tracklist_type === 'live' ){
            $seconds = $this->seconds_before_refresh();
            $this->is_expired = ( ($seconds !== false) && ($seconds <= 0) );
        }

        //location
        $this->location = get_permalink($this->post_id);
        if ( $this->tracklist_type == 'live' ){
            $this->location = $this->feed_url;
        }

        //classes
        if( $this->is_tracklist_favorited_by() ) {
            $this->classes[] = 'favorited-tracklist';
        }

    }
    
    function get_options($keys=null){
        if ($keys){
            return wpsstm_get_array_value($keys, $this->options);
        }else{
            return $this->options;
        }
    }

    function validate_playlist(){
        if(!$this->title){
            return new WP_Error( 'wpsstm_playlist_title_missing', __('Please enter a title for this playlist.','wpsstm') );
        }
        return true;
    }

    function save_tracklist(){

        //capability check
        $post_type = ($this->tracklist_type == 'static') ? wpsstm()->post_type_playlist : wpsstm()->post_type_live_playlist;
        $post_type_obj = get_post_type_object($post_type);
        $required_cap = ($this->post_id) ? $post_type_obj->cap->edit_posts : $post_type_obj->cap->create_posts;

        if ( !current_user_can($required_cap) ){
            return new WP_Error( 'wpsstm_missing_capability', __("You don't have the capability required to create a new playlist.",'wpsstm') );
        }
        
        $validated = $this->validate_playlist();
        if ( !$validated ){
            return new WP_Error( 'wpsstm_missing_capability', __('Error while validating the playlist.','wpsstm') );
        }elseif( is_wp_error($validated) ){
            return $validated;
        }

        $post_playlist_id = null;
        
        $meta_input = array();
        $meta_input = array_filter($meta_input);
        
        $post_playlist_args = array('meta_input' => $meta_input);

        if (!$this->post_id){ //not a playlist update

            $post_playlist_new_args = array(
                'post_type'     => wpsstm()->post_type_playlist,
                'post_status'   => 'draft',
                'post_author'   => get_current_user_id(),
                'post_title'    => $this->title,
            );

            $post_playlist_new_args = wp_parse_args($post_playlist_new_args,$post_playlist_args);

            //TOUFIX TOUCHECK post_date is OK but post_date_gmt doesn't update. = WP bug on drafts ?
            // see https://ryansechrest.com/2013/02/no-value-for-post_date_gmt-and-post_modified_gmt-when-creating-drafts-in-wordpress/ ?
            $success = wp_insert_post( $post_playlist_new_args, true );
            if ( is_wp_error($success) ) return $success;
            $post_playlist_id = $success;

            $this->tracklist_log( array('post_id'=>$post_playlist_id,'args'=>json_encode($post_playlist_new_args)), "WPSSTM_Post_Tracklist::save_tracklist() - post playlist inserted"); 

        }else{ //is a playlist update
            
            $post_playlist_update_args = array(
                'ID'            => $this->post_id
            );
            
            $post_playlist_update_args = wp_parse_args($post_playlist_update_args,$post_playlist_args);
            
            $success = wp_update_post( $post_playlist_update_args, true );
            if ( is_wp_error($success) ) return $success;
            $post_playlist_id = $success;
            
           $this->tracklist_log( array('post_id'=>$post_playlist_id,'args'=>json_encode($post_playlist_update_args)), "WPSSTM_Post_Tracklist::save_tracklist() - post track updated"); 
        }

        $this->post_id = $post_playlist_id;

        return $this->post_id;
        
    }

    function get_tracklist_html(){
        global $wpsstm_tracklist;
        $old_tracklist = $wpsstm_tracklist; //store temp
        $wpsstm_tracklist = $this;

        ob_start();
        wpsstm_locate_template( 'content-tracklist.php', true, false );
        $content = ob_get_clean();
        
        $wpsstm_tracklist = $old_tracklist; //restore global
        
        return $content;


    }

    public function set_tracklist_pagination( $args ) {

        $args = wp_parse_args( $args, $this->pagination );

        if ( ( $args['per_page'] > 0 ) && ( $this->track_count ) ){
            $args['total_pages'] = ceil( $this->track_count / $args['per_page'] );
        }

        $this->pagination = $args;
    }
    
    /*
    Use a custom function to display our notices since natice WP function add_settings_error() works only backend.
    */
    
    function add_notice($code,$message,$error = false){
        
        //$this->tracklist_log(array('slug'=>$slug,'code'=>$code,'error'=>$error),'[WPSSTM_Post_Tracklist notice]: ' . $message ); 
        
        $this->notices[] = array(
            'code'      => $code,
            'message'   => $message,
            'error'     => $error
        );

    }
    
    function love_tracklist($do_love){
        
        if ( !$user_id = get_current_user_id() ) return new WP_Error('no_user_id',__("User is not logged",'wpsstm'));
        if ( !$this->post_id ) return new WP_Error('no_tracklist_id',__("This tracklist does not exists in the database",'wpsstm'));

        //capability check
        //TO FIX we should add a meta to the user rather than to the tracklist, and check for another capability here ?
        /*
        $post_type = get_post_type($this->post_id);
        $tracklist_obj = get_post_type_object($post_type);
        if ( !in_array($post_type,wpsstm()->tracklist_post_types) ) return false;
        if ( !current_user_can($tracklist_obj->cap->edit_post,$this->post_id) ){
            return new WP_Error( 'wpsstm_missing_capability', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        */

        if ($do_love){
            $success = add_post_meta( $this->post_id, WPSSTM_Core_User::$loved_tracklist_meta_key, $user_id );
            do_action('wpsstm_love_tracklist',$this->post_id,$this);
        }else{
            $success = delete_post_meta( $this->post_id,WPSSTM_Core_User::$loved_tracklist_meta_key, $user_id );
            do_action('wpsstm_unlove_tracklist',$this->post_id,$this);
        }
        return $success;
    }
    
    function get_tracklist_favorited_by(){
        if ( !$this->post_id ) return false;
        return get_post_meta($this->post_id, WPSSTM_Core_User::$loved_tracklist_meta_key);
    }
    
    function is_tracklist_favorited_by($user_id = null){
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return;
        if ( !$this->post_id ) return false;
        
        $favorited_by = $this->get_tracklist_favorited_by();
        return in_array($user_id,(array)$favorited_by);
    }
    
    function get_tracklist_actions(){
        
        $tracklist_post_type = get_post_type($this->post_id);
        
        //no tracklist actions if this is a "track" tracklist
        if ($tracklist_post_type == wpsstm()->post_type_track ) return;
        
        /*
        Capability check
        */

        //playlist
        $permalink = get_permalink($this->post_id);
        
        $post_type_obj = get_post_type_object($tracklist_post_type);
        
        $tracklist_status = get_post_status($this->post_id);
        $current_status_obj = get_post_status_object( $tracklist_status );

        $can_edit_tracklist = ($this->post_id && current_user_can($post_type_obj->cap->edit_post,$this->post_id) );
        $can_trash_tracklist = current_user_can($post_type_obj->cap->delete_post,$this->post_id);

        $actions = array();


        
        //share
        $actions['share'] = array(
            'text' =>       __('Share', 'wpsstm'),
            'classes' =>    array('wpsstm-advanced-action'),
            'href' =>       $this->get_tracklist_action_url('share'),
            'classes' =>    array('wpsstm-tracklist-popup'),
        );
        
        //export
        $dl_link = $this->get_tracklist_action_url('export');
        $dl_link = add_query_arg(array('dl'=>true),$dl_link);
        $actions['export'] = array(
            'text' =>       __('Export', 'wpsstm'),
            'classes' =>    array('wpsstm-advanced-action'),
            'desc' =>       __('Export to XSPF', 'wpsstm'),
            'href' =>       $dl_link,
            'target' =>     '_blank',
        );
        
        //favorite / unfavorite
        if ( get_current_user_id() ){

            $actions['favorite'] = array(
                'text' =>      __('Favorite','wpsstm'),
                'href' =>       $this->get_tracklist_action_url('favorite'),
                'desc' =>       __('Add tracklist to favorites','wpsstm'),
                'classes' =>    array('action-favorite'),
            );
            $actions['unfavorite'] = array(
                'text' =>      __('Unfavorite','wpsstm'),
                'href' =>       $this->get_tracklist_action_url('unfavorite'),
                'desc' =>       __('Remove tracklist from favorites','wpsstm'),
                'classes' =>    array('action-unfavorite'),
            );

        }else{ //call to action
            $actions['favorite'] = array(
                'text' =>      __('Favorite','wpsstm'),
                'href' =>       '#',
                'desc' =>       __('This action requires you to be logged.','wpsstm'),
                'classes' =>    array('action-favorite','wpsstm-tooltip'),
            );
        }

        //toggle type
        if ( $this->feed_url && $this->user_can_toggle_playlist_type() ){
            
            if($this->tracklist_type == 'live'){
                $actions['live'] = array(
                    'text' =>      __('Stop sync', 'wpsstm'),
                    'classes' =>    array('wpsstm-advanced-action'),
                    'desc' =>       __('Convert this radio to a static playlist', 'wpsstm'),
                    'href' =>       $this->get_tracklist_action_url('static'),
                    'target' =>     '_parent',
                );
            }else{
                $actions['static'] = array(
                    'text' =>      __('Sync', 'wpsstm'),
                    'classes' =>    array('wpsstm-advanced-action'),
                    'desc' =>       __('Restore this playlist back to a radio', 'wpsstm'),
                    'href' =>       $this->get_tracklist_action_url('live'),
                    'target' =>     '_parent',
                );
            }
        }

        //edit backend
        if ( $can_edit_tracklist ){
            $actions['edit-backend'] = array(
                'text' =>       __('Edit'),
                'classes' =>    array('wpsstm-advanced-action','wpsstm-tracklist-popup'),
                'href' =>       get_edit_post_link( $this->post_id ),
            );
        }
        
        //trash tracklist
        if ( $can_trash_tracklist ){
            $actions['trash'] = array(
                'text' =>      __('Trash'),
                'classes' =>    array('wpsstm-advanced-action'),
                'desc' =>       __('Trash this tracklist','wpsstm'),
                'href' =>       $this->get_tracklist_action_url('trash'),
            );
        }

        return apply_filters('wpsstm_tracklist_actions',$actions, $this);
    }
    
    function get_tracklist_url(){
        
        $url = home_url();
        
        if ( !$this->post_id ) return false;
        $post_type = get_post_type($this->post_id);
        $post_type_obj = get_post_type_object($post_type);
        
        if ( !get_option('permalink_structure') ){
            $args = array(
                'post_type' =>      $post_type,
                'p' =>              $this->post_id
            );
            $url = add_query_arg($args,$url);
        }else{
            $url .= sprintf('/%s/%d/',$post_type_obj->rewrite['slug'],$this->post_id);
        }
        
        return $url;
    }

    function get_tracklist_action_url($action = null){

        $url = $this->get_tracklist_url();
        if ( !$url ) return false;

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

    function switch_status(){
        //capability check
        $post_type =        get_post_type($this->post_id);
        $post_type_obj =    get_post_type_object($post_type);

        $can_edit_cap =     $post_type_obj->cap->edit_post;
        $can_edit_post =    current_user_can($can_edit_cap,$this->post_id);
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_post_id', __('Required tracklist ID missing.','wpsstm') );
        }
        
        if ( !$can_edit_post ){
            return new WP_Error( 'wpsstm_missing_capability', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }


        //TO FIX validate status regarding user's caps
        $new_status = ( isset($_REQUEST['frontend-importer-status']) ) ? $_REQUEST['frontend-importer-status'] : null;
        
        $updated_post = array(
            'ID'            => $this->post_id,
            'post_status'   => $new_status
        );

        return wp_update_post( $updated_post, true );
        
    }

    /*
    Get autorship for a community tracklist (created through wizard)
    */
    
    function get_autorship(){
  
        //capability check
        $can_get_authorship = $this->user_can_get_tracklist_autorship();
        if ( is_wp_error($can_get_authorship) ) return $can_get_authorship;
        
        $args = array(
            'ID'            => $this->post_id,
            'post_author'   => get_current_user_id(),
        );

        return wp_update_post( $args, true );
            
    }

    function toggle_live($live = true){

        //capability check
        if ( !$this->user_can_toggle_playlist_type() ){
            return new WP_Error( 'wpsstm_missing_capability', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        
        //community (TOUFIX just for live ?)
        if ( wpsstm_is_community_post($this->post_id) ){
            $got_autorship = $this->get_autorship();
            if ( is_wp_error($got_autorship) ) return $got_autorship;
        }
        
        //toggle
        $new_type = ($live) ? wpsstm()->post_type_live_playlist : wpsstm()->post_type_playlist;

        $success = set_post_type( $this->post_id, $new_type );

        if ( is_wp_error($success) ) {
            $this->tracklist_log($success->get_error_message(),__("Error while toggling playlist type",'wpsstm')); 
        }
        return $success;

    }

    function trash_tracklist(){
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_post_id', __('Required tracklist ID missing.','wpsstm') );
        }
        
        //capability check
        $tracklist_post_type = get_post_type($this->post_id);
        $post_type_obj = get_post_type_object($tracklist_post_type);
        $can_trash_tracklist = current_user_can($post_type_obj->cap->delete_post,$this->post_id);

        if ( !$can_trash_tracklist ){
            return new WP_Error( 'wpsstm_missing_capability', __("You don't have the capability required to delete this tracklist.",'wpsstm') );
        }

        $success = wp_trash_post($this->post_id);

        if ( is_wp_error($success) ) {
            $this->tracklist_log($success->get_error_message(),__("Error while trashing tracklist",'wpsstm')); 
        }
        return $success;
        
    }
    
    function user_can_edit_tracklist(){
        $post_type = get_post_type($this->post_id);
        if ( !in_array($post_type,wpsstm()->tracklist_post_types) ) return false;
        
        $tracklist_obj = get_post_type_object($post_type);
        $can_edit_tracklist = current_user_can($tracklist_obj->cap->edit_post,$this->post_id);
        
        return $can_edit_tracklist;
    }
        
    function user_can_get_tracklist_autorship(){
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_post_id', __('Required tracklist ID missing.','wpsstm') );
        }
        
        $post_type = get_post_type($this->post_id);
        
        if ( !in_array($post_type,wpsstm()->tracklist_post_types) ){
            return new WP_Error( 'wpsstm_invalid_post_type', __('Invalid tracklist post type.','wpsstm') );
        }
        
        if ( !wpsstm_is_community_post($this->post_id) ){
            return new WP_Error( 'wpsstm_not_community_post', __('This is not a community post.','wpsstm') );
        }
            
        //capability check
        $post_type_obj = get_post_type_object($post_type);
        if ( !current_user_can($post_type_obj->cap->edit_posts) ){ //TOUFIX TOUCHECK should be create_posts ?
            return new WP_Error( 'wpsstm_missing_capability', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        
        return true;
    }
    
    function user_can_toggle_playlist_type(){
        
        $post_type = get_post_type($this->post_id);
        $allowed = array(wpsstm()->post_type_live_playlist,wpsstm()->post_type_playlist);
        if ( !in_array($post_type,$allowed) ) return;

        $post_obj =         get_post_type_object(wpsstm()->post_type_playlist);
        $live_post_obj =    get_post_type_object(wpsstm()->post_type_live_playlist);

        $can_edit_cap =     $post_obj->cap->edit_posts;
        $can_edit_type =  current_user_can($can_edit_cap);

        $can_edit_tracklist = current_user_can($live_post_obj->cap->edit_post,$this->post_id);

        return ( $can_edit_tracklist && $can_edit_type );
        
    }

    function user_can_reorder_tracks(){
        return ( $this->user_can_edit_tracklist() && ($this->tracklist_type == 'static') );
    }

    function get_tracklist_attr($values_attr=null){
        
        //TO FIX weird code, not effiscient
        $extra_classes = ( isset($values_attr['extra_classes']) ) ? $values_attr['extra_classes'] : null;
        unset($values_attr['extra_classes']);

        $values_defaults = array(
            'itemscope' =>                          true,
            'itemtype' =>                           "http://schema.org/MusicPlaylist",
            'data-wpsstm-tracklist-id' =>           $this->post_id,
            'data-wpsstm-domain' =>                 wpsstm_get_url_domain( $this->feed_url ),
        );

        $values_attr = array_merge($values_defaults,(array)$values_attr);
        
        return wpsstm_get_html_attr($values_attr);
    }

    function populate_subtracks(){
        global $wpdb;
        
        //avoid populating the subtracks several times (eg. Jetpack populates the content several times) TOUFIX TOUCHECK ?
        if ($this->track_count !== null) return;
 
        $tracks = array();
        $refresh_delay = $this->get_human_next_refresh_time();
        
        $refresh_now = ( 
            $this->is_expired && ( 
                ( wpsstm()->get_options('ajax_tracks') && wp_doing_ajax() ) || 
                ( !wpsstm()->get_options('ajax_tracks') && !wp_doing_ajax() ) 
            ) 
        );

        if ( $refresh_now ){
            
            $this->tracklist_log("refresh radio...");
            
            /*
            redirect URL
            Hook to filter bangs, etc.
            */
            $feed_url = apply_filters('wpsstm_feed_url',$this->feed_url,$this);
            
            die("API WORK");



        }
        
        $tracks = $this->get_static_subtracks();

        if ( !is_wp_error($tracks) ){
            $this->add_tracks($tracks);
        }

        $this->tracklist_log(
            array(
                'tracks_populated'=>$this->track_count,
                'is_expired'=>$this->is_expired,
                'refresh_delay'=>$refresh_delay
            ),'Populated subtracks'
        );

    }

    
    /*
    Update WP post and eventually update subtracks.
    */
    
    private function update_radio_data(){

        if (!$this->post_id){
            $this->tracklist_log('wpsstm_missing_post_id','Set live datas error' );
            return new WP_Error( 'wpsstm_missing_post_id', __('Required tracklist ID missing.','wpsstm') );
        }
        
        $this->tracklist_log('start updating live playlist...'); 

        /*
        subtracks
        */
        $success = $this->set_radio_subtracks();
        if( is_wp_error($success) ) return $success;
   
        /*
        metas
        */

        // if post title is empty, use the remote title
        $this->title = ($this->title) ? $this->title : $this->preset->get_remote_title();

        //if remote author exists, use it
        $remote_author = $this->preset->get_remote_author();
        $this->author = ( $remote_author ) ? $remote_author : $this->author;

        //time updated
        $this->updated_time = current_time( 'timestamp', true );

        $meta_input = array(
            self::$remote_title_meta_name =>  $this->preset->title,
            WPSSTM_Core_Live_Playlists::$remote_author_meta_name => $this->preset->author,
            WPSSTM_Core_Live_Playlists::$time_updated_meta_name =>  $this->updated_time
        );

        $tracklist_post = array(
            'ID' =>         $this->post_id,
            'meta_input' => $meta_input,
        );
        
        $success = wp_update_post( $tracklist_post, true );
        
        return true;

    }
    
    /*
    Clear the stored subtracks and add the new ones
    */

    private function set_radio_subtracks(){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        //capability check
        $can = wpsstm()->is_community_user_ready();
        if ( is_wp_error($can) ) return $can;
        
        //delete actual subtracks
        $this->tracklist_log('delete current tracklist subtracks...'); 
        
        $querystr = $wpdb->prepare( "DELETE FROM `$subtracks_table` WHERE tracklist_id = %d", $this->post_id );
        $success = $wpdb->get_results ( $querystr );
        
        $this->tracklist_log('..has deleted current tracklist subtracks');
        
        ////
        
        $this->tracklist_log('save subtracks...'); 

        $error_msgs = array();
        $saved_count = 0;

        $tracks = apply_filters('wpsstm_radio_tracks_input',$this->preset->tracks,$this);

        foreach((array)$tracks as $index=>$new_track){

            $new_track->position = $index + 1;
            $success = $this->add_subtrack($new_track);
            
            //populate subtrack ID
            if( is_wp_error($success) ){
                $error_code = $success->get_error_code();
                $error_msgs[] = sprintf('Failed saving subtrack #%s: %s',$index,$error_code);
            }else{
                $saved_count++;
            }
        }
        
        $this->tracklist_log(array('input'=>count($this->preset->tracks),'filtered'=>count($tracks),'errors'=>count($error_msgs),'error_msgs'=>$error_msgs),'...has saved subtracks'); 

        return true;
        
    }

    function seconds_before_refresh(){

        if ($this->tracklist_type != 'live') return false;
        
        $updated_time = (int)get_post_meta($this->post_id,WPSSTM_Core_Live_Playlists::$time_updated_meta_name,true);
        if(!$updated_time) return 0;//never imported

        if (!$this->cache_min) return 0; //no delay

        $expiration_time = $updated_time + ($this->cache_min * MINUTE_IN_SECONDS);
        $now = current_time( 'timestamp', true );

        return $expiration_time - $now;
    }
    
    function remove_cache_timestamp(){
        delete_post_meta($this->post_id,WPSSTM_Core_Live_Playlists::$time_updated_meta_name);
        $this->is_expired = true;
    }

    function get_human_next_refresh_time(){
        
        if ($this->tracklist_type != 'live') return false;

        $cache_seconds = $this->cache_min ? $this->cache_min * MINUTE_IN_SECONDS : false;

        if ( !$cache_seconds ) return false;
        
        $time_refreshed = $this->updated_time;
        $next_refresh = $time_refreshed + $cache_seconds;
        $now = current_time( 'timestamp', true );
        
        $is_future = ( ($next_refresh - $now) > 0 );
        if (!$is_future) return false;
        
        return human_time_diff( $now, $next_refresh );
    }
    
    function validate_subtrack(WPSSTM_Track $track){
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_tracklist_id', __("Missing tracklist ID.",'wpsstm') );
        }
        return $track->validate_track();
    }
    
    function queue_track(WPSSTM_Track $track){
        
        if ( !$this->user_can_edit_tracklist() ){
            return new WP_Error( 'wpsstm_missing_capability', __("You don't have the capability required to queue this track.",'wpsstm') );
        }
        
        //unset some subtracks vars or subtrack will be moved instead of added
        $new_track = clone $track;
        $new_track->subtrack_id = null;

        $success = $this->add_subtrack($new_track);
        
        if ( $success && !is_wp_error($success) ){
            do_action('wpsstm_queue_track',$track,$this->post_id);
            
            //favorites ?
            if ( $this->post_id == WPSSTM_Core_User::get_user_favorites_id() ){
                do_action('wpsstm_love_track',$track,true);
            }
        }

        return $success;

    }
    
    function dequeue_track(WPSSTM_Track $track){
        
        $this->tracklist_log($track->to_array(),"dequeue track");
        
        $subtrack_ids = $track->get_subtrack_matches($this->post_id);
        if ( is_wp_error($subtrack_ids) ) return $subtrack_ids;
        
        if (!$subtrack_ids){
            return new WP_Error( 'wpsstm_no_track_matches', __('No matches for this track in the tracklist.','wpsstm') );
        }
        
        foreach ($subtrack_ids as $subtrack_id){
            $subtrack = new WPSSTM_Track();
            $subtrack->populate_subtrack($subtrack_id);
            $success = $subtrack->unlink_subtrack();

            if ( is_wp_error($success) ){
                $track->track_log($subtrack->to_array(),"Error while unqueuing subtrack" );
            }

        }
        
        do_action('wpsstm_dequeue_track',$track,$this->post_id);

        //favorites ?
        if ( $this->post_id == WPSSTM_Core_User::get_user_favorites_id() ){
            do_action('wpsstm_love_track',$track,false);
        }

        return true;
        
    }
    
    /*
    TO FIX TO CHECK maybe we also should have a function to save multiple subtracks in one single query ?
    I mean when we have playlists with hundreds of subtracks to save...
    */
    
    private function add_subtrack(WPSSTM_Track $track){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        //NO capability check here, should be done upstream, because we should be able to use this function automatically (eg. live tracklist update)
        
        $valid = $this->validate_subtrack($track);
        if ( is_wp_error( $valid ) ) return $valid;

        //check for a track ID
        if (!$track->post_id){
            $track->local_track_lookup();
        }
        
        if (!$track->post_id){
            $track_id = $track->insert_community_track();
            
            if ( is_wp_error($track_id) ){
                return $track_id;
            }
            
        }

        if (!$track->post_id){
            return new WP_Error( 'wpsstm_missing_track_id', __('Missing track ID.','wpsstm') );
        }

        $track_data = array(
            'track_id' =>   $track->post_id
        );

        //new subtrack
        if (!$track->subtrack_id){
            $subtrack_data['time'] =            current_time('mysql');
            $subtrack_data['tracklist_id'] =    $this->post_id;
            $subtrack_data['from_tracklist'] =  $track->from_tracklist;
            $subtrack_data['track_order'] =     $this->get_subtracks_count() + 1;
            
            $track_data = array_merge($track_data,$subtrack_data);
        }

        $success = $wpdb->insert($subtracks_table,$track_data);
        if ( is_wp_error($success) ) return $success;

        $track->subtrack_id = $wpdb->insert_id;
        return $success;

    }

    private function get_static_subtracks($track_args = array()){
        global $wpdb;
        
        /*
        get subtracks from custom table
        */
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        $querystr = $wpdb->prepare( "SELECT * FROM `$subtracks_table` WHERE tracklist_id = %d ORDER BY track_order ASC", $this->post_id );
        $rows = $wpdb->get_results( $querystr);

        $post_ids = array_column($rows, 'track_id');
        if (!$post_ids) $post_ids = array(0); //https://core.trac.wordpress.org/ticket/28099
        
        /*
        run a tracks query to get an array of allowed track IDs (would exclude trashed tracks, etc.)
        */
        
        $default_track_args = array(
            'posts_per_page'=>          -1,
        );
        
        $forced_track_args = array(
            'post_type' =>              wpsstm()->post_type_track,
            'fields' =>                 'ids',
            'post__in' =>               $post_ids,
        );
        
        $track_args = wp_parse_args($track_args,$default_track_args);
        $track_args = wp_parse_args($forced_track_args,$track_args);

        $query = new WP_Query( $track_args );
        $filtered_post_ids = $query->posts;

        $tracks = array();
        foreach($rows as $row){
            
            if ( !in_array($row->track_id,$filtered_post_ids) ) continue;
            
            $subtrack = new WPSSTM_Track(); //default
            $subtrack->populate_subtrack($row->ID);
            $tracks[] = $subtrack;
        }

        return $tracks;
    }

    function get_html_metas(){
        $metas = array(
            'numTracks' => $this->track_count
        );
        
        if ( $this->tracklist_type == 'live'){
            /*
            expiration time
            */
            $seconds = $this->seconds_before_refresh();
            if ($seconds !== false){
                //if no real cache is set; let's say tracklist is already expired at load!
                $metas['wpsstmRefreshTimer'] = $seconds;
            }
        }
        
        return $metas;
    }
    
    function html_metas(){
        $metas = $this->get_html_metas();
        foreach( (array)$metas as $key=>$value ){
            printf('<meta itemprop="%s" content="%s"/>',$key,$value);
        }
    }
    
    function get_favorited_by_list(){
        $links = array();
        $output = null;
        if ( $user_ids = $this->get_tracklist_favorited_by() ){

            foreach($user_ids as $user_id){
                $user_info = get_userdata($user_id);
                $links[] = sprintf('<a href="%s" target="_blank">%s</a>',get_author_posts_url($user_id),$user_info->user_nicename);
            }
            $output = implode(', ',$links);
        }
        return $output;
    }
    
    /*
    TOUFIX this should be linked to a Wordpress query (exclude trashed posts, etc.), and allow query args ?
    */
    
    function get_subtracks_count(){
        global $wpdb;
        if (!$this->post_id) return false;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        $querystr = $wpdb->prepare( "SELECT COUNT(*) FROM `$subtracks_table` WHERE tracklist_id = %d", $this->post_id );
        return $wpdb->get_var($querystr);
    }

    function tracklist_log($data,$title = null){

        //global log
        if ($this->post_id){
            $title = sprintf('[tracklist:%s] ',$this->post_id) . $title;
        }
        wpsstm()->debug_log($data,$title);
    }

    function get_tracklist_hidden_form_fields(){
        if ($this->post_id){
            $fields[] = sprintf('<input type="hidden" name="wpsstm_tracklist_data[post_id]" value="%s" />',esc_attr($this->post_id));
        }
        if ($this->title){
            $fields[] = sprintf('<input type="hidden" name="wpsstm_tracklist_data[title]" value="%s" />',esc_attr($this->title));
        }
        return implode("\n",$fields);
    }
    
    function no_tracks_notice(){
        
        if ( $this->track_count ) return;
        
        $desc = array();
        
        $not_found = __('No tracks found.','wpsstm');

        if (!$this->feed_url) return;


        $refresh_el = sprintf('<a class="wpsstm-reload-bt" href="%s">%s</a>',$this->get_tracklist_action_url('refresh'),__('Refresh'));
        $not_found .= sprintf("  %s ?",$refresh_el);

        $this->add_notice('empty-tracklist', $not_found );
    }
    
    function importer_notice(){
        if ( is_admin() ) return;
        if ( !$this->feed_url ) return;
        if ( $this->track_count ) return;
        
        $post_type = get_post_type($this->post_id);
        $tracklist_obj = get_post_type_object($post_type);
        if ( !current_user_can($tracklist_obj->cap->edit_post,$this->post_id) ) return;
        
        $importer_url =  get_edit_post_link( $this->post_id ) . '#wpsstm-metabox-importer';
        $importer_el = sprintf('<a href="%s">%s</a>',$importer_url,__('Tracklist Importer settings','wpsstm'));
        $notice = sprintf(__('You may also want to edit the %s.','wpsstm'),$importer_el);
        $this->add_notice('importer-settings', $notice );
    }
    
    function autorship_notice(){
        if ( !$this->track_count ) return;
        if  ( !$this->user_can_get_tracklist_autorship() === true ) return;
        if ( !wpsstm_is_community_post($this->post_id) ) return;
        
        $autorship_url = $this->get_tracklist_action_url('get-autorship');
        $autorship_link = sprintf('<a href="%s">%s</a>',$autorship_url,__("add it to your profile","wpsstm"));
        $message = __("This is a temporary tracklist.","wpsstm");
        $message .= '  '.sprintf(__("Would you like to %s?","wpsstm"),$autorship_link);
        $this->add_notice('get-autorship', $message );
    }

}

class WPSSTM_Tracklist{

    //infos
    var $title = null;
    var $author = null;
    var $location = null;
    
    //datas
    var $tracks = array();
    var $notices = array();
    
    var $track;
    var $current_track = -1;
    var $track_count = null;
    var $in_subtracks_loop = false;

    /*
    $input_tracks = array of tracks objects or array of track IDs
    */
    
    function add_tracks($input_tracks){
        
        $add_tracks = array();

        //force array
        if ( !is_array($input_tracks) ) $input_tracks = array($input_tracks);

        foreach ($input_tracks as $track){

            if ( !is_a($track, 'WPSSTM_Track') ){
                if ( is_array($track) ){
                    $track_args = $track;
                    $track = new WPSSTM_Track(null,$this);
                    $track->from_array($track_args);
                }else{ //track ID
                    $track_id = $track;
                    //TO FIX check for int ?
                    $track = new WPSSTM_Track($track_id,$this);
                }
            }
            
            $add_tracks[] = $track;
        }

        $new_tracks = $this->validate_tracks($add_tracks);
        
        $this->tracks = array_merge($this->tracks,$new_tracks);
        $this->track_count = count($this->tracks);
        
        return $new_tracks;
    }

    private function validate_tracks($tracks){

        $valid_tracks = $rejected_tracks = array();
        $error_codes = array();
        
        $pending_tracks = array_unique($tracks);
        
        foreach($pending_tracks as $track){
            $valid = $track->validate_track();
            if ( is_wp_error($valid) ){

                $error_codes[] = $valid->get_error_code();
                /*
                $this->tracklist_log($valid->get_error_message(), "WPSSTM_Tracklist::validate_tracks - rejected");
                */
                $rejected_tracks[] = $track;
                continue;
            }
            $valid_tracks[] = $track;
        }
        
        if ( $rejected_tracks ){
            $error_codes = array_unique($error_codes);
            
            $cleared_tracks = array();
            foreach ($rejected_tracks as $track){
                $cleared_tracks[] = $track->to_array();
            }
            
            $this->tracklist_log(array( 'count'=>count($rejected_tracks),'codes'=>json_encode($error_codes),'rejected'=>array($cleared_tracks) ), "WPSSTM_Tracklist::validate_tracks");
        }

        return $valid_tracks;
    }

    function to_array(){
        $export = array(
            'post_id' => $this->post_id,
            'index' => $this->index,
        );
        return array_filter($export);
    }

    /**
	 * Set up the next track and iterate current track index.
	 * @return WP_Post Next track.
	 */
	public function next_subtrack() {

		$this->current_track++;

		$this->track = $this->tracks[$this->current_track];
		return $this->track;
	}

	/**
	 * Sets up the current track.
	 * Retrieves the next track, sets up the track, sets the 'in the loop'
	 * property to true.
	 * @global WP_Post $wpsstm_track
	 */
	public function the_subtrack() {
		global $wpsstm_track;
		$this->in_subtracks_loop = true;

		if ( $this->current_track == -1 ) // loop has just started
			do_action_ref_array( 'wpsstm_tracks_loop_start', array( &$this ) );

        $wpsstm_track = $this->next_subtrack();
        //$this->setup_subtrack_data( $wpsstm_track );
	}

	/**
	 * Determines whether there are more tracks available in the loop.
	 * Calls the {@see 'wpsstm_tracks_loop_end'} action when the loop is complete.
	 * @return bool True if tracks are available, false if end of loop.
	 */
	public function have_subtracks() {

		if ( $this->current_track + 1 < $this->track_count ) {
			return true;
		} elseif ( $this->current_track + 1 == $this->track_count && $this->track_count > 0 ) {
			/**
			 * Fires once the loop has ended.
			 *
			 * @since 2.0.0
			 *
			 * @param WP_Query &$this The WP_Query instance (passed by reference).
			 */
			do_action_ref_array( 'wpsstm_tracks_loop_end', array( &$this ) );
			// Do some cleaning up after the loop
			$this->rewind_tracks();
		}

		$this->in_subtracks_loop = false;
		return false;
	}

	/**
	 * Rewind the tracks and reset track index.
	 * @access public
	 */
	public function rewind_tracks() {
		$this->current_track = -1;
		if ( $this->track_count > 0 ) {
			$this->track = $this->tracks[0];
		}
	}
    
    function tracklist_log($data,$title = null){
        wpsstm()->debug_log($data,$title);
    }
    
}


