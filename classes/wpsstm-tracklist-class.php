<?php

class WP_SoundSystem_Tracklist{
    
    var $post_id = 0; //tracklist ID (can be an album, playlist or live playlist)
    
    var $options_default = null;
    var $options = array();

    //infos
    var $title = null;
    var $author = null;
    var $location = null;
    
    //datas
    var $tracks = array();
    var $total_tracks = 0;
    
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

        $post_type = get_post_type($this->post_id);
        $subtracks = array();
 
        //get tracklist metas
        $subtrack_ids = $this->get_subtracks_ids();

        foreach ((array)$subtrack_ids as $subtrack_id){
            $track_args = array(
                'post_id'  => $subtrack_id
            );
            $track = new WP_SoundSystem_Track($track_args);
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
        
    }
    
    /*
    Return the subtracks IDs for a tracklist.
    */

    function get_subtracks_ids(){
        global $wpdb;

        $ordered_ids = get_post_meta($this->post_id,'wpsstm_subtrack_ids',true);
        $ordered_ids = array_unique((array)$ordered_ids);
        
        if ( empty($ordered_ids) ) return;

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
        
        $subtrack_ids = (array)$this->get_subtracks_ids();
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
        
        $subtrack_ids = (array)$this->get_subtracks_ids();
        $subtrack_ids = array_diff($subtrack_ids,$remove_ids);
        
        return $this->set_subtrack_ids($subtrack_ids);
    }

    function set_subtrack_ids($ordered_ids){
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_tracklist_no_post_id', __('Required tracklist ID missing','wpsstm') );
        }
        
        //post type check
        $can_add_tracklist_items = in_array(get_post_type($this->post_id),array(wpsstm()->post_type_album,wpsstm()->post_type_playlist) );
        if (!$can_add_tracklist_items){
            return new WP_Error( 'wpsstm_cannot_set_subtracks', __('Tracks can only be assigned to playlists and albums','wpsstm') );
        }
        
