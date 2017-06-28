<?php

class WP_SoundSytem_Tracklist{
    
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

        foreach ($subtrack_ids as $subtrack_id){
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
    
    function set_subtrack_ids($ordered_ids){

        $ordered_ids = array_filter($ordered_ids, function($var){return !is_null($var);} ); //remove nuls if any
        $ordered_ids = array_unique($ordered_ids);
        
        wpsstm()->debug_log( array('tracklist_id'=>$this->post_id, 'subtrack_ids'=>json_encode($ordered_ids)), "WP_SoundSytem_Tracklist::set_subtrack_ids()"); 
        
        return update_post_meta($this->post_id,'wpsstm_subtrack_ids',$ordered_ids);
    }
    
    function add($tracks){

        //force array
        if ( !is_array($tracks) ){
            $tracks = array($tracks);
        }

        foreach ($tracks as $track){

            if ( !is_a($track, 'WP_SoundSystem_Subtrack') ){
                if ( is_array($track) ){
                    $track = new WP_SoundSystem_Subtrack($track);
                }
            }
            
            //set tracklist ID
            $track->tracklist_id = $this->post_id;



            $this->tracks[] = $track;
        }
        
        $this->validate_tracks();
        
        $tracks_count = null;
        foreach((array)$this->tracks as $key=>$track){
            //increment count
            $tracks_count++;
            $this->tracks[$key]->subtrack_order = $tracks_count;
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
    
    function save_subtracks(){
        
        do_action('wpsstm_save_subtracks'); //this will allow to detect if we're saving a single track or several tracks using did_action().

        foreach($this->tracks as $key=>$track){
            $saved = $track->save_track();
        }
    }
    
    function remove_subtracks(){
        foreach($this->tracks as $key=>$track){
            $track->remove_subtrack();
        }
    }
    
    function delete_subtracks(){
        foreach($this->tracks as $key=>$track){
            if ( $track->delete_track() ){
                unset($this->tracks[$key]);
            }
        }
    }
    
    function get_tracklist_admin_table(){
        require wpsstm()->plugin_dir . 'classes/wpsstm-tracklist-admin-table.php';
        $tracklist_table = new WP_SoundSytem_TracksList_Admin_Table();
        $tracklist_table->items = $this->tracks;
        
        ob_start();
        $tracklist_table->prepare_items();
        $tracklist_table->display();
        return ob_get_clean();
    }
    
    /**
    Read-only tracklist table
    **/
    function get_tracklist_table(){

        require_once wpsstm()->plugin_dir . 'classes/wpsstm-tracklist-table.php';
        $tracklist_table = new WP_SoundSytem_Tracklist_Table($this);

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
        
        wpsstm()->debug_log(json_encode(array('slug'=>$slug,'code'=>$code,'error'=>$error)),'[WP_SoundSytem_Tracklist notice]: ' . $message ); 
        
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