<?php
class WP_SoundSytem_Playlist_Slacker_Station_Scraper extends WP_SoundSytem_Live_Playlist_Preset{
    var $preset_slug = 'slacker-station-tops';
    
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

    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url = null);

        $this->preset_name = __('Slacker.com station tops','wpsstm');

    } 

}
