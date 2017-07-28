<?php

abstract class WP_SoundSystem_Tracklist{
    
    var $post_id = 0; //tracklist ID (can be an album, playlist or live playlist)
    var $tracklist_type = null;
    
    var $options_default = array();
    var $options = array();

    //infos
    var $title = null;
    var $author = null;
    var $location = null;
    
    //datas
    var $tracks = array();
    var $did_query_tracks = false;
    var $notices = array();
    
    var $updated_time = null;
    
    var $pagination = array(
        'total_items'  => null,
        'total_pages'   => null,
        'per_page'      => null,
        'current_page'  => null
    );
    
    var $tracks_strict = true; //requires a title AND an artist
    
    static $paged_var = 'tracklist_page';

    abstract protected function set_subtrack_ids($ordered_ids = null);

    function __construct($post_id = null ){
        
        $pagination_args = array(
            'per_page'      => 0, //TO FIX default option
            'current_page'  => ( isset($_REQUEST[self::$paged_var]) ) ? $_REQUEST[self::$paged_var] : 1
        );

        $this->set_tracklist_pagination($pagination_args);


        if ($post_id){
            
            $this->post_id = $post_id;

            $this->title = get_the_title($post_id);
            
            $post_author_id = get_post_field( 'post_author', $post_id );
            $this->author = get_the_author_meta( 'display_name', $post_author_id );
            
            $this->updated_time = get_post_modified_time( 'U', false, $post_id );
            $this->location = get_permalink($post_id);
            
            $this->tracklist_type = $this->get_tracklist_type();
            
        }

        $this->options = array_replace_recursive((array)$this->options_default,$this->options); //last one has priority

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
            'autoplay' =>   ( wpsstm()->get_options('autoplay') == 'on' ),
            'autosource'=>  ( wpsstm()->get_options('autosource') == 'on' ),
        );
    }
    
    function load_subtracks(){
        
        if ( $this->did_query_tracks ) return;

        $subtracks = array();
 
        //get tracklist metas
        $subtrack_ids = $this->get_subtrack_ids();

        foreach ((array)$subtrack_ids as $subtrack_id){
            $track = new WP_SoundSystem_Track($subtrack_id);
            $subtracks[] = $track;
        }
        
        $this->add($subtracks);

        $this->did_query_tracks = true;
        
    }
    
    /*
    Return the subtracks IDs for a tracklist; by type ('static' or 'live')
    */

    function get_type_subtrack_ids($type = 'any', $args = null){
        
        //get available subtracks
        
        $default = array(
            'post_status' =>    'any',
            'posts_per_page'=>  -1,
        );

        $args = wp_parse_args((array)$args,$default);

        $forced = array(
            'post_type' =>      wpsstm()->post_type_track,
            'fields' =>         'ids',
            'tracklist_id' =>   $this->post_id,
            wpsstm_tracks()->qvar_include_subtracks =>  $type,
        );

        $args = wp_parse_args($forced,$args);

        $query = new WP_Query( $args );
        return $query->posts;
        
    }
    
    /*
    Assign (static) subtracks IDs to a tracklist.
    */

    function set_type_subtrack_ids($type,$ordered_ids = null){
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_tracklist_no_post_id', __('Required tracklist ID missing','wpsstm') );
        }

        //capability check
        $post_type = get_post_type($this->post_id);
        $tracklist_obj = get_post_type_object($post_type);
        if ( !current_user_can($tracklist_obj->cap->edit_post,$this->post_id) ){
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
        $metaname = wpsstm_playlists()->subtracks_static_metaname;
        if ($type == 'live'){
            $metaname = wpsstm_live_playlists()->subtracks_live_metaname;
        }
        
        wpsstm()->debug_log( json_encode(array('tracklist_id'=>$this->post_id,'meta'=>$metaname,'subtrack_ids'=>$ordered_ids)), "WP_SoundSystem_Tracklist::set_subtrack_ids()"); 
        
        if ($ordered_ids){
            return update_post_meta($this->post_id,$metaname,$ordered_ids);
        }else{
            return delete_post_meta($this->post_id,$metaname);
        }

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
        
        wpsstm()->debug_log( array('tracklist_id'=>$this->post_id, 'subtrack_ids'=>json_encode($append_ids)), "WP_SoundSystem_Tracklist::append_subtrack_ids()");
        
        $subtrack_ids = (array)$this->get_subtrack_ids();
        $subtrack_ids = array_merge($subtrack_ids,$append_ids);
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
    
    function get_tracklist_type(){
        if (!$this->post_id) return;
        $tracklist_post_type = get_post_type($this->post_id);

        switch ($tracklist_post_type){
            case wpsstm()->post_type_track:
                $tracklist_type = 'track';
            break;
            case wpsstm()->post_type_album:
                $tracklist_type = 'album';
            break;
            case wpsstm()->post_type_playlist:
                $tracklist_type = 'static-playlist';
            break;
            case wpsstm()->post_type_live_playlist:
                $tracklist_type = 'live-playlist';
            break;
        }
        
        return $tracklist_type;
    }

    /*
    $tracks = array of tracks objects or array of track IDs
    */
    
    function add($tracks){

        //force array
        if ( !is_array($tracks) ) $tracks = array($tracks);

        foreach ($tracks as $track){

            if ( !is_a($track, 'WP_SoundSystem_Track') ){
                
                if ( is_array($track) ){
                    $track_args = $track;
                    $track = new WP_SoundSystem_Track();
                    $track->populate_array($track_args);
                }else{ //track ID
                    $track_id = $track;
                    //TO FIX check for int ?
                    $track = new WP_SoundSystem_Track($track_id);
                }
            }

            $this->tracks[] = $track;
        }
        
        $this->validate_tracks();
        
        $tracks_count = null;
        foreach((array)$this->tracks as $key=>$track){
            //increment count
            $tracks_count++;
            $this->tracks[$key]->position = $tracks_count;
        }

        $this->set_tracklist_pagination( array('total_items'=>count($this->tracks) ) );

    }

    protected function validate_tracks(){
        
        //array unique
        $pending_tracks = array_unique($this->tracks, SORT_REGULAR);
        $valid_tracks = array();
        
        foreach($pending_tracks as $track){
            if ( !$track->validate_track($this->tracks_strict) ) continue;
            $valid_tracks[] = $track;
        }
        
        $this->tracks = $valid_tracks;
        $tracks_count = count($valid_tracks);
        $this->set_tracklist_pagination( array('total_items'=>$tracks_count) );

    }

    function array_export(){
        $export = array();
        foreach ($this->tracks as $track){
            $export[] = $track->array_export();
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

    function save_subtracks($args = null){
        
        do_action('wpsstm_save_multiple_tracks'); //eg. musicbrainz auto-guess ID will be ignored
        
        $subtrack_ids = array();
        
        foreach($this->tracks as $key=>$track){
            $track_id = $track->save_track($args);
            
            if ( is_wp_error($track_id) ) continue;
            $subtrack_ids[] = $track_id;
        }

        return $this->append_subtrack_ids($subtrack_ids);
        
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
    
    function remove_subtracks(){
        $rem_ids = array();
        
        foreach($this->tracks as $key=>$track){
            if (!$track->post_id) continue;
            $rem_ids[] = $track->post_id;
        }
        
        return $this->remove_subtrack_ids($rem_ids);
    }
    
    function delete_subtracks(){
        foreach($this->tracks as $key=>$track){
            if ( ($success = $track->delete_track()) && !is_wp_error($success) ){
                unset($this->tracks[$key]);
            }
        }
        //TO FIX call remove_subtracks() ?
    }

    /**
    Read-only tracklist table
    **/
    function get_tracklist_table($args = null){
        
        $this->load_subtracks();

        require_once wpsstm()->plugin_dir . 'classes/wpsstm-tracklist-table.php';
        $tracklist_table = new WP_SoundSystem_Tracklist_Table($this,$args);

        ob_start();
        $tracklist_table->prepare_items();
        $tracklist_table->display();
        return ob_get_clean();
    }
    
    public function set_tracklist_pagination( $args ) {

        $args = wp_parse_args( $args, $this->pagination );

        if ( ( $args['per_page'] > 0 ) && ( $args['total_items'] ) ){
            $args['total_pages'] = ceil( $args['total_items'] / $args['per_page'] );
        }

        $this->pagination = $args;
    }
    
    /*
    Use a custom function to display our notices since natice WP function add_settings_error() works only backend.
    */
    
    function add_notice($slug,$code,$message,$error = false){
        
        wpsstm()->debug_log(json_encode(array('slug'=>$slug,'code'=>$code,'error'=>$error)),'[WP_SoundSystem_Tracklist notice]: ' . $message ); 
        
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
    
    function display_notices($slug){
        echo $this->get_notices($slug);
    }
    
    function get_notices($slug){
        
        $notices = array();

        foreach ($this->notices as $notice){
            if ( $notice['slug'] != $slug ) continue;

            $notice_classes = array(
                'inline',
                'settings-error',
                'wpsstm-notice',
                'is-dismissible'
            );
            
            //$notice_classes[] = ($notice['error'] == true) ? 'error' : 'updated';
            
            $notices[] = sprintf('<p %s><strong>%s</strong></p>',wpsstm_get_classes_attr($notice_classes),$notice['message']);
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
        
        $tracklist_type = get_post_type($this->post_id);
        
        //no tracklist actions if this is a "track" tracklist
        if ($tracklist_type == wpsstm()->post_type_track ) return;
        
        /*
        Capability check
        */

        $is_live_tracklist =  $this->tracklist_type == 'live-playlist';
        
        //playlist
        $permalink = get_permalink($this->post_id);
        $tracklist_status = get_post_status($this->post_id);
        $tracklist_obj = get_post_type_object($tracklist_type);
        $current_status_obj = get_post_status_object( $tracklist_status );

        //track
        $track_obj = get_post_type_object(wpsstm()->post_type_track);
        $can_edit_tracklist = current_user_can($tracklist_obj->cap->edit_post,$this->post_id);
        $can_add_tracklist_items = !$is_live_tracklist;
        
        $can_refresh = ($tracklist_type == wpsstm()->post_type_live_playlist );
        $can_share = true; //TO FIX no conditions (call to action) BUT there should be a notice if post cannot be shared
        $can_favorite = true; //call to action
        $can_add_tracks = ( $can_edit_tracklist && $can_add_tracklist_items );
        $can_lock_playlist = ( $can_edit_tracklist && ($tracklist_type == wpsstm()->post_type_live_playlist ) );
        $can_unlock_playlist = ( $can_edit_tracklist && ($tracklist_type == wpsstm()->post_type_playlist ) && $this->has_wizard_backup() );

        $actions = array();

        //refresh
        if ($can_refresh){
            $actions['refresh'] = array(
                'icon' =>       '<i class="fa fa-rss" aria-hidden="true"></i>',
                'text' =>      __('Refresh', 'wpsstm'),
            );
        }
        
        //share
        if ($can_share){
            $actions['share'] = array(
                'icon' =>       '<i class="fa fa-share-alt" aria-hidden="true"></i>',
                'text' =>      __('Share', 'wpsstm'),
                'href' =>       $this->get_tracklist_admin_gui_url('share'),
            );
        }
        
        //XSPF
        if ($can_share){
            $actions['xspf'] = array(
                'icon' =>       '<i class="fa fa-rss" aria-hidden="true"></i>',
                'text' =>      'XSPF',
                'href' =>       wpsstm_get_tracklist_link($this->post_id,'xspf'),
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
        if ($can_add_tracklist_items){
            $actions['append'] = array(
                'icon'      =>  '<i class="fa fa-plus" aria-hidden="true"></i>',
                'text'     =>  $track_obj->labels->add_new_item,
                'href'      =>  $this->get_new_tracklist_track_url(),
                'classes'   =>  array('wpsstm-requires-auth','tracklist-action'),
            );
        }
        
        //switch status
        if ($can_edit_tracklist){
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
        if ($can_lock_playlist){
            $actions['lock-playlist'] = array(
                'icon' =>       '<i class="fa fa-lock" aria-hidden="true"></i>',
                'text' =>      __('Lock', 'wpsstm'),
                'desc' =>       __('Convert this live playlist to a static playlist', 'wpsstm'),
                'href' =>       $this->get_tracklist_admin_gui_url('lock-tracklist'),
                'classes' =>    array('wpsstm-requires-auth','tracklist-action'),

            );
        }

        //unlock
        if ($can_unlock_playlist){
            $actions['unlock-playlist'] = array(
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

                $popup_action_slugs = array('share','append','advanced');
                
                //set popup
                foreach ($actions as $slug=>$action){
                    if ( !in_array($slug,$popup_action_slugs) ) continue;
                    $actions[$slug]['popup'] = true;
                }
                
            break;
            case 'admin':
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
    
    function has_wizard_backup(){
        global $post;
        return (bool)get_post_meta($this->post_id, wpsstm_live_playlists()->feed_url_meta_name.'_old',true);
    }

    function get_new_tracklist_track_url(){
        $track = new WP_SoundSystem_Track();
        $url = $track->get_new_track_url();
        $args = array(
            'tracklist_id' => $this->post_id
        );
        return add_query_arg($args,$url);
    }
    
    function move_wizard_tracks(){
        if (!$this->post_id) return;
        
        wpsstm()->debug_log($this->post_id, "WP_SoundSystem_Tracklist::move_wizard_tracks()");
        
        $this->append_wizard_tracks();
        
        //clear live playlist subtracks
        $live_tracklist = wpsstm_get_post_live_tracklist($this->post_id);
        $live_tracklist->set_subtrack_ids();
        
        //clear wizard datas
        $live_tracklist->delete_wizard_datas();

    }
    
    function append_wizard_tracks(){
        if (!$this->post_id) return;

        $live_tracklist = wpsstm_get_post_live_tracklist($this->post_id);
        $live_ids = $live_tracklist->get_subtrack_ids();

        //append live subtracks
        $static_tracklist = new WP_SoundSystem_Static_Tracklist($this->post_id);
        $static_tracklist->append_subtrack_ids($live_ids);

        wpsstm()->debug_log( array('tracklist_id'=>$this->post_id, 'live_ids'=>json_encode($live_ids)), "WP_SoundSystem_Tracklist::append_wizard_tracks()");
    }
    
    function delete_wizard_datas($backup = true){
        
        if ( !$this->post_id ) return;
        
        if ($backup){
            $feed_url = wpsstm_get_live_tracklist_url($this->post_id);
            $options = get_post_meta($this->post_id,wpsstm_live_playlists()->scraper_meta_name,true);
            
            //backup wizard datas
            if($feed_url) update_post_meta($this->post_id, wpsstm_live_playlists()->feed_url_meta_name.'_old', $feed_url );
            if($options) update_post_meta($this->post_id,wpsstm_live_playlists()->scraper_meta_name.'_old',$options);
        }
        
        delete_post_meta( $this->post_id, wpsstm_live_playlists()->feed_url_meta_name );
        delete_post_meta( $this->post_id, wpsstm_live_playlists()->scraper_meta_name );
        
        return true;
    }
    
    function restore_wizard_datas(){
        if ( !$this->post_id ) return;
        
        $feed_url = get_post_meta($this->post_id, wpsstm_live_playlists()->feed_url_meta_name.'_old',true);
        $options = get_post_meta($this->post_id,wpsstm_live_playlists()->scraper_meta_name.'_old',true);

        //restore wizard datas
        if($feed_url) update_post_meta($this->post_id, wpsstm_live_playlists()->feed_url_meta_name, $feed_url );
        if($options) update_post_meta($this->post_id,wpsstm_live_playlists()->scraper_meta_name,$options);

        //delete backup
        delete_post_meta( $this->post_id, wpsstm_live_playlists()->feed_url_meta_name.'_old' );
        delete_post_meta( $this->post_id, wpsstm_live_playlists()->scraper_meta_name.'_old' );
    }
    
    function get_orphan_track_ids(){
        $orphan_ids = array();
        $subtrack_ids = $this->get_subtrack_ids();

        //get tracks to flush
        foreach ((array)$subtrack_ids as $track_id){

            $track = new WP_SoundSystem_Track($track_id);

            //ignore if post is attached to any (other than this one) playlist
            $tracklist_ids = $track->get_parent_ids();
            if(($key = array_search($this->post_id, $tracklist_ids)) !== false) unset($tracklist_ids[$key]);
            if ( !empty($tracklist_ids) ) continue;

            //ignore if post is favorited by any user
            $loved_by = $track->get_track_loved_by();
            if ( !empty($loved_by) ) continue;

            $orphan_ids[] = $track_id;
        }
        
        return $orphan_ids;

    }

}

class WP_SoundSystem_Static_Tracklist extends WP_SoundSystem_Tracklist{

    /*
    Return the (static) subtracks IDs for a tracklist.
    */

    function get_subtrack_ids($args = null){
        return parent::get_type_subtrack_ids('static', $args);
    }
    
    /*
    Assign (static) subtracks IDs to a tracklist.
    */

    function set_subtrack_ids($ordered_ids = null){
        return parent::set_type_subtrack_ids('static',$ordered_ids);
    }
    
    function save_track_position($track_id,$position){
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
    
    function convert_to_live_playlist(){

        //TO FIX CAPABILITIES

        /*
        Existing playlist
        */
        $static_tracklist = $this;
        $static_tracklist->load_subtracks();

        if ($static_tracklist->tracks){
            $subtracks_success = $static_tracklist->remove_subtracks();
        }
        
        $converted = set_post_type( $this->post_id, wpsstm()->post_type_live_playlist );

        if ( !is_wp_error($converted) ){
            $this->restore_wizard_datas(); //if we had wizard datas
        }

        return $converted;

    }
 
}