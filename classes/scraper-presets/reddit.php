<?php
class WP_SoundSystem_Reddit_Api{

    private $subreddit_slug;
    private $subreddit_title;

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->subreddit_slug = self::get_subreddit_slug();
        
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
        add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title') );
        
        add_filter('wpsstm_input_tracks', array($this,'filter_input_tracks'),10,2);

    }
    
    function can_handle_url(){
        if (!$this->subreddit_slug ) return;
        return true;
    }

    function get_remote_url($url){
        if ( $this->can_handle_url() ){
            $url = sprintf( 'https://www.reddit.com/r/%s.json?limit=100',$this->subreddit_slug );
        }
        return $url;
    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
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
        return $options;
    }

    function get_subreddit_slug(){
        $pattern = '~^https?://(?:www.)?reddit.com/r/([^/]+)/?~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_title($title){ //because we've got no title in the JSON
        if ( $this->can_handle_url() ){
            
            if (!$this->subreddit_title){
                $transient_name = 'wpsstm-reddit-' . $this->subreddit_slug . '-title';

                if ( false === ( $title = get_transient($transient_name ) ) ) {

                    $url = sprintf( 'https://www.reddit.com/r/%s',$this->subreddit_slug );
                    $response = wp_remote_get( $url );

                    if ( is_wp_error($response) ) return $title;

                    $response_code = wp_remote_retrieve_response_code( $response );
                    if ($response_code != 200) return $title;

                    $content = wp_remote_retrieve_body( $response );

                    //QueryPath
                    try{
                        $remote_title = htmlqp( $content, 'title', WP_SoundSystem_Remote_Tracklist::$querypath_options )->innerHTML();
                    }catch(Exception $e){
                        return $title;
                    }

                    libxml_clear_errors();

                    if ( !$remote_title ) return $title;

                    set_transient( $transient_name, $remote_title, 3 * DAY_IN_SECONDS );

                }
                $this->subreddit_title = $remote_title;
            }

            $title = $this->subreddit_title;
        }
        
        return $title;
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

    function filter_input_tracks($tracks,$tracklist){
        
        if ( $this->can_handle_url() ){
            foreach((array)$tracks as $key=>$track){
                $track->artist = $this->filter_string($track->artist);
                $track->title = $this->filter_string($track->title);
                $tracks[$key] = $track;
            }
        }
        
        return $tracks;
    }

}

//register preset
function register_reddit_preset($tracklist){
    new WP_SoundSystem_Reddit_Api($tracklist);
}
function register_reddit_service_links($links){
    $links[] = array(
        'slug'      => 'reddit',
        'name'      => 'Reddit',
        'url'       => 'https://www.reddit.com',
        'pages'     => array(
            array(
                'slug'      => 'subreddit',
                'name'      => __('Music subreddit','wpsstm'),
                'example'   => 'https://www.reddit.com/r/SUBREDDIT',
            ),
        )
    );
    return $links;
}
add_filter('wpsstm_wizard_services_links','register_reddit_service_links');
add_action('wpsstm_get_remote_tracks','register_reddit_preset');
