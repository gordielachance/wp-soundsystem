<?php
class WP_SoundSytem_Playlist_Hypem_Scraper extends WP_SoundSytem_Playlist_Scraper_Preset{
    
    var $remote_slug = 'hypem';
    
    var $pattern = '~^https?://(?:www.)?hypem.com/~i';

    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'.section-track'),
            'track_artist'      => array('path'=>'.track_name .artist'),
            'track_title'       => array('path'=>'.track_name .track'),
            //'track_image'       => array('path'=>'a.thumb','attr'=>'src')
        )
    );
    
    function __construct(){
        parent::__construct();
        $this->remote_name = __('Hype Machine','wpsstm');
    }
 
}