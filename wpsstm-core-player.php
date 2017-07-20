<?php

class WP_SoundSystem_Core_Player{
    
    /**
    * @var The one true Instance
    */
    private static $instance;
    
    var $providers = array();

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSystem_Core_Player;
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
            'WP_SoundSystem_Player_Provider_Native',
            'WP_SoundSystem_Player_Provider_Youtube',
            'WP_SoundSystem_Player_Provider_Soundcloud',
            //'WP_SoundSystem_Player_Provider_Mixcloud'
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
        
        if ( !did_action('init_playable_tracklist') ) return;
        
        ?>
        <div id="wpsstm-bottom-wrapper">
            <?php

            //live playlist or frontend wizard
            if ( wpsstm()->get_options('live_playlists_enabled') == 'on' ){
                
                $post_id = get_the_ID();
                $post_type = get_post_type();
                
                $is_frontend_wizard = ( $post_id == wpsstm_wizard()->frontend_wizard_page_id );
                $is_live_playlist = ( $post_type == wpsstm()->post_type_live_playlist  );
                
                if ( $is_frontend_wizard || $is_live_playlist ){

                    $refresh_permalink = get_permalink();

                    if ( $is_frontend_wizard ){

                        if ( $feed_url = $wp_query->get(wpsstm_live_playlists()->qvar_wizard_url) ){
                            $refresh_permalink = add_query_arg(
                                array(
                                    wpsstm_live_playlists()->qvar_wizard_url => $feed_url
                                ),
                                $refresh_permalink
                            );
                        }
                    }

                }

            }

            ?>
            <div id="wpsstm-bottom">
                <div id="wpsstm-bottom-track-wrapper">
                    <div id="wpsstm-bottom-track-actions">
                        <?php 
                        //scrobbling
                        if ( wpsstm()->get_options('lastfm_scrobbling') ){
                            echo wpsstm_lastfm()->get_scrobbler_icons();
                        }
                        ?>
                    </div>
                    <div id="wpsstm-bottom-track-info"></div>
                </div>
                
                <div id="wpsstm-bottom-player-wrapper">
                    <div id="wpsstm-player-extra-previous-track" class="wpsstm-player-extra"><a href="#"><i class="fa fa-backward" aria-hidden="true"></i></a></div>
                    <div id="wpsstm-player"></div>
                    <div id="wpsstm-player-extra-next-track" class="wpsstm-player-extra"><a href="#"><i class="fa fa-forward" aria-hidden="true"></i></a></div>
                    <div id="wpsstm-player-loop" class="wpsstm-player-extra"><a title="<?php _e('Loop','wpsstm');?>" href="#"><i class="fa fa-refresh" aria-hidden="true"></i></a></div>
                    <div id="wpsstm-player-shuffle" class="wpsstm-player-extra"><a title="<?php _e('Random Wisdom','wpsstm');?>" href="#"><i class="fa fa-random" aria-hidden="true"></i></a></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    function enqueue_player_scripts_styles(){
        //TO FIX load only if player is loaded (see hook init_playable_tracklist ) ?
        
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
        $link = '<a class="wpsstm-play-track wpsstm-icon-link" href="#"><i class="wpsstm-player-icon wpsstm-player-icon-error fa fa-exclamation-triangle" aria-hidden="true"></i><i class="wpsstm-player-icon wpsstm-player-icon-pause fa fa-pause" aria-hidden="true"></i><i class="wpsstm-player-icon wpsstm-player-icon-play fa fa-play" aria-hidden="true"></i></a>';

        return $link;

    }
}

abstract class WP_SoundSystem_Player_Provider{
    
    var $name;
    var $slug;
    var $icon;
    
    function __construct(){
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
    
    function get_soundsgood_sources(WP_SoundSystem_Track $track,$platform,$args=null){

        $args_default = array(
            'cache_only'    => false,
            'limit'         => 3
        );

        $args = wp_parse_args((array)$args,$args_default);

        $sources = $cache = $saved = null;
        $transient_name = 'wpsstm_provider_source_' . $track->get_unique_id($platform); //TO FIX could be too long ?
        $cache = $sources = get_transient( $transient_name );
        $do_request = ( !$args['cache_only'] && ( false === $cache ) );

        if ( $do_request ) {

            $sources = array();

            $api_url = 'https://heartbeat.soundsgood.co/v1.0/search/sources';
            $api_args = array(
                'apiKey'                    => '0ecf356d31616a345686b9a42de8314891b87782031a2db5',
                'limit'                     => $args['limit'],
                'platforms'                 => $platform,
                'q'                         => urlencode($track->artist . ' ' . $track->title),
                'skipSavingInDatabase'      => true
            );
            $api_url = add_query_arg($api_args,$api_url);
            $response = wp_remote_get($api_url);
            $body = wp_remote_retrieve_body($response);

            if ( is_wp_error($body) ) return $body;
            $api_response = json_decode( $body, true );

            $items = wpsstm_get_array_value(array(0,'items'),$api_response);

            foreach( (array)$items as $item ){

                $url = wpsstm_get_array_value('permalink',$item);
                $title = wpsstm_get_array_value('initTitle',$item);

                $source = array('url'=>$url,'title'=>$title,'origin'=>'auto');
                $sources[] = $source;
            }

            $saved = set_transient($transient_name,$sources, wpsstm()->get_options('autosource_cache') );
        }

        wpsstm()->debug_log(json_encode(array('track'=>sprintf('%s - %s',$track->artist,$track->title),'platform'=>$platform,'args'=>$args,'saved'=>$saved,'sources_count'=>count($sources)),JSON_UNESCAPED_UNICODE),'WP_SoundSystem_Player_Provider::get_soundsgood_sources() request'); 

        return $sources;
    }
    
}

class WP_SoundSystem_Player_Provider_Native extends WP_SoundSystem_Player_Provider{
    
