<?php
class WP_SoundSystem_Source {
    var $post_id;
    var $track;
    var $position = -1;
    var $title;
    var $is_community;
    var $url; //input URL
    var $src; //URL used in the 'source' tag (which could be not the same)
    var $provider;
    var $type;
    var $similarity;
    

    private $defaults = array(
        'post_id'       => null,
        'track_id'      => null,
        'url'           => null,
        'title'         => null,
        'is_community'  => null, 
        'similarity'    => null,
    );
    
    function __construct($post_id = null){
        
        $this->provider = new WP_SoundSystem_Player_Provider(); //default
        
        if ($post_id){
            $this->post_id = $post_id;
            $this->title = get_the_title($post_id);
            $track_id = wp_get_post_parent_id( $post_id );
            $this->track = new WP_SoundSystem_Track($track_id);

            $this->url = get_post_meta($post_id,wpsstm_sources()->url_metakey,true);

            $post_author_id = get_post_field( 'post_author', $post_id );
            $community_user_id = wpsstm()->get_options('community_user_id');
            $this->is_community = ( $post_author_id == $community_user_id );

        }

        if (!$this->title && $this->provider) $this->title = $this->provider->name;

    }
    
    function from_array( $args = null ){

        $args_default = $this->defaults;
        $args = wp_parse_args((array)$args,$args_default);
        $post_id = null;

        //set properties from args input
        foreach ($args as $key=>$value){
            if ( !array_key_exists($key,$args_default) ) continue;
            if ( !isset($args[$key]) ) continue; //value has not been set
            $this->$key = $args[$key];
        }

        $this->__construct( $post_id );
        
    }

    /*
    Get the sources that have the same URL or track informations than the current one
    */
    function get_source_duplicates_ids($args=null){
        
        /*
        $query_meta_trackinfo = array(
            'relation' => 'AND',
            wpsstm_artists()->artist_metakey    => $this->track->artist,
            wpsstm_tracks()->title_metakey      => $this->track->title,
            wpsstm_albums()->album_metakey      => $this->track->album,
        );
        */
        
        $query_meta_trackinfo = array_filter($query_meta_trackinfo);
        
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
                'relation' => 'OR',
                //by source URL
                'source_url' => array(
                    'key'     => wpsstm_sources()->url_metakey,
                    'value'   => $this->url
                ),
                //by track info, TO FIX TO CHECK required ?
                //$query_meta_trackinfo,
            )
        );
        
        $args = wp_parse_args($required,$args);

        $query = new WP_Query( $args );
        return $query->posts;
    }
    
    function save_source($args = null){
        
        if (!$this->track->post_id){
            return new WP_Error( 'no_post_id', __('Unable to save source : missing track ID','wpsstm') );
        }
        
        $this->url = trim($this->url);
        
        if ( !filter_var($this->url, FILTER_VALIDATE_URL) ){
            $this->url = null;
        }
        
        if (!$this->url){
            return new WP_Error( 'no_source_url', __('Unable to save source : missing URL','wpsstm') );
        }
        
        $this->title = trim($this->title);
        
        //check if this source exists already
        $duplicate_args = array('fields'=>'ids');
        if ( $duplicate_ids = $this->get_source_duplicates_ids($duplicate_args) ){
            $this->post_id = $duplicate_ids[0];
            return $this->post_id;
        }

        $default_args = array(
            'post_author' =>    ($this->is_community) ?wpsstm()->get_options('community_user_id') : get_current_user_id(),
            'post_status' =>    'publish',
        );

        $args = wp_parse_args((array)$args,$default_args);
        
        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_source);
        $required_cap = $post_type_obj->cap->edit_posts;

        if ( !user_can($args['post_author'],$required_cap) ){
            return new WP_Error( 'wpsstm_save_source_cap_missing', __("You don't have the capability required to create a new live playlist.",'wpsstm') );
        }
        
        //also save "track" information so we can query this source even if the track has been deleted (TO FIX TO CHECK required ?)
        
        $meta_input = array(
            wpsstm_sources()->url_metakey       => $this->url,
            /*
            wpsstm_artists()->artist_metakey    => $this-track->artist,
            wpsstm_tracks()->title_metakey      => $this-track->title,
            wpsstm_albums()->album_metakey      => $this-track->album,
            wpsstm_mb()->mbid_metakey           => $this-track->mbid,
            */
        );
        
        $meta_input = array_filter($meta_input);

        $required_args = array(
            'post_title' =>     $this->title,
            'post_type' =>      wpsstm()->post_type_source,
            'post_parent' =>    $this->track->post_id,
            'meta_input' =>     $meta_input,
        );
            
        $args = wp_parse_args($required_args,$args);

        $this->post_id = wp_insert_post( $args );

        wpsstm()->debug_log(json_encode(array('args'=>$args,'post_id'=>$this->post_id)),"WP_SoundSystem_Source::save_source()");
        
        return $this->post_id;
        
    }

    function populate_provider(){
        
        if (!$this->url) return;

        foreach( (array)wpsstm_player()->providers as $provider ){

            if ( !$src_url = $provider->format_source_src($this->url) ) continue;
            
            $this->provider =       $provider;
            $this->type =           $provider->get_source_type($src_url);
            $this->src =            $src_url;
                
            break;
            
        }
        
        return $this->provider;

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
        
        $source_title = sprintf('<label class="wpsstm-source-title">%s</label>',$this->title);

        return sprintf('<a %s>%s %s</a>',wpsstm_get_html_attr($attr_arr),$this->provider->icon,$source_title);
    }
    
    function get_source_class(){

        $classes = array('wpsstm-source');
        if ($this->position == 1){
            $classes[] = 'wpsstm-active-source';
        }

        return $classes;
    }
    
    //TO FIX TO CHECK should return only one type of URL
    function get_source_url($raw = false){
        global $wpsstm_source;
        $source = $wpsstm_source;

        if (!$raw){ //playable url
            return $source->src;
        }else{
            return $source->url;
        }
    }
    
}