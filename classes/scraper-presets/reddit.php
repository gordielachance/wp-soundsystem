<?php
class WP_SoundSystem_Reddit_Api extends WP_SoundSystem_URL_Preset{
    var $preset_slug =      'reddit';
    var $preset_url =       'https://www.reddit.com/r/Music/wiki/musicsubreddits';

    private $subreddit_slug;
    private $subreddit_title;

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->subreddit_slug = self::get_subreddit_slug();
        
        $this->scraper_options['selectors'] = array(
            //in HTML
            'tracklist_title'   => array('path'=>'title','regex'=>null,'attr'=>null),
            //in JSON
            'tracks'            => array('path'=>'>data >children'),
            'track_artist'     => array('path'=>'title','regex'=> '(?:(?:.*), +by +(.*))|(?:(.*)(?: +[-|–|—]+ +)(?:.*))'),
            'track_title'      => array('path'=>'title','regex'=>'(?:(.*), +by +(?:.*))|(?:(?:.*)(?: +[-|–|—]+ +)(.*))' ),
            //'track_image'      => array('path'=>'img.cover-art','attr'=>'src'),
            'track_source_urls' => array('path'=>'url'),
        );
    }
    
    function can_handle_url(){
        if (!$this->subreddit_slug ) return;
        return true;
    }

    function get_remote_url(){
        return sprintf( 'https://www.reddit.com/r/%s.json?limit=100',$this->subreddit_slug );
    } 

    function get_subreddit_slug(){
        $pattern = '~^https?://(?:www.)?reddit.com/r/([^/]+)/?~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_title(){ //because we've got no title in the JSON
        if (!$this->subreddit_title){
            $transient_name = 'wpsstm-reddit-' . $this->subreddit_slug . '-title';

            if ( false === ( $title = get_transient($transient_name ) ) ) {

                $url = sprintf( 'https://www.reddit.com/r/%s',$this->subreddit_slug );
                $response = wp_remote_get( $url );

                if ( is_wp_error($response) ) return;

                $response_code = wp_remote_retrieve_response_code( $response );
                if ($response_code != 200) return;

                $content = wp_remote_retrieve_body( $response );
                
                //QueryPath
                try{
                    $title = htmlqp( $content, 'title', WP_SoundSystem_Remote_Tracklist::$querypath_options )->innerHTML();
                }catch(Exception $e){
                    return false;
                }

                libxml_clear_errors();

                if ( !$title ){
                    return;
                }

                set_transient( $transient_name, $title, 3 * DAY_IN_SECONDS );

            }
            $this->subreddit_title = $title;
        }

        return $this->subreddit_title;
    }
    
    /*
    Keep only reddit posts that have a media
    */
    /*
    protected function get_track_nodes($body_node){

        $selector = $this->get_selectors( array('tracks','path') );
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

    protected function validate_tracks($tracks){ //TOFIXGGG

        foreach((array)$tracks as $key=>$track){
            $track->artist = $this->filter_string($track->artist);
            $track->title = $this->filter_string($track->title);

        }

        return parent::validate_tracks($tracks);
    }

}

//register preset
function register_reddit_preset($presets){
    $presets[] = 'WP_SoundSystem_Reddit_Api';
    return $presets;
}

add_action('wpsstm_get_scraper_presets','register_reddit_preset');
