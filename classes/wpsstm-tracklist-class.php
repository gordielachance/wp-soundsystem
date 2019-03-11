<?php

class WPSSTM_Post_Tracklist extends WPSSTM_Tracklist{
    
    var $post_id = 0; //tracklist ID (can be an album, playlist or live playlist)
    var $index = -1;
    var $tracklist_type = 'static';
    
    var $options = array();
    var $preset_options = array(); //stored preset options
    
    var $updated_time = null;
    private $is_expired = null;
    
    var $pagination = array(
        'total_pages'   => null,
        'per_page'      => null,
        'current_page'  => null
    );

    var $paged_var = 'tracklist_page';

    //live
    static $feed_url_meta_name = '_wpsstm_scraper_url';
    private static $remote_title_meta_name = 'wpsstm_remote_title';
    static $scraper_meta_name = '_wpsstm_scraper_options';
    public $feed_url = null;
    
    var $preset;
    
    public $classes = array();

    function __construct($post_id = null ){
        
        $pagination_args = array(
            'per_page'      => 0, //TO FIX default option
            'current_page'  => ( isset($_REQUEST[$this->paged_var]) ) ? $_REQUEST[$this->paged_var] : 1
        );
        
        /*
        options
        */
        
        $can_autosource = ( WPSSTM_Core_Sources::can_autosource() === true);
        
        $this->options = array(
            'autosource'                => ( wpsstm()->get_options('autosource') && $can_autosource ),
            'toggle_tracklist'          => (int)wpsstm()->get_options('toggle_tracklist'),
            'tracks_strict'             => true, //requires a title AND an artist
            'remote_delay_min'          => 5,
            'is_expired'                => false,
        );

        $this->set_tracklist_pagination($pagination_args);

        //has tracklist ID
        if ( $tracklist_id = intval($post_id) ) {
            $this->post_id = $tracklist_id;
            $this->populate_tracklist_post();
        }
    }
    
    static function get_tracklist_title($post_id){
        //title
        $title = get_post_field( 'title', $post_id );
        $post_type = get_post_type($post_id);
        
        //if no title has been set, use the cached title if any
        if ( ($post_type == wpsstm()->post_type_live_playlist) && !$title ){
            $title = get_post_meta($post_id,self::$remote_title_meta_name,true);
        }
        
        return $title;
    }
    
    function populate_tracklist_post(){
        
        $post_type = get_post_type($this->post_id);

        if ( !$this->post_id || ( !in_array($post_type,wpsstm()->tracklist_post_types) ) ){
            $this->tracklist_log('Invalid tracklist post');
            return;
        }

        $post_author_id = get_post_field( 'post_author', $this->post_id );
        $this->author = get_the_author_meta( 'display_name', $post_author_id );

        //type
        
        $this->tracklist_type = ($post_type == wpsstm()->post_type_live_playlist) ? 'live' : 'static';
        
        //title
        $this->title = self::get_tracklist_title($this->post_id);
        
        
        //time updated
        $this->updated_time = (int)get_post_modified_time( 'U', true, $this->post_id, true );
        if ( $this->tracklist_type == 'live' ){//TOUFIX what if not the 'live' type ?
            $this->updated_time = (int)get_post_meta($this->post_id,WPSSTM_Core_Live_Playlists::$time_updated_meta_name,true);
        }
        $seconds = $this->seconds_before_refresh();
        $this->is_expired = ($seconds <= 0);

        //live
        $this->feed_url = get_post_meta($this->post_id, self::$feed_url_meta_name, true );
        
        //options
        //$options_db = get_post_meta($this->post_id,self::$scraper_meta_name,true);
        //$this->options = array_replace_recursive($this->options,(array)$options_db);
        
        //scraper options
        $this->preset_options = get_post_meta($this->post_id,self::$scraper_meta_name,true);
        
        
        //location
        $this->location = get_permalink($this->post_id);
        if ( $this->tracklist_type == 'live' ){
            $this->location = $this->feed_url;
        }
        
        //classes
        if( $this->is_expired ) {
            $this->classes[] = 'tracklist-expired';
        }
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
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to create a new playlist.",'wpsstm') );
        }
        
        $validated = $this->validate_playlist();
        if ( !$validated ){
            return new WP_Error( 'wpsstm_missing_cap', __('Error while validating the playlist.','wpsstm') );
        }elseif( is_wp_error($validated) ){
            return $validated;
        }

