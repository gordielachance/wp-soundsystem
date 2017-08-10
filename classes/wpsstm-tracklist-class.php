<?php

class WP_SoundSystem_Tracklist{
    
    var $post_id = 0; //tracklist ID (can be an album, playlist or live playlist)
    var $position = -1;
    var $tracklist_type = 'static';
    
    var $options_default = array();
    var $options = array();

    //infos
    var $title = null;
    var $author = null;
    var $location = null;
    
    //datas
    var $tracks = array();
    var $notices = array();
    
    var $updated_time = null;
    
    var $pagination = array(
        'total_pages'   => null,
        'per_page'      => null,
        'current_page'  => null
    );
    
    var $tracks_strict = true; //requires a title AND an artist

    static $paged_var = 'tracklist_page';
    
    var $track;
    var $current_track = -1;
    var $track_count = 0;
    var $in_track_loop = false;

    function __construct($post_id = null ){
        
        $pagination_args = array(
            'per_page'      => 0, //TO FIX default option
            'current_page'  => ( isset($_REQUEST[self::$paged_var]) ) ? $_REQUEST[self::$paged_var] : 1
        );

        $this->set_tracklist_pagination($pagination_args);

        if ($post_id){
            
            $this->post_id = $post_id;

            $this->title = get_post_field('post_title',$post_id); //use get_post_field here instead of get_the_title() so title is not filtered
            
            $post_author_id = get_post_field( 'post_author', $post_id );
            $this->author = get_the_author_meta( 'display_name', $post_author_id );
            
            //tracklist time
            $this->updated_time = get_post_modified_time( 'U', true, $this->post_id, true );
            if ( $meta = wpsstm_tracklists()->get_subtracks_update_time($this->post_id) ){
                $this->updated_time = $meta;
            }
            
            $this->location = get_permalink($post_id);

        }

        $this->options = array_replace_recursive((array)$this->get_default_options(),$this->options); //last one has priority
    }
    
    function get_options($keys=null){
        $options = array();

        if ($keys){
            return wpsstm_get_array_value($keys, $this->options);
        }else{
            return $this->options;
        }
    }
    
    protected function get_default_options(){
        return array(
            'autoplay'          => ( wpsstm()->get_options('autoplay') == 'on' ),
            'autosource'        => ( wpsstm()->get_options('autosource') == 'on' ),
            'can_play'          => ( wpsstm()->get_options('player_enabled') == 'on' ),
            'toggle_tracklist'  => 3,
        );
    }
    
    /*
    Return the subtracks IDs for a tracklist; by type ('static' or 'live')
    */

    function get_subtrack_ids($args = null){
        
        //get available subtracks
        
        $default = array(
            'post_status'   => 'any',
            'posts_per_page'=> -1,
            'orderby'       => 'subtrack_position',
        );

        $args = wp_parse_args((array)$args,$default);

        $forced = array(
            'post_type' =>      wpsstm()->post_type_track,
            'fields' =>         'ids',
            'tracklist_id' =>   $this->post_id,
            'subtracks_include' =>  $this->tracklist_type,
        );

        $args = wp_parse_args($forced,$args);

        $query = new WP_Query( $args );
        $ordered_ids = $query->posts;

        //wpsstm()->debug_log( json_encode(array('tracklist_id'=>$this->post_id,'type'=>$this->tracklist_type,'args'=>$args,'subtrack_ids'=>$ordered_ids)), "WP_SoundSystem_Tracklist::get_subtrack_ids()"); 
        
        return $ordered_ids;
        
    }
    
    /*
    Assign (static) subtracks IDs to a tracklist.
    */

    function set_subtrack_ids($ordered_ids = null){
        
        $success = false;
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_tracklist_no_post_id', __('Required tracklist ID missing','wpsstm') );
        }

        //capability check
        if ($this->tracklist_type == 'live'){
            
            $can_set_subtracks = wpsstm_live_playlists()->can_live_playlists();
            
        }else{
            
            $can_set_subtracks = $this->user_can_reorder_tracks();
            
        }

