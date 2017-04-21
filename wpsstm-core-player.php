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
        require wpsstm()->plugin_dir . 'wpsstm-player-providers.php';
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
    
    /*
    Choose a player according to its sources
    https://github.com/angelmunozs/officeplayer
    */
    
    function get_player_providers_html(){

        $providers = array();
        $tabs = array();
        $widgets = array();

        ob_start();

        ?>
        <?php
        $tabs_html = $widgets_html = null;

        foreach ($this->providers as $possible_provider){
            $provider = new $possible_provider();
            $icon = ($provider->icon) ? sprintf('<span class="wpsstm-player-tab-icon">%s</span>',$provider->icon) : null;
            $tab_text = $icon . $provider->name;
            $tabs_html[]= sprintf('<li data-provider="%s"><a href="#wpsstm-player-%s">%s</a></li>',$provider->slug,$provider->slug,$tab_text);
            $widgets_html[]= sprintf('<div id="wpsstm-player-%s" class="wpsstm-player-widget">%s</div>',$provider->slug,$provider->get_widget());

        }

        if ($tabs_html){
             printf('<ul id="wpsstm-player-tabs">%s</ul>',implode("\n",$tabs_html) );
        }

        if ($widgets_html){
             echo implode("\n",$widgets_html);
        }
        
        $output = ob_get_clean();
        return $output;
    }
    
    function player_html(){
       ?>
        <div id="wpsstm-bottom-player">
            <div id="wpsstm-player-main">
                <p class="wpsstm-player-item-title">
                    <span class="wpsstm-player-control"><i class="wpsstm-player-icon wpsstm-player-icon-pause fa fa-pause" aria-hidden="true"></i><i class="wpsstm-player-icon wpsstm-player-icon-buffering fa fa-circle-o-notch fa-spin fa-fw"></i><i class="wpsstm-player-icon wpsstm-player-icon-play fa fa-play" aria-hidden="true"></i></span>
                    Toto
                </p>
                <p class="wpsstm-player-item-controls">
                    <span class="wpsstm-player-control-rewind wpsstm-player-control"><i class="fa fa-backward" aria-hidden="true"></i></span>
                    <span class="wpsstm-player-progress wpsstm-player-control">
                        <span class="wpsstm-player-progress-bar"></span>
                    </span>

                    <span class="wpsstm-player-control-rewind wpsstm-player-control"><i class="fa fa-forward" aria-hidden="true"></i></span>

                </p>
                <p class="wpsstm-player-controls">
                    <span class="wpsstm-player-control-togglesound wpsstm-player-control"><i class="fa fa-volume-up" aria-hidden="true"></i><i class="fa fa-volume-off" aria-hidden="true"></i></span>
                </p>

            </div>
            <div id="wpsstm-player-widgets">
                <?php
                echo $this->get_player_providers_html();
                ?>
            </div>
        </div>
        <?php
    }
    
    function enqueue_player_scripts_styles(){
        global $wp_scripts;
        
        //css
        wp_enqueue_style( 'wpsstm-player',  wpsstm()->plugin_url . '_inc/css/wpsstm-player.css', null, wpsstm()->version );
        
        //js
        wp_enqueue_script( 'wpsstm-player', wpsstm()->plugin_url . '_inc/js/wpsstm-player.js', array('jquery','jquery-ui-tabs'),wpsstm()->version);
        wp_enqueue_script( 'wpsstm-player-provider', wpsstm()->plugin_url . '_inc/js/wpsstm-player-provider.js', null,wpsstm()->version);
    }
}

function wpsstm_player() {
	return WP_SoundSytem_Core_Player::instance();
}

wpsstm_player();
