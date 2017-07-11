<?php
class WP_SoundSystem_Preset_Slacker_Stations extends WP_SoundSystem_Live_Playlist_Preset{
    var $preset_slug =      'slacker-station-tops';
    var $preset_url =       'http://slacker.com/';
    
    var $pattern =          '~^https?://(?:www.)?slacker.com/station/([^/]+)/?~i';
    var $variables =        array(
        'slacker-station-slug' => null
    );
    var $options_default =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'ol.playlistList li.row:not(.heading)'),
            'track_artist'      => array('path'=>'span.artist'),
            'track_title'       => array('path'=>'span.title')
        )
    );

    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url);

        $this->preset_name = __('Slacker.com station tops','wpsstm');

    } 

}
