<?php
class WPSSTM_Post_Tracklist extends WPSSTM_Tracklist{
    
    var $post_id = null; //tracklist ID (can be an album, playlist or radio)
    var $import_id = null;
    var $index = -1;
    var $tracklist_type = 'static';
    
    var $default_options = array(
        'cache_min' => 15,
        'playable'  => true,
        'order'     => 'ASC',
    );
    
    var $default_importer_options = array();
    
    var $options = array();
    var $importer_options = array();
    
    
    public $is_expired = false;
    
    var $pagination = array(
        'total_pages'       => null,
        'per_page'          => null,
        'current_page'      => null,
    );

    var $paged_var = 'tracklist_page';

    static $tracklist_options_meta_name = '_wpsstm_tracklist_options';
    static $importer_options_meta_name = '_wpsstm_scraper_options';
    static $feed_url_meta_name = '_wpsstm_scraper_url';
    static $website_url_meta_name = '_wpsstm_website_url';
    static $import_id_meta_name = '_wpsstm_import_id';
    private static $remote_title_meta_name = 'wpsstm_remote_title';
    public $feed_url = null;
    public $website_url = null;
    public $cache_min = null;
    
    var $preset;
    
    public $classes = array();

    function __construct($post = null ){
        
        if ($post){
            if ( is_a($post,'WP_Post') ){
                $this->populate_tracklist_post($post->ID);
            }elseif ( is_int($post) ) {
                $this->populate_tracklist_post($post);
            }
        }

        $pagination_args = array(
            'per_page'      => 0, //TO FIX default option
            'current_page'  => ( isset($_REQUEST[$this->paged_var]) ) ? $_REQUEST[$this->paged_var] : 1
        );

        $this->set_tracklist_pagination($pagination_args);

    }
    
    static function get_tracklist_title($post_id){
        //title
        if ( $title = get_post_field( 'post_title', $post_id ) ) return $title;
        if ( $cached = self::get_cached_title($post_id) ) return $cached;
        return '';
    }
    
    static public function get_cached_title($post_id){
        return get_post_meta($post_id,self::$remote_title_meta_name,true);
    }
    