    var $name = 'Audio';
    var $slug = 'audio';
    var $icon = '<i class="fa fa-file-audio-o" aria-hidden="true"></i>';
    
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

class WP_SoundSystem_Player_Provider_Youtube extends WP_SoundSystem_Player_Provider{
    
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
        return 'video/youtube';
    }

    function sources_lookup($track,$args=null){
        return $this->get_soundsgood_sources($track,'youtube',$args);
    }

}

/*
The Soundcloud Provider reacts differently if we've got a soundcloud client ID or not : either it will stream a mp3, or load the soundcloud widget and use medialement's widget renderer.
*/


class WP_SoundSystem_Player_Provider_Soundcloud extends WP_SoundSystem_Player_Provider{
    
    var $name = 'Soundcloud';
    var $slug = 'soundcloud';
    var $icon = '<i class="fa fa-soundcloud" aria-hidden="true"></i>';
    var $client_id;
    
    function __construct(){
        
        $this->client_id = wpsstm()->get_options('soundcloud_client_id');
        
        parent::__construct();
        add_action( 'wp_enqueue_scripts',array($this,'provider_scripts_styles') );
    }
    
    /*
    Scripts/Styles to load
    */
    public function provider_scripts_styles(){
        if (!$this->client_id){ //soundcloud renderer (required for soundcloud widget)
            wp_enqueue_script('wp-mediaelement-renderer-soundcloud',includes_url('js/mediaelement/renderers/soundcloud.min.js'), array('wp-mediaelement'), '4.0.6');    
        }
    }

    private function get_sc_track_id($url){

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
    Store result in a transient to speed up page load.
    */
    
    private function request_sc_track_id($url){
        
        if ( !$this->client_id ) return;
        
        $transient_name = 'wpsstm_sc_track_id_' . md5($url);

        if ( false === ( $sc_id = get_transient($transient_name ) ) ) {
        
            $api_args = array(
                'url' =>        urlencode ($url),
                'client_id' =>  $this->client_id
            );

            $api_url = 'https://api.soundcloud.com/resolve.json';
            $api_url = add_query_arg($api_args,$api_url);

            $response = wp_remote_get( $api_url );
            $json = wp_remote_retrieve_body( $response );
            if ( is_wp_error($json) ) return;
            $data = json_decode($json,true);
            if ( isset($data['id']) ) {
                $sc_id = $data['id'];
                set_transient( $transient_name, $sc_id, 7 * DAY_IN_SECONDS );
            }
        }
        return $sc_id;
    }

    function format_source_src($url){

        if ( !$track_id = $this->get_sc_track_id($url) ) return;

        if ( $this->client_id ){ //stream url
            return sprintf('https://api.soundcloud.com/tracks/%s/stream?client_id=%s',$track_id,$this->client_id);
        }else{ //widget url
            $widget_url = 'https://w.soundcloud.com/player/';
            $track_url = sprintf('http://api.soundcloud.com/tracks/%s',$track_id);
            $widget_args = array(
                'url' =>        urlencode ($track_url),
                'autoplay' =>   false,
                'client_id' =>  $this->client_id
            );
            return add_query_arg($widget_args,$widget_url);
        }

    }
    
    function get_source_type($url){
        if ( $this->client_id ){ //for stream url
            return 'audio/mp3';
        }else{ //for widget url
            return 'video/soundcloud';
        }
    }

    function sources_lookup($track,$args=null){
        return $this->get_soundsgood_sources($track,'soundcloud',$args);
    }
    
    
}

class WP_SoundSystem_Player_Provider_Mixcloud extends WP_SoundSystem_Player_Provider{
    
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
	return WP_SoundSystem_Core_Player::instance();
}

wpsstm_player();

