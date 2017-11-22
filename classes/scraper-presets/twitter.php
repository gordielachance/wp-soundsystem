<?php
class WP_SoundSystem_Preset_Twitter_Timelines extends WP_SoundSystem_Live_Playlist_Preset{
    var $preset_slug =      'twitter';
    var $preset_url =       'https://www.twitter.com/';

    var $preset_options =  array(
        'selectors' => array(
            'tracks'        => array('path'=>'#main_content .timeline .tweet .tweet-text div')
        )
    );
    
    static $wizard_suggest = false; //Prefills the wizard but is not able to get a tracklist by itself, so don't populate frontend.

    function __construct($post_id = null){
        parent::__construct($post_id);

        $this->preset_name = 'Twitter';

    }
    
    static function can_handle_url($url){
        if ( !$user_slug = self::get_user_slug($url) ) return;
        return true;
    }
    
    function get_remote_url(){
        return sprintf('https://mobile.twitter.com/%s',self::get_user_slug($this->feed_url));
    }
    
    static function get_user_slug($url){
        $pattern = '~^https?://(?:(?:www|mobile).)?twitter.com/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_request_args(){
        $args = parent::get_request_args();

        //it seems that the request fails with our default user agent, remove it.
        $args['headers']['User-Agent'] = '';

        return $args;
    }

}

//register preset

function register_twitter_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_Twitter_Timelines';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_twitter_preset');