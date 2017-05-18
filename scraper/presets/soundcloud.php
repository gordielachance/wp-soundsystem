<?php
class WP_SoundSytem_Playlist_Soundcloud_Api extends WP_SoundSytem_Live_Playlist_Preset{
    
    var $preset_slug = 'soundcloud';
    
    var $pattern = '~^https?://(?:www.)?soundcloud.com/([^/]+)/?([^/]+)?~i';
    var $redirect_url= 'http://api.soundcloud.com/users/%soundcloud-userid%/%soundcloud-api-page%?client_id=%soundcloud-client-id%';
    var $variables = array(
        'soundcloud-username' => null,
        'soundcloud-page' => null
    );
    var $page_api = null;

    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'element'),
            'track_artist'      => array('path'=>'user username'),
            'track_title'       => array('path'=>'title'),
            'track_image'       => array('path'=>'artwork_url')
        )
    );
    
    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url = null);

        $this->preset_name = __('Soundcloud user tracks or likes','wpsstm');
        
        if ( $client_id = wpsstm()->get_options('soundcloud_client_id') ){
            $this->set_variable_value('soundcloud-client-id',$client_id);
        }else{
            $this->can_use_preset = false;
        }

    }

    function get_request_url(){
        
        //get soundcloud user ID
        $user_id = $this->get_user_id();
        if (!$user_id) return false;
        $this->set_variable_value('soundcloud-userid',$user_id);

        $page = $this->get_variable_value('soundcloud-page');
        
        switch($page){
            case 'likes':
                $this->page_api = 'favorites';
            break;
            default:
                $this->page_api = 'tracks';
            break;
        }
        $this->set_variable_value('soundcloud-api-page',$this->page_api);
        
        return parent::get_request_url();
    }
    
    function get_user_id(){
        
        $username = $this->get_variable_value('soundcloud-username');
        if (!$username) return false;
        
        $client_id = $this->get_variable_value('soundcloud-client-id');
        if (!$client_id) return false;

        $transient_name = 'wpsstm-soundcloud-' . $username . '-userid';

        if ( false === ( $user_id = get_transient($transient_name ) ) ) {

            $api_url = sprintf('http://api.soundcloud.com/resolve.json?url=http://soundcloud.com/%s&client_id=%s',$username,$client_id);
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
        
        return $user_id;

    }
    
    function get_tracklist_title(){
        
        $page = $this->get_variable_value('soundcloud-page');
        $username = $this->get_variable_value('soundcloud-username');
        
        $title = sprintf(__('%s on Soundcloud','wpsstm'),$username);
        $subtitle = null;
        
        switch($this->page_api){
            case 'favorites':
                $subtitle = __('Favorite tracks','wpsstm');
            break;
            case 'tracks':
                $subtitle = __('Tracks','wpsstm');
            break;
        }
        
        if ($subtitle){
            return $title . ' - ' . $subtitle;
        }else{
            return $title;
        }
    }

}