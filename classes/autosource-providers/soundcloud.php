<?php

class WPSSTM_Soundcloud_Sources_Provider extends WPSSTM_Autosource_Provider{
    function __construct(){
        if ( self::can_search_sources() ){
            add_filter('wpsstm_get_track_sources_auto',array(__class__,'inject_provider_sources'), 10, 2);
        }
    }
    
    private static function can_search_sources(){
        if ( !$client_id = wpsstm()->get_options('soundcloud_client_id') ){
            return new WP_Error( 'wpsstm_missing_soundcloud_client_id', __('Required Soundcloud client ID missing.','wpsstm') );
        }
        return true;
    }

    public static function inject_provider_sources($sources,WPSSTM_Track $track){
        $provider_sources = self::get_sources($track);
        if ( !is_wp_error($provider_sources) ){
            $sources = array_merge($sources,$provider_sources);
        }
        return $sources;
    }
    
    public static function search_track(WPSSTM_Track $track){
        
        $can_search = self::can_search_sources();
        if( is_wp_error($can_search) ) return $can_search;
        
        $terms = urlencode($track->artist . ' ' . $track->title);

        $search_args = array(
            'q' =>          $terms,
            'client_id' =>  wpsstm()->get_options('soundcloud_client_id')
        );
        
        $api_url = 'http://api.soundcloud.com/tracks.json';
        $api_url = add_query_arg($search_args,$api_url);
        
        wpsstm()->debug_log($api_url,'WPSSTM_Player_Provider_Soundcloud::search_sources');
        
        $response = wp_remote_get( $api_url );
        $json = wp_remote_retrieve_body( $response );
        if ( is_wp_error($json) ) return $json;
        return json_decode($json,true);
    }
    
    public static function get_sources(WPSSTM_Track $track){
        
        $items = self::search_track($track);
        if ( is_wp_error($items) ) return $items;
        
        $sources = array();

        foreach($items as $item){
            $source = parent::get_auto_source_obj($track);
            $source->url = $item['permalink_url'];
            $source->stream_url = $item['stream_url'];
            $source->duration = $item['duration'];
            $source->title = $item['title'];
            $sources[] = $source;
        }
        wpsstm()->debug_log(json_encode($sources),'WPSSTM_Player_Provider_Soundcloud::get_sources');
        
        return $sources;
    }
}
new WPSSTM_Soundcloud_Sources_Provider();