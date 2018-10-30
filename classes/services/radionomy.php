<?php

class WPSSTM_Radionomy{
    function __construct(){
        add_filter('wpsstm_wizard_services_links',array($this,'register_radionomy_service_links'));
        add_action('wpsstm_live_tracklist_init',array($this,'register_radionomy_preset'));
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
    var $tracklist;
    private $station_slug;
    private $station_id;
    
    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->station_slug = $this->get_station_slug();
        
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter('wpsstm_live_tracklist_title',array($this,'get_remote_title') );

    }

    function can_handle_url(){
        if ( !$this->station_slug ) return;
        return true;
    }

    function get_remote_url($url){
        if ( $this->can_handle_url() ){
            $station_id = $this->get_station_id();
            if ( is_wp_error($station_id) ) return $station_id;

            $url = sprintf('http://api.radionomy.com/tracklist.cfm?radiouid=%s&apikey=XXX&amount=20&type=xml&cover=true',$station_id);
        }
        return $url;
    }
    
    function get_live_tracklist_options($options,$tracklist){
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
                'tracks'            => array('path'=>'tracks track'),
                'track_artist'      => array('path'=>'artists'),
                'track_title'       => array('path'=>'title'),
                'track_image'       => array('path'=>'cover'),
                //playduration
            );
        }
        return $options;
    }

    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?radionomy.com/.*?/radio/([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    function get_station_id(){
        
        if (!$this->station_id){
            $transient_name = 'wpsstm-radionomy-' . $this->station_slug . '-id';

            if ( false === ( $station_id = get_transient($transient_name ) ) ) {

                $station_url = sprintf('http://www.radionomy.com/en/radio/%1$s',$this->station_slug);
                $response = wp_remote_get( $station_url );

                if ( is_wp_error($response) ) return;

                $response_code = wp_remote_retrieve_response_code( $response );
                if ($response_code != 200) return;

                $content = wp_remote_retrieve_body( $response );

                libxml_use_internal_errors(true);

                //QueryPath
                try{
                    $imagepath = htmlqp( $content, 'head meta[property="og:image"]', WPSSTM_Remote_Tracklist::$querypath_options )->attr('content');
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
            $this->station_id = $station_id;
        }

        return $this->station_id;

    }
    
    function get_remote_title($title){
        if ( $this->can_handle_url() ){
            $title = sprintf('Radionomy: %s', $this->station_slug);
        }
        return $title;
    }

}

function wpsstm_radionomy_init(){
    new WPSSTM_Radionomy();
}

add_action('wpsstm_init','wpsstm_radionomy_init');