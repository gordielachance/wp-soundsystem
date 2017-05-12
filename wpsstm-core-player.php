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

            //redirection notice
            if ( wpsstm()->get_options('autoredirect') && $redirect_auto ){
                global $wp;

                $current_url = home_url(add_query_arg(array(),$wp->request));
                $countdown = '<strong></strong>';
                $icon = '<i class="fa fa-refresh fa-fw"></i>';
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
                
                printf('<p id="wpsstm-bottom-notice-redirection" class="wpsstm-bottom-notice">%s %s %s</p>',$icon,$countdown,$text);
            }
            ?>
            <div id="wpsstm-player-sources">
                <div id="wpsstm-player-providers"></div>
                <ul></ul>
            </div>
            <div id="wpsstm-player-wrapper">
                <div id="wpsstm-player-nav-previous-page" class="wpsstm-player-nav"><a title="<?php echo $redirect_previous['title'];?>" href="<?php echo $redirect_previous['url'];?>"><i class="fa fa-fast-backward" aria-hidden="true"></i></a></div>
                <div id="wpsstm-player-nav-previous-track" class="wpsstm-player-nav"><a href="#"><i class="fa fa-backward" aria-hidden="true"></i></a></div>
                <div id="wpsstm-player"></div>
                <div id="wpsstm-player-nav-next-track" class="wpsstm-player-nav"><a href="#"><i class="fa fa-forward" aria-hidden="true"></i></a></div>
                <div id="wpsstm-player-nav-next-page" class="wpsstm-player-nav"><a title="<?php echo $redirect_next['title'];?>" href="<?php echo $redirect_next['url'];?>"><i class="fa fa-fast-forward" aria-hidden="true"></i></a></div>
            </div>
        </div>
        <?php
    }
    
    function enqueue_player_scripts_styles(){
        
        //CSS
        
        //sourcechooser plugin
        wp_register_style('mediaelement-plugin-source-chooser','https://cdnjs.cloudflare.com/ajax/libs/mediaelement-plugins/2.1.1/source-chooser/source-chooser.min.css',array('wp-mediaelement'));

        wp_enqueue_style( 'wpsstm-player',  wpsstm()->plugin_url . '_inc/css/wpsstm-player.css', array('wp-mediaelement','mediaelement-plugin-source-chooser'), wpsstm()->version );
        
        //JS
        
        //sourcechooser plugin
        wp_register_script('mediaelement-plugin-source-chooser','https://cdnjs.cloudflare.com/ajax/libs/mediaelement-plugins/2.1.1/source-chooser/source-chooser.js',array('wp-mediaelement'), '2.1.1');

        wp_enqueue_script( 'wpsstm-player', wpsstm()->plugin_url . '_inc/js/wpsstm-player.js', array('jquery','wp-mediaelement','mediaelement-plugin-source-chooser'),wpsstm()->version);
        
        //localize vars
        $localize_vars=array();
        $localize_vars['autoredirect']  = (int)wpsstm()->get_options('autoredirect');
        $localize_vars['autoplay']  = ( wpsstm()->get_options('autoplay') == 'on' );
        $localize_vars['autosource']  = ( wpsstm()->get_options('autosource') == 'on' );
        wp_localize_script('wpsstm-player','wpsstmPlayer', $localize_vars);
        
    }

    function get_track_button($track,$database_only = true){
        
        $sources = wpsstm_sources()->get_track_sources_db($track);
        
        $sources_attr_arr = array();
        $data_attr_str = null;
        
        if ($database_only){
            if ($cached_sources = wpsstm_sources()->get_track_sources_remote( $track,false,array('cache_only'=>true) ) ){
                $sources = array_merge((array)$sources,(array)$cached_sources);
                $sources = wpsstm_sources()->sanitize_sources($sources);
            }
        }else{
            if ($remote_sources = wpsstm_sources()->get_track_sources_remote($track) ){
                $sources = array_merge((array)$sources,(array)$remote_sources);
                $sources = wpsstm_sources()->sanitize_sources($sources);
            } 
        }

        //check if any provider can use the source
        foreach( (array)$sources as $source){

            foreach( (array)$this->providers as $provider ){

                if ( !$provider_source = $provider->get_source_attr($source['url']) ) continue; //cannot play source

                $source_attr =  array(
                    'title'     => $source['title'],
                    'type'      => $provider_source['type'],
                    'src'       => esc_url($provider_source['src'])
                );

                $sources_attr_arr[] = $source_attr;
                
            }

        }

        if ($sources_attr_arr){
            $data_attr_str = esc_attr( json_encode($sources_attr_arr) );
        }
         //https://wordpress.stackexchange.com/a/162945/70449
        $link = sprintf('<a class="wpsstm-play-track" data-wpsstm-sources="%s" href="#"><i class="wpsstm-player-icon wpsstm-player-icon-error fa fa-exclamation-triangle" aria-hidden="true"></i><i class="wpsstm-player-icon wpsstm-player-icon-pause fa fa-pause" aria-hidden="true"></i><i class="wpsstm-player-icon wpsstm-player-icon-buffering fa fa-circle-o-notch fa-spin fa-fw"></i><i class="wpsstm-player-icon wpsstm-player-icon-play fa fa-play" aria-hidden="true"></i></a>',$data_attr_str);

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
    Get the source based on an URL, 
    The mime types or pseudo-mime types should match http://www.mediaelementjs.com/.
    */
    
    abstract function get_source_attr($url);
    
    /*
    Search a source from this provider
    */
    
    function single_source_lookup($track,$args=null){
        if ( $sources = $this->sources_lookup($track,$args) ){
            return $sources[0];
        }
        
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
    
    function get_source_attr($url){
        
        //check file is supported
        $filetype = wp_check_filetype($url);
        if ( !$ext = $filetype['ext'] ) return;
        
        $audio_extensions = wp_get_audio_extensions();
        if ( !in_array($ext,$audio_extensions) ) return;

        return array(
            'type'  => sprintf('audio/%s',$ext), //$filetype['type'],
            'src'   => $url
        );
        
    }

    
}

class WP_SoundSytem_Player_Provider_Youtube extends WP_SoundSytem_Player_Provider{
    
    var $name = 'Youtube';
    var $slug = 'youtube';
    var $icon = '<i class="fa fa-youtube" aria-hidden="true"></i>';
    
    function get_source_attr($url){

        //youtube
        $pattern = '~(?:youtube.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu.be/)([^"&?/ ]{11})~i';
        preg_match($pattern, $url, $url_matches);

        if (!$url_matches) return;

        return array(
            'type'  => 'video/youtube',
            'src'   => $url
        );

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
    
    function get_source_attr($url){

        //soundcloud
        $pattern = '~https?://(?:api\.)?soundcloud\.com/.*~i';
        preg_match($pattern, $url, $url_matches);

        if (!$url_matches) return;
        
        $url = sprintf('https://w.soundcloud.com/player/?url=%s',$url);
        $url = add_query_arg(array('auto_play'=>false),$url);
        
        return array(
            'type'  => 'video/soundcloud',
            'src'   => $url
        );
        
    }
    
    function sources_lookup($track,$args=null){
        return wpsstm_get_soundsgood_sources($track,'soundcloud',$args);
    }
    
    
}

class WP_SoundSytem_Player_Provider_Mixcloud extends WP_SoundSytem_Player_Provider{
    
    var $name = 'Mixcloud';
    var $slug = 'mixcloud';
    var $icon = '<i class="fa fa-mixcloud" aria-hidden="true"></i>';
    
    function get_source_attr($url){

        //mixcloud
        $pattern = '~https?://(?:www\.)?mixcloud\.com/\S*~i';
        preg_match($pattern, $url, $url_matches);

        if ($url_matches){
            return 'audio/mixcloud';
        }

    }

}

function wpsstm_player() {
	return WP_SoundSytem_Core_Player::instance();
}

wpsstm_player();