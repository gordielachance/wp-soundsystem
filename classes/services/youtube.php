<?php

class WPSSTM_Youtube_Platform extends WPSSTM_Provider_Platform{
    
    static $mimetype = 'video/youtube';
    
    function __construct(){
        add_filter('wpsstm_get_source_mimetype',array(__class__,'get_youtube_source_type'),10,2);
        add_filter('wpsstm_get_autosource_providers',array(__class__,'register_youtube_autosource'));
    }
    public static function register_youtube_autosource($providers){
        $providers['youtube'] = new WPSSTM_Youtube_Track_Autosource();
        return $providers;
    }
    public static function get_youtube_source_type($type,WPSSTM_Source $source){
        if ( self::get_youtube_id($source->permalink_url) ){
            $type = self::$mimetype;
        }
        return $type;
    }
    public static function get_youtube_id($url){
        //youtube
        $pattern = '~http(?:s?)://(?:www.)?youtu(?:be.com/watch\?v=|.be/)([\w\-\_]*)(&(amp;)?[\w\?=]*)?~i';
        preg_match($pattern, $url, $url_matches);
        
        if ( !isset($url_matches[1]) ) return;
        
        return $url_matches[1];
    }
    public static function get_youtube_permalink($id){
        return sprintf('https://youtube.com/watch?v=%s',$id);
    }
}

class WPSSTM_Youtube_Track_Autosource extends WPSSTM_Track_Autosource{

    private static function can_search_sources(){
        if ( !$api_key = wpsstm()->get_options('youtube_api_key') ){
            return new WP_Error( 'wpsstm_missing_youtube_api_key', __('Required Youtube API key missing.','wpsstm') );
        }
        return true;
    }

    public function search_track(){
        
        $items = array();
        $can_search = self::can_search_sources();
        if( is_wp_error($can_search) ) return $can_search;
        
        $terms = urlencode($this->track->artist . ' ' . $this->track->title);
        
        $api_args = array(
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
        $api_url = add_query_arg($api_args,$api_url);
        wpsstm()->debug_log($api_url,'WPSSTM_Player_Provider_Youtube::search_track');
        
        $response = wp_remote_get( $api_url );
        $json = wp_remote_retrieve_body( $response );
        if ( is_wp_error($json) ) return $json;
        $api_results = json_decode($json,true);
        $api_items = wpsstm_get_array_value(array('items'),$api_results);

        if ($this->track->duration && $api_items){
            /*
            Youtube search API does not include the video length: do a second query that will fetch the contentDetails.
            This way we can weight the sources depending of their duration.
            */

            $video_ids = array();

            foreach( (array)$api_items as $api_item ){
                $video_ids[] = wpsstm_get_array_value(array('id','videoId'),$api_item);
            }

            $api_args = array(
                'id' =>                 implode(',',$video_ids),
                'part' =>               'contentDetails',
                'key' =>                wpsstm()->get_options('youtube_api_key')
            );

            $api_url = 'https://www.googleapis.com/youtube/v3/videos';
            $api_url = add_query_arg($api_args,$api_url);

            $response = wp_remote_get( $api_url );
            $json = wp_remote_retrieve_body( $response );

            if ( !is_wp_error($json) ){
                $api_results = json_decode($json,true);
                $api_items_details = wpsstm_get_array_value(array('items'),$api_results);
                $items_details = array();

                foreach((array)$api_items_details as $api_item_details){
                    $vid = wpsstm_get_array_value(array('id'),$api_item_details);
                    $details = wpsstm_get_array_value(array('contentDetails'),$api_item_details);
                    $items_details[$vid] = $details;
                }
                
                //append contentDetails to our previous API results
                foreach( (array)$api_items as $key=>$api_item ){
                    $vid = wpsstm_get_array_value(array('id','videoId'),$api_item);
                    $api_items[$key]['contentDetails'] = $items_details[$vid];
                }
                
            }
        }
        return $api_items;
    }

    function populate_track_autosources(){
        
        $items = $this->search_track();
        if ( is_wp_error($items) ) return $items;

        $sources = array();
        
        foreach((array)$items as $key=>$item){

            $vid = wpsstm_get_array_value(array('id','videoId'),$item);
            $title = wpsstm_get_array_value(array('snippet','title'),$item);
            $permalink = WPSSTM_Youtube_Platform::get_youtube_permalink($vid);

            //define autosource
            $source = new WPSSTM_Source();
            $source->is_community = true;
            $source->track_id = $this->track->post_id;
            
            $source->index = $key;
            $source->autosource_data = $item;
            $source->tags = $this->parse_source_title_tags($title);
            $source->weights['provider'] = .7;
            
            ///
            $source->permalink_url = $permalink;
            $source->title = $title;
            
            $duration_str = wpsstm_get_array_value(array('contentDetails','duration'),$item);
            if ($duration_str){
                $interval = new DateInterval($duration_str);
                $duration_sec = $interval->h * 3600 + $interval->i * 60 + $interval->s;
                $source->duration = $duration_sec;
            }
            $sources[] = $source;
        }

        $this->sources = $sources;
        
    }
    
    static function get_provider_weight(){
        return .7;
    }

}

new WPSSTM_Youtube_Platform();