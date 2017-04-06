<?php


class WP_SoundSytem_Core_Player{
    
    /**
    * @var The one true Instance
    */
    private static $instance;

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
        add_action( 'wp_footer', array($this,'display_player'));

    }
    
    function display_player(){
        if ( !is_single() ) return;
        $player = wpsstm_get_player();
       ?>
        <div id="wpsstm-player">
            <?php
                print_r($player);
        
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
