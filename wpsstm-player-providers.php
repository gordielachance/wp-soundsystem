<?php

function wpsstm_get_available_providers(){
    return array(
        'WP_SoundSytem_Provider_Youtube'
    );
}

function wpsstm_get_provider($url){
    $list = wpsstm_get_available_providers();

    foreach ($list as $classname){
        if ( class_exists($classname) && ($provider = new $classname($url)) ) {
            return $provider;
        }
    }
}


class WP_SoundSytem_Provider{
    
    var $iframe_id = 'wpsstm-iframe';
    var $url;
    var $can_play = false;
    
    function __construct($url = null){
        if ($url){
            $this->url = $url;
            $this->can_play = true;
        }
        
        add_filter( 'oembed_result', array($this,'add_iframe_id'), 10, 3 );
        


    }
    
    function add_iframe_id( $html, $url, $args ) {
        $fix  = true;
        //$fix &= strpos( $html, 'vimeo.com' ) !== false; // Embed code from Vimeo
        //$fix &= strpos( $html, ' id=' ) === false; // No ID attribute supplied by Vimeo
        //$fix &= isset( $attr['player_id'] ); // Player ID supplied
        if ( $fix ) {
            $html = str_replace(
                '<iframe ',
                sprintf( '<iframe id="%s" ', esc_attr( $this->iframe_id ) ),
                $html
            );
        }

        return $html;
    }

    function get_player(){
        //oEmbed
        $embed_code = wp_oembed_get( $this->url );
        //require_once( ABSPATH . WPINC . '/class-oembed.php' );
        //$oembed = _wp_oembed_get_object();
        return $embed_code;
    }
}

class WP_SoundSytem_Provider_Youtube extends WP_SoundSytem_Provider{

    function __construct($url){
        parent::__construct($url);
        add_filter( 'oembed_result', array($this,'enable_js_api'), 9, 3 );
    }
    
    function enable_js_api($html, $url, $args) {
        if (strstr($html, 'youtube.com/embed/')) { //youtube
            $this->iframe_id = 'wpsstm-iframe-youtube';
            wp_register_script( 'youtube-player-api', 'http://www.youtube.com/player_api');
            wp_enqueue_script( 'wpsstm-provider-youtube', wpsstm()->plugin_url . '_inc/js/provider-youtube.js', array('jquery','youtube-player-api'),wpsstm()->version,true);
            $html = str_replace('?feature=oembed', '?feature=oembed&enablejsapi=1', $html);
        }elseif (strstr($html, 'mixcloud.com/widget/')) { //mixcloud
            $this->iframe_id = 'wpsstm-iframe-mixcloud';
            wp_register_script( 'mixcloud-widget-api', '//widget.mixcloud.com/media/js/widgetApi.js');
            wp_enqueue_script( 'wpsstm-provider-mixcloud', wpsstm()->plugin_url . '_inc/js/provider-mixcloud.js', array('jquery','mixcloud-widget-api'),wpsstm()->version,true);
        }elseif (strstr($html, 'soundcloud.com/player/')) { //soundcloud
            $this->iframe_id = 'wpsstm-iframe-soundcloud';
            wp_register_script( 'soundcloud-player-api', '//w.soundcloud.com/player/api.js');
            wp_enqueue_script( 'wpsstm-provider-soundcloud', wpsstm()->plugin_url . '_inc/js/provider-soundcloud.js', array('jquery','soundcloud-player-api'),wpsstm()->version,true);
        }
        return $html;
    }
 
}


