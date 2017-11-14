<?php
class WP_SoundSystem_Preset_Twitter_Timelines extends WP_SoundSystem_Live_Playlist_Preset{
    var $preset_slug =      'twitter';
    var $preset_url =       'https://www.twitter.com/';

    var $preset_options =  array(
        'selectors' => array(
            'tracks'        => array('path'=>'#main_content .timeline .tweet .tweet-text div')
        )
    );
    
    var $wizard_suggest = false; //Prefills the wizard but is not able to get a tracklist by itself, so don't populate frontend.

    function __construct($post_id = null){
        parent::__construct($post_id);

        $this->preset_name = 'Twitter';

    }
    
    function get_remote_url(){
        
        $domain = wpsstm_get_url_domain( $this->feed_url );
        if ( $this->domain != 'twitter') return;
        
        $user_slug = $this->get_user_slug();
        if ( !$user_slug = $this->get_user_slug() ){
            return new WP_Error( 'wpsstm_twitter_missing_user_slug', __('Required user slug missing.','wpsstm') );
        }

        return sprintf('https://mobile.twitter.com/%s',$user_slug);

    }
    
    function get_user_slug(){
        $pattern = '~^https?://(?:(?:www|mobile).)?twitter.com/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
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