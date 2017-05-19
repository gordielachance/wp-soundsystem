<?php
class WP_SoundSytem_Playlist_Reddit_Api extends WP_SoundSytem_Live_Playlist_Preset{
    
    /*
    
    ([^-]+)(?: -+ )([^\[]+).*$
    
    The Yawpers - Bartleby the Womanizer [Rock] (2016)
    Cacique'97 - Epidemia [World]
    ONUKA - 19 86 [electro-folk / chillstep] (2016)
    Charley Patton -- Prayer of Death [US, Delta Blues] (1929)
    
    (.*)(?:, by )(.*)
    Ambient, by Hyperwizard

    */
    
    var $preset_slug = 'reddit';

    var $pattern = '~^https?://(?:www.)?reddit.com/r/([^/]+)/?~i';
    var $redirect_url= 'https://www.reddit.com/r/%subredit-slug%.json?limit=50';
    var $variables = array(
        'subredit-slug' => null
    );

    var $options_default = array(
        'datas_cache_min'   => 30,
        'selectors' => array(
            'tracks'            => array('path'=>'>data >children'),
            'track_artist'     => array('path'=>'title','regex'=> '^(?:.*)(?:, by )(.*)|^([^-|–]+)(?: -+|–+ )'), // '^.*, by .*|^([^-]+) -+ '),
            'track_title'      => array('path'=>'title','regex'=>'^(.*),(?: by )|(?: -+|–+ )([^\[]+)'),
            //'track_image'      => array('path'=>'img.cover-art','attr'=>'src'),
            'track_source_urls' => array('path'=>'url'),
        )
    );

    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url);

        $this->preset_name = __('Reddit (for music subs)','wpsstm');

    }

}
