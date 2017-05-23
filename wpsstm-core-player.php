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
	global $wp_query;
        ?>
        <div id="wpsstm-bottom">
            <?php
        
            $redirect_previous = $redirect_auto = wpsstm_get_player_redirection('previous');
            $redirect_next = wpsstm_get_player_redirection('next');
        
            //live playlist or frontend wizard
            if ( wpsstm()->get_options('live_playlists_enabled') == 'on' ){
                
                $post_id = get_the_ID();
                $post_type = get_post_type();
                
                $is_frontend_wizard = ( $post_id == wpsstm_live_playlists()->frontend_wizard_page_id );
                $is_live_playlist = ( $post_type == wpsstm()->post_type_live_playlist  );
                
                if ( $is_frontend_wizard || $is_live_playlist ){

                    $refresh_permalink = get_permalink();

                    if ( $is_frontend_wizard ){

                        if ( $feed_url = $wp_query->get(wpsstm_live_playlists()->qvar_frontend_wizard_url) ){
                            $refresh_permalink = add_query_arg(
                                array(
                                    wpsstm_live_playlists()->qvar_frontend_wizard_url => $feed_url
                                ),
                                $refresh_permalink
                            );
                        }
                    }
                    
                    $redirect_auto = array('title'=>get_the_title($post_id),'url'=>$refresh_permalink,'is_refresh'=>true);
                    
                }
                
                
            }
        
            //track action - WP auth notice
            if ( !get_current_user_id() ){
                $wp_auth_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
                $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(),__('here','wpsstm'));
                $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
                printf('<p id="wpsstm-bottom-notice-wp-auth" class="wpsstm-bottom-notice active">%s %s </p>',$wp_auth_icon,$wp_auth_text);
            }

            //Last.FM track action - API auth notice
            if ( wpsstm()->get_options('lastfm_scrobbling') || wpsstm()->get_options('lastfm_favorites') ){

                if ( !wpsstm_lastfm()->is_user_api_logged() ){
                    $lastfm_auth_icon = '<i class="fa fa-lastfm" aria-hidden="true"></i>';
                    $lastfm_auth_url = wpsstm_lastfm()->get_app_auth_url();
                    $lastfm_auth_link = sprintf('<a href="%s">%s</a>',$lastfm_auth_url,__('here','wpsstm'));
                    $lastfm_auth_text = sprintf(__('You need to authorize this website on Last.fm to enable its features: click %s.','wpsstm'),$lastfm_auth_link);
                    printf('<p id="wpsstm-bottom-notice-lastfm-auth" class="wpsstm-bottom-notice active">%s %s </p>',$lastfm_auth_icon,$lastfm_auth_text);
                }
            }

            //redirection notice
            if ( wpsstm()->get_options('autoredirect') && $redirect_auto ){
                //TO FIX : if the tracklists of the page have been read, we should move to another page
                /*
                global $wp;

                $current_url = home_url(add_query_arg(array(),$wp->request));
                $countdown = '<strong></strong>';
                $icon = '<i class="fa fa-refresh fa-fw fa-spin"></i>';
                $link = sprintf( '<a href="#">%s</a>',__('here','wpsstm') );

                //TO FIX not working (eg. for wizard)
                $is_refresh = ( trailingslashit($current_url) == trailingslashit($redirect_auto['url']) );
                
                $link = sprintf('<a id="wpsstm-bottom-notice-link" href="%s">%s</a>',$redirect_auto['url'],$redirect_auto['title']);
                
                if ( isset($redirect_auto['is_refresh']) ){
                    $text = sprintf(__("Refreshing %s... ",'wpsstm'),$link);
                }else{
                    
                    $link = sprintf('<a id="wpsstm-bottom-notice-link" href="%s">%s</a>',$redirect_auto['url'],$redirect_auto['title']);
                    $text = sprintf( __("On the next page : %s",'wpsstm'),$link );
                }
                
                $abord_link = sprintf( __("Click to abord.",'wpsstm'),$link );
                $text.= ' ' . $abord_link;
                
                printf('<p id="wpsstm-bottom-notice-redirection" class="wpsstm-bottom-notice active">%s %s %s</p>',$icon,$countdown,$text);
                */
            }
            ?>
            <div id="wpsstm-bottom-player">
                <div id="wpsstm-player-actions">
                    <?php 
                    //scrobbling
                    if ( wpsstm()->get_options('lastfm_scrobbling') ){
                        echo wpsstm_get_scrobbler_icons();
                    }
                    //favorites
                    if ( wpsstm()->get_options('lastfm_favorites') ){
                        ?>
                        <span class="wpsstm-love-unlove-track-links"><!--this will be filled with ajax when track is ready--></span>
                        <?php
                    }
                    ?>
                </div>
                <div id="wpsstm-player-trackinfo"></div>
                <div id="wpsstm-player-wrapper">
                    <div id="wpsstm-player-nav-previous-page" class="wpsstm-player-nav"><a title="<?php echo $redirect_previous['title'];?>" href="<?php echo $redirect_previous['url'];?>"><i class="fa fa-fast-backward" aria-hidden="true"></i></a></div>
                    <div id="wpsstm-player-nav-previous-track" class="wpsstm-player-nav"><a href="#"><i class="fa fa-backward" aria-hidden="true"></i></a></div>
                    <div id="wpsstm-player"></div>
                    <div id="wpsstm-player-nav-next-track" class="wpsstm-player-nav"><a href="#"><i class="fa fa-forward" aria-hidden="true"></i></a></div>
                    <div id="wpsstm-player-nav-next-page" class="wpsstm-player-nav"><a title="<?php echo $redirect_next['title'];?>" href="<?php echo $redirect_next['url'];?>"><i class="fa fa-fast-forward" aria-hidden="true"></i></a></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    function enqueue_player_scripts_styles(){
        
        //CSS
        wp_enqueue_style( 'wpsstm-player',  wpsstm()->plugin_url . '_inc/css/wpsstm-player.css', array('wp-mediaelement'), wpsstm()->version );
        
        //JS
        wp_register_script( 'wpsstm-tracklist', wpsstm()->plugin_url . '_inc/js/wpsstm-tracklist.js', array('jquery'),wpsstm()->version, true);
        wp_enqueue_script( 'wpsstm-player', wpsstm()->plugin_url . '_inc/js/wpsstm-player.js', array('jquery','wpsstm-tracklist','wp-mediaelement'),wpsstm()->version, true);
        
        //localize vars
        $localize_vars=array(
            'autoredirect'          => (int)wpsstm()->get_options('autoredirect'),
            'autoplay'              => ( wpsstm()->get_options('autoplay') == 'on' ),
            'autosource'            => ( wpsstm()->get_options('autosource') == 'on' ),
            'leave_page_text'       => __('A track is currently playing.  Are u sure you want to leave ?','wpsstm')
        );

        wp_localize_script('wpsstm-player','wpsstmPlayer', $localize_vars);
        
    }
    
    function get_playable_sources($track,$database_only = true){
        
        $sources = $track->sources;

        $provider_sources = array();
        
        if ($database_only){
            if ($cached_sources = wpsstm_sources()->get_track_sources_remote( $track,array('cache_only'=>true) ) ){
                $sources = array_merge((array)$sources,(array)$cached_sources);
            }
        }else{
            if ($remote_sources = wpsstm_sources()->get_track_sources_remote($track) ){
                $sources = array_merge((array)$sources,(array)$remote_sources);
            } 
        }

        //check if any provider can use the source
        foreach( (array)$sources as $source){

            foreach( (array)$this->providers as $provider ){

                if ( !$provider_source_type = $provider->get_source_type($source['url']) ) continue; //cannot handle source
                
                //if no source title set; set a default one

                if ( !isset($source['title']) || (!$title = trim($source['title']) ) ){
                    $title = sprintf(__('%s — %s','wpsstm'),$track->artist,$track->title);
                    $title = 'null'; //TO FIX
                }
                
                $provider_source = array(
                    'type'      => $provider_source_type,
                    'title'     => $title,
                    'provider'  => $provider->slug,
                    'icon'      => $provider->icon,
                    'src'       => $provider->format_source_url($source['url']),
                );

                $provider_sources[] = $provider_source;
                
            }

        }

        return $provider_sources;
    }

    function get_track_button(){
        //https://wordpress.stackexchange.com/a/162945/70449
        $link = '<a class="wpsstm-play-track" href="#"><i class="wpsstm-player-icon wpsstm-player-icon-error fa fa-exclamation-triangle" aria-hidden="true"></i><i class="wpsstm-player-icon wpsstm-player-icon-pause fa fa-pause" aria-hidden="true"></i><i class="wpsstm-player-icon wpsstm-player-icon-buffering fa fa-circle-o-notch fa-spin fa-fw"></i><i class="wpsstm-player-icon wpsstm-player-icon-play fa fa-play" aria-hidden="true"></i></a>';

        return $link;

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
    This is the function that checks if the provider can handle the source.
    The mime types or pseudo-mime types should match http://www.mediaelementjs.com/.
    */
    
    abstract public function get_source_type($url);
    
    function format_source_icon(){
        if ( !$prefix = $this->icon ){
            $prefix = sprintf('[%s]',$this->name);
        }
        return $prefix;
    }
    
    function format_source_title($title){
        return $title;
    }
    
    function format_source_url($url){
        return $url;
    }

    /*
    Search sources from this provider
    */
    
    function sources_lookup($track,$args=null){
        
    }
    
}

