<?php
class WP_SoundSytem_Preset_Reddit_Api extends WP_SoundSytem_Live_Playlist_Preset{
    
    /* https://regex101.com/r/isVHq9/13 */
    
    var $preset_slug =      'reddit';
    var $preset_url =       'https://www.reddit.com/r/Music/wiki/musicsubreddits';

    var $pattern =          '~^https?://(?:www.)?reddit.com/r/([^/]+)/?~i';
    var $redirect_url=      'https://www.reddit.com/r/%subredit-slug%.json?limit=100';
    var $variables =        array(
        'subredit-slug' => null
    );

    var $options_default =  array(
        'datas_cache_min'   => 30,
        'selectors' => array(
            'tracks'            => array('path'=>'>data >children'),
            'track_artist'     => array('path'=>'title','regex'=> '(?:(?:.*), +by +(.*))|(?:(.*)(?: +[-|–|—]+ +)(?:.*))'),
            'track_title'      => array('path'=>'title','regex'=>'(?:(.*), +by +(?:.*))|(?:(?:.*)(?: +[-|–|—]+ +)(.*))' ),
            //'track_image'      => array('path'=>'img.cover-art','attr'=>'src'),
            'track_source_urls' => array('path'=>'url'),
        )
    );

    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url);

        $this->preset_name = __('Reddit (for music subs)','wpsstm');
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
    function get_all_raw_tracks(){
        //init pagination before request
        $pagination_args = array(
            'page_items_limit'  => 50
        );

        $this->set_request_pagination( $pagination_args );
        
        return parent::get_all_raw_tracks();
    }

    
    protected function get_request_url(){
        
        $url = parent::get_request_url();
        
        //handle pagination
        $pagination_args = array(
            'limit'     => $this->request_pagination['page_items_limit']
        );
        
        $url = add_query_arg($pagination_args,$url);
        return $url;

    }
    */
    
    protected function filter_string($str){
        $str = trim($str,'"'); //remove quotation marks
        $str = trim($str,"'"); //remove quotation marks
        
        $remove_strings = array(
            '(Audio)',
            '(Official)',
            '(Official Video)',
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
        
        $str = preg_replace('~\[.*\]~', '', $str); //remove comments like [Hip-Hop]
        $str = preg_replace('~\(\d{4}\)~', '', $str); //remove dates like (1968)
        $str = preg_replace('~\d{4} ?$~', '', $str); //remove dates like 2005 at the end of the string

        foreach((array)$remove_strings as $remove_str){
            $str = str_ireplace($remove_str, "", $str);
        }

        return $str;
    }

    protected function get_track_artist($track_node){
        $artist = parent::get_track_artist($track_node);
        return $this->filter_string($artist);
    }
    
    protected function get_track_title($track_node){
        $title = parent::get_track_title($track_node);
        return $this->filter_string($title);

    }

}
