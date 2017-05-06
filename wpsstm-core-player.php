<?php


class WP_SoundSytem_Core_Player{
    
    /**
    * @var The one true Instance
    */
    private static $instance;
    
    var $providers = array();

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSytem_Core_Player;
                    self::$instance->init();
            }
            return self::$instance;
    }
    
    private function __construct() { /* Do nothing here */ }
    
    function init(){
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
    }
    
    function setup_globals(){
        $providers = $this->register_providers();
        $this->providers = apply_filters( 'wpsstm_player_providers',$providers );
    }
    
    function setup_actions(){
        add_action( 'wp_enqueue_scripts', array($this,'enqueue_player_scripts_styles'));
        add_action( 'wp_footer', array($this,'player_html'));

    }
    
    function register_providers(){
        
        $providers = array();
        
        $slugs = array(
            'WP_SoundSytem_Player_Provider_Native',
            'WP_SoundSytem_Player_Provider_Youtube',
            'WP_SoundSytem_Player_Provider_Soundcloud',
            //'WP_SoundSytem_Player_Provider_Mixcloud'
        );
        //$slugs = null;
        
        foreach((array)$slugs as $classname){
            if ( !class_exists($classname) ) continue;
            $providers[] = new $classname();
        }
        
        return $providers;
    }

    function player_html(){
       ?>
        <div id="wpsstm-bottom">
            <p id="wpsstm-bottom-refresh-notice" class="active">
                <strong></strong>
                <?php
                $icon = '<i class="fa fa-refresh fa-spin fa-fw"></i>';
                $link = sprintf( '<a href="#">%s</a>',__('here','wpsstm') );
                echo $icon  . ' ' . __("Redirecting to next tracklist... Click to abord.",'wpsstm');
                ?>
            </p>
            <div id="wpsstm-bottom-player"></div>
        </div>
        <?php
    }
    
    function enqueue_player_scripts_styles(){
        
        //CSS
        
        //sourcechooser plugin
        wp_register_style('mediaelement-plugin-source-chooser','https://cdnjs.cloudflare.com/ajax/libs/mediaelement-plugins/2.1.1/source-chooser/source-chooser.min.css',array('wp-mediaelement'));

        wp_enqueue_style( 'wpsstm-player',  wpsstm()->plugin_url . '_inc/css/wpsstm-player.css', array('wp-mediaelement','mediaelement-plugin-source-chooser'), wpsstm()->version );
        
        //JS
        
        //sourcechooser plugin
        wp_register_script('mediaelement-plugin-source-chooser','https://cdnjs.cloudflare.com/ajax/libs/mediaelement-plugins/2.1.1/source-chooser/source-chooser.js',array('wp-mediaelement'), '2.1.1');

        wp_enqueue_script( 'wpsstm-player', wpsstm()->plugin_url . '_inc/js/wpsstm-player.js', array('jquery','wp-mediaelement','mediaelement-plugin-source-chooser'),wpsstm()->version);
    }

    function get_track_button($track){

        if ( !$sources = $track->source_urls ) return;

        $provider_slugs = wpsstm_player()->providers;

        $sources_attr_arr = array();
        $sources_el_arr = array();

        foreach( $sources as $key => $url){
            
            foreach( (array)$this->providers as $provider ){

                if ( !$source = $provider->get_source($url) ) continue; //cannot play source

                $sources_el_arr[] = sprintf('<source src="%s" type="%s" />',esc_url($source['src']),$source['type']);
                
                $sources_attr_arr[] = array(
                    'type'  => $source['type'],
                    'src'   => esc_url($source['src'])
                );

            }

        }
        
        //TO FIX TO REMOVE ? test with a single player by track
        
        /*
        if ( $sources_el_arr ) {

            $player_classes = array('wpsstm-track-player');
            $player_id = 'wpsstm-track-player-';
            $player_sources_str = implode("\n",$sources_el_arr);

            $player = sprintf('<audio id="%s" %s>%s</audio>',$player_id,wpsstm_get_classes_attr($player_classes),$player_sources_str);

            return $player;
        }
        */

        if ( $sources_attr_arr ) {

            $data_attr_str = filter_var( json_encode($sources_attr_arr), FILTER_SANITIZE_SPECIAL_CHARS ); //https://wordpress.stackexchange.com/a/162945/70449
            $link = sprintf('<a class="wpsstm-play-track" data-wpsstm-sources="%s" href="#"><i class="wpsstm-player-icon wpsstm-player-icon-error fa fa-exclamation-triangle" aria-hidden="true"></i><i class="wpsstm-player-icon wpsstm-player-icon-pause fa fa-pause" aria-hidden="true"></i><i class="wpsstm-player-icon wpsstm-player-icon-buffering fa fa-circle-o-notch fa-spin fa-fw"></i><i class="wpsstm-player-icon wpsstm-player-icon-play fa fa-play" aria-hidden="true"></i></a>',$data_attr_str);
            
            return $link;
        }

    }
}

