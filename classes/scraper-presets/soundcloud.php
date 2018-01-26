<?php
class WPSSTM_Soundcloud_User_Api{

    private $user_id;
    private $user_slug;
    private $page_slug;
    private $page_api = null;
    private $client_id;
    
    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->user_slug = $this->get_user_slug();
        $this->page_slug = $this->get_user_page();
        $this->client_id = wpsstm()->get_options('soundcloud_client_id');
        
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
        add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title') );

    }

    function can_handle_url(){
        
        if ( !$this->user_slug ) return;
        return true;
    }

    function get_remote_url($url){
        
        if ( $this->can_handle_url() ){

            if ( !$user_id = $this->get_user_id() ){
                return new WP_Error( 'wpsstm_soundcloud_missing_user_id', __('Required user ID missing.','wpsstm') );
            }

            $api_page = null;

            switch($this->page_slug){
                case 'likes':
                    $this->api_page = 'favorites';
                break;
                default:
                    $this->api_page = 'tracks';
                break;
            }

            $url = sprintf('http://api.soundcloud.com/users/%s/%s?client_id=%s',$user_id,$this->api_page,$this->client_id);
        }
        
        return $url;

    }
    
    function get_live_tracklist_options($options,$tracklist){
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
            'tracks'            => array('path'=>'element'),
            'track_artist'      => array('path'=>'user username'),
            'track_title'       => array('path'=>'title'),
            'track_image'       => array('path'=>'artwork_url')
        );
        }
        return $options;
    }

    function get_user_slug(){
        $pattern = '~^http(?:s)?://(?:www\.)?soundcloud.com/([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_user_page(){
        $pattern = '~^http(?:s)?://(?:www\.)?soundcloud.com/[^/]+/([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_user_id(){
        
        if (!$this->user_id){
            $transient_name = 'wpsstm-soundcloud-' . $this->user_slug . '-userid';

            if ( false === ( $user_id = get_transient($transient_name ) ) ) {

                $api_url = sprintf('http://api.soundcloud.com/resolve.json?url=http://soundcloud.com/%s&client_id=%s',$this->user_slug,$this->client_id);
                $response = wp_remote_get( $api_url );

                if ( is_wp_error($response) ) return;

                $response_code = wp_remote_retrieve_response_code( $response );
                if ($response_code != 200) return;

                $content = wp_remote_retrieve_body( $response );
                if ( is_wp_error($content) ) return;
                $content = json_decode($content);

                if ( $user_id = $content->id ){
                    set_transient( $transient_name, $user_id );
                }

            }
            $this->user_id = $user_id;
        }

        return $this->user_id;

    }
    
    function get_remote_title($title){
        
        if ( $this->can_handle_url() ){
            
            $title = sprintf(__('%s on Soundcloud','wpsstm'),$this->user_slug);
            $subtitle = null;

            switch($this->page_slug){
                case 'likes':
                    $subtitle = __('Favorite tracks','wpsstm');
                break;
                default: //tracks
                    $subtitle = __('Tracks','wpsstm');
                break;
            }

            if ($subtitle){
                $title = $title . ' - ' . $subtitle;
            }

        }

        return $title;
    }

}

//register preset
function register_soundcloud_preset($tracklist){
    new WPSSTM_Soundcloud_User_Api($tracklist);
}
function register_soundcloud_service_links($links){
    $links[] = array(
        'slug'      => 'soundcloud',
        'name'      => 'SoundCloud',
        'url'       => 'https://soundcloud.com'
    );
    return $links;
}

if ( wpsstm()->get_options('soundcloud_client_id') ){
    add_filter('wpsstm_wizard_services_links','register_soundcloud_service_links');
    add_action('wpsstm_get_remote_tracks','register_soundcloud_preset');
}