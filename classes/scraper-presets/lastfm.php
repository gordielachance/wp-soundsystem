<?php

abstract class WP_SoundSystem_LastFM_URL extends WP_SoundSystem_URL_Preset{
    var $preset_url =       'https://www.last.fm';
    
    function __construct($feed_url = null){
        parent::__construct($feed_url);
        $this->options['selectors'] = array(
            'tracks'           => array('path'=>'table.chartlist tbody tr'),
            'track_artist'     => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap .chartlist-artists a'),
            'track_title'      => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap > a'),
            'track_image'      => array('path'=>'img.cover-art','attr'=>'src'),
            'track_source_urls' => array('path'=>'a[data-youtube-url]','attr'=>'href'),
        );
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
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?user/.*/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : 'library';
    }
    
    function get_artist_page(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/.*/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : '+tracks';
    }

}

class WP_SoundSystem_LastFM_User_URL extends WP_SoundSystem_LastFM_URL{
    var $preset_url =       'https://www.last.fm/user/XXX';
    var $user_slug;
    var $page_slug;

    function __construct($feed_url = null){
        
        parent::__construct($feed_url);
        $this->user_slug = $this->get_user_slug();
        $this->page_slug = $this->get_user_page();
        

        
    }
    
    function can_handle_url(){
        if ( !$this->user_slug ) return;
        return true;
    }

    function get_remote_url(){
        return sprintf('https://www.last.fm/fr/user/%s/%s',$this->user_slug,$this->page_slug);
    }

}

class WP_SoundSystem_LastFM_Artist_URL extends WP_SoundSystem_LastFM_URL{
    var $preset_url =       'https://www.last.fm/music/XXX';
    var $page_artist;
    var $artist_slug;
    var $page_slug;

    function __construct($feed_url = null){
        
        parent::__construct($feed_url);
        $this->artist_slug = $this->get_artist_slug();
        $this->page_slug = $this->get_artist_page();
    }
    
    function can_handle_url(){
        if ( !$this->artist_slug ) return;
        return true;
    }

    function get_remote_url(){
        return sprintf('https://www.last.fm/music/%s/%s',$this->artist_slug,$this->page_slug);
    }

    
    //On an artist page, artist is displayed only on the header page; not on each track.
    //So use body_node as input here.

    protected function get_track_artist($track_node){
        if (!$this->page_artist){
            $selector = array('path'=>'[data-page-resource-type="artist"]','regex'=>null,'attr'=>'data-page-resource-name');
            $this->page_artist = $this->parse_node($this->body_node,$selector);
        }
        return $this->page_artist;
    }

}

abstract class WP_SoundSystem_LastFM_Station extends WP_SoundSystem_LastFM_URL{

    function __construct($feed_url = null){
        
        parent::__construct($feed_url);
        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'>playlist'),
            'track_artist'      => array('path'=>'artists > name'),
            'track_title'       => array('path'=>'playlist > name'),
            'track_source_urls' => array('path'=>'playlinks url'),
        );
        
    }

}

class WP_SoundSystem_LastFM_Station_Similar_Artist_Scraper extends WP_SoundSystem_LastFM_Station{
    var $preset_url =       'https://www.last.fm/music/XXX/+similar';
    var $artist_slug;
    var $page_slug;
    
    function __construct($feed_url = null){
        parent::__construct($feed_url);
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
        return sprintf( __('Last.FM stations (similar artist): %s','wpsstm'),$this->artist_slug );
    }

}

class WP_SoundSystem_LastFM_Station_User_Recommandations_Scraper extends WP_SoundSystem_LastFM_Station{
    var $preset_url =       'https://www.last.fm/user/XXX/recommended';
    var $user_slug;
    var $page_slug;

    function __construct($feed_url = null){
        parent::__construct($feed_url);
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
        return sprintf( __('Last.FM station for %s - %s','wpsstm'),$this->user_slug,$this->page_slug );
    }
}

//register preset
function register_lastfm_preset($presets){
    $presets[] = 'WP_SoundSystem_LastFM_User_URL';
    $presets[] = 'WP_SoundSystem_LastFM_Artist_URL';
    $presets[] = 'WP_SoundSystem_LastFM_Station_Similar_Artist_Scraper';
    $presets[] = 'WP_SoundSystem_LastFM_Station_User_Recommandations_Scraper';
    return $presets;
}
add_action('wpsstm_get_scraper_presets','register_lastfm_preset');