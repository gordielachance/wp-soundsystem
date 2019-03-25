<?php
class WPSSTM_Spotify{
    
    static $spotify_options_meta_name = 'wpsstm_spotify_options';
    
    public $options = array();
    
    function __construct(){
        
        $options_default = array(
            'client_id' =>          null,
            'client_secret' =>      null,
        );
        
        $this->options = wp_parse_args(get_option( self::$spotify_options_meta_name),$options_default);
        
        /*backend*/
        add_action( 'admin_init', array( $this, 'spotify_settings_init' ) );
        add_filter( 'wpsstm_get_music_detail_engines',array($this,'register_details_engine') );
        
        if ( $this->can_spotify_api() === true ){
            
            //presets
            add_filter('wpsstm_feed_url', array($this, 'spotify_playlist_bang_to_url'));
            add_filter('wpsstm_remote_presets',array($this,'register_spotify_presets'));

            add_filter('wpsstm_wizard_service_links',array($this,'register_spotify_service_links'), 6);
            add_filter('wpsstm_wizard_bang_links',array($this,'register_spotify_bang_links'));
            
        }
    }
    
    function spotify_playlist_bang_to_url($url){
        $pattern = '~^spotify:user:([^:]+):playlist:([\w\d]+)~i';
        preg_match($pattern,$url, $matches);
        $user = isset($matches[1]) ? $matches[1] : null;
        $playlist = isset($matches[2]) ? $matches[2] : null;
        
        if ($user && $playlist){
            $url = sprintf('https://open.spotify.com/user/%s/playlist/%s',$user,$playlist);
        }
        
        return $url;
    }
    
    function register_spotify_presets($presets){
        $presets[] = new WPSSTM_Spotify_Playlist_Api_Preset();
        return $presets;
    }
    
    function get_options($keys = null){
        return wpsstm_get_array_value($keys,$this->options);
    }
        
    public function can_spotify_api(){
        
        $client_id = $this->get_options('client_id');
        $client_secret = $this->get_options('client_secret');
        
        if ( !$client_id ) return new WP_Error( 'spotify_no_client_id', __( "Required Spotify client ID missing", "wpsstm" ) );
        if ( !$client_secret ) return new WP_Error( 'spotify_no_client_secret', __( "Required Spotify client secret missing", "wpsstm" ) );
        
        return true;
        
    }
    
    function spotify_settings_init(){
        register_setting(
            'wpsstm_option_group', // Option group
            self::$spotify_options_meta_name, // Option name
            array( $this, 'spotify_settings_sanitize' ) // Sanitize
         );
        
        add_settings_section(
            'spotify_service', // ID
            'Spotify', // Title
            array( $this, 'spotify_settings_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'spotify_client', 
            __('API','wpsstm'), 
            array( $this, 'spotify_api_settings' ), 
            'wpsstm-settings-page', // Page
            'spotify_service'//section
        );
        
    }

    function spotify_settings_sanitize($input){
        if ( WPSSTM_Settings::is_settings_reset() ) return;

        $new_input['client_id'] = ( isset($input['client_id']) ) ? trim($input['client_id']) : null;
        $new_input['client_secret'] = ( isset($input['client_secret']) ) ? trim($input['client_secret']) : null;

        return $new_input;
    }
    
    function spotify_settings_desc(){
        $new_app_link = 'https://developer.spotify.com/my-applications/#!/applications/create';
        
        printf(__('Required for the Spotify importer preset.  Create a Spotify application %s to get the required informations.','wpsstm'),sprintf('<a href="%s" target="_blank">%s</a>',$new_app_link,__('here','wpsstm') ) );
    }

    function spotify_api_settings(){
        
        $client_id = $this->get_options('client_id');
        $client_secret = $this->get_options('client_secret');
        
        //client ID
        printf(
            '<p><label>%s</label> <input type="text" name="%s[client_id]" value="%s" /></p>',
            __('Client ID:','wpsstm'),
            self::$spotify_options_meta_name,
            $client_id
        );
        
        //client secret
        printf(
            '<p><label>%s</label> <input type="text" name="%s[client_secret]" value="%s" /></p>',
            __('Client Secret:','wpsstm'),
            self::$spotify_options_meta_name,
            $client_secret
        );
    }

    function register_spotify_service_links($links){
        $item = sprintf('<a href="https://www.spotify.com" target="_blank" title="%s"><img src="%s" /></a>','Spotify',wpsstm()->plugin_url . '_inc/img/spotify-icon.png');
        $links[] = $item;
        return $links;
    }
    
    function register_spotify_bang_links($links){
        $bang_playlist = '<label><code>spotify:user:USER:playlist:PLAYLIST_ID</code></label>';
        //$bang_playlist .= sprintf('<div id="wpsstm-spotify-playlist-bang" class="wpsstm-bang-desc">%s</div>',$desc);
        $links[] = $bang_playlist;
        return $links;
    }
    
    private function get_access_token(){
        
        $can_api = $this->can_spotify_api();
        if ( is_wp_error($can_api) ) return $can_api;
        
        $client_id = $this->get_options('client_id');
        $client_secret = $this->get_options('client_secret');


        $token = false;

        $args = array(
            'headers'   => array(
                'Authorization' => 'Basic '.base64_encode($client_id.':'.$client_secret)
            ),
            'body'      => array(
                'grant_type'    => 'client_credentials'
            )
        );


        $response = wp_remote_post( 'https://accounts.spotify.com/api/token', $args );

        if ( is_wp_error($response) ){
            return new WP_Error('spotify_missing_token',$response->get_error_message());
        }
            
        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body);

        if ( property_exists($body, 'access_token') ){
            return $body->access_token;
        }elseif ( property_exists($body, 'error') ){
            return new WP_Error('spotify_missing_token',$body->error);
        }else{
            return new WP_Error('spotify_missing_token','Error getting Spotify Token');
        }

    }
    
