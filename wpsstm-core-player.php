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
    }
    
    function setup_actions(){
        add_action( 'wp_enqueue_scripts', array($this,'enqueue_player_scripts_styles'));
        add_action( 'wp_footer', array($this,'player_html'));

    }
    
    /**
    Adds the provider to the list of providers and check if the class exists.
    **/
    
    function register_provider($class){
        if ( !class_exists($class) ) return;
        $this->providers[] = $class;
    }
    
    function player_html(){
       ?>
        <div id="wpsstm-bottom-player"></div>
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
        
        //soundcloud renderer
        wp_register_script('wp-mediaelement-renderer-soundcloud','https://cdnjs.cloudflare.com/ajax/libs/mediaelement/4.0.6/renderers/soundcloud.min.js', array('wp-mediaelement'), '4.0.6');
        
        wp_enqueue_script( 'wpsstm-player', wpsstm()->plugin_url . '_inc/js/wpsstm-player.js', array('jquery','wp-mediaelement','mediaelement-plugin-source-chooser','wp-mediaelement-renderer-soundcloud'),wpsstm()->version);
        
        
    }
}

function wpsstm_player() {
	return WP_SoundSytem_Core_Player::instance();
}

wpsstm_player();
