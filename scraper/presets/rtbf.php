<?php
class WP_SoundSytem_Preset_RTBF_Stations extends WP_SoundSytem_Live_Playlist_Preset{
    var $preset_slug =      'rtbf';
    var $preset_url =       'https://www.rtbf.be/';
    
    var $pattern =          '~^https?://(?:www.)?rtbf.be/(?!lapremiere)([^/]+)~i'; //ignore la premiere which has different selectors.
    var $redirect_url=      'https://www.rtbf.be/%rtbf-slug%/conducteur';
    var $variables = array(
        'rtbf-slug' => null
    );
    var $options_default =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'li.radio-thread__entry'),
            'track_artist'      => array('path'=>'span[itemprop="byArtist"]'),
            'track_title'       => array('path'=>'span[itemprop="name"]'),
            'track_image'       => array('path'=>'img[itemprop="inAlbum"]','attr'=>'data-src')
        )
    );
    
    var $wizard_suggest = false;

    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url);

        $this->preset_name = __('RTBF stations','wpsstm');

    } 
}