        if ( !$can_set_subtracks ){
            return new WP_Error( 'wpsstm_tracklist_no_edit_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        
        if ($ordered_ids){
            $ordered_ids = array_map('intval', $ordered_ids); //make sure every array item is an int - required for WP_SoundSystem_Track::get_parent_ids()
            $ordered_ids = array_filter($ordered_ids, function($var){return !is_null($var);} ); //remove nuls if any
            $ordered_ids = array_unique($ordered_ids);
        }

        //set post status to 'publish' if it is not done yet (it could be a temporary post)
        //TO FIX TO CHECK
        foreach((array)$ordered_ids as $track_id){
            $track_post_type = get_post_status($track_id);
            if ($track_post_type != 'publish'){
                wp_update_post(array(
                    'ID' =>             $track_id,
                    'post_status' =>    'publish'
                ));
            }
        }

        //static or live ?
        if ($this->tracklist_type == 'live'){
            $metaname = wpsstm_live_playlists()->subtracks_live_metaname;
        }else{
            $metaname = wpsstm_playlists()->subtracks_static_metaname;
        }
        
        wpsstm()->debug_log( json_encode(array('tracklist_id'=>$this->post_id,'type'=>$this->tracklist_type,'subtrack_ids'=>$ordered_ids)), "WP_SoundSystem_Tracklist::set_subtrack_ids()"); 
        
        if ($ordered_ids){
            $success = update_post_meta($this->post_id,$metaname,$ordered_ids);
        }else{
            $success = delete_post_meta($this->post_id,$metaname);
        }
        
        if ( is_wp_error($success) ) return $success;
        
        //update subtracks time
        $now = current_time( 'timestamp', true );
        $this->updated_time = $now;
        return update_post_meta($this->post_id,wpsstm_tracklists()->time_updated_subtracks_meta_name,$this->updated_time);

    }
    
    /*
    Append subtracks IDs to a tracklist.
    */
    
    function append_subtrack_ids($append_ids){
        //force array
        if ( !is_array($append_ids) ) $append_ids = array($append_ids);
        
        if ( empty($append_ids) ){
            return new WP_Error( 'wpsstm_tracks_no_post_ids', __('Required tracks IDs missing','wpsstm') );
        }

        $old_ids = (array)$this->get_subtrack_ids();
        $subtrack_ids = array_merge($old_ids,$append_ids);
        
        wpsstm()->debug_log( array('tracklist_id'=>$this->post_id,'old_ids'=>json_encode($old_ids),'new_ids'=>json_encode($append_ids)), "WP_SoundSystem_Tracklist::append_subtrack_ids()");
        
        return $this->set_subtrack_ids($subtrack_ids);
    }
    
    /*
    Remove subtracks IDs from a tracklist.
    */
    
    function remove_subtrack_ids($remove_ids){
        //force array
        if ( !is_array($remove_ids) ) $remove_ids = array($remove_ids);
        
        if ( empty($remove_ids) ){
            return new WP_Error( 'wpsstm_tracks_no_post_ids', __('Required tracks IDs missing','wpsstm') );
        }
        
        wpsstm()->debug_log( array('tracklist_id'=>$this->post_id, 'subtrack_ids'=>json_encode($remove_ids)), "WP_SoundSystem_Tracklist::remove_subtrack_ids()");
        
        $subtrack_ids = (array)$this->get_subtrack_ids();
        $subtrack_ids = array_diff($subtrack_ids,$remove_ids);
        
        return $this->set_subtrack_ids($subtrack_ids);
    }
    
    private function get_tracklist_type(){
        
        $tracklist_type = 'static'; //default
        
        $post_type = get_post_type($this->post_id);
        
        if ($post_type == wpsstm()->post_type_live_playlist){
            $tracklist_type = 'live';
        }

        return $tracklist_type;
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
        $this->tracks = $add_tracks;
        $this->track_count = count($this->tracks);
        
        return $add_tracks;
    }

    protected function validate_tracks($tracks){

        //array unique
        $pending_tracks = array_unique($tracks, SORT_REGULAR);
        $valid_tracks = array();
        
        foreach($pending_tracks as $track){
            if ( !$track->validate_track($this->tracks_strict) ) continue;
            $valid_tracks[] = $track;
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
            return new WP_Error( 'wpsstm_track_cap_missing', __("You don't have the capability required to create a new playlist.",'wpsstm') );
        }
        
        $validated = $this->validate_playlist();
        if ( !$validated ){
            return new WP_Error( 'wpsstm_track_cap_missing', __('Error while validating the playlist.','wpsstm') );
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

            $post_playlist_id = wp_insert_post( $post_playlist_new_args );
            wpsstm()->debug_log( array('post_id'=>$post_playlist_id,'args'=>json_encode($post_playlist_new_args)), "WP_SoundSystem_Tracklist::save_playlist() - post playlist inserted"); 

        }else{ //is a playlist update
            
            $post_playlist_update_args = array(
                'ID'            => $this->post_id
            );
            
            $post_playlist_update_args = wp_parse_args($post_playlist_update_args,$post_playlist_args);
            
            $post_playlist_id = wp_update_post( $post_playlist_update_args );
            
            wpsstm()->debug_log( array('post_id'=>$post_playlist_id,'args'=>json_encode($post_playlist_update_args)), "WP_SoundSystem_Tracklist::save_playlist() - post track updated"); 
        }

        if ( is_wp_error($post_playlist_id) ) return $post_playlist_id;

        $this->post_id = $post_playlist_id;

        return $this->post_id;
        
    }

    function save_new_subtracks($args = null){
        
        do_action('wpsstm_before_save_new_subtracks'); //eg. musicbrainz auto-guess ID will be ignored
        
        $new_ids = array();
        
        foreach($this->tracks as $key=>$track){
            
            $track_id = $track->post_id;
            
            if (!$track_id){
                $track_id = $track->save_track($args);
                if ( is_wp_error($track_id) ) continue;
                $new_ids[] = $track_id;
            }

        }

        return $new_ids;
        
    }
    
    function set_subtracks_auto_mbid(){
        //ignore if option disabled
        $auto_id = ( wpsstm()->get_options('mb_auto_id') == "on" );
        if (!$auto_id) return;
        
        foreach($this->tracks as $key=>$track){
            if (!$track->post_id) continue;
            if ( ( !$mbid = wpsstm_mb()->guess_mbid( $track->post_id ) ) || is_wp_error($mbid) ) continue;
            wpsstm_mb()->do_update_mbid($track->post_id,$mbid);
        }
    }
    
    function get_tracklist_list(){
        $this->options['autoplay'] = false;
        $this->options['autosource'] = false;
        $this->options['can_play'] = false;
        
        //return $this->get_tracklist_table();

        global $wpsstm_tracklist;
        global $post;
        
        //setup global post
        $post = get_post($this->post_id);
        setup_postdata( $post );
        
        //setup global tracklist
        $wpsstm_tracklist = $this;

        ob_start();

        wpsstm_locate_template( 'content-tracklist-list.php', true, false );

        return ob_get_clean();
    }

    function get_tracklist_table(){
        global $post;
        global $wpsstm_tracklist;
        $wpsstm_tracklist->populate_tracks(array('posts_per_page'=>-1));

        ob_start();
        wpsstm_locate_template( 'content-tracklist-table.php', true, false );
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
       
    /*
    Render notices as WP settings_errors() would.
    */
    
    function output_notices($context){
        echo $this->get_notices_output($context);
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
        if ( !current_user_can($tracklist_obj->cap->edit_post,$this->post_id) ){
            return new WP_Error( 'wpsstm_tracklist_no_edit_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
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
    
    function get_tracklist_actions($context = null){
        
        $tracklist_post_type = get_post_type($this->post_id);
        
        //no tracklist actions if this is a "track" tracklist
        if ($tracklist_post_type == wpsstm()->post_type_track ) return;
        
        /*
        Capability check
        */

        //playlist
        $permalink = get_permalink($this->post_id);
        
        $tracklist_obj = get_post_type_object($tracklist_post_type);
        
        $tracklist_status = get_post_status($this->post_id);
        $current_status_obj = get_post_status_object( $tracklist_status );

        //track
        $track_obj = get_post_type_object(wpsstm()->post_type_track);
        $can_edit_tracklist = ($this->post_id && current_user_can($tracklist_obj->cap->edit_post,$this->post_id) );
        
        $can_refresh = ( ($this->tracklist_type == 'live' ) && ($this->feed_url) );
        $can_share = $this->post_id; //TO FIX no conditions (call to action) BUT there should be a notice if post cannot be shared
        $can_favorite = $this->post_id; //call to action

        $actions = array();

        //refresh
        if ($can_refresh){
            $actions['refresh'] = array(
                'icon' =>       '<i class="fa fa-rss" aria-hidden="true"></i>',
                'text' =>      __('Refresh', 'wpsstm'),
                'href' =>      $permalink,
            );
        }
        
        //share
        if ($can_share){
            $actions['share'] = array(
                'icon' =>       '<i class="fa fa-share-alt" aria-hidden="true"></i>',
                'text' =>       __('Share', 'wpsstm'),
                'href' =>       $this->get_tracklist_admin_gui_url('share'),
            );
        }
        
        //XSPF
        if ($can_share){
            $actions['export'] = array(
                'icon' =>       '<i class="fa fa-download" aria-hidden="true"></i>',
                'text' =>       __('Export', 'wpsstm'),
                'desc' =>       __('Export to XSPF', 'wpsstm'),
                'href' =>       wpsstm_get_tracklist_link($this->post_id,'export'),
            );
        }
        
        //favorite
        if ($can_favorite){
            $actions['favorite'] = array(
                'icon'=>        '<i class="fa fa-heart-o" aria-hidden="true"></i>',
                'text' =>      __('Favorite','wpsstm'),
                'desc' =>       __('Add track to favorites','wpsstm'),
                'classes' =>    array('wpsstm-requires-auth','wpsstm-action-toggle-favorite'),
            );
            if ( !$this->is_tracklist_loved_by() ) $actions['favorite']['classes'][] = 'wpsstm-toggle-favorite-active';
        }

        //unfavorite
        if ($can_favorite){
            $actions['unfavorite'] = array(
                'icon'=>        '<i class="fa fa-heart" aria-hidden="true"></i>',
                'text' =>      __('Unfavorite','wpsstm'),
                'desc' =>       __('Remove track from favorites','wpsstm'),
                'classes' =>    array('wpsstm-requires-auth','wpsstm-action-toggle-favorite'),
            );
            if ( $this->is_tracklist_loved_by() ) $actions['unfavorite']['classes'][] = 'wpsstm-toggle-favorite-active';
        }
        
        //add track
        if ( $this->user_can_reorder_tracks() ){
            
            $new_subtrack_url = $this->get_tracklist_admin_gui_url('new-subtrack');
            $new_subtrack_url = add_query_arg(array('tracklist_id'=>$this->post_id),$new_subtrack_url);
            
            $actions['new-subtrack'] = array(
                'icon'      =>  '<i class="fa fa-plus" aria-hidden="true"></i>',
                'text'     =>   $track_obj->labels->add_new_item,
                'href'      =>  $new_subtrack_url,
                'classes'   =>  array('wpsstm-requires-auth','tracklist-action'),
            );
        }
        
        //switch status
        if ( $can_edit_tracklist && !wpsstm_is_backend() ){
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
            $form = sprintf('<form action="%s" method="POST" class="wpsstm-playlist-status"><select name="frontend-wizard-status" onchange="%s">%s</select><input type="hidden" name="%s" value="switch-status"/></form>',$permalink,$form_onchange,$status_options_str,wpsstm_tracklists()->qvar_tracklist_admin);

            $actions['status-switch'] = array(
                'icon' =>       '<i class="fa fa-calendar-check-o" aria-hidden="true"></i>',
                'text' =>      __('Status'),
                'link_after' => sprintf(' <em>%s</em>%s',$current_status_obj->label,$form),
                'classes' =>    array('wpsstm-requires-auth','tracklist-action'),
            );
        }

        //lock
        if ( !wpsstm_is_backend() && $this->user_can_lock_tracklist() ){
            $actions['lock-tracklist'] = array(
                'icon' =>       '<i class="fa fa-lock" aria-hidden="true"></i>',
                'text' =>      __('Lock', 'wpsstm'),
                'desc' =>       __('Convert this live playlist to a static playlist', 'wpsstm'),
                'href' =>       $this->get_tracklist_admin_gui_url('lock-tracklist'),
                'classes' =>    array('wpsstm-requires-auth','tracklist-action'),

            );
        }

        //unlock
        if ( !wpsstm_is_backend() && $this->user_can_unlock_tracklist() ){
            $actions['unlock-tracklist'] = array(
                'icon' =>       '<i class="fa fa-lock" aria-hidden="true"></i>',
                'text' =>      __('Unlock', 'wpsstm'),
                'desc' =>       __('Restore this playlist back to a live playlist', 'wpsstm'),
                'href' =>       $this->get_tracklist_admin_gui_url('unlock-tracklist'),
                'classes' =>    array('wpsstm-requires-auth','tracklist-action'),

            );
        }

        //context
        switch($context){
            case 'page':

                /*
                if ($can_edit_tracklist){
                    $actions['advanced'] = array(
                        'icon' =>       '<i class="fa fa-cog" aria-hidden="true"></i>',
                        'text' =>      __('Advanced', 'wpsstm'),
                        'href' =>       $this->get_tracklist_admin_gui_url('about'),
                    );
                }
                */

                $popup_action_slugs = array('share','new-subtrack','advanced');
                
                //set popup
                foreach ($actions as $slug=>$action){
                    if ( !in_array($slug,$popup_action_slugs) ) continue;
                    $actions[$slug]['popup'] = true;
                }
                
            break;
            case 'admin':
                unset($actions['refresh']);
            break;
        }
        
        $actions = apply_filters('wpsstm_tracklist_actions',$actions,$context);
        
        $default_action = wpsstm_get_blank_action();
        $default_action['classes'][] = 'wpsstm-tracklist-action';
        
        foreach((array)$actions as $slug=>$action){
            $actions[$slug] = wp_parse_args($action,$default_action);
        }
        return $actions;
        
    }

    function get_tracklist_admin_gui_url($tracklist_action = null){

        $url = null;
        
        if($this->post_id){
            $url = get_permalink($this->post_id);
            $url = add_query_arg(array(wpsstm_tracklists()->qvar_tracklist_admin=>$tracklist_action),$url);
        }

        return $url;
    }

    function move_live_tracks(){
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_tracklist_no_post_id', __('Required tracklist ID missing','wpsstm') );
        }
        
        wpsstm()->debug_log($this->post_id, "WP_SoundSystem_Tracklist::move_live_tracks()");
        
        $this->append_wizard_tracks();
        
        //clear live playlist subtracks
        $this->tracklist_type = 'live';
        $this->set_subtrack_ids();
        
        //revert type
        $this->tracklist_type = 'static';
        
        //clear wizard datas
        $this->toggle_enable_wizard(false);

    }
    
    function append_wizard_tracks(){
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_tracklist_no_post_id', __('Required tracklist ID missing','wpsstm') );
        }

        //get live IDs
        $this->tracklist_type = 'live';
        $live_ids = $this->get_subtrack_ids();

        //switch to static
        $this->tracklist_type = 'static';
        $this->append_subtrack_ids($live_ids);

        wpsstm()->debug_log( array('tracklist_id'=>$this->post_id, 'live_ids'=>json_encode($live_ids)), "WP_SoundSystem_Tracklist::append_wizard_tracks()");
    }

    function convert_to_live_playlist(){

        //TO FIX CAPABILITIES

        /*
        Existing playlist
        */
        $static_tracklist = $this;
        $subtracks_success = $static_tracklist->set_subtrack_ids(); //unset static subtracks
        
        $converted = set_post_type( $this->post_id, wpsstm()->post_type_live_playlist );
        
        $this->toggle_enable_wizard();

        return $converted;

    }
    
    function save_track_position($track_id,$position){
        
        if ( !$this->user_can_reorder_tracks() ){
            return new WP_Error( 'wpsstm_tracklist_no_edit_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        
        $ordered_ids = get_post_meta($this->post_id,wpsstm_playlists()->subtracks_static_metaname,true);
        
        //delete current
        if(($key = array_search($track_id, $ordered_ids)) !== false) {
            unset($ordered_ids[$key]);
        }
        
        //insert at position
        array_splice( $ordered_ids, $position, 0, $track_id );
        
        //save
        return $this->set_subtrack_ids($ordered_ids);
    }
    
    /*
    Flush orphan subtracks
    */
    function flush_subtracks($keep_ids = null){
        
        $flush_ids = array();
        $orphan_ids = array();
        $flushed_ids = array();
        
        
        $subtrack_ids = wpsstm_get_raw_subtrack_ids($this->tracklist_type,$this->post_id);
        if ( !$subtrack_ids ) return;

        $flush_ids = array_diff($subtrack_ids,(array)$keep_ids);

        if ( !$flush_ids ) return;
        
        foreach( (array)$flush_ids as $track_id ){
            
            $track = new WP_SoundSystem_Track($track_id);
            
            $parent_ids = (array)$track->get_parent_ids();
            
            $loved_by = $track->get_track_loved_by();

            //remove this tracklist from track parents (we can consider a subtrack as orphan if it has no other parent than this tracklist)
            $tracklist_parent_key = array_search($this->post_id, $parent_ids);
            if ( $tracklist_parent_key !== false ) unset($parent_ids[$tracklist_parent_key]);

            //is an orphan
            if ( empty($parent_ids) && empty($loved_by) ) $orphan_ids[] = $track_id;

        }

        if ( !$orphan_ids ) return;

        foreach( (array)$orphan_ids as $track_id ){
            $success = wp_trash_post($track_id);
            if ( $success ) $flushed_ids[] = $track_id;
        }

        wpsstm()->debug_log(json_encode(array('subtrack_ids'=>implode(',',(array)$subtrack_ids),'keep_ids'=>implode(',',(array)$keep_ids),'flush_ids'=>implode(',',(array)$flush_ids),'flushed'=>implode(',',(array)$flushed_ids))),"WP_SoundSystem_Tracklist::flush_subtracks()");

        return $flushed_ids;

    }
    
    function user_can_lock_tracklist(){

        if ( get_post_type($this->post_id) != wpsstm()->post_type_live_playlist ) return;
        
        $static_post_obj =  get_post_type_object(wpsstm()->post_type_playlist);
        $live_post_obj =  get_post_type_object(wpsstm()->post_type_live_playlist);

        $can_edit_static_cap = $static_post_obj->cap->edit_posts;
        $can_edit_static =    current_user_can($can_edit_static_cap);

        $can_edit_tracklist = current_user_can($live_post_obj->cap->edit_post,$this->post_id);
        return ( $can_edit_tracklist && !$this->is_wizard_disabled() && $can_edit_static );
        
    }
    
    function user_can_unlock_tracklist(){

        if ( get_post_type($this->post_id) != wpsstm()->post_type_playlist ) return;
        
        $static_post_obj =  get_post_type_object(wpsstm()->post_type_playlist);
        $live_post_obj =  get_post_type_object(wpsstm()->post_type_live_playlist);

        $can_edit_live_cap = $live_post_obj->cap->edit_posts;
        $can_edit_live =    current_user_can($can_edit_live_cap);

        $can_edit_tracklist = current_user_can($live_post_obj->cap->edit_post,$this->post_id);

        return ( $can_edit_tracklist && $this->is_wizard_disabled() && $can_edit_live );
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
        $tracklist_obj = get_post_type_object($post_type);
        $can_edit_tracklist = current_user_can($tracklist_obj->cap->edit_post,$this->post_id);
        
        return ( ($this->tracklist_type == 'static') && $can_edit_tracklist );
    }

    function get_tracklist_class($extra_classes = null){

        $classes = array(
            'wpsstm-tracklist',
        );
        
        $classes = array_merge($classes,(array)$extra_classes);

        if ( $this->get_options('can_play') ){
            $classes[] = 'wpsstm-playable-tracklist';
        }

        if ( ($this->tracklist_type == 'live') && $this->is_expired ){
            $classes[] = 'wpsstm-expired-tracklist';
        }
        
        //capabilities
        $tracklist_type = get_post_type($this->post_id);
        $tracklist_type_obj = get_post_type_object($tracklist_type);
        $can_manage_tracklist = current_user_can($tracklist_type_obj->cap->edit_post,$this->post_id);
        
        if ($can_manage_tracklist){
            $classes[] = 'wpsstm-can-manage-tracklist';
        }

        return $classes;
    }
    
    function get_tracklist_options_attr(){
        
        $allowed_options = array('autoplay','autosource','can_play','toggle_tracklist');
        
        $attr_options = array();

        foreach((array)$this->get_options() as $slug=>$value){
            if ( !in_array($slug,$allowed_options) ) continue;
            $attr_options[$slug] = $value;
        }

        return htmlspecialchars( json_encode($attr_options), ENT_QUOTES, 'UTF-8');
    }

    function get_expire_time(){

        if ( $this->tracklist_type != 'live' ) return;

        return (int)$this->expiration_time;
    }
    
    function get_refresh_rate(){

        if ( !$this->post_id ) return;
        if ( $this->tracklist_type != 'live' ) return;

        $freq = $this->get_options('datas_cache_min');

        $freq_secs = $freq * MINUTE_IN_SECONDS;

        return $refresh_time_human = human_time_diff( 0, $freq_secs );
    }
    
    function populate_tracks($args = null){

        $required = array(
            'post_type'         => wpsstm()->post_type_track,
            'tracklist_id'      => $this->post_id,
            'subtracks_include' => $this->tracklist_type,
            'orderby'           => 'subtrack_position',
        );
        
        $args = wp_parse_args($required,(array)$args);
        
        $subtrack_ids = $this->get_subtrack_ids();
        $this->add_tracks($subtrack_ids);
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
            delete_post_meta($this->post_id,wpsstm_tracklists()->time_updated_subtracks_meta_name); //force subtracks refresh
            return delete_post_meta($this->post_id, wpsstm_wizard()->wizard_disabled_metakey );
        }
    }

}

class WP_SoundSystem_Single_Track_Tracklist extends WP_SoundSystem_Tracklist{
    function get_subtrack_ids($args = null){
        return array($this->post_id);
    }
}
