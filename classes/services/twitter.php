<?php
class WPSSTM_Twitter{
    function __construct(){
        add_action('wpsstm_before_remote_response',array($this,'register_twitter_preset'));
    }
    //register preset
    function register_twitter_preset($tracklist){
        new WPSSTM_Twitter_Timeline_Preset($tracklist);
    }

}
class WPSSTM_Twitter_Timeline_Preset{

    function __construct($remote){

        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
        
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter( 'wpsstm_live_tracklist_request_args',array($this,'remote_request_args'),10,2 );
        
    }
    
    function can_handle_url($url){
        $user_slug = $this->get_user_slug($url);
        if ( !$user_slug ) return;
        return true;
    }

    function get_remote_url($url){
        if ( $this->can_handle_url($url) ){
            $user_slug = $this->get_user_slug($url);
            $url = sprintf('https://mobile.twitter.com/%s',$user_slug);
        }
        return $url;
    }
    
    function set_selectors($remote){
        
        if ( !$this->can_handle_url($remote->feed_url_no_filters) ) return;
        $remote->options['selectors']['tracks']['path'] = '#main_content .timeline .tweet .tweet-text div';
    }
    
    function get_user_slug($url){
        $pattern = '~^https?://(?:(?:www|mobile).)?twitter.com/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function remote_request_args($args,$remote){
        if ( $this->can_handle_url($remote->feed_url_no_filters) ){
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