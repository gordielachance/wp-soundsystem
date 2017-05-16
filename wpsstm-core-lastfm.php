<?php

//https://github.com/matt-oakes/PHP-Last.fm-API/

use LastFmApi\Api\AuthApi;
use LastFmApi\Api\ArtistApi;
use LastFmApi\Api\TrackApi;

class WP_SoundSytem_Core_LastFM
{
    private $apiKey;
    private $auth;
    private $artistApi;
    
    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSytem_Core_LastFM;
                    self::$instance->init();
            }
            return self::$instance;
    }
    
    private function __construct() { /* Do nothing here */ }
    
    function init(){
        require_once(wpsstm()->plugin_dir . 'lastfm/_inc/php/autoload.php');
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
    }
    
    function setup_globals(){
        $this->apiKey = wpsstm()->get_options('lastfm_client_id'); //required
        
        $bio = $this->get_artist_bio('u2');
        $track = new WP_SoundSystem_Track(array('artist'=>'u2','title'=>'Sunday Bloody Sunday'));
        $track_results = $this->search_track($track);

        print_r($track_results);
        die();
    }
    
    function setup_actions(){
    }

    public function get_artist_bio($artist){
        $auth = new AuthApi('setsession', array('apiKey' => $this->apiKey));
        $artist_api = new ArtistApi($auth);
        $artistInfo = $artist_api->getInfo(array("artist" => $artist));
        return $artistInfo['bio'];
    }
    
    public function search_track(WP_SoundSystem_Track $track,$limit=1,$page=null){
        $auth = new AuthApi('setsession', array('apiKey' => $this->apiKey));
        $track_api = new TrackApi($auth);
        $result = $track_api->search(array(
                'artist' => $track->artist,
                'track' =>  $track->title,
                'limit' =>  $limit
            )
        );
        return $result;
    }
    
}


function wpsstm_lastfm() {
	return WP_SoundSytem_Core_LastFM::instance();
}

wpsstm_lastfm();