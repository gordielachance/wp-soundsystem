<?php
class WPSSTM_Spotify{
    static $spotify_id_meta_key = '_wpsstm_spotify_id';
    static $spotify_data_meta_key = '_wpsstm_spotify_data';
    function __construct(){
        if ( wpsstm()->get_options('spotify_client_id') && wpsstm()->get_options('spotify_client_secret') ){
            add_filter('wpsstm_wizard_services_links',array($this,'register_spotify_service_links'));
            add_action('wpsstm_live_tracklist_populated',array($this,'register_spotify_presets'));
            add_action( 'add_meta_boxes', array($this, 'metabox_spotify_register'));
            add_action( 'save_post', array($this,'metabox_spotify_save')); 
            
        //backend columns
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_artist), array(__class__,'spotify_columns_register'), 10, 2 );
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_track), array(__class__,'spotify_columns_register'), 10, 2 );
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_album), array(__class__,'spotify_columns_register'), 10, 2 );
        
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_artist), array(__class__,'spotify_columns_content'), 10, 2 );
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_track), array(__class__,'spotify_columns_content'), 10, 2 );
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_album), array(__class__,'spotify_columns_content'), 10, 2 );
            
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
    
    static function get_access_token(){
        
        $client_id = wpsstm()->get_options('spotify_client_id');
        $client_secret = wpsstm()->get_options('spotify_client_secret');


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
            wpsstm()->debug_log($response->get_error_message(),'Error getting Spotify Token' ); 
            return $response;
        }
        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body);
        $token = $body->access_token;

        return $token;

    }
    
    static function get_spotify_request_args(){
        $token = self::get_access_token();
        if ( is_wp_error( $token ) ) return $token;
        
        $request_args = array(
            'headers'=>array(
                'Authorization' =>  'Bearer ' . $token,
                'Accept' =>         'application/json',
            )
        );
        return $request_args;
        
    }
    
    static function get_spotify_track(WPSSTM_Track $track){
        $valid = $track->validate_track();
        if ( is_wp_error( $valid ) ) return $valid;

        $spotify_id = null;
        $spotify_request_args = self::get_spotify_request_args();
        if ( is_wp_error( $spotify_request_args ) ) return $spotify_request_args;
        //
        
        $querystr = sprintf('track:%s artist:%s',$track->title,$track->artist);
        
        $url_args = array(
            'q' =>      urlencode($querystr),
            'type'=>    'track',
            'limit'=>   10
        );
        
        $url = add_query_arg($url_args,'https://api.spotify.com/v1/search');
        
        $response = wp_remote_get($url,$spotify_request_args);
        $body = wp_remote_retrieve_body($response);
        if ( is_wp_error($body) ) return $body;
        $json = json_decode($body,true);
        $match = wpsstm_get_array_value(array('tracks','items',0),$json);

        $spotify = array(
            'id' =>     wpsstm_get_array_value(array('id'),$match),
            'artist' => wpsstm_get_array_value(array('artists',0,'name'),$match),
            'title' =>  wpsstm_get_array_value(array('name'),$match),
            'album' =>  wpsstm_get_array_value(array('album','name'),$match),
        );
        
        $track->track_log(json_encode($spotify),'Spotify Track match');
        
        return $spotify;
        
    }
    
    function update_spotify_track_id(WPSSTM_Track $track){

        $spotify = self::get_spotify_track($track);
        $spotify_id = isset($spotify['id']) ? $spotify['id'] : null;
        if ( $track->post_id && $spotify_id && !is_wp_error($spotify_id) ){
            $success = update_post_meta($track->post_id,self::$spotify_id_meta_key,$spotify_id);
            $track->track_log($success,'Stored Spotify track ID');
        }

        return $track->spotify_id = $spotify_id;
    }
    
    function metabox_spotify_register(){
        global $post;

        add_meta_box(
            'wpsstm-parent-track', 
            'Spotify', 
            array($this,'track_spotify_id_content'),
            wpsstm()->post_type_track, 
            'side', 
            'core'
        );
    }

    function track_spotify_id_content( $post ){
        $track = new WPSSTM_Track($post->ID);
        ?>
        <div style="text-align:center">
            <label class="screen-reader-text" for="wpsstm_spotify_id"><?php _e('Spotify ID','wpsstm') ?></label>
            <input name="wpsstm_spotify_id" value="<?php echo $track->spotify_id;?>" placeholder="<?php _e('Spotify ID','wpsstm') ?>" />
        </div>
        <?php 
            if($track->spotify_id){
                $uri = sprintf('spotify:track:%s',$track->spotify_id);
                ?>
                <div align="center">
                    <?php printf('<a href="%s">%s</a>',$uri,$uri); ?>
                </div>
            <?php
        }
        wp_nonce_field( 'wpsstm_spotify_meta_box', 'wpsstm_spotify_meta_box_nonce' );
    }
    
    function metabox_spotify_save( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_valid_nonce = ( isset($_POST['wpsstm_spotify_meta_box_nonce']) && wp_verify_nonce( $_POST['wpsstm_spotify_meta_box_nonce'], 'wpsstm_spotify_meta_box' ) );
        if ( !$is_valid_nonce || $is_autodraft || $is_autosave || $is_revision ) return;
        
        unset($_POST['wpsstm_spotify_meta_box_nonce']); //so we avoid the infinite loop

        $spotify_id = ( isset($_POST[ 'wpsstm_spotify_id' ]) ) ? $_POST[ 'wpsstm_spotify_id' ] : null;
        //TO FIX TO CHECK validate spotify ID ?
        
        update_post_meta( $post_id, self::$spotify_id_meta_key, $spotify_id );

    }
    
    public static function spotify_columns_register($defaults) {
        $defaults['spotifyid'] = __('Spotify ID','wpsstm');
        return $defaults;
    }
    
    public static function spotify_columns_content($column,$post_id){
        global $post;
        
        switch ( $column ) {
            case 'spotifyid':
                $link = ($link = wpsstm_get_post_spotify_link_for_post($post_id)) ? $link : '-';
                echo $link;
                
            break;
        }
    }
    
}

class WPSSTM_Spotify_URL_Api_Preset{
    var $tracklist;
    private $user_slug;
    private $playlist_slug;

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        
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
            
            $spotify_args = WPSSTM_Spotify::get_spotify_request_args();
            if ( !is_wp_error($spotify_args) ){
                $args = array_merge($args,$spotify_args);
            }
            
        }

        return $args;
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