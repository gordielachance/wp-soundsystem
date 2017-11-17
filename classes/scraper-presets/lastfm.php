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
        if ( !$user_slug = $this->get_user_slug ){
            return new WP_Error( 'wpsstm_lastfm_missing_user_slug', __('Required user slug missing.','wpsstm') );
        }
        
        return sprintf('https://www.last.fm/user/%s/library',$user_slug);
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
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_artist_page(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/.*/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
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
    
    function can_load_feed(){
        if ( !$user_slug = $this->get_user_slug() ) return;
        return true;
    }
    
    function get_remote_url(){

        $page = $this->get_user_page();

        return sprintf('https://www.last.fm/fr/user/%s/%s',$this->get_user_slug(),$page);
    }
}

class WP_SoundSystem_Preset_LastFM_User_Loved_Scraper extends WP_SoundSystem_Preset_LastFM_User_Scraper{

    var $preset_slug =      'last-fm-user-favorites';
    var $preset_url =       'https://www.last.fm/user/XXX/loved';

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name = __('Last.FM user favorites','wpsstm');
    }
    
    function can_load_feed(){
        if ( !$user_slug = $this->get_user_slug() ) return;
        if ( $this->get_user_page() != 'loved' ) return;
        return true;
    }

}

class WP_SoundSystem_Preset_LastFM_Artist_Scraper extends WP_SoundSystem_Preset_LastFM_Scraper{

    var $preset_slug =      'last-fm-artist';
    var $preset_url =       'https://www.last.fm/music/XXX';

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name = __('Last.FM artist (top tracks)','wpsstm');
    }
    
    function can_load_feed(){
        if ( !$artist_slug = $this->get_artist_slug() ) return;
        return true;
    }
    
    function get_remote_url(){
        return sprintf('https://www.last.fm/music/%s/+tracks',$this->get_artist_slug());
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
    
    function can_load_feed(){
        if ( !$artist_slug = $this->get_artist_slug() ) return;
        if ( $this->get_artist_page() != '+similar' ) return;
        return true;
    }
    
    function get_remote_url(){
        return sprintf('https://www.last.fm/player/station/music/%s?ajax=1',$this->get_artist_slug());
    }
    
    function get_remote_title(){
        return sprintf( __('Last.FM stations (similar artist): %s','wpsstm'), $this->get_artist_slug() );
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

    function can_load_feed(){
        if ( !$user_slug = $this->get_user_slug() ) return;
        if ( !$this->get_station_page() ) return;
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
        return sprintf('https://www.last.fm/player/station/user/%s/%s?ajax=1',$this->get_user_slug(),$this->get_station_page());
    }
    
    function get_remote_title(){
        return sprintf( __('Last.FM station for %s - %s','wpsstm'), $this->get_user_slug(),$this->get_station_page() );
    }
}




//register preset

function register_lastfm_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_LastFM_User_Scraper';
    $presets[] = 'WP_SoundSystem_Preset_LastFM_User_Loved_Scraper';
    //$presets[] = 'WP_SoundSystem_Preset_LastFM_Artist_Scraper'; //TO FIX : NOT WORKING BECAUSE WE CANNOT GET ARTIST WHEN PARSING TRACKS ON /+tracks
    $presets[] = 'WP_SoundSystem_Preset_LastFM_Station_Similar_Artist_Scraper';
    $presets[] = 'WP_SoundSystem_Preset_LastFM_Station_User_Recommandations_Scraper';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_lastfm_preset');