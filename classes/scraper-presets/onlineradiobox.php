<?php
class WP_SoundSystem_Preset_OnlineRadioBox_Scraper extends WP_SoundSystem_Live_Playlist_Preset{
    
    var $preset_slug =      'onlineradiobox';
    var $preset_url =       'http://onlineradiobox.com/';
    var $pattern =          '~^https?://(?:www.)?onlineradiobox.com/[^/]+/([^/]+)/?~i';
    var $variables =        array(
        'station-slug' => null
    );
    var $redirect_url =     'http://onlineradiobox.com/ma/%station-slug%/playlist';

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'.tablelist-schedule tr'),
            'track_artist'      => array('path'=>'a','regex'=>'(.+?)(?= - )'),
            'track_title'       => array('path'=>'a','regex'=>' - (.*)'),
        )
    );
    
    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name =    'Online Radio Box';
    }
    
    function validate_tracks($tracks){
        //limit to 30 tracks to avoid creating too much posts
        //TO FIX this should be in wpsstm core.
        $tracks = array_slice($tracks,0,30);
        return parent::validate_tracks($tracks);
    }
 
}