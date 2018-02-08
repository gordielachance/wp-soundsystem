<?php

class WPSSTM_Core_Player{

    function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_player_scripts_styles_shared' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_player_scripts_styles_shared' ) );

        add_action( 'wp_footer', array($this,'player_html'));
        add_action( 'admin_footer', array($this,'player_html'));

    }

    public static function get_providers(){
        
        $providers = array();
        
        $slugs = array(
            'WPSSTM_Player_Provider_Native',
            'WPSSTM_Player_Provider_Youtube',
            'WPSSTM_Player_Provider_Soundcloud'
        );

        foreach((array)$slugs as $classname){
            if ( !class_exists($classname) ) continue;
            $providers[] = new $classname();
        }

        return apply_filters( 'wpsstm_player_providers',$providers );
    }

    function player_html(){
	   global $wp_query;
        
        if ( !did_action('init_playable_tracklist') ) return;
        
        ?>
        <div id="wpsstm-bottom-wrapper">
            <div id="wpsstm-bottom">
                <div id="wpsstm-bottom-track-wrapper">
                    <span id="wpsstm-bottom-track-info"></span>
                    <?php
                    //player actions
                    if ( $actions = $this->get_player_links() ){
                        $list = get_actions_list($actions,'player');
                        echo $list;
                    }                       
                    ?>
                </div>
                <table id="wpsstm-bottom-player-wrapper">
                    <tr>
                        <td id="wpsstm-player-extra-previous-track" class="wpsstm-player-extra"><a href="#"><i class="fa fa-backward" aria-hidden="true"></i></a></td>
                        <td id="wpsstm-player"></td>
                        <td id="wpsstm-player-extra-next-track" class="wpsstm-player-extra"><a href="#"><i class="fa fa-forward" aria-hidden="true"></i></a></td>
                        <td id="wpsstm-player-loop" class="wpsstm-player-extra"><a title="<?php _e('Loop','wpsstm');?>" href="#"><i class="fa fa-refresh" aria-hidden="true"></i></a></td>
                        <td id="wpsstm-player-shuffle" class="wpsstm-player-extra"><a title="<?php _e('Random Wisdom','wpsstm');?>" href="#"><i class="fa fa-random" aria-hidden="true"></i></a></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    function enqueue_player_scripts_styles_shared(){
        //TO FIX load only if player is loaded (see hook init_playable_tracklist ) ?

        //JS
        wp_enqueue_script( 'wpsstm-player', wpsstm()->plugin_url . '_inc/js/wpsstm-player.js', array('wpsstm'),wpsstm()->version, true);
        
        //localize vars
        $localize_vars=array(
            'leave_page_text'       => __('A track is currently playing.  Are u sure you want to leave ?','wpsstm'),
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
        return apply_filters('wpsstm_get_player_actions',$actions);
    }
    
}

class WPSSTM_Player_Provider{
    
    var $name;
    static $slug = 'default';

    function __construct(){
    }

    /*
    Check if the provider is able to play this URL
    */
    
    public static function can_play_source($url){
        return false;
    }

    public function get_stream_url($url){
        return $url;
    }

    /*
    Returns the the mime type or pseudo-mime type for this source
    https://github.com/mediaelement/mediaelement/blob/master/docs/usage.md
    */
    
    public function get_source_type($url){
        
    }

    function format_source_title($title){
        return $title;
    }
    
    public static function get_sources(WPSSTM_Track $track){
    }
    
}

class WPSSTM_Player_Provider_Native extends WPSSTM_Player_Provider{
    
    var $name = 'Audio';
    static $slug = 'audio';
    var $icon = '<i class="fa fa-file-audio-o" aria-hidden="true"></i>';
    
    public static function can_play_source($url){
        if ( !$ext = self::get_file_url_ext($url) ) return false;
        
        //check file is supported
        $audio_extensions = wp_get_audio_extensions();
        if ( !in_array($ext,$audio_extensions) ) return false;
        
        return true;
    }
    
    //get file URL extension
    private static function get_file_url_ext($url){
        $filetype = wp_check_filetype($url);
        if ( !$ext = $filetype['ext'] ) return;
        return $ext;
    }

    function get_source_type($url){
        
        if ( !$ext = self::get_file_url_ext($url) ) return;
        
        return sprintf('audio/%s',$ext);//$filetype['type'],
        
    }

}

class WPSSTM_Player_Provider_Youtube extends WPSSTM_Player_Provider{
    
    var $name = 'Youtube';
    static $slug = 'youtube';

    public static function can_play_source($url){
        return ( self::get_youtube_id($url) );
    }
    
    public static function get_youtube_id($url){
        //youtube
        $pattern = '~http(?:s?)://(?:www.)?youtu(?:be.com/watch\?v=|.be/)([\w\-\_]*)(&(amp;)?[\w\?=]*)?~i';
        preg_match($pattern, $url, $url_matches);
        
        if ( !isset($url_matches[1]) ) return;
        
        return $url_matches[1];
    }
    
    function get_stream_url($url){
        if ( !$id = self::get_youtube_id($url) ) return;
        return self::get_youtube_permalink($id);
    }
    
    public static function get_youtube_permalink($id){
        return sprintf('https://youtube.com/watch?v=%s',$id);
    }
    
    function get_source_type($url){
        return 'video/youtube';
    }
}

/*
The Soundcloud Provider reacts differently if we've got a soundcloud client ID or not : either it will stream a mp3, or load the soundcloud widget and use medialement's widget renderer.
*/

//TO FIX always use the video/soundcloud type; use audio/mp3 only to DOWNLOAD the track.
//This would avoid an extra API call when streaming a SC track.
class WPSSTM_Player_Provider_Soundcloud extends WPSSTM_Player_Provider{
    
    var $name = 'Soundcloud';
    static $slug = 'soundcloud';
    var $client_id;
    
    function __construct(){
        
        $this->client_id = wpsstm()->get_options('soundcloud_client_id');
        
        parent::__construct();
        add_action( 'wp_enqueue_scripts',array($this,'provider_scripts_styles') );
    }
    
    public static function can_play_source($url){
        return ( self::get_sc_track_id($url) );
    }
    
    /*
    Scripts/Styles to load
    */
    public function provider_scripts_styles(){
        if (!$this->client_id){ //soundcloud renderer (required for soundcloud widget)
            wp_enqueue_script('wp-mediaelement-renderer-soundcloud',includes_url('js/mediaelement/renderers/soundcloud.min.js'), array('wp-mediaelement'), '4.0.6');    
        }
    }

    private static function get_sc_track_id($url){

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
            return self::request_sc_track_id($url);
        }

    }
    
    /*
    Get the ID of a Soundcloud track URL (eg. https://soundcloud.com/phasescachees/jai-toujours-reve-detre-un-gangster-feat-hippocampe-fou)
    Requires a Soundcloud Client ID.
    Store result in a transient to speed up page load.
    //TO FIX IMPORTANT slows down the website on page load.  Rather should run when source is saved ?
    */
    
    private static function request_sc_track_id($url){
        
        $client_id = wpsstm()->get_options('soundcloud_client_id');
        if ( !$client_id ) return;
        
        $transient_name = 'wpsstm_sc_track_id_' . md5($url);

        if ( false === ( $sc_id = get_transient($transient_name ) ) ) {

            $api_args = array(
                'url' =>        urlencode ($url),
                'client_id' =>  $client_id
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

    function get_stream_url($url){

        if ( !$track_id = self::get_sc_track_id($url) ) return;

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

}