<?php

class WP_SoundSystem_LastFM_URL{
    var $tracklist;

    function __construct($tracklist){
        
        $this->tracklist = $tracklist;

        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
        add_filter( 'wpsstm_live_tracklist_track_artist',array($this,'artist_header_track_artist'), 10, 3 );

    }
    
    function can_handle_url(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return ( !empty($matches) );
    }

    function get_user_slug(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?(?:user/([^/]+))~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
                   
    function get_artist_slug(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_user_page(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?user/[^/]+/([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : 'library';
    }
    
    function get_artist_page(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/[^/]+/([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : '+tracks';
    }
    
    function get_album_name(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/[^/]+/(?!\+)([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function is_station(){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?player/station~i';
        preg_match($pattern,$this->tracklist->redirect_url, $matches);
        if ( !empty($matches) ) return true;
    }
                   
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){

            $options['selectors'] = array(
                'tracks'           => array('path'=>'table.chartlist tbody tr'),
                'track_artist'     => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap .chartlist-artists a'),
                'track_title'      => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap > a'),
                'track_image'      => array('path'=>'img.cover-art','attr'=>'src'),
                'track_source_urls' => array('path'=>'a[data-youtube-url]','attr'=>'href'),
            );
            
        }
        
        return $options;
        
    }
    
    //on artists and album pages; artist is displayed in a header on the top of the page
    function artist_header_track_artist($artist,$track_node,$tracklist){
        
        if ( $this->get_artist_slug() && !$this->is_station() ){

            if ( $album_slug = $this->get_album_name() ){
                $selector = array('path'=>'[itemtype="http://schema.org/MusicGroup"] [itemprop="name"]');
            }else{
                $selector = array('path'=>'[data-page-resource-type="artist"]','regex'=>null,'attr'=>'data-page-resource-name');
            }

            $artist = $tracklist->parse_node($tracklist->body_node,$selector);
            
        }
 
        return $artist;
    }


}

abstract class WP_SoundSystem_LastFM_Station extends WP_SoundSystem_LastFM_URL{

    function __construct($tracklist){
        parent::__construct($tracklist);
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){

            $options['selectors'] = array(
                'tracks'            => array('path'=>'>playlist'),
                'track_artist'      => array('path'=>'artists > name'),
                'track_title'       => array('path'=>'playlist > name'),
                'track_source_urls' => array('path'=>'playlinks url'),
            );
            
        }

        return $options;
    }
    

}

class WP_SoundSystem_LastFM_User_Stations extends WP_SoundSystem_LastFM_Station{
    private $user_slug;
    private $page_slug;

    function __construct($tracklist){
        parent::__construct($tracklist);
        $this->user_slug = $this->get_user_slug();
        $this->page_slug = $this->get_station_page();

        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter('wpsstm_live_tracklist_title',array($this,'get_remote_title') );

    }
    
    function can_handle_url(){
        if ( !$this->user_slug ) return;
        if ( !$this->page_slug ) return;
        return true;
    }

    function get_user_slug(){
        $pattern = '~^lastfm:user:([^:]+):station~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_station_page(){
        $pattern = '~^lastfm:user:[^:]+:station:([^:]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_url($url){
        
        if ( $this->can_handle_url() ){
            $url = sprintf('https://www.last.fm/player/station/user/%s/%s?ajax=1',$this->user_slug,$this->page_slug );
        }
        return $url;
    }
    
    function get_remote_title($title){
        if ( $this->can_handle_url() ){
            $title = sprintf( __('Last.fm station for %s - %s','wpsstm'),$this->user_slug,$this->page_slug );
        }
        return $title;
    }
}

class WP_SoundSystem_LastFM_Artist_Stations extends WP_SoundSystem_LastFM_Station{
    private $artist_slug;
    private $page_slug;
    
    function __construct($tracklist){
        parent::__construct($tracklist);
        $this->artist_slug = $this->get_artist_slug();
        $this->page_slug = $this->get_artist_page();
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title') );
    }
    
    function can_handle_url(){
        if ( !$this->artist_slug ) return;
        if ( !$this->page_slug == '+similar' ) return;
        return true;
    }

    function get_remote_url($url){
        if ( $this->can_handle_url() ){
            $url = sprintf('https://www.last.fm/player/station/music/%s?ajax=1',$this->artist_slug);
        }
        return $url;
    }

    function get_remote_title($title){
        if ( $this->can_handle_url() ){
            $title = sprintf( __('Last.fm stations (similar artist): %s','wpsstm'),$this->artist_slug );
        }
        return $title;
    }

}

//register presets
function register_lastfm_preset($tracklist){
    new WP_SoundSystem_LastFM_URL($tracklist);
    new WP_SoundSystem_LastFM_User_Stations($tracklist);
    new WP_SoundSystem_LastFM_Artist_Stations($tracklist);
}


function register_lastfm_service_links($links){
    $links[] = array(
        'slug'      => 'lastfm',
        'name'      => 'Last.fm',
        'url'       => 'https://www.last.fm/',
        'pages'     => array(
            array(
                'slug'          => 'stations',
                'name'          => __('stations','wpsstm'),
                'example'       => 'lastfm:user:USERNAME:station:STATION_TYPE',
            )
        )
    );

    return $links;
}

add_action('wpsstm_get_remote_tracks','register_lastfm_preset');
add_filter('wpsstm_wizard_services_links','register_lastfm_service_links');