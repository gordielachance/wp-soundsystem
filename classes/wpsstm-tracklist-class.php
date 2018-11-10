<?php

class WPSSTM_Post_Tracklist extends WPSSTM_Tracklist{
    
    var $unique_id = null; //TO FIX useful ?
    var $post_id = 0; //tracklist ID (can be an album, playlist or live playlist)
    var $index = -1;
    var $tracklist_type = 'static';
    
    var $options_default = array();
    var $options = array();
    
    var $updated_time = null;
    
    var $pagination = array(
        'total_pages'   => null,
        'per_page'      => null,
        'current_page'  => null
    );

    var $paged_var = 'tracklist_page';
    
    var $did_query_tracks = false; // so we know if the tracks have been requested yet or not
    
    //live
    static $feed_url_meta_name = '_wpsstm_scraper_url';
    static $scraper_meta_name = '_wpsstm_scraper_options';
    public $feed_url = null;
    var $scraper_options = array();

    function __construct($post_id = null ){
        
        $pagination_args = array(
            'per_page'      => 0, //TO FIX default option
            'current_page'  => ( isset($_REQUEST[$this->paged_var]) ) ? $_REQUEST[$this->paged_var] : 1
        );
        
        $default_options = self::get_default_options();
        $url_options = $this->get_url_options();
        $this->options = wp_parse_args($url_options,$default_options);

        $this->set_tracklist_pagination($pagination_args);
        
        
        $this->unique_id = uniqid(); //in case we don't have a post ID; useful for JS
        
        if ($post_id){
            $this->post_id = $post_id;
            $this->populate_tracklist_post();
        }
    }
    
    function populate_tracklist_post(){

        if (!$this->post_id) return;

        $this->title = get_the_title($this->post_id);
        $post_author_id = get_post_field( 'post_author', $this->post_id );
        $this->author = get_the_author_meta( 'display_name', $post_author_id );

        //tracklist time
        

        //live
        $this->feed_url = get_post_meta($this->post_id, self::$feed_url_meta_name, true );
        $this->is_expired = $this->check_has_expired();
        $this->ajax_tracklist = $this->check_has_expired();
        
        //scraper
        $scraper_db = get_post_meta($this->post_id,self::$scraper_meta_name,true);
        $scraper_default = WPSSTM_Remote_Datas::get_default_scraper_options();
        $this->scraper_options = array_replace_recursive($scraper_default,(array)$scraper_db); //last one has priority
        
        //location
        $this->location = get_permalink($this->post_id);
        if ( $this->tracklist_type == 'live' ){
            $this->location = $this->feed_url;
        }
        
        //time updated
        $this->updated_time = get_post_modified_time( 'U', true, $this->post_id, true );
        if ( $this->tracklist_type == 'live' ){
            $this->updated_time = get_post_meta($this->post_id,WPSSTM_Core_Live_Playlists::$time_updated_meta_name,true);
        }
        
    }
    
    function get_options($keys=null){
        
        $options = apply_filters('wpsstm_tracklist_options',$this->options,$this);

        if ($keys){
            return wpsstm_get_array_value($keys, $options);
        }else{
            return $options;
        }
    }
    
    public static function get_default_options(){
        
        $options = array(
            'autoload'                  => ( !is_admin() ) ? true : false,
            'autoplay'                  => ( wpsstm()->get_options('autoplay') == 'on' ),
            'autosource'                => ( ( wpsstm()->get_options('autosource') == 'on' ) && (WPSSTM_Core_Sources::can_autosource() === true) ),
            'can_play'                  => ( wpsstm()->get_options('player_enabled') == 'on' ),
            'toggle_tracklist'          => (int)wpsstm()->get_options('toggle_tracklist'),
            'playable_opacity_class'    => ( wpsstm()->get_options('playable_opacity_class') == 'on' ),
            'tracks_strict'             => true, //requires a title AND an artist
            'ajax_tracklist'            => false,//should we load the subtracks through ajax ? (enabled by default for live playlists).
            'ajax_autosource'           => true,
            'is_expired'                => false,
            'ajax_tracklist'            => false,
            'datas_cache_min'           => 0,
        );

        return $options;

    }

