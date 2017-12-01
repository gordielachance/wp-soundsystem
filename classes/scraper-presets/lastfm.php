<?php

class WP_SoundSystem_LastFM_URL extends WP_SoundSystem_URL_Preset{
    var $preset_slug =      'lastfm';
    var $preset_url =       'https://www.last.fm';
    
    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->scraper_options['selectors'] = array(
            'tracks'           => array('path'=>'table.chartlist tbody tr'),
            'track_artist'     => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap .chartlist-artists a'),
            'track_title'      => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap > a'),
            'track_image'      => array('path'=>'img.cover-art','attr'=>'src'),
            'track_source_urls' => array('path'=>'a[data-youtube-url]','attr'=>'href'),
        );
    }
    
    function can_handle_url(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm~i';
        preg_match($pattern, $this->feed_url, $matches);
        return ( !empty($matches) );
    }

    function get_user_slug(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?(?:user/([^/]+))~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    function get_artist_slug(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_user_page(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?user/[^/]+/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : 'library';
    }
    
    function get_artist_page(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/[^/]+/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : '+tracks';
    }
    
    function get_album_name(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/[^/]+/(?!\+)([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    //Artist is displayed differently on artist/album/track pages.
    protected function get_track_artist($track_node){

        $artist = $this->get_artist_slug();
        
        if ( !$artist = $this->get_artist_slug() ) return parent::get_track_artist($track_node); //not an artist page
        
        if ( $album = $this->get_album_name() ){
            $selector = array('path'=>'[itemtype="http://schema.org/MusicGroup"] [itemprop="name"]','regex'=>null);
            $artist = $this->parse_node($this->body_node,$selector);
        }else{
            $selector = array('path'=>'[data-page-resource-type="artist"]','regex'=>null,'attr'=>'data-page-resource-name');
            $artist = $this->parse_node($this->body_node,$selector);
        }

        return $artist;
    }

}

abstract class WP_SoundSystem_LastFM_Station extends WP_SoundSystem_LastFM_URL{
    
    var $preset_slug =      'lastfm-station';

    function __construct($post_id = null){
        
        parent::__construct($post_id);
        $this->scraper_options['selectors'] = array(
            'tracks'            => array('path'=>'>playlist'),
            'track_artist'      => array('path'=>'artists > name'),
            'track_title'       => array('path'=>'playlist > name'),
            'track_source_urls' => array('path'=>'playlinks url'),
        );
        
    }

}

class WP_SoundSystem_LastFM_Similar_Artist_Station extends WP_SoundSystem_LastFM_Station{
    var $preset_slug =      'lastfm-station-similar-artist';
    var $preset_url =       'https://www.last.fm/music/XXX/+similar';
    private $artist_slug;
    private $page_slug;
    
    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->artist_slug = $this->get_artist_slug();
        $this->page_slug = $this->get_artist_page();
    }
    
    function can_handle_url(){
        if ( !$this->artist_slug ) return;
        if ( $this->page_slug != '+similar' ) return;
        return true;
    }

    function get_remote_url(){
        return sprintf('https://www.last.fm/player/station/music/%s?ajax=1',$this->artist_slug);
    }

    function get_remote_title(){
        return sprintf( __('Last.fm stations (similar artist): %s','wpsstm'),$this->artist_slug );
    }

}

class WP_SoundSystem_LastFM_User_Recommandations_Station extends WP_SoundSystem_LastFM_Station{
    var $preset_slug =      'lastfm-station-user-recommandations';
    var $preset_url =       'https://www.last.fm/user/XXX/recommended';
    private $user_slug;
    private $page_slug;

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->user_slug = $this->get_user_slug();
        $this->page_slug = $this->get_station_page();
        
    }
    
    function can_handle_url(){
        if ( !$this->user_slug ) return;
        if ( !$this->page_slug ) return;
        return true;
    }

    function get_user_slug(){
        $pattern = '~^lastfm:user:([^:]+):station~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_station_page(){
        $pattern = '~^lastfm:user:[^:]+:station:([^:]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_url(){
        return sprintf('https://www.last.fm/player/station/user/%s/%s?ajax=1',$this->user_slug,$this->page_slug );
    }
    
    function get_remote_title(){
        return sprintf( __('Last.fm station for %s - %s','wpsstm'),$this->user_slug,$this->page_slug );
    }
}

//register preset
function register_lastfm_preset($presets){
    $presets[] = 'WP_SoundSystem_LastFM_URL';
    $presets[] = 'WP_SoundSystem_LastFM_Similar_Artist_Station';
    $presets[] = 'WP_SoundSystem_LastFM_User_Recommandations_Station';
    return $presets;
}


function register_lastfm_service_links($links){
    $links[] = array(
        'slug'      => 'lastfm',
        'name'      => 'Last.fm',
        'url'       => 'https://www.last.fm/',
    );

    $links[] = array(
        'slug'          => 'lastfm-stations',
        'parent_slug'   => 'lastfm',
        'name'          => __('stations','wpsstm'),
        'example'       => 'https://www.bbc.co.uk/STATION',
    );
    

    
    return $links;
}

add_action('wpsstm_get_scraper_presets','register_lastfm_preset');
add_filter('wpsstm_wizard_services_links','register_lastfm_service_links');