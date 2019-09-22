<?php

//TOUFIX SHOULD NOT EXIST ANYMORE
class WPSSTM_Player{
    
    var $options = array();

    function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_player_scripts_styles' ), 5 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_player_scripts_styles' ), 5 );

    }

    function enqueue_player_scripts_styles(){
        //TO FIX load only if player is loaded (see hook wpsstm_load_player ) ?

        //CSS
        wp_enqueue_style('wp-mediaelement');

        //JS
        wp_enqueue_script( 'wpsstm-player', wpsstm()->plugin_url . '_inc/js/wpsstm-player.js', array('wp-mediaelement','wpsstm-functions'),wpsstm()->version, true);
        
        //localize vars
        $localize_vars=array(
            'leave_page_text'       =>      __('A track is currently playing.  Are u sure you want to leave ?','wpsstm'),
            'plugin_path'           =>      trailingslashit( get_bloginfo('url') ) . WPINC . '/js/mediaelement/', //do not forget final slash here
        );

        wp_localize_script('wpsstm-player','wpsstmPlayer', $localize_vars);
        
    }

    
}

function wpsstm_player_init(){
    if ( !wpsstm()->get_options('player_enabled') ) return;
    new WPSSTM_Player();
}

add_action('wpsstm_init','wpsstm_player_init');