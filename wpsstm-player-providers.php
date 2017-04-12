<?php

abstract class WP_SoundSytem_Provider{
    
    var $name;
    var $slug;
    var $pattern;
    var $url;
    
    function __construct($url){
        $this->url = $url;
    }

    function can_load_url(){
        if (!$this->pattern) return false;
        preg_match($this->pattern, $this->url, $url_matches);
        if (!$url_matches) return false;
        
        return true;
        
    }


    function get_widget(){
        //load widget scripts & styles
        $this->provider_scripts_styles();
        
        return sprintf('<iframe id="wpsstm-player-iframe-%s" type="text/html" src="%s" frameborder="0"></iframe>',$this->slug,$this->get_iframe_url());

        return $this->oembed_html;
    }
    
    /*
    Scripts/Styles to load
    */
    public abstract function provider_scripts_styles();
    
    /*
    Scripts/Styles to load
    */
    public abstract function get_iframe_url();

}

class WP_SoundSytem_Provider_Youtube extends WP_SoundSytem_Provider{
    
    var $name = 'Youtube';
    var $slug = 'youtube';
    var $pattern = '~(?:youtube.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu.be/)([^"&?/ ]{11})~i';
    var $icon = '<i class="fa fa-youtube" aria-hidden="true"></i>';

    function provider_scripts_styles(){
        wp_register_script( 'youtube-player-api', 'http://www.youtube.com/player_api');
        wp_enqueue_script( 'wpsstm-provider-youtube', wpsstm()->plugin_url . '_inc/js/provider-youtube.js', array('jquery','youtube-player-api'),wpsstm()->version,true);
        
    }
    
    function get_iframe_url(){
        $url = 'https://www.youtube.com/embed/BLya0SiphU8';
        $iframe_args = array(
            'rel'           => 0,
            'showinfo'      => 0,
            'enablejsapi'   => 1
        );
        $url = add_query_arg($iframe_args,$url);
        return $url;
    }

}

class WP_SoundSytem_Provider_Mixcloud extends WP_SoundSytem_Provider{
    
    var $name = 'Mixcloud';
    var $slug = 'mixcloud';
    var $pattern = '~https?://(?:www\.)?mixcloud\.com/\S*~i';
    var $icon = '<i class="fa fa-mixcloud" aria-hidden="true"></i>';

    function provider_scripts_styles(){
        wp_register_script( 'mixcloud-widget-api', '//widget.mixcloud.com/media/js/widgetApi.js');
        wp_enqueue_script( 'wpsstm-provider-mixcloud', wpsstm()->plugin_url . '_inc/js/provider-mixcloud.js', array('jquery','mixcloud-widget-api'),wpsstm()->version,true);
    }
    
    function get_iframe_url(){
        $url = 'https://www.mixcloud.com/widget/iframe/';
        $iframe_args = array(
            'embed_type'        => 'widget_standard',
            //'embed_uuid'        => '37b4ad1a-39be-4f76-a3a7-89eb741e8e2e',
            'feed'              => $this->url,
            'hide_artwork'      => 1,
            'hide_cover'        => 1,
            'hide_tracklist'    => 1,
            'light'             => 1,
            'mini'              => 1,
            'replace'           => 0,
        );
        $url = add_query_arg($iframe_args,$url);
        return $url;
    }
    
}
class WP_SoundSytem_Provider_Soundcloud extends WP_SoundSytem_Provider{
    
    var $name = 'Soundcloud';
    var $slug = 'soundcloud';
    var $pattern = '~https?://(?:api\.)?soundcloud\.com/.*~i';
    var $icon = '<i class="fa fa-soundcloud" aria-hidden="true"></i>';

    function provider_scripts_styles(){

    }
    
    function get_iframe_url(){
        $url = 'https://w.soundcloud.com/player/';
        $iframe_args = array(
            'url'       => $this->url,
            'color'     => 'ff5500',
            'inverse'   => false,
            'auto_play' => false,
            'show_user' => true
        );
        $url = add_query_arg($iframe_args,$url);
        return $url;
    }
}

//no spotify widget : there is no JS SDK available for the player

wpsstm_player()->register_provider('WP_SoundSytem_Provider_Youtube');
wpsstm_player()->register_provider('WP_SoundSytem_Provider_Mixcloud');
wpsstm_player()->register_provider('WP_SoundSytem_Provider_Soundcloud');
