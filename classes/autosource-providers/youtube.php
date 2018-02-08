<?php

class WPSSTM_Youtube_Sources_Provider extends WPSSTM_Autosource_Provider{
    
    function __construct(){
        if ( self::can_search_sources() ){
            add_filter('wpsstm_get_track_sources_auto',array(__class__,'inject_provider_sources'), 10, 2);
        }
    }
    
    private static function can_search_sources(){
        if ( !$api_key = wpsstm()->get_options('youtube_api_key') ){
            return new WP_Error( 'wpsstm_missing_youtube_api_key', __('Required Youtube API key missing.','wpsstm') );
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
            'q' =>                  $terms,
            'maxResults' =>         50,
            'safeSearch' =>         'none',
            'order' =>              'relevance',
            'part' =>               'snippet',
            'type' =>               'video',
            //'videoDuration' =>      'any',
            //'topicId' =>            '/m/04rlf',
            'videoCategoryId' =>    '10',
            'key' =>                wpsstm()->get_options('youtube_api_key')
        );
        
        $api_url = 'https://www.googleapis.com/youtube/v3/search';
        $api_url = add_query_arg($search_args,$api_url);
        wpsstm()->debug_log($api_url,'WPSSTM_Player_Provider_Youtube::search_sources');
        
        $response = wp_remote_get( $api_url );
        $json = wp_remote_retrieve_body( $response );
        if ( is_wp_error($json) ) return $json;
        return json_decode($json,true);
    }
    
    public static function get_sources(WPSSTM_Track $track){
        
        $api_results = self::search_track($track);
        if ( is_wp_error($api_results) ) return $api_results;
        
        $sources = array();
        $items = wpsstm_get_array_value(array('items'),$api_results);
        
        foreach($items as $item){
            
            $id = wpsstm_get_array_value(array('id','videoId'),$item);
            $title = wpsstm_get_array_value(array('snippet','title'),$item);
            $permalink = WPSSTM_Player_Provider_Youtube::get_youtube_permalink($id);
            
            $source = parent::get_auto_source_obj($track);
            $source->url = $permalink;
            $source->title = $title;
            $sources[] = $source;
        }
        wpsstm()->debug_log(json_encode($sources),'WPSSTM_Player_Provider_Youtube::get_sources');

        return $sources;
        
    }
}
new WPSSTM_Youtube_Sources_Provider();