    function populate_tracklist_post($post_id = null){
        
        if (!$post_id) $post_id = $this->post_id;
        $post_id = filter_var($post_id, FILTER_VALIDATE_INT); //cast to int
        $post_type = get_post_type($post_id);
        
        if ( !in_array( $post_type,wpsstm()->tracklist_post_types) ){
            return new WP_Error( 'wpsstm_invalid_track_entry', __("This is not a valid tracklist entry.",'wpsstm') );
        }
        
        $this->post_id = $post_id;

        //type        
        $this->tracklist_type = ($post_type == wpsstm()->post_type_radio) ? 'live' : 'static';

        //options
        $db_options = (array)get_post_meta($this->post_id,self::$tracklist_options_meta_name,true);

        $cache_min = get_post_meta($this->post_id,WPSSTM_Core_Radios::$cache_min_meta_name,true);
        if( is_numeric($cache_min) ){
            $db_options['cache_min'] = $cache_min;
        }

        $this->options = array_replace_recursive($this->default_options,(array)$db_options);//last one has priority

        $this->feed_url =       get_post_meta($this->post_id, self::$feed_url_meta_name, true );
        $this->website_url =    get_post_meta($this->post_id, self::$website_url_meta_name, true );        
        $this->date_timestamp = (int)get_post_modified_time( 'U', true, $this->post_id, true );
        $this->import_id =      get_post_meta($this->post_id,self::$import_id_meta_name, true);

        //importer options
        $db_importer_options = (array)get_post_meta($this->post_id,self::$importer_options_meta_name,true);
        $this->importer_options = array_replace_recursive($this->default_importer_options,$db_importer_options);//last one has priority
        
        //title (will be filtered)
        $this->title = get_the_title($this->post_id);
        
        //author
        $post_author_id = get_post_field( 'post_author', $this->post_id );
        $this->author = get_the_author_meta( 'display_name', $post_author_id );

        if ( $this->tracklist_type === 'live' ){
            
            $seconds = $this->seconds_before_refresh();
            $this->is_expired = ( ($seconds !== false) && ($seconds <= 0) );
            
            $last_import_time = get_post_meta($this->post_id,WPSSTM_Core_Radios::$time_updated_meta_name,true);
            $last_import_time = filter_var($last_import_time, FILTER_VALIDATE_INT);

            if ($last_import_time){
                $this->date_timestamp = $last_import_time;
            }
            
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

        return $this->post_id;

    }
    
    function get_options($keys=null){
        if ($keys){
            return wpsstm_get_array_value($keys, $this->options);
        }else{
            return $this->options;
        }
    }
    
    function get_importer_options($keys=null){

        if ($keys){
            return wpsstm_get_array_value($keys, $this->importer_options);
        }else{
            return $this->importer_options;
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
        $post_type = ($this->tracklist_type == 'static') ? wpsstm()->post_type_playlist : wpsstm()->post_type_radio;
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
        
        if (!$this->post_id) return; //eg. new tracklist
        
        $tracklist_post_type = get_post_type($this->post_id);
        
        //no tracklist actions if this is a "track" tracklist
        //TOUFIX TOUCHECK
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
            'classes' =>    array('wpsstm-tracklist-popup wpsstm-action-popup'),
        );
        
        //export
        if ( !get_current_user_id() ){
            $dl_link = $this->get_tracklist_action_url('export');
            $dl_link = add_query_arg(array('dl'=>true),$dl_link);
            $actions['export'] = array(
                'text' =>       __('Export', 'wpsstm'),
                'classes' =>    array('wpsstm-advanced-action'),
                'desc' =>       __('Export to XSPF', 'wpsstm'),
                'href' =>       $dl_link,
                'target' =>     '_blank',
            );
        }else{ //call to action
            $actions['export'] = array(
                'text' =>       __('Export', 'wpsstm'),
                'classes' =>    array('wpsstm-advanced-action','wpsstm-freeze'),
                'desc' =>       __('Export to XSPF (logged users only)', 'wpsstm'),
                'href' =>       '#',
            );
        }
        
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
            
            $is_trashed = ( get_post_type($this->post_id) === 'trash' );
            if ($is_trashed){
                $actions['trash']['classes'][] = 'wpsstm-freeze';
            }

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
    Get autorship for a bot tracklist (created through wizard)
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
        
        //bot (TOUFIX just for live ?)
        if ( wpsstm_is_bot_post($this->post_id) ){
            $got_autorship = $this->get_autorship();
            if ( is_wp_error($got_autorship) ) return $got_autorship;
        }
        
        //toggle
        $new_type = ($live) ? wpsstm()->post_type_radio : wpsstm()->post_type_playlist;

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
        
        if ( !wpsstm_is_bot_post($this->post_id) ){
            return new WP_Error( 'wpsstm_not_bot_post', __('This is not a bot post.','wpsstm') );
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
        $allowed = array(wpsstm()->post_type_radio,wpsstm()->post_type_playlist);
        if ( !in_array($post_type,$allowed) ) return;

        $post_obj =         get_post_type_object(wpsstm()->post_type_playlist);
        $live_post_obj =    get_post_type_object(wpsstm()->post_type_radio);

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
            'wpsstm-playable' =>                    ( wpsstm()->get_options('player_enabled') && $this->get_options('playable') && $this->track_count ),
        );

        $values_attr = array_merge($values_defaults,(array)$values_attr);
        
        return wpsstm_get_html_attr($values_attr);
    }

    function populate_subtracks(){
        global $wpdb;
        
        //avoid populating the subtracks several times (eg. Jetpack populates the content several times) TOUFIX TOUCHECK ?

        if ($this->track_count !== null) return;

        $tracks = array();

        //refresh radio ?
        
        if ( $this->tracklist_type === 'live' ){
            $refresh_now = ( $this->is_expired && (
                    ( wpsstm()->get_options('ajax_tracks') && wp_doing_ajax() ) || 
                    ( !wpsstm()->get_options('ajax_tracks') && !wp_doing_ajax() ) 
                ) 
            );

            if ( $refresh_now ){
                $synced = $this->sync_radio();
                if ( is_wp_error($synced) ) return $synced;
            }
        }
        
        //get static subtracks
        $tracks = $this->get_static_subtracks();
        
        /*
        $i = 0;
        $executionStartTime = microtime(true);
        while ($i <= 10) {
            $tracks = $this->get_static_subtracks();
            $i++;
        }
        $executionEndTime = microtime(true);
        $seconds = $executionEndTime - $executionStartTime;
        
        print_r($seconds);die();
        */

        $tracks = apply_filters('wpsstm_get_subtracks',$tracks,$this);
        if ( is_wp_error($tracks) ) return $tracks;

        $this->add_tracks($tracks);
        
    }
    
    private function import_xspf(){
        
        $this->tracklist_log("import XSPF...");

        //check feed URL
        if ( !$this->feed_url ){
                $error = new WP_Error( 'wpsstm_missing_feed_url', __('Please add a Feed URL in the radio settings.','wpsstm') );
                $this->add_notice('refresh_radio',$error->get_error_message() );
                return $error;
        }

        /*
        redirect URL
        Hook to filter bangs, etc.
        */
        $feed_url = apply_filters('wpsstm_feed_url',$this->feed_url,$this);

        /*
        Get the XSPF tracklist (as an array)
        http://xspf.org
        */

        if ( wpsstm_is_xpsf_url($feed_url) ){

            $this->tracklist_log("...is an XSPF url, do not query WPSSTM API.");
            
            //check is local file
            //holy mole ! Please get premium instead of hacking my plugin !
            if ( !WPSSTM_Core_API::is_premium() && !wpsstm_is_local_file($feed_url) ) {
                $error = new WP_Error( 'missing_api_key', __('Importing remote files requires an API key.','wpsstm') );
                
                if ( current_user_can('manage_options') ){
                    $this->add_notice('missing_api_key',$error->get_error_message() );
                }
                
                return $error;
            }

            $response = wp_remote_get( $feed_url );
            $xspf = wp_remote_retrieve_body( $response );

        }else{
            $importer_options = get_post_meta($this->post_id, WPSSTM_Post_Tracklist::$importer_options_meta_name,true);
            $params = array(
                'input' =>      $feed_url,
                'options'=>     $importer_options
            );
            
            /*
            API
            */
            $xspf = WPSSTM_Core_API::api_request('import',$params);

            if ( is_wp_error($xspf) ){

                $error_code = $xspf->get_error_code();
                $error_message = $xspf->get_error_message();

                if($error_code == 'rest_forbidden'){

                    if ( current_user_can('manage_options') ){
                        $api_link = sprintf('<a href="%s" target="_blank">%s</a>',WPSSTM_API_REGISTER_URL,__('here','wpsstmapi') );
                        $this->add_notice('wpsstm-api-error',sprintf(__('An API key is needed. Get one %s.','wpsstm'),$api_link)  );
                    }

                }else{
                    $this->add_notice('wpsstm-api-error',$error_message  );
                }

                return $xspf;
                
            }

        }

        /*
        convert XSPF to array
        */
        $xspf = simplexml_load_string($xspf, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xspf);
        $xspf = json_decode($json,TRUE);
        $this->import_id = wpsstm_get_array_value('identifier',$xspf);

        /*
        Create playlist from the XSPF array
        */

        $playlist = new WPSSTM_Tracklist();
        $playlist_tracks = array();

        $playlist->title = wpsstm_get_array_value('title',$xspf);
        $playlist->author = wpsstm_get_array_value('creator',$xspf);
        $playlist->location = wpsstm_get_array_value('location',$xspf);

        if ($date = wpsstm_get_array_value('date',$xspf) ){
            $playlist->date_timestamp = strtotime ($date);
        }

        $xspf_tracks = wpsstm_get_array_value(array('trackList','track'),$xspf);

        foreach ((array)$xspf_tracks as $xspf_track) {

            $track = new WPSSTM_Track();

            //identifier

            //title
            $track->title = wpsstm_get_array_value('title',$xspf_track);

            //creator
            $track->artist = wpsstm_get_array_value('creator',$xspf_track);

            //album
            $track->album = wpsstm_get_array_value('album',$xspf_track);

            //image
            $track->image_url = wpsstm_get_array_value('image',$xspf_track);

            //trackNum
            $track->position = wpsstm_get_array_value('trackNum',$xspf_track);

            //duration
            $track->duration = wpsstm_get_array_value('duration',$xspf_track);

            //links
            //when there are several links, it is an array; while it is a string for a single link.  So force array.
            if ( $link_urls = wpsstm_get_array_value('location',$xspf_track) ){

                $link_urls = (array)$link_urls;
                $addlinks = array();

                foreach($link_urls as $url){
                    $link = new WPSSTM_Track_Link();
                    $link->permalink_url = $url;
                    $link->is_bot = true;
                    $addlinks[] = $link;
                }

                $track->add_links($addlinks);

            }
            
            //identifiers
            if ( $identifiers = wpsstm_get_array_value('identifier',$xspf_track) ){
                $identifiers = (array)$identifiers;

                foreach($identifiers as $url){
                    
                    //MusicBrainz
                    $regex = '~^https://www.musicbrainz.org/track/([^/]+)/?$~i';
                    preg_match($regex, $url, $matches);
                    
                    if ( $mbid = wpsstm_get_array_value(1,$matches) ){
                        $track->musicbrainz_id = $mbid;
                        continue;
                    }
                    //Spotify
                    $regex = '~^https://open.spotify.com/track/([^/]+)/?$~i';
                    preg_match($regex, $url, $matches);
                    
                    if ( $mbid = wpsstm_get_array_value(1,$matches) ){
                        $track->musicbrainz_id = $mbid;
                        continue;
                    }
                    
                }
                
            }

            //meta

            //extension

            ////

            $playlist_tracks[] = $track;
        }

        $playlist->add_tracks($playlist_tracks);

        return $playlist;
        
    }
    
    private function sync_radio(){
 
        $this->tracklist_log("sync radio...");
        
        $playlist = $this->import_xspf();
        if ( is_wp_error($playlist) ) return $playlist;
        
        //update import ID if any (even if we received an error, we should have the import ID populated)
        if ($this->import_id){
            $this->tracklist_log($this->get_debug_url(),"import succeeded");
            update_post_meta( $this->post_id, self::$import_id_meta_name, $this->import_id );
        }

        $updated = $this->update_radio_data($playlist);

        $this->tracklist_log(
            array(
                'tracks_populated' =>   $this->track_count,
                'is_expired' =>         $this->is_expired,
                'refresh_delay' =>      $this->get_human_next_refresh_time()
            ),'Imported subtracks'
        );
        
        return $updated;
    }

    
    /*
    Update WP post and eventually update subtracks.
    */
    
    private function update_radio_data(WPSSTM_Tracklist $tracklist){

        if (!$this->post_id){
            $this->tracklist_log('wpsstm_missing_post_id','Set live datas error' );
            return new WP_Error( 'wpsstm_missing_post_id', __('Required tracklist ID missing.','wpsstm') );
        }
        
        $this->tracklist_log('start updating radio...');

        /*
        subtracks
        */
        $success = $this->set_radio_subtracks($tracklist);
        if( is_wp_error($success) ) return $success;
   
        /*
        metas
        */

        $meta_input = array(
            self::$remote_title_meta_name =>                            $tracklist->title,
            WPSSTM_Core_Radios::$remote_author_meta_name =>     $tracklist->author,
            WPSSTM_Core_Radios::$time_updated_meta_name =>      $tracklist->date_timestamp,
        );

        $tracklist_post = array(
            'ID' =>         $this->post_id,
            'meta_input' => $meta_input,
        );
        
        $success = wp_update_post( $tracklist_post, true );
        if ( is_wp_error($success) ) return $success;
        
        return $this->populate_tracklist_post();

    }
    
    /*
    Clear the stored subtracks and add the new ones
    */

    private function set_radio_subtracks(WPSSTM_Tracklist $tracklist){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        //check bot user
        $bot_ready = wpsstm()->is_bot_ready();
        if ( is_wp_error($bot_ready) ) return $bot_ready;
        
        $bot_id = wpsstm()->get_options('bot_user_id');
        
        //delete actual subtracks
        $this->tracklist_log('delete current tracklist subtracks...'); 
        
        $querystr = $wpdb->prepare( "DELETE FROM `$subtracks_table` WHERE tracklist_id = %d", $this->post_id );
        $success = $wpdb->get_results ( $querystr );
        
        $this->tracklist_log('..has deleted current tracklist subtracks');
        
        ////
        
        $this->tracklist_log('save subtracks...'); 

        $error_msgs = array();

        $tracks = apply_filters('wpsstm_radio_tracks_input',$tracklist->tracks,$this);

        foreach((array)$tracks as $index=>$new_track){

            $new_track->position = $index + 1;
            $new_track->subtrack_author = $bot_id; //set bot as author

            $success = $this->insert_subtrack($new_track);

            //populate subtrack ID
            if( is_wp_error($success) ){
                $error_code = $success->get_error_code();
                $error_msgs[] = sprintf('Failed saving subtrack #%s: %s',$index,$error_code);
            }
        }
        
        $this->tracklist_log(array('input'=>count($tracklist->tracks),'output'=>count($tracks),'errors'=>count($error_msgs),'error_msgs'=>$error_msgs),'...has saved subtracks'); 

        return true;
        
    }

    function seconds_before_refresh(){

        if ($this->tracklist_type != 'live') return false;
        
        $updated_time = (int)get_post_meta($this->post_id,WPSSTM_Core_Radios::$time_updated_meta_name,true);
        if(!$updated_time) return 0;//never imported

        if ( !$cache_min = $this->get_options('cache_min') ) return 0; //no delay

        $expiration_time = $updated_time + ($cache_min * MINUTE_IN_SECONDS);
        $now = current_time( 'timestamp', true );

        $seconds = $expiration_time - $now;

        return $seconds;
    }
    
    function remove_import_timestamp(){
        if ( !$last_import_time = get_post_meta($this->post_id,WPSSTM_Core_Radios::$time_updated_meta_name,true) ) return;
        if ( !$success = delete_post_meta($this->post_id,WPSSTM_Core_Radios::$time_updated_meta_name) ) return;
        $this->is_expired = true;
    }
    
    function get_human_pulse(){
        if ($this->tracklist_type != 'live') return false;
        if ( !$cache_min = $this->get_options('cache_min') ) return false;
        $cache_seconds = $cache_min * MINUTE_IN_SECONDS;
        
        $now = current_time( 'timestamp', true );
        $then = $now + $cache_seconds;
        
        return human_time_diff( $now, $then );
    }

    function get_human_next_refresh_time(){
        
        if ($this->tracklist_type != 'live') return false;
        if ( !$cache_min = $this->get_options('cache_min') ) return false;
        
        $cache_seconds = $cache_min * MINUTE_IN_SECONDS;
        $time_refreshed = $this->date_timestamp;
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

        $success = $this->insert_subtrack($new_track);

        if ( $success && !is_wp_error($success) ){
            do_action('wpsstm_queue_track',$track,$this->post_id);
            
            //favorites ?
            if ( $this->post_id == WPSSTM_Core_User::get_user_favorites_tracklist_id() ){
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
        if ( $this->post_id == WPSSTM_Core_User::get_user_favorites_tracklist_id() ){
            do_action('wpsstm_love_track',$track,false);
        }

        return true;
        
    }
    
    /*
    TO FIX TO CHECK maybe we also should have a function to save multiple subtracks in one single query ?
    I mean when we have playlists with hundreds of subtracks to save...
    */
    
    public function insert_subtrack(WPSSTM_Track $track){
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
            $track_id = $track->insert_bot_track();
            
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

        /*
        insert subtrack
        */
        if (!$track->subtrack_id){
            $subtrack_data['subtrack_time'] =       current_time('mysql');
            $subtrack_data['tracklist_id'] =        $this->post_id;
            $subtrack_data['from_tracklist'] =      $track->from_tracklist;
            $subtrack_data['subtrack_author'] =     ($author = $track->subtrack_author) ? $author : get_current_user_id();
            $subtrack_data['subtrack_order'] =      $this->get_subtracks_count() + 1;
            
            $track_data = array_merge($track_data,$subtrack_data);
        }

        $success = $wpdb->insert($subtracks_table,$track_data);
        //$track->track_log(array('success'=>$success,'subtrack_id'=>$wpdb->insert_id,'data'=>$track_data), "add subtrack" ); 

        if ( is_wp_error($success) ) return $success;

        $track->subtrack_id = $wpdb->insert_id;
        
        return $track->subtrack_id;

    }

    private function get_static_subtracks(){
        global $wpdb;

        $track_args = array(
            'posts_per_page'=>          -1,
            'orderby'=>                 'subtrack_position',
            'order'=>                   $this->get_options('order'),
            'post_type' =>              wpsstm()->post_type_track,
            'subtrack_query' =>         true,
            'fields' =>                 'subtrack=>track',
            'tracklist_id' =>           $this->post_id,
        );

        $query = new WP_Query( $track_args );
        $posts = $query->posts;
        $subtracks = array();

        foreach($posts as $post){
            /*
            Populate track by not hitting the DB, so we remain fast
            */
            $subtrack = new WPSSTM_Track();
            $subtrack->populate_from_post_obj($post);
            $subtracks[] = $subtrack;
        }

        return $subtracks;
    }

    function get_html_metas(){
        $metas = array(
            'numTracks' => $this->get_subtracks_count(),
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
    TOUFIX 
    this should be linked to a Wordpress query (exclude trashed posts, etc.), and allow query args ?
    cache this value ?
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
        WP_SoundSystem::debug_log($data,$title);
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

        if ( $this->feed_url && ($this->tracklist_type === 'live' ) ){
            $refresh_el = sprintf('<a class="wpsstm-reload-bt" href="%s">%s</a>',$this->get_tracklist_action_url('refresh'),__('Refresh'));
            $not_found .= sprintf("  %s ?",$refresh_el);
        }

        $this->add_notice('empty-tracklist', $not_found );
    }
    
    function importer_notice(){
        if ( is_admin() ) return;
        if ( !$this->feed_url ) return;
        if ( $this->tracklist_type !== 'live' ) return;
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
        if ( !wpsstm_is_bot_post($this->post_id) ) return;
        
        $autorship_url = $this->get_tracklist_action_url('get-autorship');
        $autorship_link = sprintf('<a href="%s">%s</a>',$autorship_url,__("add it to your profile","wpsstm"));
        $message = __("This is a temporary tracklist.","wpsstm");
        $message .= '  '.sprintf(__("Would you like to %s?","wpsstm"),$autorship_link);
        $this->add_notice('get-autorship', $message );
    }
    
    function get_debug_url(){
        if (!$this->import_id) return;
        return $xspf_url = WPSSTM_API_CACHE . sprintf('%s-feedback.json',$this->import_id);
    }
    
    function get_backend_tracks_url(){
        $links_url = admin_url('edit.php');
        $links_url = add_query_arg( 
            array(
                'post_type' =>      wpsstm()->post_type_track,
                'tracklist_id' =>   $this->post_id,
                //'post_status' => 'publish'
            ),$links_url 
        );
        return $links_url;
    }

}