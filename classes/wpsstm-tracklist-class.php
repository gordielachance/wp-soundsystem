<?php

class WP_SoundSystem_Tracklist{
    
    var $post_id = 0; //tracklist ID (can be an album, playlist or live playlist)
    var $index = -1;
    var $tracklist_type = 'static';
    
    var $options_default = array();
    var $options = array();

    //infos
    var $title = null;
    var $author = null;
    var $location = null;
    
    //datas
    var $tracks = array();
    var $tracks_error = null;
    var $notices = array();
    
    var $updated_time = null;
    
    var $pagination = array(
        'total_pages'   => null,
        'per_page'      => null,
        'current_page'  => null
    );
    
    var $tracks_strict = true; //requires a title AND an artist
    public $ajax_refresh = false;//should we load the subtracks through ajax ? (enabled by default for live playlists).

    var $paged_var = 'tracklist_page';
    
    var $track;
    var $current_track = -1;
    var $track_count = -1; //-1 when not yet populated
    var $in_track_loop = false;
    var $did_query_tracks = false; // so we know if the tracks have been requested yet or not

    function __construct($post_id = null ){
        
        $pagination_args = array(
            'per_page'      => 0, //TO FIX default option
            'current_page'  => ( isset($_REQUEST[$this->paged_var]) ) ? $_REQUEST[$this->paged_var] : 1
        );
        
        $this->options = $this->get_default_options();

        $this->set_tracklist_pagination($pagination_args);
        
        $this->post_id = $post_id;
        $this->populate_tracklist_post();
        
    }
    
    function populate_tracklist_post(){

        if (!$this->post_id) return;

        $this->title = get_the_title($this->post_id);
        $post_author_id = get_post_field( 'post_author', $this->post_id );
        $this->author = get_the_author_meta( 'display_name', $post_author_id );

        //tracklist time
        $this->updated_time = get_post_modified_time( 'U', true, $this->post_id, true );

        $this->location = get_permalink($this->post_id);
    }
    
    function get_options($keys=null){
        
        $options = apply_filters('wpsstm_tracklist_options',$this->options,$this);

        if ($keys){
            return wpsstm_get_array_value($keys, $options);
        }else{
            return $options;
        }
    }
    
    protected function get_default_options(){
        return array(
            'autoload'                  => ( !is_admin() ) ? true : false,
            'autoplay'                  => ( wpsstm()->get_options('autoplay') == 'on' ),
            'autosource'                => ( wpsstm()->get_options('autosource') == 'on' ),
            'can_play'                  => ( wpsstm()->get_options('player_enabled') == 'on' ),
            'toggle_tracklist'          => (int)wpsstm()->get_options('toggle_tracklist'),
            'playable_opacity_class'    => ( wpsstm()->get_options('playable_opacity_class') == 'on' ),
        );
    }
    
    /*
    Assign subtracks IDs to a tracklist.
    */

    function set_subtrack_ids($ordered_ids = null){
        global $wpdb;
        
        $success = false;
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_post_id', __('Required tracklist ID missing.','wpsstm') );
        }

        //capability check
        if ($this->tracklist_type == 'live'){
            $can_set_subtracks = wpsstm_live_playlists()->can_live_playlists();
        }else{
            $can_set_subtracks = $this->user_can_reorder_tracks();
            
        }

        if ( !$can_set_subtracks ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        
        if ($ordered_ids){
            $ordered_ids = array_map('intval', $ordered_ids); //make sure every array item is an int - required for WP_SoundSystem_Track::get_parent_ids()
            $ordered_ids = array_filter($ordered_ids, function($var){return !is_null($var);} ); //remove nuls if any
            $ordered_ids = array_unique($ordered_ids);
        }

        wpsstm()->debug_log( json_encode(array('tracklist_id'=>$this->post_id,'type'=>$this->tracklist_type,'subtrack_ids'=>$ordered_ids)), "WP_SoundSystem_Tracklist::set_subtrack_ids()"); 
        
        //delete actual subtracks
        $subtracks_table_name = $wpdb->prefix . wpsstm()->subtracks_table_name;
        $querystr = $wpdb->prepare( "DELETE FROM $subtracks_table_name WHERE tracklist_id = '%s'", $this->post_id );
        $success = $wpdb->get_results ( $querystr );
        
        //set new subtracks
        $subtrack_pos = 0;
        foreach((array)$ordered_ids as $subtrack_id){
            $wpdb->insert($subtracks_table_name, array(
                'track_id' =>       $subtrack_id,
                'tracklist_id' =>   $this->post_id,
                'track_order' =>    $subtrack_pos
            ));
            $subtrack_pos++;
        }
        
        //TO FIX handle errors ?
        
        return true;

    }
    
