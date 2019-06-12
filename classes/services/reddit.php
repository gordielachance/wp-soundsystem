<?php

class WPSSTM_Reddit{
    function __construct(){
        add_filter('wpsstm_wizard_service_links',array($this,'register_reddit_service_links'));
        add_filter('wpsstm_remote_presets',array($this,'register_reddit_preset'));
    }
    //register preset
    function register_reddit_preset($presets){
        $presets[] = new WPSSTM_Reddit_Api_Preset();
        return $presets;
    }
    
    function register_reddit_service_links($links){
        $item = sprintf('<a href="https://www.reddit.com" target="_blank" title="%s"><img src="%s" /></a>',__('Music subreddits','wpsstm'),wpsstm()->plugin_url . '_inc/img/reddit-icon.png');
        $links[] = $item;
        return $links;
    }

}

class WPSSTM_Reddit_Api_Preset extends WPSSTM_Remote_Tracklist{
    
    var $subreddit_slug;

    function __construct($url = null,$options = null) {
        
        $this->preset_options = array(
            'selectors' => array(
                //in JSON
                'tracks'            => array('path'=>'>data >children'),
                'track_artist'      => array('path'=>'>data >title','regex'=> '(?:(?:.*), +by +(.*))|(?:(.*)(?: +[-|–|—]+ +)(?:.*))'),
                'track_title'       => array('path'=>'>data >title','regex'=>'(?:(.*), +by +(?:.*))|(?:(?:.*)(?: +[-|–|—]+ +)(.*))' ),
                'track_image'       => array('path'=>'>data >thumbnail'),
                'track_link_urls'   => array('path'=>'>data >url'),
            )
        );
        
        parent::__construct($url,$options);
        
        //TOUFIXadd_filter('wpsstm_remote_tracks', array($this,'filter_remote_tracks'),10,2);

    }
    
    public function init_url($url){
        $this->subreddit_slug = $this->get_subreddit_slug($url);
        return $this->subreddit_slug;
    }

    function get_remote_request_url(){
        $url = sprintf( 'https://www.reddit.com/r/%s.json',$this->subreddit_slug );

        //https://www.reddit.com/dev/api/
        $args = array(
            'limit' => 25, //default:25
        );
        return add_query_arg($args,$url);
    }

    function get_subreddit_slug($url){
        $pattern = '~^https?://(?:www.)?reddit.com/r/([^/]+)/?~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_title(){ //because we've got no title in the JSON
        
        if (!$this->subreddit_slug) return;

        $transient_name = 'wpsstm-reddit-' .$this->subreddit_slug . '-title';

        if ( false === ( $remote_title = get_transient($transient_name ) ) ) {

            $remote_title = null;

            $url = sprintf( 'https://www.reddit.com/r/%s',$this->subreddit_slug );
            $response = wp_remote_get( $url );

            if ( is_wp_error($response) ) return $title;

            $response_code = wp_remote_retrieve_response_code( $response );
            if ($response_code != 200) return $title;

            $content = wp_remote_retrieve_body( $response );

            //QueryPath
            try{
                $remote_title = htmlqp( $content, 'title', WPSSTM_Remote_Tracklist::$querypath_options )->innerHTML();
            }catch(Exception $e){
                return $title;
            }

            libxml_clear_errors();

            if ( $remote_title ){
                set_transient( $transient_name, $remote_title, 3 * DAY_IN_SECONDS );
                $title = $remote_title;
            }

        }
        
        return $title;
    }

    protected function get_track_nodes($body_node){

        $selector = $this->get_selectors( array('tracks','path') );
        $post_nodes = qp( $body_node, null, self::$querypath_options )->find($selector);
        
        ////printf('<br/>count before:%s<br/>',$post_nodes->length);

        foreach($post_nodes as $key=>$node) {
            $title = qp( $node, null, self::$querypath_options )->find('title')->innerHTML();
            $media = qp( $node, null, self::$querypath_options )->find('media')->innerHTML();
            $tag = qp( $node, null, self::$querypath_options )->find('link_flair_text')->innerHTML();
            $tag = strtolower($tag);

            /*
            remove nodes that have not been flagged as media by reddit.
            This is kind arbitrary but well, that's a way to do it.
            */
            if (!$media){
                ////printf('no media for post#%s: %s<br/>',$key,$title);
                $node->remove();
                continue;
            }
            
            /*
            remove nodes that have the playlist tag (used eg. on /r/jazznoir)
            */
            if ($tag === 'playlist'){
                $node->remove();
                continue;
            }
            
        }
        
        //update selection
        $post_nodes = qp( $body_node, null, self::$querypath_options )->find($selector);
        ////printf('<br/>count after:%s<br/>',$post_nodes->length);

        return $post_nodes;
    }


    protected function filter_string($str){
        
        //remove quotation marks
        $str = trim($str,'"'); 
        $str = trim($str,"'");
        
        //remove some strings
        $remove_strings = array(
            '(Audio)',
            '(Official)',
            '(Official Video)',
            '(Official Audio)',
            '(Official Videoclip)',
            '(Clip officiel)',
            '(Lyric Video)',
            '(Official Music Video)',
            '(HD)',
            '(Music Video)',
            '(High Quality)',
            ' HD',
            ' HQ'
            
        );
        
        foreach((array)$remove_strings as $remove_str){
            $str = str_ireplace($remove_str, "", $str);
        }
        
        //remove some (regex) strings
        $remove_regexes = array(
            '~\[.*\]~', //eg [Hip-Hop]
            '~\(\d{4}\)~', //dates - eg. (1968)
            '~\d{4} ?$~', //dates (end of string) eg. 2005
            '~[-|–|—] *$~' //dash (end of string)
        );
        
        foreach((array)$remove_regexes as $pattern){
            $str = preg_replace($pattern, '', $str);
        }

        return $str;
    }

    /*
    TOUFIX
    function filter_remote_tracks($tracks,$remote){

        foreach((array)$tracks as $key=>$track){
            $track->artist = $this->filter_string($track->artist);
            $track->title = $this->filter_string($track->title);
            $tracks[$key] = $track;
        }

        return $tracks;
    }
    */

}

function wpsstm_reddit_init(){
    new WPSSTM_Reddit();
}

add_action('wpsstm_load_services','wpsstm_reddit_init');
