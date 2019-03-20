<?php
class WPSSTM_Source{
    var $post_id;
    
    var $index = null;
    
    var $title; //source title (as they might appear on Youtube or Soundcloud, for example; when there is no artist + track title)
    var $title_artist; //if available, the artist name
    var $title_track; //if available, the track name
    
    var $is_community; //TRUE if source was populated automatically
    var $permalink_url; //source link
    var $stream_url;
    var $download_url;
    var $mime_type;
    var $duration; //in seconds
    var $track;

    function __construct($post_id = null){

        //has track ID
        if ( $source_id = intval($post_id) ) {
            $this->post_id = $source_id;
            $this->populate_source_post();
        }

        $this->track = new WPSSTM_Track(); //default

    }
    
    function populate_source_post(){

        if ( !$this->post_id || ( get_post_type($this->post_id) != wpsstm()->post_type_source ) ){
            $this->source_log('Invalid source post');
            return;
        }
        
        $this->title = get_the_title($this->post_id);
        $this->permalink_url = get_post_meta($this->post_id,WPSSTM_Core_Sources::$source_url_metakey,true);
        $this->index = get_post_field('menu_order', $this->post_id);
        if ( $track_id = wp_get_post_parent_id( $this->post_id ) ){
            $this->track = new WPSSTM_Track($track_id);
        }

    }

    function from_array( $args ){
        
        if ( !is_array($args) ) return;

        $allowed = array(
            'post_id',
            'index',
            'permalink_url',
            'title',
            'is_community',
        );
        
        //set properties from args input
        foreach ($args as $key=>$value){
            
            if ( !in_array($key,$allowed) ) continue;
            
            switch($key){
                default:
                    if ( !isset($args[$key]) ) continue; //value has not been set
                    $this->$key = $args[$key];
                break;
            }
  
        }

        //source
        if ( $this->post_id ){
            $this->populate_source_post();
        }
        
    }

