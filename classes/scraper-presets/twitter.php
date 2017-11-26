<?php
class WP_SoundSystem_Twitter_Timeline extends WP_SoundSystem_URL_Preset{
    var $preset_url =       'https://www.twitter.com/';

    var $user_slug;
    
    static $wizard_suggest = false; //Prefills the wizard but is not able to get a tracklist by itself, so don't populate frontend.

    function __construct($feed_url = null){
        parent::__construct($feed_url);
        $this->user_slug = $this->get_user_slug();
        
        $this->options['selectors'] = array(
            'tracks'        => array('path'=>'#main_content .timeline .tweet .tweet-text div')
        );
        
    }
    
    function can_handle_url(){
        if ( !$this->user_slug ) return;
        return true;
    }

    function get_remote_url(){
        
        return sprintf('https://mobile.twitter.com/%s',$this->user_slug);
    }
    
    function get_user_slug(){
        $pattern = '~^https?://(?:(?:www|mobile).)?twitter.com/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_request_args(){
        //it seems that the request fails with our default user agent, remove it.
        $args['headers']['User-Agent'] = '';

        return $args;
    }

}

//register preset
function register_twitter_preset($presets){
    $presets[] = 'WP_SoundSystem_Twitter_Timeline';
    return $presets;
}
add_action('wpsstm_get_scraper_presets','register_twitter_preset');