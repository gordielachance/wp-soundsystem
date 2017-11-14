<?php
class WP_SoundSystem_Preset_Reddit_Api extends WP_SoundSystem_Live_Playlist_Preset{
    
    /* https://regex101.com/r/isVHq9/13 */
    
    var $preset_slug =      'reddit';
    var $preset_url =       'https://www.reddit.com/r/Music/wiki/musicsubreddits';

    var $preset_options =  array(
        'datas_cache_min'   => 30,
        'selectors' => array(
            'tracks'            => array('path'=>'>data >children'),
            'track_artist'     => array('path'=>'title','regex'=> '(?:(?:.*), +by +(.*))|(?:(.*)(?: +[-|–|—]+ +)(?:.*))'),
            'track_title'      => array('path'=>'title','regex'=>'(?:(.*), +by +(?:.*))|(?:(?:.*)(?: +[-|–|—]+ +)(.*))' ),
            //'track_image'      => array('path'=>'img.cover-art','attr'=>'src'),
            'track_source_urls' => array('path'=>'url'),
        )
    );

    function __construct($post_id = null){
        parent::__construct($post_id);

        $this->preset_name = __('Reddit (for music subs)','wpsstm');
    }
    
    function get_remote_url(){
        
        $domain = wpsstm_get_url_domain( $this->feed_url );
        if ( $this->domain != 'reddit') return;
        
        $subreddit_slug = $this->get_subreddit_slug();
        if ( is_wp_error($subreddit_slug) ) return $subreddit_slug;

        return sprintf('https://www.reddit.com/r/%s.json?limit=100',$subreddit_slug);

    }
    
    function get_subreddit_slug(){
        $pattern = '~^https?://(?:www.)?reddit.com/r/([^/]+)/?~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    /*
    Keep only reddit posts that have a media
    */
    /*
    protected function get_track_nodes($body_node){

        $selector = $this->get_options( array('selectors','tracks','path') );
        $post_nodes = qp( $body_node, null, self::$querypath_options )->find($selector);
        //var_dump($post_nodes->length);

        foreach($post_nodes as $key=>$node) {
            $media = qp( $node, null, self::$querypath_options )->find('media')->innerHTML();
            if (!$media) unset($post_nodes[$key]);
        }
        //var_dump($post_nodes->length);
        return $post_nodes;
    }
    */
    
    /*
    function get_remote_tracks($args = null){
        //init pagination before request
        $pagination_args = array(
            'page_items_limit'  => 50
        );

        $this->set_request_pagination( $pagination_args );
        
        return parent::get_remote_tracks($args);
    }

    */
    
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

    protected function validate_tracks($tracks){

        foreach((array)$tracks as $key=>$track){
            $track->artist = $this->filter_string($track->artist);
            $track->title = $this->filter_string($track->title);

        }

        return parent::validate_tracks($tracks);
    }

}

//register preset

function register_reddit_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_Reddit_Api';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_reddit_preset');
