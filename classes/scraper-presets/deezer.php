<?php
class WPSSTM_Deezer_Playlists{
    var $tracklist;
    private $playlist_id;

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->playlist_id = $this->get_playlist_id();
        
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
    }
    
    function can_handle_url(){
        if ( !$this->playlist_id ) return;
        return true;
    }
    
    function get_live_tracklist_options($options,$tracklist){
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
                'tracks'            => array('path'=>'#tab_tracks_content [itemprop="track"]'),
                'track_artist'      => array('path'=>'[itemprop="byArtist"]'),
                'track_title'       => array('path'=>'span[itemprop="name"]'),
                'track_album'       => array('path'=>'[itemprop="inAlbum"]')
            );
        }
        return $options;
    }

    function get_playlist_id(){
        $pattern = '~^https?://(?:www.)?deezer.com/(?:.*/)?playlist/([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

}

//register preset
function register_deezer_preset($tracklist){
    new WPSSTM_Deezer_Playlists($tracklist);
}

function register_deezer_service_links($links){
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

add_action('wpsstm_get_remote_tracks','register_deezer_preset');
add_filter('wpsstm_wizard_services_links','register_deezer_service_links');