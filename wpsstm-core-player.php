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

        //add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script_embeds' ), 11 );
        add_action( 'wp_enqueue_scripts', array($this,'enqueue_player_scripts_styles'));
        add_action( 'wp_footer', array($this,'show_player'));

    }
    
    /**
    Adds the provider to the list of providers and check if the class exists.
    **/
    
    function register_provider($class){
        if ( !class_exists($class) ) return;
        $this->providers[] = $class;
    }

    
    /*
    Get the music sources for a post and return its player.
    */
    
    function get_post_player_html($post_id = false){
        global $post;
        if (!$post_id) $post_id = $post->ID;
        
        $urls = wpsstm_get_post_player_sources($post_id);
        return $this->get_player_html($urls);
    }
    
    /*
    Choose a player according to its sources
    https://github.com/angelmunozs/officeplayer
    */
    
    function get_player_html($urls){

        $providers = array();
        $tabs = array();
        $widgets = array();
        
        foreach ($urls as $url){

            foreach ($this->providers as $possible_provider){
                $provider = new $possible_provider($url);
                if ( $provider->can_load_url() ){
                    $providers[] = $provider;
                }
            }
            
        }

        ob_start();

        ?>
        <?php
        $tabs_html = $widgets_html = null;

        foreach ((array)$providers as $provider){
            $icon = ($provider->icon) ? sprintf('<span class="wpsstm-player-widget-icon">%s</span>',$provider->icon) : null;
            $tab_text = $icon . $provider->name;
            $tabs_html.= sprintf('<li><a href="#wpsstm-player-widget-%s">%s</a></li>',$provider->slug,$tab_text);
            $widgets_html.= sprintf('<div id="wpsstm-player-widget-%s" class="wpsstm-player-widget">%s</div>',$provider->slug,$provider->get_widget());

        }

        if ($tabs_html){
             printf('<ul id="wpsstm-player-tabs">%s</ul>',$tabs_html);
        }

        if ($widgets_html){
             echo $widgets_html;
        }

        ?>
        <?php
        
        $output = ob_get_clean();
        return $output;
    }
    
    function show_player(){
        if ( !is_single() ) return;
        if ( !$player = $this->get_post_player_html() ) return;
        
        /*
        $file = 'playlist-xspf.php';
        if ( file_exists( wpsstm_locate_template( $file ) ) ){
            $template = wpsstm_locate_template( $file );
        }
        */
        
       ?>
        <div id="wpsstm-bottom-player">
            <div id="wpsstm-player-main">
                <p class="wpsstm-player-item-title">
                    <span class="wpsstm-player-control-toggleplay wpsstm-player-control-toggle wpsstm-player-control"><i class="fa fa-play" aria-hidden="true"></i><i class="fa fa-pause" aria-hidden="true"></i></span>
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
                    <span class="wpsstm-player-control-togglesound wpsstm-player-control-toggle wpsstm-player-control"><i class="fa fa-volume-up" aria-hidden="true"></i><i class="fa fa-volume-off" aria-hidden="true"></i></span>
                </p>

            </div>
            <div id="wpsstm-player-widgets">
                <?php print_r($player);?>
            </div>
            
            <?php

			// Previous/next post navigation.
			the_post_navigation( array(
				'next_text' => '<span class="meta-nav" aria-hidden="true">' . __( 'Next', 'twentyfifteen' ) . '</span> ' .
					'<span class="screen-reader-text">' . __( 'Next post:', 'twentyfifteen' ) . '</span> ' .
					'<span class="post-title">%title</span>',
				'prev_text' => '<span class="meta-nav" aria-hidden="true">' . __( 'Previous', 'twentyfifteen' ) . '</span> ' .
					'<span class="screen-reader-text">' . __( 'Previous post:', 'twentyfifteen' ) . '</span> ' .
					'<span class="post-title">%title</span>',
			) );
            ?>
        </div>
        <?php
    }
    
    function enqueue_player_scripts_styles(){
        global $wp_scripts;
        
        //css
        wp_enqueue_style( 'wpsstm-player',  wpsstm()->plugin_url . '_inc/css/wpsstm-player.css', null, wpsstm()->version );
        
        //js
        wp_enqueue_script( 'wpsstm-player', wpsstm()->plugin_url . '_inc/js/wpsstm-player.js', array('jquery','jquery-ui-tabs'),wpsstm()->version);
    }
        
    function enqueue_script_embeds(){
        global $wp_query;
        $datas = array();
        
        if (!$posts = $wp_query->posts) return;
        
        $allowed_post_types = array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_track,
            wpsstm()->post_type_album,
            wpsstm()->post_type_playlist
        );
        
        foreach ((array)$posts as $post){
            $post_type = $post->post_type;
            if (!in_array($post_type,$allowed_post_types)) continue;

            $postdata = array();
            
            //load datas
            $artist = wpsstm_get_post_artist($post->ID);
            $track = wpsstm_get_post_track($post->ID);
            $album = wpsstm_get_post_album($post->ID);

            switch( $post_type ){

                case wpsstm()->post_type_artist:
                    
                    if (!$artist) break;
                    
                    $postdata['artist'][] = $artist;
                break;
                    
                case wpsstm()->post_type_track:
                    
                    if (!$artist or !$track) break;
                    
                    $postdata['title'][] = array(
                        'artist'    => $artist,
                        'title'     => $track,
                        'album'     => ($album) ? $album : null
                    );
                    
                break;
                    
                case wpsstm()->post_type_album:
                    
                    if (!$artist or !$album) break;
                    
                    $postdata['album'][] = array(
                        'artist'    => $artist,
                        'album'     => $album
                    );
                    
                break;
                    

            }
            
            if ($postdata){
                $datas[$post->ID] = $postdata;
            }
            
        }
        
        if (!$datas) return;

        wp_localize_script( 'wpsstm-embeds', 'wpsstmEmbed', $datas );
        wp_enqueue_script( 'wpsstm-embeds', wpsstm()->plugin_url . '_inc/js/wpsstm_embeds.js', array('jquery'),wpsstm()->version);

        
    }

}

function wpsstm_player() {
	return WP_SoundSytem_Core_Player::instance();
}

wpsstm_player();
