<?php

class WPSSTM_Player{
    
    var $options = array();

    function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_player_scripts_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_player_scripts_styles' ) );
        add_action( 'wp_footer', array($this,'bottom_player'));
        add_action( 'admin_footer', array($this,'bottom_player'));

    }
    
    function bottom_player(){
        $options=array('id'=>'wpsstm-bottom-player');
        echo $this->get_player_html($options);
    }

    function get_player_html( $options = array() ){
        global $wpsstm_player;
        $wpsstm_player = $this;
        
        $defaults = array(
        'id' => null,
        );
        
        $options = wp_parse_args($options,$defaults);
        
        wpsstm()->debug_log($options,'init player');
        
        $wpsstm_player->options = $options;
        
        ob_start();
        wpsstm_locate_template( 'player.php', true, false );
        $html = ob_get_clean();
        
        return $html;
    }
    
    function enqueue_player_scripts_styles(){
        //TO FIX load only if player is loaded (see hook wpsstm_load_player ) ?

        //CSS
        wp_enqueue_style('wp-mediaelement');

        //JS
        wp_enqueue_script( 'wpsstm-player', wpsstm()->plugin_url . '_inc/js/wpsstm-player.js', array('wp-mediaelement'),wpsstm()->version, true);
        
        //localize vars
        $localize_vars=array(
            'leave_page_text'       =>      __('A track is currently playing.  Are u sure you want to leave ?','wpsstm'),
            'plugin_path'           =>      trailingslashit( get_bloginfo('url') ) . WPINC . '/js/mediaelement/', //do not forget final slash here
        );

        wp_localize_script('wpsstm-player','wpsstmPlayer', $localize_vars);
        
    }

    function get_track_button(){
        //https://wordpress.stackexchange.com/a/162945/70449
        $link = '<a class="wpsstm-icon wpsstm-icon-link" href="#"><i class="wpsstm-player-icon wpsstm-player-icon-error fa fa-exclamation-triangle" aria-hidden="true"></i><i class="wpsstm-player-icon wpsstm-player-icon-pause fa fa-pause" aria-hidden="true"></i><i class="wpsstm-player-icon wpsstm-player-icon-play fa fa-play" aria-hidden="true"></i></a>';

        return $link;

    }
    
    function get_player_links(){
        $actions = array();
        
        $actions['queue'] = array(
            'text' =>       __('Player queue', 'wpsstm'),
            'href' =>       '#',
        );
        
        return apply_filters('wpsstm_get_player_actions',$actions);
    }
    
    function get_audio_attr($values_attr=null){

        $values_defaults = array(
            'autoplay' =>   wpsstm()->get_options('autoplay'),
        );

        $values_attr = array_merge($values_defaults,(array)$values_attr);
        
        return wpsstm_get_html_attr($values_attr);
    }
    
}

function wpsstm_player_init(){
    if ( !wpsstm()->get_options('player_enabled') ) return;
    new WPSSTM_Player();
}

add_action('wpsstm_init','wpsstm_player_init');