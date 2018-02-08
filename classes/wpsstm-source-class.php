<?php
class WPSSTM_Source{
    var $post_id;
    var $track_id;
    var $index = -1;
    var $title;
    var $is_community; //TRUE if source was populated automatically
    var $url; //source link
    var $stream_url;
    var $provider;
    var $type;
    var $match;

    function __construct($post_id = null){
        
        $this->provider = new WPSSTM_Player_Provider(); //default

        if ( $post_id && ( get_post_type($post_id) == wpsstm()->post_type_source ) ){
            $this->post_id = (int)$post_id;
            $this->title = get_the_title($post_id);
            $this->track_id = wp_get_post_parent_id( $post_id );

            $this->url = get_post_meta($post_id,WPSSTM_Core_Sources::$source_url_metakey,true);

            $this->match = $this->get_track_match();
            
            if ($this->index == -1){ //if not set yet
                $this->index = get_post_field('menu_order', $this->post_id);
            }

        }

    }
    
    function get_default(){
        return array(
            'post_id'       => null,
            'index'         => -1,
            'track_id'      => null,
            'url'           => null,
            'title'         => null,
            'is_community'  => null,
            'match'         => null,
        );
    }
    
    function from_array( $args = null ){

        $args_default = $this->get_default();
        $args = wp_parse_args((array)$args,$args_default);
        $post_id = null;

        //set properties from args input
        foreach ($args as $key=>$value){
            
            switch($key){
                default:
                    if ( !array_key_exists($key,$args_default) ) continue;
                    if ( !isset($args[$key]) ) continue; //value has not been set
                    $this->$key = $args[$key];
                break;
            }
  
        }

        $this->__construct( $this->post_id );
        
    }

    /*
    Get the sources that have the same URL or track informations than the current one
    */
    function get_source_duplicates_ids($args=null){
        
        $track = new WPSSTM_Track($this->track_id);
        
        /*
        $query_meta_trackinfo = array(
            'relation' => 'AND',
            WPSSTM_Core_Artists::$artist_metakey    => $track->artist,
            WPSSTM_Core_Tracks::$title_metakey      => $track->title,
            WPSSTM_Core_Albums::$album_metakey      => $track->album,
        );
        $query_meta_trackinfo = array_filter($query_meta_trackinfo);
        */
        
        
        $default = array(
            'post_status'       => 'any',
            'posts_per_page'    => -1,
            'fields'            => 'ids'
        );
        
        $args = wp_parse_args((array)$args,$default);

        $required = array(
            'post__not_in'      => ($this->post_id) ? array($this->post_id) : null, //exclude current source
            'post_parent'       => $track->post_id,
            'post_type'         => array(wpsstm()->post_type_source),

            'meta_query'        => array(
                //by source URL
                'source_url' => array(
                    'key'     => WPSSTM_Core_Sources::$source_url_metakey,
                    'value'   => $this->url
                )
            )
        );
        
        $args = wp_parse_args($required,$args);

        $query = new WP_Query( $args );
        return $query->posts;
    }
    
    function sanitize_source(){
        if (!$this->track_id){
            return new WP_Error( 'no_post_id', __('Unable to save source : missing track ID','wpsstm') );
        }
        
        $this->url = trim($this->url);
        
        if (!$this->url){
            return new WP_Error( 'no_url', __('Unable to save source : missing URL','wpsstm') );
        }
        
        if ( !filter_var($this->url, FILTER_VALIDATE_URL) ){
            return new WP_Error( 'no_valid_url', __('Unable to save source : bad URL','wpsstm') );
        }

        $this->title = trim($this->title);
    }
    
    /*
    Save source only if it does not exists yet
    */
    function save_unique_source(){
        
        //sanitize
        $sanitized = $this->sanitize_source();

        if ( is_wp_error($sanitized) ){
            return $sanitized;
        }

        //check for duplicates
        $duplicates = $this->get_source_duplicates_ids();

        if ( !empty($duplicates) ){
            $source_id = $duplicates[0];
        }else{
            $source_id = $this->save_source();
        }
        return $source_id;
    }

    function save_source(){
        $sanitized = $this->sanitize_source();
        if ( is_wp_error($sanitized) ) return $sanitized;
        
        $post_author = ($this->is_community) ? wpsstm()->get_options('community_user_id') : get_current_user_id();
        
        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_source);
        $required_cap = $post_type_obj->cap->edit_posts;

        if ( !user_can($post_author,$required_cap) ){
            return new WP_Error( 'wpsstm_save_source_cap_missing', __("You don't have the capability required to edit sources.",'wpsstm') );
        }

        $args = array(
            'post_author' =>    $post_author,
            'post_status' =>    'publish',
        );
 
        $required_args = array(
            'post_title' =>     $this->title,
            'post_type' =>      wpsstm()->post_type_source,
            'post_parent' =>    $this->track_id,
            'meta_input' =>     array(
                WPSSTM_Core_Sources::$source_url_metakey => $this->url
            )
        );
            
        $args = wp_parse_args($required_args,$args);

