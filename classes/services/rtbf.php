<?php
class WPSSTM_RTBF{
    function __construct(){
        add_action('wpsstm_live_tracklist_populated',array($this,'register_rtbf_preset'));
    }
    //register preset
    function register_rtbf_preset($tracklist){
        new WPSSTM_RTBF_Preset($tracklist);
    }
    
}
class WPSSTM_RTBF_Preset{

    private $station_slug;

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->station_slug = $this->get_station_slug();
        
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
    }
    
    function can_handle_url(){
        if (!$this->station_slug ) return;
        return true;
    }

    function get_remote_url($url){
        if ( $this->can_handle_url() ){
            $url = sprintf('https://www.rtbf.be/%s/conducteur',$this->station_slug);
        }
        return $url;
    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
                'tracks'            => array('path'=>'li.radio-thread__entry'),
                'track_artist'      => array('path'=>'span[itemprop="byArtist"]'),
                'track_title'       => array('path'=>'span[itemprop="name"]'),
                'track_image'       => array('path'=>'img[itemprop="inAlbum"]','attr'=>'data-src')
            );
        }
        return $options;
    }

    
    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?rtbf.be/(?!lapremiere)([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
}

function wpsstm_rtbf_init(){
    new WPSSTM_RTBF();
}

add_action('wpsstm_init','wpsstm_reddit_init');