    protected function get_url_options(){
        $url_options = isset( $_REQUEST['tracklist_options'] ) ? (array)$_REQUEST['tracklist_options'] : array();
        return $url_options;
    }

    public function get_title(){
        $title = $this->title;
        if (!$title && $this->post_id){
            $title = sprintf(__('(playlist #%d)','wpsstm'),$this->post_id);
        }
        return $title;
    }

    function validate_playlist(){
        if(!$this->title){
            return new WP_Error( 'wpsstm_playlist_title_missing', __('Please enter a title for this playlist.','wpsstm') );
        }
        return true;
    }

    function save_playlist(){

        //capability check
        $playlist_type_obj = get_post_type_object(wpsstm()->post_type_playlist);
        $create_playlist_cap = $playlist_type_obj->cap->edit_posts;

        if ( !current_user_can($create_playlist_cap) ){
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
            
            $this->tracklist_log( array('post_id'=>$post_playlist_id,'args'=>json_encode($post_playlist_new_args)), "WPSSTM_Post_Tracklist::save_playlist() - post playlist inserted"); 

        }else{ //is a playlist update
            
            $post_playlist_update_args = array(
                'ID'            => $this->post_id
            );
            
            $post_playlist_update_args = wp_parse_args($post_playlist_update_args,$post_playlist_args);
            
            $success = wp_update_post( $post_playlist_update_args, true );
            if ( is_wp_error($success) ) return $success;
            $post_playlist_id = $success;
            
           $this->tracklist_log( array('post_id'=>$post_playlist_id,'args'=>json_encode($post_playlist_update_args)), "WPSSTM_Post_Tracklist::save_playlist() - post track updated"); 
        }

        $this->post_id = $post_playlist_id;

        return $this->post_id;
        
    }

