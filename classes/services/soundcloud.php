<?php

class WPSSTM_Souncloud{
    
    static $mimetype = 'video/soundcloud';
    static $soundcloud_options_meta_name = 'wpsstm_souncloud_options';
    public $options = array();
    
    function __construct(){
        
        $options_default = array(
            'client_id' =>      null,
            'client_secret' =>  null
        );
        
        $this->options = wp_parse_args(get_option( self::$soundcloud_options_meta_name),$options_default);

        add_filter('wpsstm_get_link_mimetype',array($this,'get_soundcloud_link_type'),10,2);
        add_filter('wpsstm_get_stream_url',array($this,'get_soundcloud_stream_url'),10,2);
        
        /*backend*/
        add_action( 'admin_init', array( $this, 'soundcloud_settings_init' ) );

    }
    
    function soundcloud_settings_init(){
        register_setting(
            'wpsstm_option_group', // Option group
            self::$soundcloud_options_meta_name, // Option name
            array( $this, 'soundcloud_settings_sanitize' ) // Sanitize
         );
        
        add_settings_section(
            'souncloud_service', // ID
            'Soundcloud', // Title
            array( $this, 'souncloud_settings_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'soundcloud_client', 
            __('API','wpsstm'), 
            array( $this, 'soundcloud_api_settings' ), 
            'wpsstm-settings-page', // Page
            'souncloud_service'//section
        );
        
    }
    
    function soundcloud_settings_sanitize($input){
        if ( WPSSTM_Settings::is_settings_reset() ) return;
        
        //soundcloud
        $new_input['client_id'] = ( isset($input['client_id']) ) ? trim($input['client_id']) : null;
        $new_input['client_secret'] = ( isset($input['client_secret']) ) ? trim($input['client_secret']) : null;

        return $new_input;
    }
    
    function get_options($keys = null){
        return wpsstm_get_array_value($keys,$this->options);
    }
    

    
    function souncloud_settings_desc(){
        $new_app_link = 'http://soundcloud.com/you/apps/new';
        $desc = array();
        $desc[] = sprintf('Required to play Souncloud tracks.','wpsstm');
        $desc[] = sprintf(__('Get an API key %s.','wpsstm'),sprintf('<a href="%s" target="_blank">%s</a>',$new_app_link,__('here','wpsstm') ) );
        echo implode('  ',$desc);
    }

    function soundcloud_api_settings(){
        $client_id = $this->get_options('client_id');
        $client_secret = $this->get_options('client_secret');

        //client ID
        printf(
            '<p><label>%s</label> <input type="text" name="%s[client_id]" value="%s" /></p>',
            __('Client ID:','wpsstm'),
            self::$soundcloud_options_meta_name,
            $client_id
        );
        
        //client secret
        printf(
            '<p><label>%s</label> <input type="text" name="%s[client_secret]" value="%s" /></p>',
            __('Client Secret:','wpsstm'),
            self::$soundcloud_options_meta_name,
            $client_secret
        );
        
    }
    
    public static function can_soundcloud_api(){
        
        $client_id = $this->get_options('client_id');
        $client_secret = $this->get_options('client_secret');
        
        if ( !$client_id ) return new WP_Error( 'soundcloud_no_client_id', __( "Required Soundcloud client ID missing", "wpsstm" ) );
        if ( !$client_secret ) return new WP_Error( 'soundcloud_no_client_secret', __( "Required Soundcloud client secret missing", "wpsstm" ) );
        
        return true;
        
    }

    public function get_soundcloud_link_type($type,WPSSTM_Track_Link $link){
        if ( $this->get_sc_track_id($link->permalink_url) ){
            $type = self::$mimetype;
        }
        return $type;
    }
    public function get_sc_track_id($url){

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
        
        if ( isset($url_matches[1]) && isset($url_matches[2]) ){
            return $this->request_sc_track_id($url);
        }

    }
    
    public function get_soundcloud_stream_url($url,WPSSTM_Track_Link $link){

        $client_id = $this->get_options('client_id');
        $sc_track_id = $this->get_sc_track_id($url);

        if ($sc_track_id){
            //widget URL
            $widget_url = 'https://w.soundcloud.com/player/';
            $track_url = sprintf('http://api.soundcloud.com/tracks/%s',$sc_track_id);
            $widget_args = array(
                'url' =>        urlencode ($track_url),
                'autoplay' =>   false
            );
            $url = add_query_arg($widget_args,$widget_url);
            
            if ( $client_id ){ //stream url
                $url = sprintf('https://api.soundcloud.com/tracks/%s/stream?client_id=%s',$sc_track_id,$client_id);
            }
            
        }
        


        return $url;

    }
    /*
    Scripts/Styles to load
    */
    public function provider_scripts_styles(){
        if (!$this->client_id){ //soundcloud renderer (required for soundcloud widget)
            wp_enqueue_script('wp-mediaelement-renderer-soundcloud',includes_url('js/mediaelement/renderers/soundcloud.min.js'), array('wp-mediaelement'), '4.0.6');    
        }
    }

    /*
    Get the ID of a Soundcloud track URL (eg. https://soundcloud.com/phasescachees/jai-toujours-reve-detre-un-gangster-feat-hippocampe-fou)
    Requires a Soundcloud Client ID.
    Store result in a transient to speed up page load.
    //TO FIX IMPORTANT slows down the website on page load.  Rather should run when link is saved ?
    */
    
    private function request_sc_track_id($url){
        
        $client_id = $this->get_options('client_id');
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
}

function wpsstm_souncloud_init(){
    new WPSSTM_Souncloud();
}

add_action('wpsstm_load_services','wpsstm_souncloud_init');