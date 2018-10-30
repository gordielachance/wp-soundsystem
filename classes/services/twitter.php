<?php
class WPSSTM_Twitter{
    function __construct(){
        add_action('wpsstm_live_tracklist_init',array($this,'register_twitter_preset'));
    }
    //register preset
    function register_twitter_preset($tracklist){
        new WPSSTM_Twitter_Timeline_Preset($tracklist);
    }

}
class WPSSTM_Twitter_Timeline_Preset{
    private $user_slug;

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->user_slug = $this->get_user_slug();

        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
        
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter( 'wpsstm_live_tracklist_request_args',array($this,'remote_request_args') );
        
    }
    
    function can_handle_url(){
        if ( !$this->user_slug ) return;
        return true;
    }

    function get_remote_url($url){
        if ( $this->can_handle_url() ){
            $url = sprintf('https://mobile.twitter.com/%s',$this->user_slug);
        }
        return $url;
    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors']['tracks']['path'] = '#main_content .timeline .tweet .tweet-text div';
        }
        return $options;
    }
    
    function get_user_slug(){
        $pattern = '~^https?://(?:(?:www|mobile).)?twitter.com/([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function remote_request_args($args){
        if ( $this->can_handle_url() ){
            //it seems that the request fails with our default user agent, remove it.
            $args['headers']['User-Agent'] = '';
        }

        return $args;
    }

}

function wpsstm_twitter_init(){
    new WPSSTM_Twitter();
}

add_action('wpsstm_init','wpsstm_twitter_init');