    /*
    Get the sources that have the same URL or track informations than the current one
    */
    function get_source_duplicates_ids($args=null){

        /*
        $query_meta_trackinfo = array(
            'relation' => 'AND',
            WPSSTM_Core_Tracks::$artist_metakey    => $this->track->artist,
            WPSSTM_Core_Tracks::$title_metakey      => $this->track->title,
            WPSSTM_Core_Tracks::$album_metakey      => $this->track->album,
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
            'post_parent'       => $this->track->post_id,
            'post_type'         => array(wpsstm()->post_type_source),

            'meta_query'        => array(
                //by source URL
                'source_url' => array(
                    'key'     => WPSSTM_Core_Sources::$source_url_metakey,
                    'value'   => $this->permalink_url
                )
            )
        );
        
        $args = wp_parse_args($required,$args);

        $query = new WP_Query( $args );
        return $query->posts;
    }
    
    function validate_source(){
        
        $this->permalink_url = trim($this->permalink_url);
        
        if (!$this->permalink_url){
            return new WP_Error( 'wpsstm_souce_missing_url', __('Unable to validate source: missing URL','wpsstm') );
        }
        
        if ( !filter_var($this->permalink_url, FILTER_VALIDATE_URL) ){
            return new WP_Error( 'wpsstm_souce_missing_valid_url', __('Unable to validate source: bad URL','wpsstm') );
        }

        $this->title = trim($this->title);
    }

    function save_source(){
        
        $validated = $this->validate_source();

        if ( is_wp_error($validated) ) return $validated;
        
        if (!$this->track->post_id){
            return new WP_Error( 'wpsstm_souce_missing_post_id', __('Unable to validate source: missing track ID','wpsstm') );
        }
        
        //check for duplicates
        $duplicates = $this->get_source_duplicates_ids();
        if ( !empty($duplicates) ){
            $source_id = $duplicates[0];
            $this->post_id = $source_id;
            //$this->source_log($source_id,'This source already exists, do not create it');
        }else{
            $post_author = ($this->is_community) ? wpsstm()->get_options('community_user_id') : get_current_user_id();

            //capability check
            $post_type_obj = get_post_type_object(wpsstm()->post_type_source);
            $required_cap = ($this->post_id) ? $post_type_obj->cap->edit_posts : $post_type_obj->cap->create_posts;

            if ( !user_can($post_author,$required_cap) ){
                return new WP_Error( 'wpsstm_missing_capability', __("You don't have the capability required to edit this souce.",'wpsstm') );
            }

            $args = array(
                'post_author' =>    $post_author,
                'post_status' =>    'publish',
            );

            $required_args = array(
                'post_title' =>     $this->title,
                'post_type' =>      wpsstm()->post_type_source,
                'post_parent' =>    $this->track->post_id,
                'meta_input' =>     array(
                    WPSSTM_Core_Sources::$source_url_metakey => $this->permalink_url
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

            $this->source_log(
                json_encode(array('args'=>$args,'post_id'=>$this->post_id)),
                "WPSSTM_Source::save_source()
            ");
        }
        
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

    function get_single_source_attributes(){
        
        $attr = array(
            'data-wpsstm-source-id' =>              $this->post_id,
            'data-wpsstm-source-domain' =>          wpsstm_get_url_domain($this->permalink_url),
            'data-wpsstm-source-idx' =>             $this->track->current_source,
            'data-wpsstm-source-src' =>             $this->get_stream_url(),
            'data-wpsstm-source-type' =>            $this->get_source_mimetype(),
            'data-wpsstm-community-source' =>       (int)wpsstm_is_community_post($this->post_id),
            'class' =>                              implode( ' ',$this->get_source_class() ),
        );
        return $attr;
    }
    
    function get_source_class(){
        $classes = array('wpsstm-source');
        $classes = apply_filters('wpsstm_source_classes',$classes,$this);
        return array_filter(array_unique($classes));
    }
    
    function get_stream_url(){
        if ($this->stream_url) return $this->stream_url;
        return $this->stream_url = apply_filters('wpsstm_get_source_stream_url',$this->permalink_url,$this);
    }
    
    function get_source_action_url($action = null){
        
        $url = null;
        
        $args = array(
            'wpsstm_action'=>$action
        );

        if ($this->post_id){
            $url = get_permalink($this->post_id);
            $url = add_query_arg($args,$url);
        }

        return $url;
    }
    
    function get_source_links($context = null){
        $actions = array();
        
        $source_type_obj = get_post_type_object(wpsstm()->post_type_source);

        //caps
        $can_edit_source = current_user_can($source_type_obj->cap->edit_post,$this->post_id);
        $can_delete_source = current_user_can($source_type_obj->cap->delete_post,$this->post_id);
        $can_reorder_sources = $this->track->user_can_reorder_sources();
        
        $actions['provider'] = array(
            'text' =>       wpsstm_get_url_domain($this->permalink_url),
            'href' =>       $this->permalink_url,
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

    function get_source_mimetype(){
        if ($this->mime_type !== null) return $this->mime_type; //already populated
        $mime = false;
        
        //audio file
        $filetype = wp_check_filetype($this->permalink_url);
        if ( $ext = $filetype['ext'] ){
            $audio_extensions = wp_get_audio_extensions();
            if ( in_array($ext,$audio_extensions) ){
                $mime = sprintf('audio/%s',$ext);
            }
        }

        $this->mime_type = apply_filters('wpsstm_get_source_mimetype',$mime,$this);
        return $this->mime_type;
    }
    
    function get_source_title(){
        $title = $this->title;
        if (!$title){
            $title = wpsstm_get_url_domain($this->permalink_url);
        }
        return $title;
    }

    function get_source_icon(){
        if ($this->icon !== null) return $this->icon;
        $icon = '<i class="fa fa-file-audio-o" aria-hidden="true"></i>';
        $this->icon = apply_filters('wpsstm_get_source_icon',$icon,$this);
        return $this->icon;
    }
    
    function source_log($data,$title = null){
        
        if ($this->post_id){
            $title = sprintf('[source:%s] ',$this->post_id) . $title;
        }

        $this->track->track_log($data,$title);

    }
    
    function to_array(){

        $arr = array(
            'post_id' =>        $this->post_id,

            'index' =>          $this->index,

            'title' =>          $this->title,
            'title_artist' =>   $this->title_artist,
            'title_track' =>    $this->title_track,

            'is_community' =>   $this->is_community,
            'permalink_url' =>  $this->permalink_url,
            'stream_url' =>     $this->stream_url,
            'download_url' =>   $this->download_url,
            'mime_type' =>      $this->mime_type,
            'duration' =>       $this->duration,
            'trackt_id' =>      $this->track->post_id,
        );
        return array_filter($arr);
    }

}