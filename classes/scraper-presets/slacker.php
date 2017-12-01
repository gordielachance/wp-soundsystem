<?php
class WP_SoundSystem_Slacker_Stations{
    var $preset_slug =      'slacker-station';
    var $preset_url =       'http://slacker.com/';

    private $station_slug;

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->station_slug = $this->get_station_slug();
        
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
    }
    
    function can_handle_url(){
        if (!$this->station_slug ) return;
        return true;
    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
                'tracks'            => array('path'=>'ol.playlistList li.row:not(.heading)'),
                'track_artist'      => array('path'=>'span.artist'),
                'track_title'       => array('path'=>'span.title')
            );
        }
        return $options;
    }

    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?slacker.com/station/([^/]+)/?~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }


}

//register preset
function register_slacker_preset($tracklist){
    new WP_SoundSystem_Slacker_Stations($tracklist);
}

//TO FIX TO REPAIR add_action('wpsstm_get_remote_tracks','register_slacker_preset');