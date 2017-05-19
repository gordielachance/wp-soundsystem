<?php
class WP_SoundSytem_Playlist_Hypem_Scraper extends WP_SoundSytem_Live_Playlist_Preset{
    
    var $preset_slug = 'hypem';
    
    var $pattern = '~^https?://(?:www.)?hypem.com/~i';

    var $options_default = array(
        'selectors' => array(
            'tracks'            => array('path'=>'.section-track'),
            'track_artist'      => array('path'=>'.track_name .artist'),
            'track_title'       => array('path'=>'.track_name .track'),
            //'track_image'       => array('path'=>'a.thumb','attr'=>'src')
        )
    );
    
    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url);
        $this->preset_name = __('Hype Machine','wpsstm');
    }
 
}