    public function get_spotify_request_args(){
        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) return $token;
        
        $request_args = array(
            'headers'=>array(
                'Authorization' =>  'Bearer ' . $token,
                'Accept' =>         'application/json',
            )
        );
        return $request_args;
        
    }
    
    public function register_details_engine($engines){
        $engines[] = new WPSSTM_Spotify_Data();
        return $engines;
    }

}

class WPSSTM_Spotify_Data extends WPSSTM_Music_Data{
    public $slug = 'spotify';
    public $name = 'Spotify';
    public $entries_table_classname = 'WPSSTM_MB_Entries';
            
    protected function get_supported_post_types(){
        return array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_track,
            wpsstm()->post_type_album
        );
    }

    public function get_music_item_url($post_id = null){
        $id = null;
        $link = null;
        if (!$id = self::get_post_music_id($post_id) ) return;

        $post_type = get_post_type($post_id);
        $remote_type = null;

        switch($post_type){

            case wpsstm()->post_type_artist:
                $remote_type = 'artist';
            break;

            case wpsstm()->post_type_track:
                $remote_type = 'track';
            break;

            case wpsstm()->post_type_album:
                $remote_type = 'album';
            break;

        }
        
        if (!$remote_type) return;
        return sprintf('https://open.spotify.com/%s/%s',$remote_type,$id);
    }

    function get_item_auto_id( $artist,$album = null,$track = null ){
        
        $entries = $this->query_music_entries($artist,$album,$track);
        if ( is_wp_error($entries) || !$entries ) return $entries;
        
        $entry = wpsstm_get_array_value(array(0),$entries);
        $id = wpsstm_get_array_value(array('id'),$entry);
        
        return $id;

    }
    
    protected function get_details_for_post($post_id){
        
        if ( !$post_type = get_post_type($post_id) ) return false;
        
        if ( !$music_id = $this->get_post_music_id($post_id) ){
            return new WP_Error('wpsstm_missing_music_id',__("Missing music ID",'wpsstm'));
        }
        
        //remote API type
        $endpoint = null;
        switch($post_type){
            case wpsstm()->post_type_artist:
                $endpoint = 'artists';
            break;

            case wpsstm()->post_type_track:
                $endpoint = 'tracks';
            break;

            case wpsstm()->post_type_album:
                $endpoint = 'albums';
            break;
        }
        
        $api_url = sprintf('services/spotify/data/%endpoint/%s',$endpoint,$music_id);
        $api_results = WPSSTM_Core_API::api_request($api_url);
        return $api_results;
    }
    
    protected function query_music_entries( $artist,$album = null,$track = null ){

        $artist = urlencode($artist);
        $album = ($album) ? $album : '_';
        $album = urlencode($album);
        $track = urlencode($track);
        
        if($artist && $track){//track
            $api_url = sprintf('services/spotify/search/%s/%s/%s',$artist,$album,$track);
        }elseif($artist && $album){//album
            $api_url = sprintf('services/spotify/search/%s/%s',$artist,$album);
        }elseif($artist){//artist
            $api_url = sprintf('services/spotify/search/%s',$artist);
        }

        $api_results = WPSSTM_Core_API::api_request($api_url);
        return $api_results;
        
    }
    
    
    protected function get_fillable_details_map($post_id = null){
        $items = array();
        $post_type = get_post_type($post_id);
        
        switch($post_type){
            //TOUFIX
        }
        return $items;
        
    }
            
}