        if (!$this->post_id){
            $success = wp_insert_post( $args, true );
        }else{
            $success = wp_update_post( $args, true );
        }
        
        if ( is_wp_error($success) ) return $success;
        $this->post_id = $success;

        wpsstm()->debug_log(json_encode(array('args'=>$args,'post_id'=>$this->post_id)),"WPSSTM_Source::save_source()");
        
        return $this->post_id;
    }
    

    
    function trash_source(){
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_post_id', __("Missing source ID.",'wpsstm') );
        }
        
        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_source);
        $can_delete_source = current_user_can($post_type_obj->cap->delete_post,$this->post_id);

        if (!$can_delete_source){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to delete this source.",'wpsstm') );
        }
        
        return wp_trash_post( $this->post_id );
    }
    
    /*
    Populate provider and stream URL.
    This should be called only when necessary since provider could do API requests to get the stream URL.
    */

    function populate_source_provider(){
        
        if (!$this->url) return;
        
        //populate provider
        $source_provider = null;
        foreach( (array)WPSSTM_Core_Player::get_providers() as $provider ){
            if ( !$provider::can_play_source($this->url) ) continue;
            $source_provider = $provider;
            break;
        }
        
        
        if ($source_provider){

            //populate stream URL
            $this->stream_url = $source_provider->get_stream_url($this->url);
            $this->type = $source_provider->get_source_type($this->stream_url);
            
            //set provider as title if no title set
            if ( $source_provider && !$this->title){
                $this->title = $source_provider->name;
            }
            
            $this->provider = $source_provider; //populate it
            return $source_provider;
        }


    }
    
    function get_track_match(){
        
        $track = new WPSSTM_Track($this->track_id);
        
        //TO FIX what if source has been populated at tracklist request ? Should be 100% ?

        //sanitize data so it is easier to compare
        $source_title_sanitized = sanitize_title($this->title);
        $track_artist_sanitized = sanitize_title($track->artist);
        $track_title_sanitized = sanitize_title($track->title);

        //remove artist from source title so the string to compare is shorter
        $maybe_remove_artist = str_replace($track_artist_sanitized,"", $source_title_sanitized,$count);
        if ($count){
            $source_title_sanitized = $maybe_remove_artist;
        }
        
        //TO FIX remove banned words from source title and track title before compare ?

        similar_text($source_title_sanitized, $track_title_sanitized, $similarity_pc);
        return round($similarity_pc);
    }
    
    function get_single_source_attributes(){
        global $wpsstm_track;
        
        $attr = array(
            'data-wpsstm-source-id' =>              $this->post_id,
            'data-wpsstm-track-id' =>               $this->track_id,
            'data-wpsstm-source-provider' =>        $this->provider::$slug,
            'data-wpsstm-source-idx' =>             $wpsstm_track->current_source,
            'data-wpsstm-source-type' =>            $this->type,
            'data-wpsstm-source-src' =>             $this->stream_url,
            'data-wpsstm-community-source' =>       (int)wpsstm_is_community_post($this->post_id),
            'class'                 =>              implode( ' ',$this->get_source_class() ),
        );
        return $attr;
    }
    
    function get_source_class(){
        $classes = array('wpsstm-source');
        $classes = apply_filters('wpsstm_source_classes',$classes,$this);
        return array_filter(array_unique($classes));
    }
    
    function get_source_action_url($action = null){
        
        $url = null;
        
        $args = array(WPSSTM_Core_Sources::$qvar_source_action=>$action);

        if ($this->post_id){
            $url = get_permalink($this->post_id);
            $url = add_query_arg($args,$url);
        }

        return $url;
    }
    
    function get_source_links($context = null){
        global $wpsstm_track;
        $actions = array();
        
        $source_type_obj = get_post_type_object(wpsstm()->post_type_source);

        //caps
        $can_edit_source = current_user_can($source_type_obj->cap->edit_post,$this->post_id);
        $can_delete_source = current_user_can($source_type_obj->cap->delete_post,$this->post_id);
        $can_reorder_sources = $wpsstm_track->user_can_reorder_sources();
        
        $actions['provider'] = array(
            'text' =>       $this->provider->name,
            'href' =>       $this->url,
            'target' =>     '_blank',
        );

        if ($can_delete_source){
            $actions['trash'] = array(
                'text' =>       __('Trash', 'wpsstm'),
                'desc' =>       __('Trash this source','wpsstm'),
                'href' =>       $this->get_source_action_url('trash'),
            );
        }

        if ( $can_reorder_sources ){
            $actions['move'] = array(
                'text' =>       __('Move', 'wpsstm'),
                'desc' =>       __('Change source position','wpsstm'),
            );
        }
        
        if ( $can_edit_source ){
            $actions['edit-backend'] = array(
                'text' =>      __('Edit'),
                'classes' =>    array('wpsstm-advanced-action'),
                'href' =>       get_edit_post_link( $this->post_id ),
            );
        }

        return apply_filters('wpsstm_source_actions',$actions,$context);
    }
    
    

}