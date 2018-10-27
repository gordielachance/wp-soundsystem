<?php

class WPSSTM_Spotify{
    function __construct(){
        if ( wpsstm()->get_options('spotify_client_id') && wpsstm()->get_options('spotify_client_secret') ){
            add_filter('wpsstm_wizard_services_links',array($this,'register_spotify_service_links'));
            add_action('wpsstm_live_tracklist_populated',array($this,'register_spotify_presets'));
        }
    }
    //register presets
    function register_spotify_presets($tracklist){
        new WPSSTM_Spotify_URL_Api_Preset($tracklist);
        new WPSSTM_Spotify_URI_Api_Preset($tracklist);
    }
    function register_spotify_service_links($links){
        $links[] = array(
            'slug'      => 'spotify',
            'name'      => 'Spotify',
            'url'       => 'https://www.spotify.com',
            'pages'     => array(
                array(
                    'slug'      => 'playlists',
                    'name'      => __('playlists','wpsstm'),
                    'example'   => 'https://open.spotify.com/user/USER_SLUG/playlist/PLAYLIST_ID',
                ),
            )
        );
        return $links;
    }
    /*
    Use SongWhip.com to get Spotify track ID
    */
    static function get_spotify_track_id(WPSSTM_Track $track){
        
        $valid = $track->validate_track();
        if ( is_wp_error( $valid ) ) return $valid;

        $spotify_id = null;
        
        $url_args = array(
            'q'=>urlencode($track->artist . ' ' . $track->title)
        );
        
        //$url = 'https://api.songwhip.com/?country=BE&q=test';
        $url = add_query_arg($url_args,'https://songwhip.com/search');

        $response = wp_remote_get($url);
        $body = wp_remote_retrieve_body($response);

        if ( is_wp_error($body) ) return $body;
        
        $json = json_decode($body,true);
        
        if ($json['status'] != 'success'){
            return new WP_Error( 'wpsstm_songwhip_search', __('Error while searching on SongWhip.','wpsstm') );
        }
        
        $tracks = wpsstm_get_array_value(array('data','tracks'),$json);
        $first_track = reset($tracks);
        $spotify_url = wpsstm_get_array_value(array('sourceUrl'),$first_track);
        $spotify_title = wpsstm_get_array_value(array('name'),$first_track);
        $spotify_artist = wpsstm_get_array_value(array('artists',0,'name'),$first_track);

        $pattern = '~https?://open.spotify.com/track/([^/]+)~';
        preg_match($pattern, $spotify_url, $url_matches);

        if ( !isset($url_matches[1]) ) return;
        
            $spotify_id = $url_matches[1];
            $track->track_log( json_encode(array('track'=>sprintf('%s - %s - %s',$track->artist,$track->title,$track->album),'spotify_id'=>$spotify_id,'spotify_artist'=>$spotify_artist,'spotify_title'=>$spotify_title),JSON_UNESCAPED_UNICODE),'Found Spotify track ID');

        return $spotify_id;
    }
}

class WPSSTM_Spotify_URL_Api_Preset{
    var $tracklist;

    private $token = null;
    private $client_id;
    private $client_secret;
    private $user_slug;
    private $playlist_slug;

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->client_id = wpsstm()->get_options('spotify_client_id');
        $this->client_secret = wpsstm()->get_options('spotify_client_secret');
        
