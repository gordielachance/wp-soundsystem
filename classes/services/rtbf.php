<?php
class WPSSTM_RTBF{
    function __construct(){
        add_filter('wpsstm_remote_presets',array($this,'register_rtbf_preset'));
    }
    //register preset
    function register_hypem_preset($presets){
        $presets[] = new WPSSTM_RTBF_Preset();
        return $presets;
    }
    
}
class WPSSTM_RTBF_Preset extends WPSSTM_Remote_Tracklist{
    
    var $station_slug;

    function __construct($url = null,$options = null) {
        
        $this->preset_options = array(
            'selectors' => array(
                'tracks'            => array('path'=>'li.radio-thread__entry'),
                'track_artist'      => array('path'=>'span[itemprop="byArtist"]'),
                'track_title'       => array('path'=>'span[itemprop="name"]'),
                'track_image'       => array('path'=>'img[itemprop="inAlbum"]','attr'=>'data-src')
            )
        );
        
        parent::__construct($url,$options);
    }
    
    public function init_url($url){
        $this->station_slug = $this->get_station_slug($url);
        return $this->station_slug;
    }

    function get_remote_request_url(){
        return sprintf('https://www.rtbf.be/%s/conducteur',$this->station_slug);
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

add_action('wpsstm_load_services','wpsstm_reddit_init');