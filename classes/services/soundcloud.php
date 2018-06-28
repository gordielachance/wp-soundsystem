<?php

class WPSSTM_Soundcloud_Platform extends WPSSTM_Provider_Platform{
    
    static $mimetype = 'video/soundcloud';
    
    function __construct(){
        add_filter('wpsstm_get_source_mimetype',array(__class__,'get_soundcloud_source_type'),10,2);
        add_filter('wpsstm_get_autosource_providers',array(__class__,'register_soundcloud_autosource'));
    }
    public static function register_soundcloud_autosource($providers){
        $providers['soundcloud'] = new WPSSTM_Soundcloud_Track_Autosource();
        return $providers;
    }
    public static function get_soundcloud_source_type($type,WPSSTM_Source $source){
        if ( self::get_sc_track_id($source->permalink_url) ){
            $type = self::$mimetype;
        }
        return $type;
    }
    public static function get_sc_track_id($url){

        /*
        check for souncloud API track URL
        
        https://api.soundcloud.com/tracks/9017297
        */

        $pattern = '~https?://api.soundcloud.com/tracks/([^/]+)~';
        preg_match($pattern, $url, $url_matches);

        if ( isset($url_matches[1]) ){
            return $url_matches[1];
        }
        
        /*
        check for souncloud widget URL
        
        https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/282715465&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&visual=true
        */
        
        $pattern = '~https?://w.soundcloud.com/player/.*tracks/([^&]+)~';
        preg_match($pattern, $url, $url_matches);

        if ( isset($url_matches[1]) ){
            return $url_matches[1];
        }

        /*
        check for souncloud track URL
        
        https://soundcloud.com/phasescachees/jai-toujours-reve-detre-un-gangster-feat-hippocampe-fou
        */
        
        //TOFIXKKK

        $pattern = '~https?://(?:www.)?soundcloud.com/([^/]+)/([^/]+)~';
        preg_match($pattern, $url, $url_matches);
        
        if ( isset($url_matches[1]) && isset($url_matches[2]) ){
            return sprintf('%s/%s',$url_matches[1],$url_matches[2]);
        }

    }
    
    public static function get_stream_url($url){

        if ( !$track_id = self::get_sc_track_id($url) ) return;

        if ( $this->client_id ){ //stream url
            return sprintf('https://api.soundcloud.com/tracks/%s/stream?client_id=%s',$track_id,$this->client_id);
        }else{ //widget url
            $widget_url = 'https://w.soundcloud.com/player/';
            $track_url = sprintf('http://api.soundcloud.com/tracks/%s',$track_id);
            $widget_args = array(
                'url' =>        urlencode ($track_url),
                'autoplay' =>   false,
                'client_id' =>  $this->client_id
            );
            return add_query_arg($widget_args,$widget_url);
        }

    }
    /*
    Scripts/Styles to load
    */
    public function provider_scripts_styles(){
        if (!$this->client_id){ //soundcloud renderer (required for soundcloud widget)
            wp_enqueue_script('wp-mediaelement-renderer-soundcloud',includes_url('js/mediaelement/renderers/soundcloud.min.js'), array('wp-mediaelement'), '4.0.6');    
        }
    }
    

    
    /*
    Get the ID of a Soundcloud track URL (eg. https://soundcloud.com/phasescachees/jai-toujours-reve-detre-un-gangster-feat-hippocampe-fou)
    Requires a Soundcloud Client ID.
    Store result in a transient to speed up page load.
    //TO FIX IMPORTANT slows down the website on page load.  Rather should run when source is saved ?
    */
    
    private static function request_sc_track_id($url){
        
        $client_id = wpsstm()->get_options('soundcloud_client_id');
        if ( !$client_id ) return;
        
        $transient_name = 'wpsstm_sc_track_id_' . md5($url);

        if ( false === ( $sc_id = get_transient($transient_name ) ) ) {

            $api_args = array(
                'url' =>        urlencode ($url),
                'client_id' =>  $client_id
            );

            $api_url = 'https://api.soundcloud.com/resolve.json';
            $api_url = add_query_arg($api_args,$api_url);

            $response = wp_remote_get( $api_url );
            $json = wp_remote_retrieve_body( $response );
            if ( is_wp_error($json) ) return;
            $data = json_decode($json,true);
            if ( isset($data['id']) ) {
                $sc_id = $data['id'];
                set_transient( $transient_name, $sc_id, 7 * DAY_IN_SECONDS );
            }
        }
        return $sc_id;
    }
}

class WPSSTM_Soundcloud_Track_Autosource extends WPSSTM_Track_Autosource{

    private static function can_search_sources(){
        if ( !$client_id = wpsstm()->get_options('soundcloud_client_id') ){
            return new WP_Error( 'wpsstm_missing_soundcloud_client_id', __('Required Soundcloud client ID missing.','wpsstm') );
        }
        return true;
    }

    public function search_track(){
        
        $can_search = self::can_search_sources();
        if( is_wp_error($can_search) ) return $can_search;
        
        $terms = urlencode($this->track->artist . ' ' . $this->track->title);

        $search_args = array(
            'q' =>          $terms,
            'limit' =>      50,
            'client_id' =>  wpsstm()->get_options('soundcloud_client_id')
        );
        
        $api_url = 'http://api.soundcloud.com/tracks.json';
        $api_url = add_query_arg($search_args,$api_url);

        wpsstm()->debug_log($api_url,'WPSSTM_Player_Provider_Soundcloud::search_sources');
        
        $response = wp_remote_get( $api_url );
        $json = wp_remote_retrieve_body( $response );
        if ( is_wp_error($json) ) return $json;
        $items = json_decode($json,true);
        return $items;
    }
    
    private function get_soundcloud_tags($item){
        $tags = array();
        
        //track type
        if ( $track_type = $item['track_type'] ){
            $avoid_tags = array('other');
            if ( !in_array($track_type,$avoid_tags) ){
                $tags[] = $track_type;
            }
        }
        
        //source title
        $title_tags = $this->parse_source_title_tags($item['title']);
        $tags = array_merge($tags,(array)$title_tags);
        
        return $tags;
    }
    
    function populate_track_autosources(){
        
        $items = $this->search_track();
        if ( is_wp_error($items) ) return $items;
        
        $sources = array();

        foreach((array)$items as $key=>$item){
            
            //define autosource
            $source = new WPSSTM_Source();
            $source->track_id = $this->track->post_id;
            $source->is_community = true;
            
            $source->index = $key;
            
            $source->autosource_data = $item;
            ///
            $source->permalink_url = $item['permalink_url'];
            $source->stream_url = $item['stream_url'];
            $source->duration = round($item['duration'] / 1000);
            $source->title = $item['title'];
            $source->tags = $this->get_soundcloud_tags($item);

            if($item['download_url']){
                $source->download_url = $item['download_url'];
            }
            $sources[] = $source;

        }

        $this->sources = $sources;
    }
    
    static function get_provider_weight(){
        return .85;
    }

}

new WPSSTM_Soundcloud_Platform();