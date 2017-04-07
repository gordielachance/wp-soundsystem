<?php

abstract class WP_SoundSytem_Provider{
    
    var $provider_slug;
    var $oembed_html;
    
    /*
    Can this provider work with the input widget html ?
    */
    public abstract static function can_handle_oembed($html);

    function __construct($oembed_html){
        $this->oembed_html = $oembed_html;
        add_filter( 'wpsstm_player_oembed_html', array($this,'add_iframe_id') );
    }

    function get_updated_oembed_html(){
        //load widget scripts & styles
        $this->provider_scripts_styles();
        
        //allow providers to filter widget html
        $this->oembed_html = apply_filters('wpsstm_player_oembed_html',$this->oembed_html);
        
        return $this->oembed_html;
    }
    
    function add_iframe_id( $html ) {
        $fix  = true;
        //$fix &= strpos( $html, 'vimeo.com' ) !== false; // Embed code from Vimeo
        //$fix &= strpos( $html, ' id=' ) === false; // No ID attribute supplied by Vimeo
        //$fix &= isset( $attr['player_id'] ); // Player ID supplied
        if ( $fix ) {
            $html = str_replace(
                '<iframe ',
                sprintf( '<iframe id="%s" ', esc_attr( 'wpsstm-iframe-' . $this->provider_slug ) ),
                $html
            );
        }

        return $html;
    }


    
    /*
    Scripts/Styles to load
    */
    public abstract function provider_scripts_styles();

}

class WP_SoundSytem_Provider_Youtube extends WP_SoundSytem_Provider{
    
    var $provider_slug = 'youtube';
    
    function __construct($oembed_html){
        parent::__construct($oembed_html);
        add_filter( 'wpsstm_player_oembed_html', array($this,'youtube_filter_oembed_html'), 10, 3 );
    }
    
    static function can_handle_oembed($html){
        if ( strstr($html, 'youtube.com/embed/') ) return true;
        return false;
    }

    function youtube_filter_oembed_html($html, $url, $args) {
        $html = str_replace('?feature=oembed', '?feature=oembed&enablejsapi=1', $html);
        return $html;
    }
    
    function provider_scripts_styles(){
        wp_register_script( 'youtube-player-api', 'http://www.youtube.com/player_api');
        wp_enqueue_script( 'wpsstm-provider-youtube', wpsstm()->plugin_url . '_inc/js/provider-youtube.js', array('jquery','youtube-player-api'),wpsstm()->version,true);
        
    }

}

class WP_SoundSytem_Provider_Mixcloud extends WP_SoundSytem_Provider{
    
    var $provider_slug = 'mixcloud';
    
    static function can_handle_oembed($html){
        if ( strstr($html, 'mixcloud.com/widget/') ) return true;
        return false;
    }
    
    function provider_scripts_styles(){
        wp_register_script( 'mixcloud-widget-api', '//widget.mixcloud.com/media/js/widgetApi.js');
        wp_enqueue_script( 'wpsstm-provider-mixcloud', wpsstm()->plugin_url . '_inc/js/provider-mixcloud.js', array('jquery','mixcloud-widget-api'),wpsstm()->version,true);
    }
}
class WP_SoundSytem_Provider_Soundcloud extends WP_SoundSytem_Provider{
    
    var $provider_slug = 'soundcloud';
    
    static function can_handle_oembed($html){
        if ( strstr($html, 'soundcloud.com/player/') ) return true;
        return false;
    }
    
    function provider_scripts_styles(){
        wp_register_script( 'mixcloud-widget-api', '//widget.mixcloud.com/media/js/widgetApi.js');
        wp_enqueue_script( 'wpsstm-provider-mixcloud', wpsstm()->plugin_url . '_inc/js/provider-mixcloud.js', array('jquery','mixcloud-widget-api'),wpsstm()->version,true);
    }
}

wpsstm_player()->register_provider('WP_SoundSytem_Provider_Youtube');
wpsstm_player()->register_provider('WP_SoundSytem_Provider_Mixcloud');
wpsstm_player()->register_provider('WP_SoundSytem_Provider_Soundcloud');