        //capability check
        $post_type = get_post_type($this->post_id);
        $tracklist_obj = get_post_type_object($post_type);
        if ( !current_user_can($tracklist_obj->cap->edit_post,$this->post_id) ){
            return new WP_Error( 'wpsstm_tracklist_no_edit_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        
        $ordered_ids = array_map('intval', $ordered_ids); //make sure every array item is an int - required for WP_SoundSystem_Track::get_parent_ids()
        $ordered_ids = array_filter($ordered_ids, function($var){return !is_null($var);} ); //remove nuls if any
        $ordered_ids = array_unique($ordered_ids);
        
        wpsstm()->debug_log( array('tracklist_id'=>$this->post_id, 'subtrack_ids'=>json_encode($ordered_ids)), "WP_SoundSystem_Tracklist::set_subtrack_ids()"); 
        
        return update_post_meta($this->post_id,'wpsstm_subtrack_ids',$ordered_ids);
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
    
    function add($tracks){

        //force array
        if ( !is_array($tracks) ) $tracks = array($tracks);

        foreach ($tracks as $track){

            if ( !is_a($track, 'WP_SoundSystem_Track') ){
                if ( is_array($track) ){
                    $track = new WP_SoundSystem_Track($track);
                }
            }

            $this->tracks[] = $track;
        }
        
        $this->validate_tracks();
        
        $tracks_count = null;
        foreach((array)$this->tracks as $key=>$track){
            //increment count
            $tracks_count++;
            $this->tracks[$key]->order = $tracks_count;
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
        $post_type_obj = get_post_type_object(wpsstm()->post_type_playlist);
        $required_cap = $post_type_obj->cap->edit_posts;

        if ( !current_user_can($required_cap) ){
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
            wpsstm_artists()->metakey           => $this->artist,
            wpsstm_tracks()->metakey            => $this->title,
            wpsstm_albums()->metakey            => $this->album,
            wpsstm_mb()->mb_id_meta_name        => $this->mbid,
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

        }else{ //is a track update
            
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
        
        $success = $this->append_subtrack_ids($subtrack_ids);
        
        return $success;
        
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
        
        return $tracklist->remove_subtrack_ids($rem_ids);
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
    
    function get_tracklist_actions_el(){
        
        $tracklist_actions = array();
        $action_default = array(
            'text' =>       null,
            'icon' =>       null,
            'classes' =>    array('tracklist-action'),
        );
        
        $post_type = get_post_type($this->post_id);
        $temp_status = wpsstm()->temp_status;
        $post_status = get_post_status($this->post_id);

        //refresh playlist
        if ($post_type == wpsstm()->post_type_live_playlist ){
            $refresh_text = __('Refresh','wpsstm');

            $link_attr = array(
                'title'     => $refresh_text,
                'href'      => '#',
            );

            $tracklist_actions['refresh'] = array(
                'icon' => '<i class="fa fa-rss" aria-hidden="true"></i>',
                'text' =>   sprintf('<a %s>%s</a>',wpsstm_get_html_attr($link_attr),$refresh_text)
            );
        }

        //share
        if ( in_array($post_status,array('publish',$temp_status)) ){


            $share_url = wpsstm_get_tracklist_link($this->post_id);
            $share_text = __('Share', 'wpsstm');

            $link_attr = array(
                'title'     => __('Share this tracklist', 'wpsstm'),
                'href'      => $share_url,
            );

            $tracklist_actions['share'] = array(
                'icon' => '<i class="fa fa-share-alt" aria-hidden="true"></i>',
                'text' =>   sprintf('<a %s>%s</a>',wpsstm_get_html_attr($link_attr),$share_text)
            );
        }

        //xspf
        if ( in_array($post_status,array('publish',$temp_status)) ){
            $xspf_url = wpsstm_get_tracklist_link($this->post_id,'xspf');
            $xspf_text = __('XSPF', 'wpsstm');

            $link_attr = array(
                'title'     => __('XSPF tracklist', 'wpsstm'),
                'href'      => $xspf_url,
                'target'     => '_blank'
            );

            $tracklist_actions['xspf'] = array(
                'icon'  => '<i class="fa fa-rss" aria-hidden="true"></i>',
                'text'  => sprintf('<a %s>%s</a>',wpsstm_get_html_attr($link_attr),$xspf_text)
            );
        }

        //favorite & unfavorite
        if ( $this->post_id ) {

            //favorite
            $action_classes = array('wpsstm-requires-auth','tracklist-action');
            if ( !$this->is_tracklist_loved_by() ) $action_classes[] = 'active';

            $link_attr = array(
                'href'  => '#',
                'title' => __('Add tracklist to favorites','wpsstm')
            );

            $link_attr = wpsstm_get_html_attr($link_attr);

            $tracklist_actions['favorite'] = array(
                'icon'=>        '<i class="fa fa-heart-o" aria-hidden="true"></i>',
                'text' =>       sprintf('<a %s>%s</a>',$link_attr,__('Favorite','wpsstm')),
                'classes' =>    $action_classes
            );

            //unfavorite
            $action_classes = array('wpsstm-requires-auth','tracklist-action');
            if ( $this->is_tracklist_loved_by() ) $action_classes[] = 'active';

            $link_attr = array(
                'href'  => '#',
                'title' => __('Remove tracklist from favorites','wpsstm')
            );

            $tracklist_actions['unfavorite'] = array(
                'icon'=>    '<i class="fa fa-heart" aria-hidden="true"></i>',
                'text' =>   sprintf('<a %s>%s</a>',wpsstm_get_html_attr($link_attr),__('Unfavorite','wpsstm')),
                'classes' =>    $action_classes
            );
        }

        $tracklist_actions = apply_filters('wpsstm_tracklist_actions',$tracklist_actions);

        $track_actions_els = array();
        foreach($tracklist_actions as $slug => $action){
            $action = wp_parse_args($action,$action_default);
            //$loading = '<i class="fa fa-circle-o-notch fa-fw fa-spin"></i>';

            $action_attr = array(
                'id'        => 'tracklist-action-' . $slug,
                'class'     => implode("\n",$action['classes'])
            );

            $track_actions_els[] = sprintf('<li %s>%s %s</li>',wpsstm_get_html_attr($action_attr),$action['icon'],$action['text']);
        }

        return sprintf('<ul id="wpsstm-tracklist-actions" class="wpsstm-actions-list">%s</ul>',implode("\n",$track_actions_els));
        
    }
    
    function get_tracklist_admin_actions_el(){
        
        $tracklist_actions = array();
        $action_default = array(
            'text' =>       null,
            'icon' =>       null,
            'classes' =>    array('tracklist-admin-action'),
        );
        $temp_status = wpsstm()->temp_status;
        $post_status = get_post_status($this->post_id);
        $post_type = get_post_type($this->post_id);
        $permalink = get_permalink($this->post_id);
        
        //add track
        $post_type = get_post_type($this->post_id);
        $tracklist_obj = get_post_type_object($post_type);
        $track_obj = get_post_type_object(wpsstm()->post_type_track);
        $can_add_tracklist_items = in_array($post_type,array(wpsstm()->post_type_album,wpsstm()->post_type_playlist) );

        if ( $can_add_tracklist_items && current_user_can($tracklist_obj->cap->edit_post,$this->post_id) ){
            
            $append_blank_track_url = $this->get_tracklist_admin_gui_url('add-track');
            $append_blank_track_url = add_query_arg(array('TB_iframe'=>true),$append_blank_track_url);
            
            $add_track_text = $track_obj->labels->add_new_item;
            $action_classes = array('wpsstm-requires-auth','tracklist-action');
            
            $link_attr = array(
                'title'     => $add_track_text,
                'href'      => $append_blank_track_url,
                'class'     => implode(' ',array('thickbox'))
            );

            $tracklist_actions['add-track'] = array(
                'icon'      => '<i class="fa fa-plus" aria-hidden="true"></i>',
                'text'      =>   sprintf('<a %s>%s</a>',wpsstm_get_html_attr($link_attr),$add_track_text),
                'classes'   =>    $action_classes
            );
        }

        //status switcher
        if ($user_id = get_current_user_id() ){

            $status_options = array();
            $current_status = get_post_status_object( get_post_status( $this->post_id) );

            $statii = array(
                $temp_status    => __('Temporary','wpsstm'),
                'draft'         => __('Draft'),
                'publish'       => __('Published'),
                'private'       => __('Private'),
                'trash'         => __('Trash'),
            );

            //show temporary status only when it has that status
            if ($post_status != $temp_status) unset($statii[$temp_status]);

            foreach($statii as $status=>$str){
                $selected = selected($post_status, $status, false);
                $status_options[] = sprintf('<option value="%s" %s>%s</option>',$status,$selected,$str);
            }

            $status_options_str = implode("\n",$status_options);
            $form_onchange = "if(this.value !='') { this.form.submit(); }";
            $form = sprintf('<form action="%s" method="POST" class="wpsstm-playlist-status"><select name="frontend-wizard-status" onchange="%s">%s</select><input type="hidden" name="frontend-wizard-action" value="switch-status"/></form>',$permalink,$form_onchange,$status_options_str);
            $status_switch_text = __('Status');
            $action_classes = array('wpsstm-requires-auth','tracklist-action');

            $link_attr = array(
                'title'     => __('Switch tracklist status','wpssm'),
                'href'      => '#',
            );

            $tracklist_actions['status-switch'] = array(
                'icon'  => '<i class="fa fa-calendar-check-o" aria-hidden="true"></i>',
                'text' =>   sprintf('<a %s>%s</a> <em>%s</em>%s',wpsstm_get_html_attr($link_attr),$status_switch_text,$current_status->label,$form),
                'classes'   =>    $action_classes
            );
        }

        //lock playlist
        if ( ($user_id = get_current_user_id() ) && ($post_status != $temp_status) && ($post_type == wpsstm()->post_type_live_playlist ) ){
            $switch_type_url = add_query_arg(array('frontend-wizard-action'=>'import'),$permalink);
            $switch_type_text = __('Lock', 'wpsstm');
            $action_classes = array('wpsstm-requires-auth','tracklist-action');

            $link_attr = array(
                'title'     => __('Convert this live playlist to a static playlist', 'wpsstm'),
                'href'      => $switch_type_url,
            );

            $tracklist_actions['lock-playlist'] = array(
                'icon'      => '<i class="fa fa-lock" aria-hidden="true"></i>',
                'text'      =>   sprintf('<a %s>%s</a>',wpsstm_get_html_attr($link_attr),$switch_type_text),
                'classes'   =>    $action_classes
            );
        }

        $tracklist_actions = apply_filters('wpsstm_tracklist_admin_actions',$tracklist_actions);

        $track_actions_els = array();
        foreach($tracklist_actions as $slug => $action){
            $action = wp_parse_args($action,$action_default);
            //$loading = '<i class="fa fa-circle-o-notch fa-fw fa-spin"></i>';

            $action_attr = array(
                'id'        => 'tracklist-admin-action-' . $slug,
                'class'     => implode("\n",$action['classes'])
            );

            $track_actions_els[] = sprintf('<li %s>%s %s</li>',wpsstm_get_html_attr($action_attr),$action['icon'],$action['text']);
        }

        return sprintf('<ul id="wpsstm-tracklist-admin-actions" class="wpsstm-actions-list">%s</ul>',implode("\n",$track_actions_els));
        
    }
    
    function get_tracklist_admin_gui_url($tracklist_action = null){

        $url = null;
        
        if($this->post_id){
            $url = get_permalink($this->post_id);
            $url = add_query_arg(array(wpsstm_tracklists()->qvar_admin=>$tracklist_action),$url);
        }

        return $url;
    }

}