abstract class WP_SoundSytem_Player_Provider{
    
    var $name;
    var $slug;
    var $icon;
    
    function __construct(){
        add_action( 'wp_enqueue_scripts',array($this,'provider_scripts_styles') );
    }
    
    /*
    Scripts/Styles to load
    */
    public function provider_scripts_styles(){
        /* override if any style or script is required to run this provider, eg. a MediaElement.js renderer */
    }

    /*
    Get the source based on an URL, 
    The mime types or pseudo-mime types should match http://www.mediaelementjs.com/.
    */
    
    abstract function get_source($url);
    
}

class WP_SoundSytem_Player_Provider_Native extends WP_SoundSytem_Player_Provider{
    
    var $name = 'Wordpress';
    var $slug = 'wp';
    var $icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
    
    function get_source($url){
        
        //check file is supported
        $filetype = wp_check_filetype($url);
        if ( !$ext = $filetype['ext'] ) return;
        
        $audio_extensions = wp_get_audio_extensions();
        if ( !in_array($ext,$audio_extensions) ) return;

        return array(
            'type'  => sprintf('audio/%s',$ext), //$filetype['type'],
            'src'   => $url
        );
        
    }

    
}

class WP_SoundSytem_Player_Provider_Youtube extends WP_SoundSytem_Player_Provider{
    
    var $name = 'Youtube';
    var $slug = 'youtube';
    var $icon = '<i class="fa fa-youtube" aria-hidden="true"></i>';
    
    function get_source($url){

        //youtube
        $pattern = '~(?:youtube.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu.be/)([^"&?/ ]{11})~i';
        preg_match($pattern, $url, $url_matches);

        if (!$url_matches) return;

        return array(
            'type'  => 'video/youtube',
            'src'   => $url
        );

    }

    
}

class WP_SoundSytem_Player_Provider_Soundcloud extends WP_SoundSytem_Player_Provider{
    
    var $name = 'Soundcloud';
    var $slug = 'soundcloud';
    var $icon = '<i class="fa fa-soundcloud" aria-hidden="true"></i>';
    
    function provider_scripts_styles(){
        //soundcloud renderer
        wp_enqueue_script('wp-mediaelement-renderer-soundcloud','https://cdnjs.cloudflare.com/ajax/libs/mediaelement/4.0.6/renderers/soundcloud.min.js', array('wp-mediaelement'), '4.0.6');
    }
    
    function get_source($url){

        //soundcloud
        $pattern = '~https?://(?:api\.)?soundcloud\.com/.*~i';
        preg_match($pattern, $url, $url_matches);

        if (!$url_matches) return;
        
        $url = sprintf('https://w.soundcloud.com/player/?url=%s',$url);
        $url = add_query_arg(array('auto_play'=>false),$url);
        
        return array(
            'type'  => 'video/soundcloud',
            'src'   => $url
        );
        
    }
    
    
}

class WP_SoundSytem_Player_Provider_Mixcloud extends WP_SoundSytem_Player_Provider{
    
    var $name = 'Mixcloud';
    var $slug = 'mixcloud';
    var $icon = '<i class="fa fa-mixcloud" aria-hidden="true"></i>';
    
    function get_source($url){

        //mixcloud
        $pattern = '~https?://(?:www\.)?mixcloud\.com/\S*~i';
        preg_match($pattern, $url, $url_matches);

        if ($url_matches){
            return 'audio/mixcloud';
        }

    }

}

function wpsstm_player() {
	return WP_SoundSytem_Core_Player::instance();
}

wpsstm_player();

