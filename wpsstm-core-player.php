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
        
            $redirect_previous = wpsstm_get_player_redirection('previous');
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

                }
                
                
            }
        
            //track action - WP auth notice
            if ( !get_current_user_id() ){
                $wp_auth_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
                $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(),__('here','wpsstm'));
                $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
                printf('<p id="wpsstm-bottom-notice-wp-auth" class="wpsstm-bottom-notice">%s %s </p>',$wp_auth_icon,$wp_auth_text);
            }

            //Last.FM track action - API auth notice
            if ( !wpsstm_lastfm()->is_user_api_logged() ){
                $lastfm_auth_icon = '<i class="fa fa-lastfm" aria-hidden="true"></i>';
                $lastfm_auth_url = wpsstm_lastfm()->get_app_auth_url();
                $lastfm_auth_link = sprintf('<a href="%s">%s</a>',$lastfm_auth_url,__('here','wpsstm'));
                $lastfm_auth_text = sprintf(__('You need to authorize this website on Last.fm to enable its features: click %s.','wpsstm'),$lastfm_auth_link);
                printf('<p id="wpsstm-bottom-notice-lastfm-auth" class="wpsstm-bottom-notice">%s %s </p>',$lastfm_auth_icon,$lastfm_auth_text);
            }

            ?>
            <div id="wpsstm-bottom-player">
                <div id="wpsstm-player-actions">
                    <?php 
                    //scrobbling
                    if ( wpsstm()->get_options('lastfm_scrobbling') ){
                        echo wpsstm_lastfm()->get_scrobbler_icons();
                    }
                    //favorites
                    if ( wpsstm()->get_options('lastfm_favorites') ){
                        echo $love_unlove = wpsstm_lastfm()->get_track_loveunlove_icons();
                    }
                    ?>
                </div>
                <div id="wpsstm-player-trackinfo"></div>
                <div id="wpsstm-player-wrapper">
                    <div id="wpsstm-player-extra-previous-page" class="wpsstm-player-extra"><a title="<?php echo $redirect_previous['title'];?>" href="<?php echo $redirect_previous['url'];?>"><i class="fa fa-fast-backward" aria-hidden="true"></i></a></div>
                    <div id="wpsstm-player-extra-previous-track" class="wpsstm-player-extra"><a href="#"><i class="fa fa-backward" aria-hidden="true"></i></a></div>
                    <div id="wpsstm-player"></div>
                    <div id="wpsstm-player-extra-next-track" class="wpsstm-player-extra"><a href="#"><i class="fa fa-forward" aria-hidden="true"></i></a></div>
                    <div id="wpsstm-player-extra-next-page" class="wpsstm-player-extra"><a title="<?php echo $redirect_next['title'];?>" href="<?php echo $redirect_next['url'];?>"><i class="fa fa-fast-forward" aria-hidden="true"></i></a></div>
                    <div id="wpsstm-player-loop" class="wpsstm-player-extra"><a title="<?php _e('Loop','wpsstm');?>" href="#"><i class="fa fa-refresh" aria-hidden="true"></i></a></div>
                    <div id="wpsstm-player-shuffle" class="wpsstm-player-extra"><a title="<?php _e('Random Wisdom','wpsstm');?>" href="#"><i class="fa fa-random" aria-hidden="true"></i></a></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    function enqueue_player_scripts_styles(){
        
        //CSS
        wp_enqueue_style( 'wpsstm-player',  wpsstm()->plugin_url . '_inc/css/wpsstm-player.css', array('wp-mediaelement'), wpsstm()->version );
        
        //JS
        wp_enqueue_script( 'wpsstm-player', wpsstm()->plugin_url . '_inc/js/wpsstm-player.js', array('jquery','wp-mediaelement'),wpsstm()->version, true);
        
        //localize vars
        $localize_vars=array(
            'autoplay'              => ( wpsstm()->get_options('autoplay') == 'on' ),
            'autosource'            => ( wpsstm()->get_options('autosource') == 'on' ),
            'leave_page_text'       => __('A track is currently playing.  Are u sure you want to leave ?','wpsstm'),
            'refreshing_text'       => __('Refreshing','wpsstm')
        );

        wp_localize_script('wpsstm-player','wpsstmPlayer', $localize_vars);
        
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
    Check if the provider can handle the source by returning a cleaned URL
    */
    
    abstract public function format_source_src($url);

    /*
    Returns the the mime type or pseudo-mime type for this source
    https://github.com/mediaelement/mediaelement/blob/master/docs/usage.md
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
    
    //get file URL extension
    function get_file_url_ext($url){
        $filetype = wp_check_filetype($url);
        if ( !$ext = $filetype['ext'] ) return;
        return $ext;
    }
    
    function format_source_src($url){
        
        if ( !$ext = $this->get_file_url_ext($url) ) return;
        
        //check file is supported
        $audio_extensions = wp_get_audio_extensions();
        if ( !in_array($ext,$audio_extensions) ) return;
        
        return $url;
    }
    
    function get_source_type($url){
        
        if ( !$ext = $this->get_file_url_ext($url) ) return;
        
        return sprintf('audio/%s',$ext);//$filetype['type'],
        
    }

}

class WP_SoundSytem_Player_Provider_Youtube extends WP_SoundSytem_Player_Provider{
    
    var $name = 'Youtube';
    var $slug = 'youtube';
    var $icon = '<i class="fa fa-youtube" aria-hidden="true"></i>';
    
    function get_youtube_id($url){
        //youtube
        $pattern = '~http(?:s?)://(?:www.)?youtu(?:be.com/watch\?v=|.be/)([\w\-\_]*)(&(amp;)?[\w\?=]*)?~i';
        preg_match($pattern, $url, $url_matches);
        
        if ( !isset($url_matches[1]) ) return;
        
        return $url_matches[1];
    }
    
    function format_source_src($url){
        if ( !$yt_id = $this->get_youtube_id($url) ) return;
        return sprintf('https://youtube.com/watch?v=%s',$yt_id);
    }
    
    function get_source_type($url){
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
    
    function get_sc_track_id($url){

        /*
        check for souncloud API track URL
        
        https://api.soundcloud.com/tracks/9017297
        */
        
        $pattern = '~https?://api.soundcloud.com/tracks/([^/]+)~';
        preg_match($pattern, $url, $url_matches);

        if ( isset($url_matches[1]) ){
            return $url_matches[1];
        }
        
        /*
        check for souncloud widget URL
        
        https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/282715465&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&visual=true
        */
        
        $pattern = '~https?://w.soundcloud.com/player/.*tracks/([^&]+)~';
        preg_match($pattern, $url, $url_matches);

        if ( isset($url_matches[1]) ){
            return $url_matches[1];
        }

        /*
        check for souncloud track URL
        
        https://soundcloud.com/phasescachees/jai-toujours-reve-detre-un-gangster-feat-hippocampe-fou
        */

        $pattern = '~https?://(?:www.)?soundcloud.com/([^/]+)/([^/]+)~';
        preg_match($pattern, $url, $url_matches);

        if ( isset($url_matches) ){
            return $this->request_sc_track_id($url);
        }

    }
    
    /*
    Get the ID of a Soundcloud track URL (eg. https://soundcloud.com/phasescachees/jai-toujours-reve-detre-un-gangster-feat-hippocampe-fou)
    Requires a Soundcloud Client ID.
    */
    
    function request_sc_track_id($url){
        if ( !$soundcloud_client_id = wpsstm()->get_options('soundcloud_client_id') ) return;

        $api_url = 'https://api.soundcloud.com/resolve.json';
        $api_url = add_query_arg(array(
            'url' =>        urlencode ($url),
            'client_id' =>  $soundcloud_client_id
        ),$api_url);

        $response = wp_remote_get( $api_url );
        $json = wp_remote_retrieve_body( $response );
        if ( is_wp_error($json) ) return;
        $data = json_decode($json,true);
        if ( isset($data['id']) ) return $data['id'];
    }

    function format_source_src($url){
        if ( !$track_id = $this->get_sc_track_id($url) ) return;
        
        if ( !$client_id = wpsstm()->get_options('soundcloud_client_id') ) return;
        
        return sprintf('https://api.soundcloud.com/tracks/%s/stream?client_id=%s',$track_id,$client_id);
        
        /*
        TO FIX we sould be able to make it work without soundcloud_client_id, using that kind of URLS :
        https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/282715465&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&visual=true
        */
    }
    
    function get_source_type($url){

        //soundcloud
        $pattern = '~https?://(?:api\.)?soundcloud\.com/.*~i';
        preg_match($pattern, $url, $url_matches);

        if (!$url_matches) return;
        
        return 'video/soundcloud';
    }

    
    function sources_lookup($track,$args=null){
        return wpsstm_get_soundsgood_sources($track,'soundcloud',$args);
    }
    
    
}

class WP_SoundSytem_Player_Provider_Mixcloud extends WP_SoundSytem_Player_Provider{
    
    var $name = 'Mixcloud';
    var $slug = 'mixcloud';
    var $icon = '<i class="fa fa-mixcloud" aria-hidden="true"></i>';
    
    function format_source_src($url){
        //mixcloud
        $pattern = '~https?://(?:www\.)?mixcloud\.com/\S*~i';
        preg_match($pattern, $url, $url_matches);

        if (!$url_matches) return;
    }
    
    function get_source_type($url){
        return 'audio/mixcloud';
    }
}

function wpsstm_player() {
	return WP_SoundSytem_Core_Player::instance();
}

wpsstm_player();

