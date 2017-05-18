<?php
class WP_SoundSytem_Playlist_Slacker_Station_Scraper extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $remote_slug = 'slacker-station-tops';
    
    var $pattern = '~^https?://(?:www.)?slacker.com/station/([^/]+)/?~i';
    var $variables = array(
        'slacker-station-slug' => null
    );
    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'ol.playlistList li.row:not(.heading)'),
            'track_artist'      => array('path'=>'span.artist'),
            'track_title'       => array('path'=>'span.title')
        )
    );

    function __construct(){
        parent::__construct();

        $this->remote_name = __('Slacker.com station tops','wpsstm');

    } 

}
