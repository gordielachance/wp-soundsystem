<?php
class WP_SoundSystem_Slacker_Stations{

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
function register_slacker_service_links($links){
    $links[] = array(
        'slug'      => 'slacker',
        'name'      => 'Slacker',
        'url'       => 'http://www.slacker.com',
        'pages'     => array(
            array(
                'slug'      => 'stations',
                'name'      => __('stations','wpsstm'),
                'example'   => 'http://www.slacker.com/station/STATION_SLUG',
            ),
        )
    );
    return $links;
}
//add_filter('wpsstm_wizard_services_links','register_slacker_service_links');
//TO FIX TO REPAIR add_action('wpsstm_get_remote_tracks','register_slacker_preset');