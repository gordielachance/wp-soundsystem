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
        require $this->plugin_dir . 'wpsstm-player-providers.php';
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
    }
    
    function setup_globals(){
    }
    
    function setup_actions(){

        //add_action('init', array($this,'register_oembed') );
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

    function get_player_html($url){
        
        //get WP oEmbed widget
        $widget_html = wp_oembed_get( $url );
        
        $provider = null;
        
        foreach ($this->providers as $possible_provider){
            if ( $possible_provider::can_handle_oembed($widget_html) ){
                $provider = new $possible_provider($widget_html);
                break;
            }
        }

        if (!$provider) return;
        
        return $provider->get_updated_oembed_html();
    }
    
    function get_provider_html($post_id = false){
        $provider = null;
        $sources = wpsstm_get_post_player_sources($post_id);

        foreach ((array)$sources as $source){
            if ($player = $this->get_player_html($source->link_url) ){
                return $player;
            }
        }    
    }
    
    function show_player(){
        if ( !is_single() ) return;
        if ( !$provider = $this->get_provider_html() ) return;
        
        /*
        $file = 'playlist-xspf.php';
        if ( file_exists( wpsstm_locate_template( $file ) ) ){
            $template = wpsstm_locate_template( $file );
        }
        */
        
       ?>
        <div id="wpsstm-player">
            <div id="wpsstm-player-provider">
                <?php print_r($provider);?>
            </div>
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
        wp_enqueue_style( 'wpsstm-player',  wpsstm()->plugin_url . '_inc/css/wpsstm-player.css',wpsstm()->version );
        wp_enqueue_script( 'wpsstm-player', wpsstm()->plugin_url . '_inc/js/wpsstm-player.js', array('jquery'),wpsstm()->version);
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

    function register_oembed(){ //TO FIX
        wp_oembed_add_provider( '#http://(www\.)?youtube\.com/watch.*#i', 'http://www.youtube.com/oembed', true );
    }

    
}

function wpsstm_player() {
	return WP_SoundSytem_Core_Player::instance();
}

wpsstm_player();