    /*
    Append subtracks IDs to a tracklist.
    */
    
    function append_subtrack_ids($append_ids){
        
        //force array
        if ( !is_array($append_ids) ) $append_ids = array($append_ids);
        
        if ( empty($append_ids) ){
            return new WP_Error( 'wpsstm_tracks_no_post_ids', __('Required tracks IDs missing.','wpsstm') );
        }

        $subtrack_ids = (array)$this->get_subtracks(array('fields' =>'ids'));

        wpsstm()->debug_log( json_encode(array('tracklist_id'=>$this->post_id,'current_ids'=>$subtrack_ids,'append_ids'=>$append_ids)), "WP_SoundSystem_Tracklist::append_subtrack_ids()");
        
        $updated_ids = array_merge($subtrack_ids,$append_ids);
        
        return $this->set_subtrack_ids($updated_ids);
    }
    
    /*
    Remove subtracks IDs from a tracklist.
    */
    
    function remove_subtrack_ids($remove_ids){
        //force array
        if ( !is_array($remove_ids) ) $remove_ids = array($remove_ids);
        
        if ( empty($remove_ids) ){
            return new WP_Error( 'wpsstm_tracks_no_post_ids', __('Required tracks IDs missing.','wpsstm') );
        }
        
        $subtrack_ids = (array)$this->get_subtracks(array('fields' =>'ids'));

        wpsstm()->debug_log( json_encode(array('tracklist_id'=>$this->post_id,'current_ids'=>$subtrack_ids,'remove_ids'=>$remove_ids)), "WP_SoundSystem_Tracklist::remove_subtrack_ids()");

        $updated_ids = array_diff($subtrack_ids,$remove_ids);
        
        return $this->set_subtrack_ids($updated_ids);
    }

    /*
    $input_tracks = array of tracks objects or array of track IDs
    */
    
    function add_tracks($input_tracks){
        
        $add_tracks = array();

        //force array
        if ( !is_array($input_tracks) ) $input_tracks = array($input_tracks);

        foreach ($input_tracks as $track){

            if ( !is_a($track, 'WP_SoundSystem_Track') ){
                
                if ( is_array($track) ){
                    $track_args = $track;
                    $track = new WP_SoundSystem_Track();
                    $track->from_array($track_args);
                }else{ //track ID
                    $track_id = $track;
                    //TO FIX check for int ?
                    $track = new WP_SoundSystem_Track($track_id);
                }
            }
            
            $add_tracks[] = $track;
        }

        //allow users to alter the input tracks.
        $add_tracks = apply_filters('wpsstm_input_tracks',$add_tracks,$this);
        $add_tracks = $this->validate_tracks($add_tracks);
        
        return $add_tracks;
    }

    protected function validate_tracks($tracks){

        //array unique
        $pending_tracks = array_unique($tracks, SORT_REGULAR);
        $valid_tracks = $rejected_tracks = array();
        $error_codes = array();
        
        foreach($pending_tracks as $track){
            $valid = $track->validate_track($this->tracks_strict);
            if ( is_wp_error($valid) ){
                
                $error_codes[] = $valid->get_error_code();
                /*
                wpsstm()->debug_log($valid->get_error_message(), "WP_SoundSystem_Tracklist::validate_tracks - rejected");
                */
                $rejected_tracks[] = $track;
                continue;
            }
            $valid_tracks[] = $track;
        }
        
        if ( $rejected_tracks ){
            $error_codes = array_unique($error_codes);
            wpsstm()->debug_log(array( 'count'=>count($rejected_tracks),'codes'=>json_encode($error_codes),'rejected'=>json_encode(array($rejected_tracks)) ), "WP_SoundSystem_Tracklist::validate_tracks");
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
            wpsstm_artists()->artist_metakey    => $this->artist,
            wpsstm_tracks()->title_metakey      => $this->title,
            wpsstm_albums()->album_metakey      => $this->album,
            wpsstm_mb()->mbid_metakey           => $this->mbid,
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
            
            wpsstm()->debug_log( array('post_id'=>$post_playlist_id,'args'=>json_encode($post_playlist_new_args)), "WP_SoundSystem_Tracklist::save_playlist() - post playlist inserted"); 

        }else{ //is a playlist update
            
            $post_playlist_update_args = array(
                'ID'            => $this->post_id
            );
            
            $post_playlist_update_args = wp_parse_args($post_playlist_update_args,$post_playlist_args);
            
            $success = wp_update_post( $post_playlist_update_args, true );
            if ( is_wp_error($success) ) return $success;
            $post_playlist_id = $success;
            
            wpsstm()->debug_log( array('post_id'=>$post_playlist_id,'args'=>json_encode($post_playlist_update_args)), "WP_SoundSystem_Tracklist::save_playlist() - post track updated"); 
        }

        $this->post_id = $post_playlist_id;

        return $this->post_id;
        
    }