        $post_playlist_id = null;
        $meta_input = array();
        
        /*
        $meta_input = array(
            WPSSTM_Core_Artists::$artist_metakey    => $this->artist,
            WPSSTM_Core_Tracks::$title_metakey      => $this->title,
            WPSSTM_Core_Albums::$album_metakey      => $this->album,
            WPSSTM_MusicBrainz::$mbid_metakey           => $this->mbid,
            //sources is more specific, will be saved below
        );
        */

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
        
        $is_ajax_refresh = wpsstm()->get_options('ajax_load_tracklists');

        if ( $is_ajax_refresh && !wp_doing_ajax() ){
            $this->tracklist_log("force is_expired to FALSE (we'll rely on ajax to refresh the tracklist)");
            $this->is_expired = false;
        }

        ob_start();
        wpsstm_locate_template( 'content-tracklist.php', true, false );
        $content = ob_get_clean();
        
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
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        */

        if ($do_love){
            $success = add_post_meta( $this->post_id, WPSSTM_Core_Tracklists::$loved_tracklist_meta_key, $user_id );
            do_action('wpsstm_love_tracklist',$this->post_id,$this);
        }else{
            $success = delete_post_meta( $this->post_id,WPSSTM_Core_Tracklists::$loved_tracklist_meta_key, $user_id );
            do_action('wpsstm_unlove_tracklist',$this->post_id,$this);
        }
        return $success;
    }
    
    function get_tracklist_favorited_by(){
        if ( !$this->post_id ) return false;
        return get_post_meta($this->post_id, WPSSTM_Core_Tracklists::$loved_tracklist_meta_key);
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
        $can_request = WPSSTM_Core_Live_Playlists::is_community_user_ready();
        $can_refresh = ( ($this->tracklist_type == 'live' ) && ($this->feed_url) && !is_wp_error($can_request) );

        $actions = array();

        //refresh
        if ($can_refresh){
            $actions['refresh'] = array(
                'text' =>      __('Refresh', 'wpsstm'),
                'href' =>      $this->get_tracklist_action_url('render'),
            );
        }
        
        //share
        $actions['share'] = array(
            'text' =>       __('Share', 'wpsstm'),
            'classes' =>    array('wpsstm-advanced-action'),
            'href' =>       $this->get_tracklist_action_url('share'),
            'classes' =>    array('wpsstm-tracklist-popup'),
        );
        
        //export
        $actions['export'] = array(
            'text' =>       __('Export', 'wpsstm'),
            'classes' =>    array('wpsstm-advanced-action'),
            'desc' =>       __('Export to XSPF', 'wpsstm'),
            'href' =>       $this->get_tracklist_action_url('export'),
            'target' =>     '_blank',
        );
        
        //favorite / unfavorite
        if ( get_current_user_id() ){

            $actions['favorite'] = array(
                'text' =>      __('Favorite','wpsstm'),
                'href' =>       $this->get_tracklist_action_url('favorite'),
                'ajax' =>       $this->get_tracklist_action_url('favorite',true),
                'desc' =>       __('Add tracklist to favorites','wpsstm'),
                'classes' =>    array('action-favorite'),
            );
            $actions['unfavorite'] = array(
                'text' =>      __('Unfavorite','wpsstm'),
                'href' =>       $this->get_tracklist_action_url('unfavorite'),
                'ajax' =>       $this->get_tracklist_action_url('unfavorite',true),
                'desc' =>       __('Remove tracklist from favorites','wpsstm'),
                'classes' =>    array('action-unfavorite'),
            );

        }else{ //call to action
            $actions['favorite'] = array(
                'text' =>      __('Favorite','wpsstm'),
                'href' =>       '#',
                'desc' =>       __('This action requires you to be logged.','wpsstm'),
                'classes' =>    array('action-favorite','wpsstm-tooltip','wpsstm-requires-login'),
            );
        }

        //toggle type
        if ( $this->feed_url && $this->user_can_toggle_playlist_type() ){
            
            if($this->tracklist_type == 'live'){
                $actions['live'] = array(
                    'text' =>      __('Stop sync', 'wpsstm'),
                    'classes' =>    array('wpsstm-advanced-action'),
                    'desc' =>       __('Convert this live playlist to a static playlist', 'wpsstm'),
                    'href' =>       $this->get_tracklist_action_url('static'),
                    'target' =>     '_parent',
                );
            }else{
                $actions['static'] = array(
                    'text' =>      __('Sync', 'wpsstm'),
                    'classes' =>    array('wpsstm-advanced-action'),
                    'desc' =>       __('Restore this playlist back to a live playlist', 'wpsstm'),
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

        return apply_filters('wpsstm_tracklist_actions',$actions);
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

    function get_tracklist_action_url($action = null,$ajax=false){

        $url = $this->get_tracklist_url();
        if ( !$url ) return false;

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
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }


        //TO FIX validate status regarding user's caps
        $new_status = ( isset($_REQUEST['frontend-wizard-status']) ) ? $_REQUEST['frontend-wizard-status'] : null;
        
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
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
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
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to delete this tracklist.",'wpsstm') );
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
            'data-wpsstm-tracklist-options' =>      json_encode($this->get_options()),
            'data-wpsstm-toggle-tracklist' =>       $this->get_options('toggle_tracklist'),
            'data-wpsstm-domain' =>                 wpsstm_get_url_domain( $this->feed_url ),
        );

        $values_attr = array_merge($values_defaults,(array)$values_attr);
        
        return wpsstm_get_html_attr($values_attr);
    }
    
    public function populate_preset(){
        /*
        redirect URL
        Hook to filter bangs, etc.
        */
        $feed_url = apply_filters('wpsstm_feed_url',$this->feed_url);

        /*
        Build presets.
        The default preset, WPSSTM_Remote_Tracklist, should be hooked with the lowest priority
        */
        $presets = array();
        $presets = apply_filters('wpsstm_remote_presets',$presets);

        /*
        Select a preset based on the tracklist URL, or use the default preset
        */
        foreach((array)$presets as $test_preset){
            
            $test_preset->__construct($feed_url,$this->preset_options);
            
            if ( ( $ready = $test_preset->init_url($feed_url) ) && !is_wp_error($ready) ){
                $this->preset = $test_preset;
                break;
            }
        }
        
        //default presset
        if (!$this->preset){
            $this->preset = new WPSSTM_Remote_Tracklist($feed_url,$this->preset_options);
        }

        $this->tracklist_log($this->preset->get_preset_name(),'preset found');
    }


    function populate_subtracks(){
        global $wpdb;

        $live = ( ($this->tracklist_type == 'live') && $this->is_expired );
        $refresh_delay = $this->get_human_next_refresh_time();

        if ($live){
            
            $this->populate_preset();
            $success =       $this->preset->populate_remote_tracks();
            
            $tracks = $this->preset->tracks;
            
            // if post title is empty, use the remote title
            $remote_title = $this->preset->title;

            if (!$this->title && $remote_title){
                $this->title = $remote_title;
            }
            
            //time updated
            $this->updated_time = current_time( 'timestamp', true );

            //if remote author exists, use it
            $remote_author = $this->preset->author;
            $this->author = ($remote_author) ? $remote_author : $this->author;
            
            // handle remote errors
            if ( is_wp_error($success) ){
                $this->add_notice($success->get_error_code(), $success->get_error_message() );                
            }else{
                $this->add_tracks($tracks);
                $updated = $this->set_live_datas($this->preset);
            }
            
            //link to wizard if we have a remote response (request did succeed) but no tracks
            if ( !is_wp_error($this->preset->response_body) && !$tracks ){
                $wizard_url =  get_edit_post_link( $this->post_id ) . '#wpsstm-metabox-wizard';
                $wizard_link = sprintf('<a href="%s">%s</a>',$wizard_url,__('here','wpsstm'));
                $this->add_notice('wizard_link', sprintf(__('We reached the remote page but were unable to parse the tracklist.  Click %s to open the parser settings.','wpsstm'),$wizard_link) );
            }

        }else{
            $tracks = $this->get_static_subtracks();
            $this->add_tracks($tracks);
        }
        
        $this->tracklist_log(array('tracks_populated'=>$this->track_count,'live'=>$live,'refresh_delay'=>$refresh_delay),'Populated subtracks');

        return true;
    }
    
    /*
    Update WP post and eventually update subtracks.
    */
    
    function set_live_datas(WPSSTM_Remote_Tracklist $datas){

        if (!$this->post_id){
            $this->tracklist_log('wpsstm_missing_post_id','Set live datas error' );
            return new WP_Error( 'wpsstm_missing_post_id', __('Required tracklist ID missing.','wpsstm') );
        }
        
        //capability check
        $can = WPSSTM_Core_Live_Playlists::is_community_user_ready();
        if ( is_wp_error($can) ) return $can;
        
        $this->tracklist_log('SET LIVE DATAS'); 

        //save subtracks
        if ($this->tracks){
            $success = $this->update_subtracks();
            if( is_wp_error($success) ) return $success;
        }
               
        //update tracklist

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

    function seconds_before_refresh(){
        
        return 10;//TOUFIX TOUREMOVE

        if ($this->tracklist_type != 'live') return false;

        $cache_min = $this->get_options('remote_delay_min');
        if (!$cache_min) return false;
        
        $updated_time = (int)get_post_meta($this->post_id,WPSSTM_Core_Live_Playlists::$time_updated_meta_name,true);
        if ( !$updated_time ) return false;
        
        $expiration_time = $updated_time + ($cache_min * MINUTE_IN_SECONDS);
        $now = current_time( 'timestamp', true );

        return $expiration_time - $now;
    }

    function get_human_next_refresh_time(){

        $cache_seconds = ( $cache_min = $this->get_options('remote_delay_min') ) ? $cache_min * MINUTE_IN_SECONDS : false;

        if ( !$cache_seconds ) return false;
        
        $time_refreshed = $this->updated_time;
        $next_refresh = $time_refreshed + $cache_seconds;
        $now = current_time( 'timestamp', true );
        
        $is_future = ( ($next_refresh - $now) > 0 );
        if (!$is_future) return false;
        
        return human_time_diff( $now, $next_refresh );
    }
    
    /*
    Clear the stored subtracks and add the new ones
    */

    protected function update_subtracks(){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        //TOUFIX TOUCHECK capability check
        
        //delete actual subtracks
        $this->tracklist_log('delete current tracklist subtracks'); 
        
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        $querystr = $wpdb->prepare( "DELETE FROM `$subtracks_table` WHERE tracklist_id = %d", $this->post_id );
        $success = $wpdb->get_results ( $querystr );
        

        $errors = array();
        $no_updates = 0;
        $saved = 0;
        foreach((array)$this->tracks as $index=>$track){
            $track->position = $index + 1;
            $success = $this->save_subtrack($track);
            
            //populate subtrack ID
            if( is_wp_error($success) ){
                $errors[] = $success;
            }else{
                $saved++;
                if ($success === false){ //no rows updated, but no errors either
                    $no_updates++;
                }
            }
        }
        
        $this->tracklist_log(sprintf('%s subtracks saved',$saved)); 
        
        if($errors){
            $this->tracklist_log(sprintf('%s errors occured when updating subtracks',count($errors))); 
        }
        if($no_updates){
            $this->tracklist_log(sprintf('%s subtracks remained identical',count($errors))); 
        }
        
        return true;
        
    }
    
    function validate_subtrack(WPSSTM_Track $track,$strict = true){
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_tracklist_id', __("Missing tracklist ID.",'wpsstm') );
        }
        return $track->validate_track($strict);
    }
    
    function queue_track(WPSSTM_Track $track){
        //unset some subtracks vars or subtrack will be moved instead of added
        $new_track = clone $track;
        $new_track->subtrack_id = null;

        $success = $this->save_subtrack($new_track);
        
        if ( $success && !is_wp_error($success) ){
            do_action('wpsstm_queue_track',$track,$this->post_id);
            
            //favorites ?
            if ( $this->post_id == wpsstm()->user->favorites_id ){
                do_action('wpsstm_love_track',$track);
            }
        }

        return $success;

    }
    
    function dequeue_track(WPSSTM_Track $track){
        
        $subtrack_ids = $track->get_subtrack_matches($this->post_id);
        if ( is_wp_error($subtrack_ids) ) return $subtrack_ids;
        
        foreach ($subtrack_ids as $subtrack_id){
            $subtrack = new WPSSTM_Track();
            $subtrack->populate_subtrack($subtrack_id);
            $success = $subtrack->remove_subtrack();

            if ( is_wp_error($success) ) return $success;

        }
        
        if ( $success && !is_wp_error($success) ){
            do_action('wpsstm_dequeue_track',$track,$this->post_id);
            
            //favorites ?
            if ( $this->post_id == wpsstm()->user->favorites_id ){
                do_action('wpsstm_unlove_track',$track);
            }
        }

        return true;
        
    }
    
    function save_subtrack(WPSSTM_Track $track){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        $valid = $this->validate_subtrack($track);
        if ( is_wp_error( $valid ) ) return $valid;
        
        //check for a track ID
        if (!$track->post_id){
            $track->local_track_lookup();
        }
        
        //TOUFIX should we check for a capabiliy here ?
        
        $track_data = array();

        //basic data
        if($track->post_id){
            $track_data = array(
                'track_id' =>   $track->post_id
            );
        }else{
            $track_data = array(
                'artist' =>   $track->artist,
                'title' =>   $track->title,
                'album' =>   $track->album
            );
        }

        //new subtrack
        if (!$track->subtrack_id){
            $subtrack_data['time'] =            current_time('mysql');
            $subtrack_data['tracklist_id'] =    $this->post_id;
            $subtrack_data['from_tracklist'] =  $track->from_tracklist;
            $subtrack_data['track_order'] =     $this->get_subtracks_count() + 1;
            
            $track_data = array_merge($track_data,$subtrack_data);
        }

        //is an update
        if ($track->subtrack_id){
            
            $success = $wpdb->update( 
                $subtracks_table, //table
                $track_data, //data
                array(
                    'ID'=>$track->subtrack_id
                )
            );
            
            if ( is_wp_error($success) ){
                $track->track_log($track->to_array(),"Error while updating subtrack" ); 
            }
        //is a new entry
        }else{
            $success = $wpdb->insert($subtracks_table,$track_data);

            if ( !is_wp_error($success) ){ //we want to return the created subtrack ID
                $track->subtrack_id = $wpdb->insert_id;
                //$track->track_log($track->to_array(),"Subtrack inserted" ); 
            }else{
                $error_msg = $success->get_error_message();
                $track->track_log(array('track'=>json_encode($track->to_array()),'error'=>$error_msg), "Error while saving subtrack" ); 
            }
        }
        
        return $success;

    }

    private function get_static_subtracks(){
        global $wpdb;
        //get subtracks
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        $querystr = $wpdb->prepare( "SELECT ID FROM `$subtracks_table` WHERE tracklist_id = %d ORDER BY track_order ASC", $this->post_id );
        $subtrack_ids = $wpdb->get_col( $querystr);
        
        //TOUFIX should we pass the subtrack IDS in a regular WP Query ?

        $tracks = array();
        foreach((array)$subtrack_ids as $subtrack_id){
            
            $subtrack = new WPSSTM_Track(); //default
            $subtrack->populate_subtrack($subtrack_id);
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
            //if no real cache is set; let's say tracklist is already expired at load!
            $metas['wpsstmRefreshTimer'] = $this->seconds_before_refresh();
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
                $links[] = sprintf('<a href="%s" target="_blank">%s</a>',get_author_posts_url($user_id),$user_info->user_login);
            }
            $output = implode(', ',$links);
        }
        return $output;
    }
    
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
    
    function do_tracklist_action($action){
        global $wp_query;
        
        $success = null;
        
        //action
        switch($action){

            case 'queue': //add subtrack
                
                $track = new WPSSTM_Track();
                
                //build track from request
                if( $url_track = $wp_query->get( 'wpsstm_track_data' ) ){
                    $track->from_array($url_track);
                }

                $success = $this->queue_track($track);
                
            break;
                
            case 'dequeue':
                
                $track = new WPSSTM_Track();
                
                //build track from request
                if( $url_track = $wp_query->get( 'wpsstm_track_data' ) ){
                    $track->from_array($url_track);
                }
                
                $success = $this->dequeue_track($track);
                
                
            break;

            case 'favorite':
            case 'unfavorite':
                $do_love = ( $action == 'favorite');
                $success = $this->love_tracklist($do_love);
            break;

            case 'trash':
                $success = $this->trash_tracklist();
            break;

            case 'live':
            case 'static':
                $live = ( $action == 'live');
                $success = $this->toggle_live($live);
            break;
            case 'refresh':
                //remove updated time
                $success = delete_post_meta($this->post_id,WPSSTM_Core_Live_Playlists::$time_updated_meta_name);
            break;
            case 'get-autorship':
                $success = $this->get_autorship();
            break;
        }
        return $success;
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
    var $track_count = 0;
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

    protected function validate_tracks($tracks){

        $valid_tracks = $rejected_tracks = array();
        $error_codes = array();
        $use_strict = $this->get_options('tracks_strict');
        
        $pending_tracks = array_unique($tracks);
        
        foreach($pending_tracks as $track){
            $valid = $track->validate_track($use_strict);
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

    
}


