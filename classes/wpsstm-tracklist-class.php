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
            $subtrack = array(
                'post_id'  => $subtrack_id
            );
            $subtracks[] = $subtrack;
        }
        
        $this->add($subtracks);
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
    
    function append_subtrack_ids($new_ids){
        //force array
        if ( !is_array($new_ids) ) $new_ids = array($new_ids);
        
        if ( empty($new_ids) ) return false;
        
        $subtrack_ids = $this->get_subtracks_ids();
        $subtrack_ids = array_merge($subtrack_ids,$new_ids);
        return $this->set_subtrack_ids($subtrack_ids);
    }
    
    function remove_subtrack_ids($remove_ids){
        //force array
        if ( !is_array($remove_ids) ) $remove_ids = array($remove_ids);
        
        if ( empty($remove_ids) ) return false;
        
        $subtrack_ids = $this->get_subtracks_ids();
        $subtrack_ids = array_diff($subtrack_ids,$remove_ids);
        
        return $this->set_subtrack_ids($subtrack_ids);
    }

    function set_subtrack_ids($ordered_ids){
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_tracklist_no_post_id', __('This playlist has no post ID','wpsstm') );
        }
        
        //capability check
        $post_type = get_post_type($this->post_id);
        $tracklist_obj = get_post_type_object($post_type);
        if ( !current_user_can($tracklist_obj->cap->edit_post,$this->post_id) ){
            return new WP_Error( 'wpsstm_tracklist_no_edit_cap', __('You have not the capability required to edit this tracklist.','wpsstm') );
        }

        $ordered_ids = array_filter($ordered_ids, function($var){return !is_null($var);} ); //remove nuls if any
        $ordered_ids = array_unique($ordered_ids);
        
        wpsstm()->debug_log( array('tracklist_id'=>$this->post_id, 'subtrack_ids'=>json_encode($ordered_ids)), "WP_SoundSystem_Tracklist::set_subtrack_ids()"); 
        
        return update_post_meta($this->post_id,'wpsstm_subtrack_ids',$ordered_ids);
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
            return new WP_Error( 'wpsstm_track_cap_missing', __('You have not the capability required to create a new playlist','wpsstm') );
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
    
    function get_tracklist_admin_table(){
        require wpsstm()->plugin_dir . 'classes/wpsstm-tracklist-admin-table.php';
        $tracklist_table = new WP_SoundSystem_TracksList_Admin_Table();
        $tracklist_table->items = $this->tracks;
        
        ob_start();
        $tracklist_table->prepare_items();
        $tracklist_table->display();
        return ob_get_clean();
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

        if ($do_love){
            return add_post_meta( $this->post_id, wpsstm_tracklists()->favorited_tracklist_meta_key, $user_id );
        }else{
            return delete_post_meta( $this->post_id, wpsstm_tracklists()->favorited_tracklist_meta_key, $user_id );
        }
    }
    
    function get_tracklist_loved_by($tracklist_id){
        if ( !$this->post_id ) return false;
        return get_post_meta($this->post_id, wpsstm_tracklists()->favorited_tracklist_meta_key);
    }
    
    function is_tracklist_loved_by($user_id = null){
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return;
        if ( !$this->post_id ) return false;
        
        $loved_by = $this->get_tracklist_loved_by($this->post_id);
        return in_array($user_id,(array)$loved_by);
    }

}