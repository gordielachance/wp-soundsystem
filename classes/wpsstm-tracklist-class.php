<?php

class WP_SoundSystem_Tracklist{
    
    var $post_id = 0; //tracklist ID (can be an album, playlist or live playlist)
    
    var $options_default = array();
    var $options = array();

    //infos
    var $title = null;
    var $author = null;
    var $location = null;
    
    //datas
    var $tracks = array();
    var $did_query_tracks = false;
    
    var $updated_time = null;
    
    var $pagination = array(
        'total_items'  => null,
        'total_pages'   => null,
        'per_page'      => null,
        'current_page'  => null
    );
    
    var $tracks_strict = true; //requires a title AND an artist
    
    static $paged_var = 'tracklist_page';

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
            
        }

        $this->options = array_replace_recursive((array)$this->options_default,$this->options); //last one has priority

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
        
        //try to populate cached autosources if item has not any
        //TO FIX only if playable ? Is this at the right place ?
        foreach($this->tracks as $track){		
            if (!$track->sources){
                $track->populate_track_sources_auto(array('cache_only'=>true));
            }
        }
        
        $this->did_query_tracks = true;
        
    }
    
    /*
    Return the subtracks IDs for a tracklist.
    */

    function get_subtrack_ids(){
        global $wpdb;

        $ordered_ids = get_post_meta($this->post_id,'wpsstm_subtrack_ids',true);
        if ( empty($ordered_ids) ) return;
        
        $ordered_ids = array_unique($ordered_ids);

        //validate those IDs, we must be sure they are tracks.
        $args = array(
            'post_type'         => array(wpsstm()->post_type_track),
            'post_status'       => 'any',
            'posts_per_page'    => -1,
            'fields'            => 'ids',
            'post__in'          => $ordered_ids
        );

        $query = new WP_Query( $args );
        $post_ids = $query->posts;
        
        foreach($ordered_ids as $key=>$ordered_id){
            if (!in_array($ordered_id,$post_ids)) unset($ordered_ids[$key]);
        }

        return $ordered_ids;
        
    }
    
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

    function set_subtrack_ids($ordered_ids = null){
        
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
        foreach((array)$ordered_ids as $track_id){
            $track_post_type = get_post_status($track_id);
            if ($track_post_type != 'publish'){
                wp_update_post(array(
                    'ID' =>             $track_id,
                    'post_status' =>    'publish'
                ));
            }
        }
        
        wpsstm()->debug_log( array('tracklist_id'=>$this->post_id, 'subtrack_ids'=>json_encode($ordered_ids)), "WP_SoundSystem_Tracklist::set_subtrack_ids()"); 
        
        if ($ordered_ids){
            return update_post_meta($this->post_id,'wpsstm_subtrack_ids',$ordered_ids);
        }else{
            return delete_post_meta($this->post_id,'wpsstm_subtrack_ids');
        }

    }
    
    function save_track_position($track_id,$position){
        $ordered_ids = get_post_meta($this->post_id,'wpsstm_subtrack_ids',true);
        
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
    
    function save_subtracks(){
        
        do_action('wpsstm_save_subtracks',$this); //this will allow to detect if we're saving a single track or several tracks using did_action().
        
        $subtrack_ids = array();
        
        foreach($this->tracks as $key=>$track){
            $track_id = $track->save_track();
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

    function get_options($keys = null){
        return wpsstm_get_array_value($keys,$this->options);
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
 
        foreach ($this->notices as $notice){
            if ( $notice['slug'] != $slug ) continue;
            
            $notice_classes = array(
                'inline',
                'settings-error',
                'notice',
                'is-dismissible'
            );
            
            $notice_classes[] = ($notice['error'] == true) ? 'error' : 'updated';
            
            printf('<div %s><p><strong>%s</strong></p></div>',wpsstm_get_classes_attr($notice_classes),$notice['message']);
        }
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
    
    function get_tracklist_actions(){
        
        /*
        Capability check
        */
        
        $temp_status = wpsstm()->temp_status;
        
        //playlist
        $permalink = get_permalink($this->post_id);
        $tracklist_type = get_post_type($this->post_id);
        $tracklist_status = get_post_status($this->post_id);
        $tracklist_obj = get_post_type_object($tracklist_type);
        $current_status_obj = get_post_status_object( $tracklist_status );
        
        //track
        $track_obj = get_post_type_object(wpsstm()->post_type_track);
        $can_edit_tracklist = current_user_can($tracklist_obj->cap->edit_post,$this->post_id);
        $can_add_tracklist_items = in_array($tracklist_type,array(wpsstm()->post_type_album,wpsstm()->post_type_playlist) );
        
        $can_refresh = ($tracklist_type == wpsstm()->post_type_live_playlist );
        $can_share = true; //TO FIX no conditions (call to action) but notice if post cannot be shared
        $can_export = ( in_array($tracklist_status,array('publish',wpsstm()->temp_status)) );
        $can_favorite = true; //call to action
        $can_add_tracks = ( $can_edit_tracklist && $can_add_tracklist_items );
        $can_lock_playlist = ( $can_edit_tracklist && ($tracklist_type == wpsstm()->post_type_live_playlist ) );
        $can_unlock_playlist = ( $can_edit_tracklist && ($tracklist_type == wpsstm()->post_type_playlist ) && $this->has_wizard_backup() );

        $actions = array();

        //refresh
        if ($can_refresh){
            $actions['refresh'] = array(
                'icon' =>       '<i class="fa fa-rss" aria-hidden="true"></i>',
                'title' =>      __('Refresh', 'wpsstm'),
            );
        }
        
        //share
        if ($can_share){
            $actions['share'] = array(
                'icon' =>       '<i class="fa fa-share-alt" aria-hidden="true"></i>',
                'title' =>      __('Share', 'wpsstm'),
                'href' =>       get_permalink($this->post_id),
            );
        }
        
        //XSPF
        if ($can_export){
            $actions['xspf'] = array(
                'icon' =>       '<i class="fa fa-rss" aria-hidden="true"></i>',
                'title' =>      'XSPF',
                'href' =>       wpsstm_get_tracklist_link($this->post_id,'xspf'),
            );
        }
        
        //favorite
        if ($can_favorite){
            $actions['favorite'] = array(
                'icon'=>        '<i class="fa fa-heart-o" aria-hidden="true"></i>',
                'title' =>      __('Favorite','wpsstm'),
                'desc' =>       __('Add track to favorites','wpsstm'),
                'classes' =>    array('wpsstm-requires-auth','wpsstm-action-toggle-favorite'),
            );
            if ( !$this->is_tracklist_loved_by() ) $actions['favorite']['classes'][] = 'wpsstm-toggle-favorite-active';
        }

        //unfavorite
        if ($can_favorite){
            $actions['unfavorite'] = array(
                'icon'=>        '<i class="fa fa-heart" aria-hidden="true"></i>',
                'title' =>      __('Unfavorite','wpsstm'),
                'desc' =>       __('Remove track from favorites','wpsstm'),
                'classes' =>    array('wpsstm-requires-auth','wpsstm-action-toggle-favorite'),
            );
            if ( $this->is_tracklist_loved_by() ) $actions['unfavorite']['classes'][] = 'wpsstm-toggle-favorite-active';
        }
        
        //add track
        if ($can_add_tracklist_items){
            $actions['append'] = array(
                'icon'      =>  '<i class="fa fa-plus" aria-hidden="true"></i>',
                'title'     =>  $track_obj->labels->add_new_item,
                'href'      =>  $this->get_new_tracklist_track_url(),
                'classes'   =>  array('wpsstm-requires-auth','tracklist-action'),
            );
        }
        
        //switch status
        if ($can_edit_tracklist){
            $status_options = array();
            $statii = array('draft','publish','private','trash');

            //show temporary status only when it has that status
            if ($tracklist_status == wpsstm()->temp_status) $statii[] = wpsstm()->temp_status;

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
                'title' =>      __('Status'),
                'link_after' => sprintf(' <em>%s</em>%s',$current_status_obj->label,$form),
                'classes' =>    array('wpsstm-requires-auth','tracklist-action'),
            );
        }
        
        //lock
        if ($can_lock_playlist){
            $actions['lock-playlist'] = array(
                'icon' =>       '<i class="fa fa-lock" aria-hidden="true"></i>',
                'title' =>      __('Lock', 'wpsstm'),
                'desc' =>       __('Convert this live playlist to a static playlist', 'wpsstm'),
                'href' =>       $this->get_tracklist_admin_gui_url('lock-tracklist'),
                'classes' =>    array('wpsstm-requires-auth','tracklist-action'),

            );
        }

        //unlock
        if ($can_unlock_playlist){
            $actions['unlock-playlist'] = array(
                'icon' =>       '<i class="fa fa-lock" aria-hidden="true"></i>',
                'title' =>      __('Unlock', 'wpsstm'),
                'desc' =>       __('Restore this playlist back to a live playlist', 'wpsstm'),
                'href' =>       $this->get_tracklist_admin_gui_url('unlock-tracklist'),
                'classes' =>    array('wpsstm-requires-auth','tracklist-action'),

            );
        }
        
        return apply_filters('wpsstm_tracklist_actions',$actions);
    }
    
    function get_tracklist_row_actions(){
        $actions = $this->get_tracklist_actions();
        $popup_slugs = array('append');
        
        foreach((array)$actions as $slug=>$action){
            if ( !in_array($slug,$popup_slugs) ) continue;
            
            if ( $action['tab_id'] ){
                $action['href'] .= sprintf('#%s',$action['tab_id']);
            }
            
            $action['link_classes'][] = 'thickbox';
            $action['href'] = add_query_arg(array('TB_iframe'=>true),$action['href']);
            $actions[$slug] = $action;
        }
        return $actions;
    }
    
    function get_tracklist_popup_actions(){
        $actions = $this->get_tracklist_actions();
        
        foreach((array)$actions as $slug=>$action){
            
            if ( $action['tab_id'] ){
                $action['href'] = sprintf('#%s',$action['tab_id']);
            }
            
            $actions[$slug] = $action;
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

}