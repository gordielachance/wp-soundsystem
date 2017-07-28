<?php
class WP_SoundSystem_Source {
    var $post_id;
    var $track_id;
    var $url; //input URL
    var $title;
    var $is_auto; //is this an auto source ?
    
    var $src; //URL used in the 'source' tag (which could be not the same)
    var $provider;
    var $type;
    var $similarity;

    private $defaults = array(
        'post_id'       => null,
        'track_id'      => null,
        'url'           => null,
        'title'         => null,
        'is_auto'       => null, 
        'similarity'    => null,
    );
    
    function __construct($post_id = null){
        
        if ($post_id){
            $this->post_id = $post_id;
            $this->title = get_the_title($post_id);
            $this->track_id = wp_get_post_parent_id( $post_id );

            $this->url = get_post_meta($post_id,wpsstm_sources()->url_metakey,true);
            $this->populate_source_url();
            
            $post_author_id = get_post_field( 'post_author', $post_id );
            $community_user_id = wpsstm()->get_options('community_user_id');
            $this->is_auto = ( $post_author_id == $community_user_id );

        }

        //TO FIX do this as a filter
        //if (!$this->title && $this->provider) $this->title = $this->provider->name;

    }
    
    function populate_array( $args = null ){

        $args_default = $this->defaults;
        $args = wp_parse_args((array)$args,$args_default);

        //set properties from args input
        foreach ($args as $key=>$value){
            if ( !array_key_exists($key,$args_default) ) continue;
            if ( !isset($args[$key]) ) continue; //value has not been set
            $this->$key = $args[$key];
        }

        //populate post ID if source already exists in the DB
        if ( $duplicates = $this->get_source_duplicates() ){
            $this->__construct( $duplicates[0] );
        } 
    }
    
    /*
    Get the sources (IDs) that have the same URL than the current one
    */
    function get_source_duplicates(){

        $args = array(
            'post_parent'       => $this->track_id,
            'post_type'         => array(wpsstm()->post_type_source),
            'post_status'       => 'any',
            'posts_per_page'    => -1,
            'fields'            => 'ids',
            'meta_query'        => array(
                'source_url' => array(
                    'key'     => wpsstm_sources()->url_metakey,
                    'value'   => $this->url
                )
            )
        );

        if ($this->post_id){
            $args['post__not_in'] = array($this->post_id);
        }


        $query = new WP_Query( $args );
        return $query->posts;
    }
    
    function save_source($args = null){
        
        $this->sanitize_source();
        
        if (!$this->url){
            return new WP_Error( 'no_source_url', __('Unable to save source : missing URL','wpsstm') );
        }
        
        //check if this source exists already
        if ( $duplicate_ids = $this->get_source_duplicates() ){
            $this->post_id = $duplicate_ids[0];
            return $this->post_id;
        }
        
        $default_args = array(
            'post_author' =>    get_current_user_id(),
        );
        $args = wp_parse_args((array)$args,$default_args);
        
        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_source);
        $required_cap = $post_type_obj->cap->edit_posts;

        if ( !user_can($args['post_author'],$required_cap) ){
            return new WP_Error( 'wpsstm_save_source_cap_missing', __("You don't have the capability required to create a new live playlist.",'wpsstm') );
        }

        $required_args = array(
            'post_title' =>     $this->title,
            'post_type' =>      wpsstm()->post_type_source,
            'post_parent' =>    $this->track_id,
            'meta_input' =>     array(
                wpsstm_sources()->url_metakey => $this->url,
            ),
        );
            
        $args = wp_parse_args($required_args,$args);

        $this->post_id = wp_insert_post( $args );

        wpsstm()->debug_log(json_encode(array('args'=>$args,'post_id'=>$this->post_id)),"WP_SoundSystem_Source::save_source()");
        
        return $this->post_id;
        
    }
    
    function sanitize_source(){
        
        $this->url = trim($this->url);
        
        if ( !filter_var($this->url, FILTER_VALIDATE_URL) ){
            $this->url = null;
        }
        
        $this->title = trim($this->title);

    }
    
    private function populate_source_url(){
        
        if (!$this->url) return;

        foreach( (array)wpsstm_player()->providers as $provider ){

            if ( !$src_url = $provider->format_source_src($this->url) ) continue;
            
            $this->provider =       $provider;
            $this->type =           $provider->get_source_type($src_url);
            $this->src =            $src_url;
                
            break;
            
        }

    }

    function get_provider_link(){
        if ( !$this->provider ) return;

        $classes = array('wpsstm-source-provider-link','wpsstm-icon-link');

        $attr_arr = array(
            'class'     => implode(' ',$classes),
            'href'      => $this->url,
            'target'    => '_blank',
            'title'     => $this->title
        );
        
        if ($this->title){
            $source_title = sprintf('<span class="wpsstm-source-title">%s</span>',$this->title);
        }else{
            $source_title = sprintf('<span class="wpsstm-source-title">%s</span>',$this->provider->name);
        }

        return sprintf('<a %s>%s %s</a>',wpsstm_get_html_attr($attr_arr),$this->provider->icon,$source_title);
    }
    
}