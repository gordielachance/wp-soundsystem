<?php

class WPSSTM_Deezer{
    function __construct(){
        add_action('wpsstm_before_remote_response',array(__class__,'register_deezer_preset'));
        add_filter('wpsstm_wizard_services_links',array(__class__,'register_deezer_service_links'));
    }
    //register preset
    static function register_deezer_preset($remote){
        new WPSSTM_Deezer_Preset($remote);
    }

    static function register_deezer_service_links($links){
        $links[] = array(
            'slug'      => 'deezer',
            'name'      => 'Deezer',
            'url'       => 'https://www.deezer.com',
            'pages'     => array(
                array(
                    'slug'          => 'playlists',
                    'name'          => __('playlists','wpsstm'),
                    'example'       => 'http://www.deezer.com/fr/playlist/PLAYLIST_ID',
                )
            )
        );

        return $links;
    }
}

class WPSSTM_Deezer_Preset{
    function __construct($remote){
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
    }
    
    function can_handle_url($url){
        $playlist_id = $this->get_playlist_id($url);
        if ( !$playlist_id ) return;
        return true;
    }
    
    function set_selectors($remote){
        
        if ( !$this->can_handle_url($remote->feed_url_no_filters) ) return;
        $remote->options['selectors'] = array(
            'tracks'            => array('path'=>'#tab_tracks_content [itemprop="track"]'),
            'track_artist'      => array('path'=>'[itemprop="byArtist"]'),
            'track_title'       => array('path'=>'span[itemprop="name"]'),
            'track_album'       => array('path'=>'[itemprop="inAlbum"]')
        );
    }

    function get_playlist_id($url){
        $pattern = '~^https?://(?:www.)?deezer.com/(?:.*/)?playlist/([^/]+)~i';
        preg_match($pattern,$url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

}

function wpsstm_deezer_init(){
    new WPSSTM_Deezer();
}

add_action('wpsstm_init','wpsstm_deezer_init');