class WPSSTM_Spotify_Entries extends WPSSTM_Music_Entries {
    //TOUFIX
}

class WPSSTM_Spotify_Playlist_Api_Preset extends WPSSTM_Remote_Tracklist{
    
    var $playlist_id;
    var $playlist_data;

    function __construct($url = null,$options = null) {
        
        $this->preset_options = array(
            'selectors' => array(
                'tracks'           => array('path'=>'root > items'),
                'track_artist'     => array('path'=>'track > artists > name'),
                'track_album'      => array('path'=>'track > album > name'),
                'track_title'      => array('path'=>'track > name'),
            )
        );
        
        parent::__construct($url,$options);
        
        $this->request_pagination['tracks_per_page'] = 100; //spotify API
        

    }
    
    function init_url($url){
        global $wpsstm_spotify;

        if ( $this->playlist_id = self::get_playlist_id_from_url($url) ){
            
            $api_url = sprintf('services/spotify/data/playlists/%s',$this->playlist_id);
            
            $api_results = WPSSTM_Core_API::api_request($api_url);
            if (is_wp_error($api_results)) return $api_results;
            
            $this->playlist_data = $api_results;
            
            //update pagination
            $total_tracks = wpsstm_get_array_value(array('tracks','total'),$this->playlist_data);
            $this->request_pagination['total_pages'] = ceil($total_tracks / $this->request_pagination['tracks_per_page']);

        }

        return (bool)$this->playlist_id;

    }
    
    static function get_playlist_id_from_url($url){
        $pattern = '~^https?://(?:open|play).spotify.com/user/([^/]+)/playlist/([\w\d]+)~i';
        preg_match($pattern,$url, $matches);

        $user_id =  isset($matches[1]) ? $matches[1] : null;
        $playlist_id = isset($matches[2]) ? $matches[2] : null;
        
        return $playlist_id;
        
    }

    function get_remote_request_url(){
        $url = sprintf('https://api.spotify.com/v1/playlists/%s/tracks',$this->playlist_id);

        $pagination_args = array(
            'limit'     => $this->request_pagination['tracks_per_page'],
            'offset'    => $this->request_pagination['current_page'] * $this->request_pagination['tracks_per_page']
        );

        $url = add_query_arg($pagination_args,$url);

        return $url;
    }

    function get_remote_request_args(){
        global $wpsstm_spotify;
        
        $args = parent::get_remote_request_args();
        $spotify_args = $wpsstm_spotify->get_spotify_request_args();
        
        if (is_wp_error($spotify_args) ) return $spotify_args;
        return array_merge($args,$spotify_args);
    }

    function get_remote_title(){
        $title = wpsstm_get_array_value('name', $this->playlist_data);
        return $title;

    }
    
    function get_remote_author(){
        $author = wpsstm_get_array_value(array('owner','id'), $this->playlist_data);
        return $author;
    }

}


function wpsstm_spotify_init(){
    global $wpsstm_spotify;
    $wpsstm_spotify = new WPSSTM_Spotify();
}

add_action('wpsstm_load_services','wpsstm_spotify_init');