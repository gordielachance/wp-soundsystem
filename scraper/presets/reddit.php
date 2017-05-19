<?php
class WP_SoundSytem_Playlist_Reddit_Api extends WP_SoundSytem_Live_Playlist_Preset{
    
    /* https://regex101.com/r/isVHq9/13 */
    
    var $preset_slug = 'reddit';

    var $pattern = '~^https?://(?:www.)?reddit.com/r/([^/]+)/?~i';
    var $redirect_url= 'https://www.reddit.com/r/%subredit-slug%.json?limit=100';
    var $variables = array(
        'subredit-slug' => null
    );

    var $options_default = array(
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
    
    protected function get_track_artist($track_node){
        $artist = parent::get_track_artist($track_node);
        $artist = trim($artist,'"'); //remove quotation marks
        $artist = trim($artist,"'"); //remove quotation marks
        $artist = preg_replace('~\[.*\]~', '', $artist); //remove comments like [Hip-Hop]
        return $artist;
    }
    
    protected function get_track_title($track_node){
        $title = parent::get_track_title($track_node);
        $title = trim($title,'"'); //remove quotation marks
        $title = trim($title,"'"); //remove quotation marks
        $title = preg_replace('~\(\d{4}\)~', '', $title); //remove dates like (1968)
        $title = preg_replace('~\[.*\]~', '', $title); //remove comments like [Hip-Hop]
        
        $remove_strings = array(
            '(Audio)',
            '(Official)',
            '(Official Video)',
            '(Official Videoclip)',
            '(Clip officiel)',
            '(Lyric Video)',
            '(Official Music Video)'
        );
        
        foreach((array)$remove_strings as $remove_str){
            $title = str_ireplace($remove_str, "", $title);
        }
        
        
        return $title;
    }
    
    /*
    //TO FIX
    Keep only reddit posts that have a media
    */
    /*
    protected function get_track_nodes($body_node){

        echo"BEFORE:";
        $selector = $this->get_options( array('selectors','tracks','path') );
        $post_nodes = qp( $body_node, null, self::$querypath_options )->find($selector);
        //var_dump($post_nodes->length);
        
        $media_posts_wrapper = qp( '', null, self::$querypath_options );
        

        foreach($post_nodes as $key=>$node) {

            $title = qp( $node, null, self::$querypath_options )->find('title')->innerHTML();
            $media = qp( $node, null, self::$querypath_options )->find('media')->innerHTML();
            //if (!$media) continue;
            
            $media_posts_wrapper->append($node);
            
        }

        return $media_posts_wrapper;
    }
    */

}
