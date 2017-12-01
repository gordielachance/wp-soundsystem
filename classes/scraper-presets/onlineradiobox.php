<?php
class WP_SoundSystem_OnlineRadioBox_Scraper{
    private $station_slug;

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->station_slug = $this->get_station_slug();
        
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
        
    }
    
    function can_handle_url(){
        if ( !$this->station_slug ) return;
        return true;
    }
    
    function get_remote_url($url){
        if ( $this->can_handle_url() ){
            $url = sprintf('http://onlineradiobox.com/gr/%s/playlist',$this->station_slug);
        }
        return $url;
    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
                'tracks'            => array('path'=>'.tablelist-schedule tr'),
                'track_artist'      => array('path'=>'a','regex'=>'(.+?)(?= - )'),
                'track_title'       => array('path'=>'a','regex'=>' - (.*)'),
            );
        }
        return $options;
    }

    function get_station_slug(){
        global $wpsstm_tracklist;
        $pattern = '~^https?://(?:www.)?onlineradiobox.com/[^/]+/([^/]+)/~i';
        preg_match($pattern, $wpsstm_tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

}

//register preset
function register_onlineradiobox_preset($tracklist){
    new WP_SoundSystem_OnlineRadioBox_Scraper($tracklist);
}
function register_onlineradiobox_service_link($links){
    $links[] = array(
        'slug'      => 'onlineradiobox',
        'name'      => 'Online Radio Box',
        'url'       => 'http://onlineradiobox.com/',
        'pages'     => array(
            array(
                'slug'      => 'playlists',
                'name'      => __('playlists','wpsstm'),
                'example'   => 'http://onlineradiobox.com/COUNTRY/RADIO_SLUG',
            ),
        )
    );
    return $links;
}
add_filter('wpsstm_wizard_services_links','register_onlineradiobox_service_link');
add_action('wpsstm_get_remote_tracks','register_onlineradiobox_preset');