class WP_SoundSytem_Player_Provider_Native extends WP_SoundSytem_Player_Provider{
    
    var $name = 'Wordpress';
    var $slug = 'wp';
    var $icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
    
    function get_source_type($url){
        
        //check file is supported
        $filetype = wp_check_filetype($url);
        if ( !$ext = $filetype['ext'] ) return;
        
        $audio_extensions = wp_get_audio_extensions();
        if ( !in_array($ext,$audio_extensions) ) return;
        
        return sprintf('audio/%s',$ext);//$filetype['type'],
        
    }

}

class WP_SoundSytem_Player_Provider_Youtube extends WP_SoundSytem_Player_Provider{
    
    var $name = 'Youtube';
    var $slug = 'youtube';
    var $icon = '<i class="fa fa-youtube" aria-hidden="true"></i>';
    
    function get_source_type($url){

        //youtube
        $pattern = '~(?:youtube.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu.be/)([^"&?/ ]{11})~i';
        preg_match($pattern, $url, $url_matches);

        if (!$url_matches) return;
        
        return 'video/youtube';
    }

    function sources_lookup($track,$args=null){
        return wpsstm_get_soundsgood_sources($track,'youtube',$args);
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
    
    function get_source_type($url){
        //soundcloud
        $pattern = '~https?://(?:api\.)?soundcloud\.com/.*~i';
        preg_match($pattern, $url, $url_matches);

        if (!$url_matches) return;
        
        return 'video/soundcloud';
    }

    function format_source_url($url){
        $url = sprintf('https://w.soundcloud.com/player/?url=%s',$url);
        $url = add_query_arg(array('auto_play'=>false),$url);
        return $url;
    }
    
    function sources_lookup($track,$args=null){
        return wpsstm_get_soundsgood_sources($track,'soundcloud',$args);
    }
    
    
}

class WP_SoundSytem_Player_Provider_Mixcloud extends WP_SoundSytem_Player_Provider{
    
    var $name = 'Mixcloud';
    var $slug = 'mixcloud';
    var $icon = '<i class="fa fa-mixcloud" aria-hidden="true"></i>';
    
    function get_source_type($url){
        //mixcloud
        $pattern = '~https?://(?:www\.)?mixcloud\.com/\S*~i';
        preg_match($pattern, $url, $url_matches);

        if (!$url_matches) return;
        
        return 'audio/mixcloud';
    }

}

function wpsstm_player() {
	return WP_SoundSytem_Core_Player::instance();
}

wpsstm_player();

