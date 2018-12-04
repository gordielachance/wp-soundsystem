<?php

class WPSSTM_Radionomy{
    function __construct(){
        add_filter('wpsstm_wizard_services_links',array($this,'register_radionomy_service_links'));
        add_action('wpsstm_before_remote_response',array($this,'register_radionomy_preset'));
    }
    
    //register preset
    function register_radionomy_preset($tracklist){
        new WPSSTM_Radionomy_API_Preset($tracklist);
    }
    function register_radionomy_service_links($links){
        $links[] = array(
            'slug'      => 'radionomy',
            'name'      => 'Radionomy',
            'url'       => 'https://www.radionomy.com',
            'pages'     => array(
                array(
                    'slug'      => 'stations',
                    'name'      => __('stations','wpsstm'),
                    'example'   => 'https://www.radionomy.com/LANG/radio/RADIO_SLUG',
                ),
            )
        );
        return $links;
    }
    
}

class WPSSTM_Radionomy_API_Preset{
    
    function __construct($remote){
        
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_radionomy_api_url') );
        add_filter('wpsstm_live_tracklist_title',array($this,'get_remote_title'),10,2 );

    }

    function is_web_url($url){
        $station_slug = $this->get_website_url_station_slug($url);
        return ( $station_slug );
    }
    
    function get_website_url_station_slug($url){
        $pattern = '~^https?://(?:www.)?radionomy.com/.*?/radio/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function is_api_url($url){
        $station_id = $this->get_api_url_station_id($url);
        return ( $station_id );
    }
    
    function get_api_url_station_id($url){
        $pattern = '~^http(?:s)?://api.radionomy.com/tracklist.cfm\?radiouid=([\w\d-]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }        

    function get_radionomy_api_url($url){
        if ( $this->is_web_url($url) && ($station_slug = $this->get_website_url_station_slug($url)) ){
            $station_id = $this->get_station_id($station_slug);
            if ( $station_id && !is_wp_error($station_id) ){
                $url = sprintf('http://api.radionomy.com/tracklist.cfm?radiouid=%s&apikey=XXX&amount=20&type=xml&cover=true',$station_id);
            }
        }
        return $url;
    }
    
    function set_selectors($remote){
        if ( !$this->is_api_url($remote->redirect_url) ) return;
        
        $remote->options['selectors'] = array(
            'tracks'            => array('path'=>'tracks track'),
            'track_artist'      => array('path'=>'artists'),
            'track_title'       => array('path'=>'title'),
            'track_image'       => array('path'=>'cover'),
            //playduration
        );

    }

    function get_station_id($station_slug){

        $transient_name = 'wpsstm-radionomy-' . $station_slug . '-id';

        if ( false === ( $station_id = get_transient($transient_name ) ) ) {

            $station_url = sprintf('http://www.radionomy.com/en/radio/%1$s',$station_slug);
            $response = wp_remote_get( $station_url );

            if ( is_wp_error($response) ) return;

            $response_code = wp_remote_retrieve_response_code( $response );
            if ($response_code != 200) return;

            $content = wp_remote_retrieve_body( $response );

            libxml_use_internal_errors(true);

            //QueryPath
            try{
                $imagepath = htmlqp( $content, 'head meta[property="og:image"]', WPSSTM_Remote_Datas::$querypath_options )->attr('content');
            }catch(Exception $e){
                return false;
            }

            libxml_clear_errors();

            $image_file = basename($imagepath);

            $pattern = '~^([^.]+)~';
            preg_match($pattern, $image_file, $matches);

            if ( !isset($matches[1]) ){
                return new WP_Error( 'wpsstm_radionomy_missing_station_id', __('Required station ID missing.','wpsstm') );
            }

            $station_id = $matches[1];
            set_transient( $transient_name, $station_id, 1 * DAY_IN_SECONDS );

        }

        return $station_id;

    }
    
    function get_remote_title($title,$remote){
        if ( $this->is_api_url($remote->redirect_url) ){
            $station_slug = $this->get_website_url_station_slug($remote->url);
            $title = sprintf('Radionomy: %s', $station_slug);
        }
        return $title;
    }

}

function wpsstm_radionomy_init(){
    new WPSSTM_Radionomy();
}

add_action('wpsstm_init','wpsstm_radionomy_init');