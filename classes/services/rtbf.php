<?php
class WPSSTM_RTBF{
    function __construct(){
        add_action('wpsstm_before_remote_response',array($this,'register_rtbf_preset'));
    }
    //register preset
    function register_rtbf_preset($tracklist){
        new WPSSTM_RTBF_Preset($tracklist);
    }
    
}
class WPSSTM_RTBF_Preset{

    function __construct($remote){
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
    }
    
    function can_handle_url($url){
        $station_slug = $this->get_station_slug($url);
        if (!$station_slug ) return;
        return true;
    }

    function get_remote_url($url){
        if ( $this->can_handle_url($url) ){
            $station_slug = $this->get_station_slug($url);
            $url = sprintf('https://www.rtbf.be/%s/conducteur',$station_slug);
        }
        return $url;
    }
    
    function set_selectors($remote){
        
        if ( !$this->can_handle_url($remote->feed_url_no_filters) ) return;
        $remote->options['selectors'] = array(
            'tracks'            => array('path'=>'li.radio-thread__entry'),
            'track_artist'      => array('path'=>'span[itemprop="byArtist"]'),
            'track_title'       => array('path'=>'span[itemprop="name"]'),
            'track_image'       => array('path'=>'img[itemprop="inAlbum"]','attr'=>'data-src')
        );
    }

    
    function get_station_slug($url){
        $pattern = '~^https?://(?:www.)?rtbf.be/(?!lapremiere)([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
}

function wpsstm_rtbf_init(){
    new WPSSTM_RTBF();
}

add_action('wpsstm_init','wpsstm_reddit_init');