        $this->user_slug = $this->get_user_slug();
        $this->playlist_slug = $this->get_playlist_slug();

        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
        add_filter( 'wppstm_live_tracklist_pagination',array($this,'get_remote_pagination') );
        add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title') );
        add_filter( 'wpsstm_live_tracklist_author',array($this,'get_remote_author') );
        add_filter( 'wpsstm_live_tracklist_request_args',array($this,'remote_request_args') );
        
    }

    function can_handle_url(){

        if ( !$this->user_slug ) return;
        if ( !$this->playlist_slug ) return;

        return true;
    }

    function get_remote_url($url){
        
        if ( $this->can_handle_url() ){
            $url = sprintf('https://api.spotify.com/v1/users/%s/playlists/%s/tracks',$this->user_slug,$this->playlist_slug);

            //handle pagination
            $limit = $this->tracklist->request_pagination['page_items_limit'];
            $pagination_args = array(
                'limit'     => $limit,
                'offset'    => ($this->tracklist->request_pagination['current_page'] - 1) * $limit
            );

            $url = add_query_arg($pagination_args,$url);
        }

        return $url;
        

    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
                'tracks'           => array('path'=>'root > items'),
                'track_artist'     => array('path'=>'track > artists > name'),
                'track_album'      => array('path'=>'track > album > name'),
                'track_title'      => array('path'=>'track > name'),
            );
        }
        return $options;
    }
    
    function get_user_slug(){
        $pattern = '~^https?://(?:open|play).spotify.com/user/([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);

        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_playlist_slug(){
        $pattern = '~^https?://(?:open|play).spotify.com/user/[^/]+/playlist/([\w\d]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_playlist_data(){
        //TO FIX use transient ?
        $response = wp_remote_get( sprintf('https://api.spotify.com/v1/users/%s/playlists/%s',$this->user_slug,$this->playlist_slug), $this->tracklist->get_request_args() );
        
        $json = wp_remote_retrieve_body($response);
        
        if ( is_wp_error($json) ) return $json;
        
        return json_decode($json,true);
    }

    function get_remote_pagination($pagination){
        
        if ( $this->can_handle_url() ){
            $data = $this->get_playlist_data();

            if ( is_wp_error($data) ){
                return $data;
            }

            //TO FIX not very clean ? Should we remove track_count and use pagination variable only ?
            $this->tracklist->track_count = wpsstm_get_array_value(array('tracks','total'), $data);

            //init pagination before request
            $pagination['page_items_limit'] = 100;
            

        }
        
        return $pagination;

    }
    
    function get_remote_title($title){
        
        if ( $this->can_handle_url() ){
            $data = $this->get_playlist_data();
            if ( !is_wp_error($data) ){
                 $title = wpsstm_get_array_value('name', $data);
            }
           
        }
        return $title;

    }
    
    function get_remote_author($author){
        if ( $this->can_handle_url() ){
            $author = $this->user_slug;
        }
        return $author;
    }

    function remote_request_args($args){
        
        if ( $this->can_handle_url() ){
        
            if ( $token = $this->get_access_token() ){
                $args['headers']['Authorization'] = 'Bearer ' . $token;           
            }

            $args['headers']['Accept'] = 'application/json';
            
        }

        return $args;
    }

    function get_access_token(){
        
        if ($this->token === null){
            
            $this->token = false;

            $args = array(
                'headers'   => array(
                    'Authorization' => 'Basic '.base64_encode($this->client_id.':'.$this->client_secret)
                ),
                'body'      => array(
                    'grant_type'    => 'client_credentials'
                )
            );


            $response = wp_remote_post( 'https://accounts.spotify.com/api/token', $args );

            if ( is_wp_error($response) ){
                wpsstm()->debug_log($response->get_error_message(),'Spotify preset error' ); 
            }
            $body = wp_remote_retrieve_body($response);
            $body = json_decode($body);
            $this->token = $body->access_token;
            
        }
        
        return $this->token;

    }

}

//Spotify Playlists URIs
class WPSSTM_Spotify_URI_Api_Preset extends WPSSTM_Spotify_URL_Api_Preset{

    function get_user_slug(){
        $pattern = '~^spotify:user:([^:]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_playlist_slug(){
        $pattern = '~^spotify:user:.*:playlist:([\w\d]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
}

function wpsstm_spotify_init(){
    new WPSSTM_Spotify();
}

add_action('wpsstm_init','wpsstm_spotify_init');