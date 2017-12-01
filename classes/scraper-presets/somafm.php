<?php
class WP_SoundSystem_SomaFM_Stations{
    var $preset_slug =      'somafm-station';
    var $preset_url =       'http://somafm.com/';

    private $station_slug;

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->station_slug = $this->get_station_slug();
        
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
        
        add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title') );
 
    }
    
    function can_handle_url(){
        if (!$this->station_slug ) return;
        return true;
    }

    function get_remote_url($url){
        if ( $this->can_handle_url() ){
            $url = sprintf('http://somafm.com/songs/%s.xml',$this->station_slug );
        }
        return $url;
    }  
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
                'tracks'            => array('path'=>'song'),
                'track_artist'      => array('path'=>'artist'),
                'track_title'       => array('path'=>'title'),
                'track_album'       => array('path'=>'album'),
            );  
        }
        return $options;
    }

    
    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?somafm.com/([^/]+)/?$~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_title($title){
        if ( $this->can_handle_url() ){
            $title = sprintf( __('%s on SomaFM','wpsstm'),$this->station_slug );
        }
        return $title;
    }

}

//register preset
function register_somafm_preset($tracklist){
    new WP_SoundSystem_SomaFM_Stations($tracklist);
}

add_action('wpsstm_get_remote_tracks','register_somafm_preset');