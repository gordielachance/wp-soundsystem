<?php

abstract class WP_SoundSystem_Preset_LastFM_Scraper extends WP_SoundSystem_Live_Playlist_Preset{

    var $preset_slug =      'last-fm';
    var $preset_url =       'https://www.last.fm';

    var $preset_options =  array(
        'selectors' => array(
            'tracks'           => array('path'=>'table.chartlist tbody tr'),
            'track_artist'     => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap .chartlist-artists a'),
            'track_title'      => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap > a'),
            'track_image'      => array('path'=>'img.cover-art','attr'=>'src'),
            'track_source_urls' => array('path'=>'a[data-youtube-url]','attr'=>'href'),
        )
    );

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name = __('Last.FM user library','wpsstm');
    }
    
    function get_remote_url(){
        $domain = wpsstm_get_url_domain( $this->feed_url );
        if ( $domain != 'lastfm') return;
        if ( !$user_slug = self::get_user_slug($this->feed_url) ){
            return new WP_Error( 'wpsstm_lastfm_missing_user_slug', __('Required user slug missing.','wpsstm') );
        }
        
        return sprintf('https://www.last.fm/user/%s/library',$user_slug);
    }
    
    static function get_user_slug($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?(?:user/([^/]+))~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    static function get_artist_slug($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    static function get_user_page($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?user/.*/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    static function get_artist_page($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/.*/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

}

class WP_SoundSystem_Preset_LastFM_User_Scraper extends WP_SoundSystem_Preset_LastFM_Scraper{
    var $preset_slug =      'last-fm-user';
    var $preset_url =       'https://www.last.fm/user/XXX';

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name = __('Last.FM user','wpsstm');
    }
    
    static function can_handle_url($url){
        if ( !$user_slug = self::get_user_slug($url) ) return;
        return true;
    }
    
    function get_remote_url(){

        $page = self::get_user_page($this->feed_url);

        return sprintf('https://www.last.fm/fr/user/%s/%s',self::get_user_slug($this->feed_url),$page);
    }
}

class WP_SoundSystem_Preset_LastFM_User_Loved_Scraper extends WP_SoundSystem_Preset_LastFM_User_Scraper{

    var $preset_slug =      'last-fm-user-favorites';
    var $preset_url =       'https://www.last.fm/user/XXX/loved';

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name = __('Last.FM user favorites','wpsstm');
    }
    
    static function can_handle_url($url){
        if ( !$user_slug = self::get_user_slug($url) ) return;
        if ( self::get_user_page($url) != 'loved' ) return;
        return true;
    }

}

class WP_SoundSystem_Preset_LastFM_Artist_Scraper extends WP_SoundSystem_Preset_LastFM_Scraper{

    var $preset_slug =      'last-fm-artist';
    var $preset_url =       'https://www.last.fm/music/XXX';
    var $artist;

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name = __('Last.FM artist (top tracks)','wpsstm');
    }
    
    static function can_handle_url($url){
        if ( !$artist_slug = self::get_artist_slug($url) ) return;
        return true;
    }
    
    function get_remote_url(){
        return sprintf('https://www.last.fm/music/%s/+tracks',self::get_artist_slug($this->feed_url));
    }
    
    //On an artist page, artist is displayed only on the header page; not on each track.
    //So use body_node as input here.

    protected function get_track_artist($track_node){
        if (!$this->artist){
            $selector = array('path'=>'[data-page-resource-type="artist"]','regex'=>null,'attr'=>'data-page-resource-name');
            $this->artist = $this->parse_node($this->body_node,$selector);
        }
        return $this->artist;
    }

}

abstract class WP_SoundSystem_Preset_LastFM_Station extends WP_SoundSystem_Preset_LastFM_Scraper{

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'>playlist'),
            'track_artist'      => array('path'=>'artists > name'),
            'track_title'       => array('path'=>'playlist > name'),
            'track_source_urls' => array('path'=>'playlinks url'),
        )
    );

}

class WP_SoundSystem_Preset_LastFM_Station_Similar_Artist_Scraper extends WP_SoundSystem_Preset_LastFM_Station{

    var $preset_slug =      'last-fm-station-artist';
    var $preset_url =       'https://www.last.fm/music/XXX/+similar';

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name = __('Last.FM stations (similar artist)','wpsstm');
    }
    
    static function can_handle_url($url){
        if ( !$artist_slug = self::get_artist_slug($url) ) return;
        if ( self::get_artist_page($url) != '+similar' ) return;
        return true;
    }
    
    function get_remote_url(){
        return sprintf('https://www.last.fm/player/station/music/%s?ajax=1',self::get_artist_slug($this->feed_url));
    }
    
    function get_remote_title(){
        return sprintf( __('Last.FM stations (similar artist): %s','wpsstm'), self::get_artist_slug($this->feed_url) );
    }

}



class WP_SoundSystem_Preset_LastFM_Station_User_Recommandations_Scraper extends WP_SoundSystem_Preset_LastFM_Station{

    var $preset_slug =      'last-fm-station-user';
    var $preset_url =       'https://www.last.fm/user/XXX/recommended';
    var $station_slug;

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name = __('Last.FM user stations','wpsstm');
    }

    static function can_handle_url($url){
        if ( !$user_slug = self::get_user_slug($url) ) return;
        if ( !self::get_station_page($url) ) return;
        return true;
    }
    
    static function get_user_slug($url){
        $pattern = '~^lastfm:user:([^:]+):station~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    static function get_station_page($url){
        $pattern = '~^lastfm:user:[^:]+:station:([^:]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_url(){
        return sprintf('https://www.last.fm/player/station/user/%s/%s?ajax=1',self::get_user_slug($this->feed_url),self::get_station_page($this->feed_url) );
    }
    
    function get_remote_title(){
        return sprintf( __('Last.FM station for %s - %s','wpsstm'),self::get_user_slug($this->feed_url),self::get_station_page($this->feed_url) );
    }
}




//register preset

function register_lastfm_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_LastFM_User_Scraper';
    $presets[] = 'WP_SoundSystem_Preset_LastFM_User_Loved_Scraper';
    $presets[] = 'WP_SoundSystem_Preset_LastFM_Artist_Scraper';
    $presets[] = 'WP_SoundSystem_Preset_LastFM_Station_Similar_Artist_Scraper';
    $presets[] = 'WP_SoundSystem_Preset_LastFM_Station_User_Recommandations_Scraper';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_lastfm_preset');