    function get_tracklist_html(){
        $this->register_tracklist_inline_js();
        ob_start();
        wpsstm_locate_template( 'content-tracklist.php', true, false );
        $html = ob_get_clean();
        return $html;
        
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
    
    function add_notice($slug,$code,$message,$error = false){
        
        //$this->tracklist_log(json_encode(array('slug'=>$slug,'code'=>$code,'error'=>$error)),'[WPSSTM_Post_Tracklist notice]: ' . $message ); 
        
        $this->notices[] = array(
            'slug'      => $slug,
            'code'      => $code,
            'message'   => $message,
            'error'     => $error
        );

    }

    function get_notices_output($context){
        
        $notices = array();

        foreach ($this->notices as $notice){
            if ( $notice['slug'] != $context ) continue;
            
            $notice_classes = array(
                'inline',
                'settings-error',
                'wpsstm-notice',
                'is-dismissible'
            );
            
            //$notice_classes[] = ($notice['error'] == true) ? 'error' : 'updated';
            
            $notice_attr_arr = array(
                'id'    => sprintf('wpsstm-notice-%s',$notice['code']),
                'class' => implode(' ',$notice_classes),
            );

            $notices[] = sprintf('<p %s><strong>%s</strong></p>',wpsstm_get_html_attr($notice_attr_arr),$notice['message']);
        }
        
        return implode("\n",$notices);
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
    
    function get_tracklist_loved_by(){
        if ( !$this->post_id ) return false;
        return get_post_meta($this->post_id, WPSSTM_Core_Tracklists::$loved_tracklist_meta_key);
    }
    
    function is_tracklist_loved_by($user_id = null){
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return;
        if ( !$this->post_id ) return false;
        
        $loved_by = $this->get_tracklist_loved_by();
        return in_array($user_id,(array)$loved_by);
    }
    
    function get_tracklist_links($context = null){
        
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
        $can_favorite = $this->post_id; //call to action TO FIX to CHECK

        $actions = array();

        //refresh
        if ($can_refresh){
            $actions['refresh'] = array(
                'text' =>      __('Refresh', 'wpsstm'),
                'href' =>      $this->get_tracklist_action_url('refresh'),
            );
        }
        
        //share
        $actions['share'] = array(
            'text' =>       __('Share', 'wpsstm'),
            'classes' =>    array('wpsstm-advanced-action'),
            'href' =>       $this->get_tracklist_admin_url('share'),
            'classes' =>    array('wpsstm-link-popup'),
        );
        
        //export
        $actions['export'] = array(
            'text' =>       __('Export', 'wpsstm'),
            'classes' =>    array('wpsstm-advanced-action'),
            'desc' =>       __('Export to XSPF', 'wpsstm'),
            'href' =>       $this->get_tracklist_action_url('export'),
        );
        
        //favorite / unfavorite
        if ($can_favorite){
            
            $actions['favorite'] = array(
                'text' =>      __('Favorite','wpsstm'),
                'href' =>       $this->get_tracklist_action_url('favorite'),
                'desc' =>       __('Add to favorites','wpsstm'),
            );
            
            $actions['unfavorite'] = array(
                'text' =>      __('Unfavorite','wpsstm'),
                'href' =>       $this->get_tracklist_action_url('unfavorite'),
                'desc' =>       __('Remove from favorites','wpsstm'),
            );
        }
        
        //switch status
        if ( $can_edit_tracklist ){
            
            $status_options = array();
            $statii = array('draft','publish','private','trash');

            foreach($statii as $slug){
                $status_obj = get_post_status_object( $slug );
                $status_label = $status_obj->label;
                $selected = selected($tracklist_status, $slug, false);

                $status_options[] = sprintf('<option value="%s" %s>%s</option>',$slug,$selected,$status_label);
            }

            //status form
            $status_options_str = implode("\n",$status_options);
            $form_onchange = "if(this.value !='') { this.form.submit(); }";
            $form = sprintf('<form action="%s" method="POST" class="wpsstm-playlist-status"><select name="frontend-wizard-status" onchange="%s">%s</select><input type="hidden" name="%s" value="switch-status"/></form>',$permalink,$form_onchange,$status_options_str,WPSSTM_Core_Tracklists::$qvar_tracklist_action);

            $actions['status-switch'] = array(
                'text' =>      __('Status'),
                'classes' =>    array('wpsstm-advanced-action'),
                'link_after' => sprintf(' <em>%s</em>%s',$current_status_obj->label,$form),
            );
        }

        //toggle type
        if ( $this->user_can_toggle_playlist_type() ){
            $actions['lock-tracklist'] = array(
                'text' =>      __('Lock', 'wpsstm'),
                'classes' =>    array('wpsstm-advanced-action'),
                'desc' =>       __('Convert this live playlist to a static playlist', 'wpsstm'),
                'href' =>       $this->get_tracklist_action_url('lock-tracklist'),
            );
            $actions['unlock-tracklist'] = array(
                'text' =>      __('Unlock', 'wpsstm'),
                'classes' =>    array('wpsstm-advanced-action'),
                'desc' =>       __('Restore this playlist back to a live playlist', 'wpsstm'),
                'href' =>       $this->get_tracklist_action_url('unlock-tracklist'),
            );
        }

        //edit backend
        if ( $can_edit_tracklist ){
            $actions['edit-backend'] = array(
                'text' =>       __('Edit'),
                'classes' =>    array('wpsstm-advanced-action','wpsstm-link-popup'),
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
        
        //log
        if ( $can_edit_tracklist ){
            $actions['debug'] = array(
                'text' =>      __('Log'),
                'classes'   =>  array('wpsstm-link-popup','wpsstm-advanced-action'),
                'desc' =>       __('View debug log','wpsstm'),
                'href' =>       $this->get_tracklist_admin_url('debug'),
            );
        }
        
        //context
        switch($context){
            case 'page':
            break;
            case 'popup':
                unset($actions['refresh']);
            break;
        }
        
        return apply_filters('wpsstm_tracklist_actions',$actions,$context);
    }

    
    function get_tracklist_admin_url($tab =  null){
        if ( !$this->post_id ) return;
        $args = array(WPSSTM_Core_Tracklists::$qvar_tracklist_admin=>$tab);
        $url = get_permalink($this->post_id);
        $url = add_query_arg($args,$url);
        return $url;
    }

    function get_tracklist_action_url($action = null){
        if ( !$this->post_id ) return;

        $url = add_query_arg(
            array(
                WPSSTM_Core_Tracklists::$qvar_tracklist_action=>$action
            ),
            get_permalink($this->post_id)
        );

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
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_post_id', __('Required tracklist ID missing.','wpsstm') );
        }
        
        if ( !wpsstm_is_community_post($this->post_id) ){
            return new WP_Error( 'wpsstm_not_community_post', __('This is not a community post.','wpsstm') );
        }
            
        //capability check
        $can_get_authorship = $this->can_get_tracklist_authorship();
        
        if ( !$can_get_authorship ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        
        $args = array(
            'ID'            => $this->post_id,
            'post_author'   => get_current_user_id(),
        );

        return wp_update_post( $args, true );
            
    }

    function toggle_playlist_type(){

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
        $new_type = ($this->tracklist_type == 'static') ? wpsstm()->post_type_live_playlist : wpsstm()->post_type_playlist;

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
    
    function save_feed_url(){

        if (!$this->feed_url){
            return delete_post_meta( $this->post_id, self::$feed_url_meta_name );
        }else{
            return update_post_meta( $this->post_id, self::$feed_url_meta_name, $this->feed_url );
        }
    }
    
    function save_track_position($track_id,$index){
        return;//TOUFIX
        if ( !$this->user_can_reorder_tracks() ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
    }
    
    function can_get_tracklist_authorship(){
        
        if ( !$post_type = get_post_type($this->post_id) ) return false;
        if ( !in_array($post_type,wpsstm()->tracklist_post_types) ) return false; //is not a tracklist (maybe we are checking a single track here)
        if ( !wpsstm_is_community_post($this->post_id) ) return false;
            
        //capability check
        $post_type_obj = get_post_type_object($post_type);
        return current_user_can($post_type_obj->cap->edit_posts);
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
    
    function user_can_store_tracklist(){

        $community_user_id = wpsstm()->get_options('community_user_id');
        $post_author = get_post_field( 'post_author', $this->post_id );
        if( $post_author != $community_user_id ) return false;

        $post_type = get_post_type($this->post_id);
        $tracklist_obj = get_post_type_object($post_type);
        return current_user_can($tracklist_obj->cap->edit_post,$this->post_id);
        
    }
    
    function user_can_reorder_tracks(){
        $post_type = get_post_type($this->post_id);
        if ( !in_array($post_type,wpsstm()->tracklist_post_types) ) return false;
        
        $tracklist_obj = get_post_type_object($post_type);
        $can_edit_tracklist = current_user_can($tracklist_obj->cap->edit_post,$this->post_id);
        
        return ( ($this->tracklist_type == 'static') && $can_edit_tracklist );
    }
    
    function register_tracklist_inline_js(){

        ob_start();
        ?>
        var wpsstm_tracklist_<?php echo $this->unique_id;?>_options = <?php echo json_encode($this->get_options());?>;
        <?php
        $inline = ob_get_clean();

        //TO FIX TO CHECK we should rather hook this on 'wpsstm-tracklists' ?  But it does not work
        wp_add_inline_script('wpsstm', $inline);

    }
    
    function get_tracklist_attr($values_attr=null){
        
        //TO FIX weird code, not effiscient
        $extra_classes = ( isset($values_attr['extra_classes']) ) ? $values_attr['extra_classes'] : null;
        unset($values_attr['extra_classes']);
        
        //for data attribute
        $options = $this->get_options();

        $values_defaults = array(
            'itemscope' =>                          true,
            'itemtype' =>                           "http://schema.org/MusicPlaylist",
            'data-wpsstm-tracklist-id' =>           $this->post_id,
            'data-wpsstm-tracklist-unique-id' =>    $this->unique_id,
            'data-wpsstm-tracklist-idx' =>          $this->index,
            'data-wpsstm-toggle-tracklist' =>       $this->get_options('toggle_tracklist'),
            'data-wpsstm-domain' =>                 wpsstm_get_url_domain( $this->feed_url ),
        );

        $values_attr = array_merge($values_defaults,(array)$values_attr);
        
        return wpsstm_get_html_attr($values_attr);
    }

    function get_tracklist_class($extra_classes = null){

        $classes = array(
            'wpsstm-tracklist',
            ( $this->get_options('ajax_tracklist') ) ? 'ajax-tracklist' : null,
            $this->get_options('can_play') ? 'tracklist-playable' : null,
            ( $this->get_options('can_play') && $this->get_options('playable_opacity_class') ) ? 'playable-opacity' : null,
            ( $this->is_tracklist_loved_by() ) ? 'wpsstm-loved-tracklist' : null
            
        );
        
        if($extra_classes){
            $classes = array_merge($classes,(array)$extra_classes);
        }

        $classes = apply_filters('wpsstm_tracklist_classes',$classes,$this);

        return array_filter(array_unique($classes));
    }
    
    //if the tracklist is ajaxed and that this is not an ajax request, 
    //pretend did_query_tracks is true so we don't try to populate them
    //do not move under __construct since option 'ajax_tracklist' value might have changed when we call this.
    function wait_for_ajax(){
        return ($this->get_options('ajax_tracklist') && !wpsstm_is_ajax());
    }

    function populate_subtracks(){
        global $wpdb;

        if ( $this->did_query_tracks ) return true;

        //TOUFIX check is expired
        $this->is_expired = true;
        
        $live = ( ($this->tracklist_type == 'live') && $this->is_expired && !$this->wait_for_ajax() );
        $remote = new WPSSTM_Remote_Datas($this->feed_url,$this->scraper_options);
        
        //TOUFIX check is expired
        $this->is_expired = true;
        $live = true;

        if ($live){
            new WPSSTM_Live_Playlist_Stats($this); //remote request stats
            $tracks = $remote->get_remote_tracks();
            if ( is_wp_error($tracks) ) return $tracks;
            
        }else{
            $tracks = $this->get_static_subtracks();
        }

        //populate tracks
        $this->did_query_tracks = true;
        $this->tracks = $this->add_tracks($tracks);
        $this->track_count = count($this->tracks);
        
        $this->tracklist_log(sprintf('%s tracks have been populated',$this->track_count));
            
        //update live tracklist
        if ($live){
            $updated = $this->set_live_datas($remote);
            if ( is_wp_error($updated) ) return $updated;
        }
 
        if ( !$this->get_options('ajax_autosource') ){ //TOUFIX MOVE ELSEWHERE ?
            $this->tracklist_autosource();
        }
        
        return true;
    }
    
    /*
    Update WP post and eventually update subtracks.
    */
    
    function set_live_datas(WPSSTM_Remote_Datas $datas){

        if (!$this->post_id){
            $this->tracklist_log('wpsstm_missing_post_id','Set live datas error' );
            return new WP_Error( 'wpsstm_missing_post_id', __('Required tracklist ID missing.','wpsstm') );
        }

        //save subtracks
        if ($this->tracks){
            $success = $this->update_subtracks();
            if( is_wp_error($success) ) return $success;
        }
               
        //update tracklist
        $this->updated_time = current_time( 'timestamp', true );//set the time tracklist has been updated

        $meta_input = array(
            WPSSTM_Core_Live_Playlists::$remote_title_meta_name =>  $datas->get_remote_title(),
            WPSSTM_Core_Live_Playlists::$remote_author_meta_name => $datas->get_remote_author(),
            WPSSTM_Core_Live_Playlists::$time_updated_meta_name =>  $this->updated_time,
        );

        $tracklist_post = array(
            'ID' =>         $this->post_id,
            'meta_input' => $meta_input,
        );
        
        return true;

    }
    
    function get_time_before_refresh(){

        $cache_seconds = ( $cache_min = $this->get_options('datas_cache_min') ) ? $cache_min * MINUTE_IN_SECONDS : false;
        
        if ( !$cache_seconds ) return false;
        if ( $this->is_expired ) return false;
        
        $time_refreshed = $this->updated_time;
        $time_before = $time_refreshed + $cache_seconds;
        $now = current_time( 'timestamp', true );

        return $refresh_time_human = human_time_diff( $now, $time_before );
    }
    
    //UTC
    function get_expiration_time(){
        $cache_seconds = ( $cache_min = $this->get_options('datas_cache_min') ) ? $cache_min * MINUTE_IN_SECONDS : false;
        return $this->updated_time + $cache_seconds;
    }

    // checks if the playlist has expired (and thus should be refreshed)
    // set 'expiration_time'
    private function check_has_expired(){
        
        $cache_duration_min = $this->get_options('datas_cache_min');
        $has_cache = (bool)$cache_duration_min;
        
        if (!$has_cache){
            return true;
        }else{
            $now = current_time( 'timestamp', true );
            $expiration_time = $this->get_expiration_time(); //set expiration time
            return ( $now >= $expiration_time );
        }

    }
    
    /*
    Clear the stored subtracks and add the new ones
    */

    protected function update_subtracks(){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        //delete actual subtracks
        $this->tracklist_log('delete current tracklist subtracks'); 
        
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        $querystr = $wpdb->prepare( "DELETE FROM $subtracks_table WHERE tracklist_id = '%s'", $this->post_id );
        $success = $wpdb->get_results ( $querystr );
        

        $errors = array();
        $no_updates = 0;
        $saved = 0;
        foreach((array)$this->tracks as $track){
            $success = $track->save_subtrack();
            
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
    
    private function tracklist_autosource(){
        $this->tracklist_log('tracklist autosource'); 
        foreach((array)$this->tracks as $track){
            $track->populate_sources();
            if (!$track->sources){
                $success = $track->autosource();
            }
        }
    }
    
    private function get_static_subtracks(){
        global $wpdb;
        //get subtracks
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        $querystr = $wpdb->prepare( "SELECT * FROM $subtracks_table WHERE tracklist_id = '%s'", $this->post_id );
        $subtracks = $wpdb->get_results ( $querystr );
        
        $tracks = array();
        foreach((array)$subtracks as $subtrack){

            $track = new WPSSTM_Track(); //default
            
            if ($subtrack->track_id){
                $track = new WPSSTM_Track($subtrack->track_id);
            }else{
                $track_arr = array(
                    'artist' => $subtrack->artist,
                    'title' =>  $subtrack->title,
                    'album' =>  $subtrack->album,
                    //TOUFIX playlist id ?
                );
                $track->from_array($track_arr);
            }
            
            $track->subtrack_id = $subtrack->ID;
            
            $tracks[] = $track;
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
            $expiration_time = ($expiration = $this->get_expiration_time() ) ? $expiration : current_time( 'timestamp', true );
            $metas['wpsstmExpiration'] = $expiration_time;
        }
        
        return $metas;
    }
    
    function html_metas(){
        $metas = $this->get_html_metas();
        foreach( (array)$metas as $key=>$value ){
            printf('<meta itemprop="%s" content="%s"/>',$key,$value);
        }
    }
    
    function get_loved_by_list(){
        $links = array();
        $output = null;
        if ( $user_ids = $this->get_tracklist_loved_by() ){
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
        return $wpdb->get_var("SELECT COUNT(*) FROM $subtracks_table WHERE tracklist_id = $this->post_id");
    }

    function tracklist_log($message,$title = null){

        if (is_array($message) || is_object($message)) {
            $message = implode("\n", $message);
        }
        
        //tracklist log
        if ( $log_file = $this->get_tracklist_log_path() ){
            $blogtime = current_time( 'mysql' );
            $output = sprintf('[%s] %s - %s',$blogtime,$title,$message);

            error_log($output.PHP_EOL,3,$log_file);
        }
        
        //global log
        if ($this->post_id){
            $title = sprintf('[tracklist:%s] ',$this->post_id) . $title;
        }
        wpsstm()->debug_log($message,$title,null);
        

    }
    
    
    function get_tracklist_log_path(){
        if ( !$this->post_id ) return;
        $log_dir = wpsstm_get_uploads_dir();
        return $log_dir . sprintf('%s-debug.log',$this->post_id);
    }
    
    function delete_log(){
        $log_file = $this->get_tracklist_log_path();
        wp_delete_file($log_file);
    }

}

class WPSSTM_Tracklist{

    //infos
    var $title = null;
    var $author = null;
    var $location = null;
    
    //datas
    var $tracks = array();
    var $tracks_error = null; //TOUFIX TOCHECK
    var $notices = array();
    
    var $track;
    var $current_track = -1;
    var $track_count = -1; //-1 when not yet populated
    var $in_track_loop = false;

    function __construct($post_id = null){
 
    }

    /*
    $input_tracks = array of tracks objects or array of track IDs
    */
    
    function add_tracks($input_tracks){
        
        $add_tracks = array();
        $current_index = count($this->tracks);

        //force array
        if ( !is_array($input_tracks) ) $input_tracks = array($input_tracks);

        foreach ($input_tracks as $track){

            if ( !is_a($track, 'WPSSTM_Track') ){
                
                if ( is_array($track) ){
                    $track_args = $track;
                    $track = new WPSSTM_Track(null);
                    $track->from_array($track_args);
                }else{ //track ID
                    $track_id = $track;
                    //TO FIX check for int ?
                    $track = new WPSSTM_Track($track_id);
                }
            }
            
            $track->tracklist = $this;
            $track->index = $current_index;
            $add_tracks[] = $track;
            $current_index++;
        }

        $add_tracks = $this->validate_tracks($add_tracks);
        
        return $add_tracks;
    }

    protected function validate_tracks($tracks){

        //array unique
        $pending_tracks = array_unique($tracks, SORT_REGULAR);
        $valid_tracks = $rejected_tracks = array();
        $error_codes = array();
        $use_strict = $this->get_options('tracks_strict');
        
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
            $this->tracklist_log(array( 'count'=>count($rejected_tracks),'codes'=>json_encode($error_codes),'rejected'=>json_encode(array($rejected_tracks)) ), "WPSSTM_Tracklist::validate_tracks");
        }

        return $valid_tracks;
    }

    function to_array(){
        $export = array();
        foreach ($this->tracks as $track){
            $export[] = $track->to_array();
        }

        return array_filter($export);
    }

    /**
	 * Set up the next track and iterate current track index.
	 * @return WP_Post Next track.
	 */
	public function next_track() {

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
	public function the_track() {
		global $wpsstm_track;
		$this->in_track_loop = true;

		if ( $this->current_track == -1 ) // loop has just started
			/**
			 * Fires once the loop is started.
			 *
			 * @since 2.0.0
			 *
			 * @param WP_Query &$this The WP_Query instance (passed by reference).
			 */
			do_action_ref_array( 'wpsstm_tracks_loop_start', array( &$this ) );

		$wpsstm_track = $this->next_track();
		//$this->setup_trackdata( $wpsstm_track );
	}

	/**
	 * Determines whether there are more tracks available in the loop.
	 * Calls the {@see 'wpsstm_tracks_loop_end'} action when the loop is complete.
	 * @return bool True if tracks are available, false if end of loop.
	 */
	public function have_tracks() {
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

		$this->in_track_loop = false;
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