    function save_subtracks($args = null){
        
        //do not auto guess MBID while saving subtracks
        remove_action( 'save_post', array(wpsstm_mb(),'auto_set_mbid'), 6);
        
        $new_ids = array();

        //filter tracks that does not exist in the DB yet
        $new_tracks = array_filter($this->tracks, function($track){
            if (!$track->post_id) return true;
            return false;
        });
        
        //save those new tracks
        foreach($new_tracks as $key=>$track){
            $success = $track->save_track($args);
            if ( is_wp_error($success) ){
                wpsstm()->debug_log($success->get_error_code(),'WP_SoundSystem_Tracklist::save_subtracks' );
                continue;
            }
        }
        
        //get all track IDs
        $track_ids = array_map(
            function($track){
                return $track->post_id;
            },
            $this->tracks
        );
        
        //set new subtracks
        return $this->set_subtrack_ids($track_ids);
    }

    function get_tracklist_html(){
        ob_start();
        wpsstm_locate_template( 'content-tracklist.php', true, false );
        $output = ob_get_clean();

        return $output;
        
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
        
        //wpsstm()->debug_log(json_encode(array('slug'=>$slug,'code'=>$code,'error'=>$error)),'[WP_SoundSystem_Tracklist notice]: ' . $message ); 
        
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
        if ( !in_array($post_type,wpsstm_tracklists()->tracklist_post_types) ) return false;
        if ( !current_user_can($tracklist_obj->cap->edit_post,$this->post_id) ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        */

        if ($do_love){
            return add_post_meta( $this->post_id, wpsstm_tracklists()->favorited_tracklist_meta_key, $user_id );
        }else{
            return delete_post_meta( $this->post_id, wpsstm_tracklists()->favorited_tracklist_meta_key, $user_id );
        }
    }
    
    function get_tracklist_loved_by(){
        if ( !$this->post_id ) return false;
        return get_post_meta($this->post_id, wpsstm_tracklists()->favorited_tracklist_meta_key);
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
        $can_refresh = ( ($this->tracklist_type == 'live' ) && ($this->feed_url) && wpsstm_live_playlists()->can_live_playlists() );
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
            'href' =>       $this->get_tracklist_popup_url('share'),
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
                'desc' =>       __('Remove track from favorites','wpsstm'),
            );
        }
        
