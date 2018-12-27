<?php
class WPSSTM_Twitter{
    function __construct(){
        add_filter('wpsstm_remote_presets',array($this,'register_twitter_preset'));
    }
    //register preset
    function register_twitter_preset($presets){
        $presets[] = new WPSSTM_Twitter_Timeline_Preset();
        return $presets;
    }

}
class WPSSTM_Twitter_Timeline_Preset extends WPSSTM_Remote_Tracklist{
    
    var $user_slug;

    function __construct($url = null,$options = null) {
        
        $this->default_options['selectors']['tracks']['path'] = '#main_content .timeline .tweet .tweet-text div';
        
        parent::__construct($url,$options);

    }
    
    function init_url($url){
        $this->user_slug = $this->get_user_slug($url);
        return $this->user_slug;
    }

    function get_remote_request_url(){
        return sprintf('https://mobile.twitter.com/%s',$this->user_slug);
    }

    function get_user_slug($url){
        $pattern = '~^https?://(?:(?:www|mobile).)?twitter.com/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_request_args(){
        $args = parent::get_remote_request_args();
        $args['headers']['User-Agent'] = ''; //it seems that the request fails with our default user agent, remove it.

        return $args;
    }

}

function wpsstm_twitter_init(){
    new WPSSTM_Twitter();
}

add_action('wpsstm_init','wpsstm_twitter_init');