        //add track
        if ( $this->user_can_reorder_tracks() ){
            
            $track_post_type_obj = get_post_type_object(wpsstm()->post_type_track);

            $actions['new-subtrack'] = array(
                'text'     =>   $track_post_type_obj->labels->add_new_item,
                'href'      =>  $this->get_tracklist_popup_url('new-subtrack'),
                'classes'   =>  array('wpsstm-link-popup'),
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
            $form = sprintf('<form action="%s" method="POST" class="wpsstm-playlist-status"><select name="frontend-wizard-status" onchange="%s">%s</select><input type="hidden" name="%s" value="switch-status"/></form>',$permalink,$form_onchange,$status_options_str,wpsstm_tracklists()->qvar_tracklist_action);

            $actions['status-switch'] = array(
                'text' =>      __('Status'),
                'classes' =>    array('wpsstm-advanced-action'),
                'link_after' => sprintf(' <em>%s</em>%s',$current_status_obj->label,$form),
            );
        }

        //lock
        if ( $this->user_can_lock_tracklist() ){
            $actions['lock-tracklist'] = array(
                'text' =>      __('Lock', 'wpsstm'),
                'classes' =>    array('wpsstm-advanced-action'),
                'desc' =>       __('Convert this live playlist to a static playlist', 'wpsstm'),
                'href' =>       $this->get_tracklist_action_url('lock-tracklist'),
            );
        }

        //unlock
        if ( $this->user_can_unlock_tracklist() ){
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
                'text' =>      __('Edit backend', 'wpsstm'),
                'classes' =>    array('wpsstm-advanced-action'),
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

    
    function get_tracklist_popup_url($action =  null){
        $url = $this->get_tracklist_action_url('popup');
        
        if ($action){
            $url = add_query_arg(array('popup-action'=>$action),$url);
        }

        return $url;
    }

    function get_tracklist_action_url($action = null){
        if ( !$this->post_id ) return;

        $url = add_query_arg(
            array(
                wpsstm_tracklists()->qvar_tracklist_action=>$action
            ),
            get_permalink($this->post_id)
        );

        return $url;
    }

    function append_wizard_tracks(){
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_post_id', __('Required tracklist ID missing.','wpsstm') );
        }

        //get live IDs
        $this->tracklist_type = 'live';
        $live_ids = $this->get_subtracks(array('fields' =>'ids'));

        //switch to static
        $this->tracklist_type = 'static';
        $this->append_subtrack_ids($live_ids);

        wpsstm()->debug_log( array('tracklist_id'=>$this->post_id, 'live_ids'=>json_encode($live_ids)), "WP_SoundSystem_Tracklist::append_wizard_tracks()");
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
        $can_get_authorship = $this->user_can_get_autorship();
        
        if ( !$can_get_authorship ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        
        $args = array(
            'ID'            => $this->post_id,
            'post_author'   => get_current_user_id(),
        );

        return wp_update_post( $args, true );
            
    }

    function convert_to_live_playlist(){

        if ( !$this->user_can_unlock_tracklist() ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        
        if ( wpsstm_is_community_post($this->post_id) ){
            $got_autorship = $this->get_autorship();
            if ( is_wp_error($got_autorship) ) return $got_autorship;
        }

        /*
        Existing playlist
        */
        $static_tracklist = $this;
        $subtracks_success = $static_tracklist->set_subtrack_ids(); //unset static subtracks
        
        $converted = set_post_type( $this->post_id, wpsstm()->post_type_live_playlist );
        
        $this->toggle_enable_wizard();

        return $converted;

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
        
        return wp_trash_post($this->post_id);
    }
    
    function save_track_position($track_id,$index){
        
        if ( !$this->user_can_reorder_tracks() ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }

        $subtrack_ids = $this->get_subtracks(array('fields' =>'ids'));
        
        //delete current
        if(($key = array_search($track_id, $subtrack_ids)) !== false) {
            unset($subtrack_ids[$key]);
        }
        
        //insert at position
        array_splice( $subtrack_ids, $index, 0, $track_id );
        
        //save
        return $this->set_subtrack_ids($subtrack_ids);
    }
    
    function user_can_get_autorship(){
        
        if ( !$this->post_id ) return false;
        if ( !wpsstm_is_community_post($this->post_id) ) return false;
            
        //capability check
        $post_type = get_post_type($this->post_id);
        $post_type_obj = get_post_type_object($post_type);
        return current_user_can($post_type_obj->cap->edit_posts);
    }
    
    function user_can_lock_tracklist(){

        if ( get_post_type($this->post_id) != wpsstm()->post_type_live_playlist ) return;
        
        $static_post_obj =  get_post_type_object(wpsstm()->post_type_playlist);
        $live_post_obj =  get_post_type_object(wpsstm()->post_type_live_playlist);

        $can_edit_static_cap = $static_post_obj->cap->edit_posts;
        $can_edit_static =    current_user_can($can_edit_static_cap);

        $can_edit_tracklist = current_user_can($live_post_obj->cap->edit_post,$this->post_id);

        return ( $can_edit_tracklist && $can_edit_static );
        
    }
    
    function user_can_unlock_tracklist(){

        if ( get_post_type($this->post_id) != wpsstm()->post_type_playlist ) return;
        
        $static_post_obj =  get_post_type_object(wpsstm()->post_type_playlist);
        $live_post_obj =  get_post_type_object(wpsstm()->post_type_live_playlist);

        $can_edit_live_cap = $live_post_obj->cap->edit_posts;
        $can_edit_live =    current_user_can($can_edit_live_cap);

        $can_edit_tracklist = current_user_can($live_post_obj->cap->edit_post,$this->post_id);

        return ( $can_edit_tracklist && $can_edit_live );
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
        if ( !in_array($post_type,wpsstm_tracklists()->tracklist_post_types) ) return false;
        
        $tracklist_obj = get_post_type_object($post_type);
        $can_edit_tracklist = current_user_can($tracklist_obj->cap->edit_post,$this->post_id);
        
        return ( ($this->tracklist_type == 'static') && $can_edit_tracklist );
    }
    
    function get_tracklist_attr($values_attr=null){
        
        //TO FIX weird code, not effiscient
        $extra_classes = ( isset($values_attr['extra_classes']) ) ? $values_attr['extra_classes'] : null;
        unset($values_attr['extra_classes']);
        
        //for data attribute
        $options = $this->get_options();

        $values_defaults = array(
            'itemscope' =>                      true,
            'itemtype' =>                       "http://schema.org/MusicPlaylist",
            'data-wpsstm-tracklist-id' =>       $this->post_id,
            'data-wpsstm-tracklist-idx' =>      $this->index,
            'data-tracks-count' =>              $this->track_count,
            'data-wpsstm-tracklist-options' =>  htmlspecialchars(json_encode($options)), 
            'data-wpsstm-toggle-tracklist' =>   $this->get_options('toggle_tracklist'),
        );

        $values_attr = array_merge($values_defaults,(array)$values_attr);
        
        return wpsstm_get_html_attr($values_attr);
    }

    function get_tracklist_class(){

        $classes = array(
            'wpsstm-tracklist',
            ( $this->ajax_refresh ) ? 'tracklist-ajaxed' : null,
            $this->get_options('autoplay') ? 'tracklist-autoplay' : null,
            $this->get_options('autosource') ? 'tracklist-autosource' : null,
            $this->get_options('can_play') ? 'tracklist-playable' : null,
            ( $this->get_options('can_play') && $this->get_options('playable_opacity_class') ) ? 'playable-opacity' : null,
            ( $this->is_tracklist_loved_by() ) ? 'wpsstm-loved-tracklist' : null
            
        );

        $classes = apply_filters('wpsstm_tracklist_classes',$classes,$this);

        return array_filter(array_unique($classes));
    }
    
    //if the tracklist is ajaxed and that this is not an ajax request, 
    //pretend did_query_tracks is true so we don't try to populate them
    //do not move under __construct since ->ajax_refresh value might have changed when we call this.
    function wait_for_ajax(){
        return ($this->ajax_refresh && !wpsstm_is_ajax());
    }


    function populate_subtracks($args = null){

        if ( $this->did_query_tracks ) return true;

        if ( $this->ajax_refresh ){

            if ( $this->wait_for_ajax() ){
                $url = $this->get_tracklist_action_url('refresh');
                $link = sprintf( '<a class="wpsstm-refresh-tracklist" href="%s">%s</a>',$url,__('Click to load the tracklist.','wpsstm') );
                $error = new WP_Error( 'requires-refresh', $link );
                $this->tracks_error = $error;
                return $error;
            }
        }

        $required = array('fields' => 'ids',);
        $args = wp_parse_args($required,(array)$args);
        
        $tracks_ids = $this->get_subtracks($args);

        $this->did_query_tracks = true;

        if ( is_wp_error($tracks_ids) ){
            $this->tracks_error = $tracks_ids;
            return $tracks;
        }

        $this->tracks = $this->add_tracks($tracks_ids);
        $this->track_count = count($this->tracks);
        return true;
    }
    
    public function get_subtracks($args = null){
        
        $default = array(
            'post_status'   => 'any',
            'posts_per_page'=> -1,
            'orderby'       => 'track_order',
            'order'         => 'ASC',
        );

        $args = wp_parse_args((array)$args,$default);
        
        $required = array(
            'post_type'         => wpsstm()->post_type_track,
            'tracklist_id'      => $this->post_id, //fetch only subtracks for this tracklist
        );
        
        $args = wp_parse_args($required,(array)$args);
        
        $query = new WP_Query( $args );
        $subtracks = $query->posts;

        //wpsstm()->debug_log( json_encode(array('tracklist_id'=>$this->post_id,'type'=>$this->tracklist_type,'args'=>$args,'subtrack_ids'=>$ordered_ids)), "WP_SoundSystem_Tracklist::get_subtracks"); 

        return $subtracks;
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
    
    function is_wizard_disabled(){
        return (bool)get_post_meta($this->post_id, wpsstm_wizard()->wizard_disabled_metakey, true );
    }
    
    function toggle_enable_wizard($enable=true){
        if (!$enable){
            return update_post_meta($this->post_id, wpsstm_wizard()->wizard_disabled_metakey, true );
        }else{
            return delete_post_meta($this->post_id, wpsstm_wizard()->wizard_disabled_metakey );
        }
    }
}

class WP_SoundSystem_Single_Track_Tracklist extends WP_SoundSystem_Tracklist{
    function get_subtracks($args = null){
        $required = array(
            'post__in' => array($this->post_id)
        );
        $args = wp_parse_args($required,(array)$args);
        return parent::get_subtracks($args);
    }
}
