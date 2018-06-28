<?php

abstract class WPSSTM_Track_Autosource{
    
    var $track;
    var $sources;
    
    //tags we should extract form the source title (if it is not in the track title)
    static $extract_tags = array('official','mix','remix','rmx','live','cover','karaoke','originally by','acoustic','demo','edit','soundalike','vocal','revision','remake','instrumental','loop','break');
    
    //tags that should be reduced (to the first item)
    static $reduce_tags = array(
        array('remix','rmx')
    );
    
    static $positive_tags = array('official','original');
    static $negative_tags = array('mix','remix','live','cover','karaoke','originally by','acoustic','demo','edit','soundalike','vocal','revision','remake','instrumental','loop','break');

    abstract function populate_track_autosources();
    abstract function search_track();
    abstract static function get_provider_weight();

    /*
    Extract some tags out of the source title;
    Only if it does not appears in the track title.
    */
    //TO FIX when contains the word 'remix', it outputs the tags 'remix' AND 'mix'.  Should fix this.
    public function parse_source_title_tags($source_title){
        
        $tags = array();
        
        $sanitized_source_title = sanitize_title($source_title);
        $sanitized_track_title = sanitize_title($this->track->title);
        foreach((array)self::$extract_tags as $tag){
            $sanitized_tag = sanitize_title($tag);
            if ( stripos($sanitized_track_title, $tag) ) continue; //tag IS contained in the track title, skip
            if ( stripos($sanitized_source_title, $tag) ){
                $tags[] = $tag;
            }
        }
        
        return $tags;
    }

    public function populate_weight(WPSSTM_Source $source){

        $weights = array(
            'provider' =>   static::get_provider_weight(),
            'relevance' =>  self::get_relevance_weight( $source->index,count($this->sources) ),
            'duration' =>   self::get_duration_weight($source->duration,$this->track->duration),
            'tags' =>       self::get_tags_weight($source->tags),
        );

        //default to .5 if no weight set
        foreach($weights as $key=>$weight){
            $weights[$key] = ($weight === false) ? .5 : $weight;
        }

        $source->weights = $weights;

        //total
        $source->weight = array_product($weights);

    }
    
    private static function get_duration_weight($source_duration = null, $track_duration = null){

        if (!$track_duration || !$source_duration) return false;
        
        //compute duration difference in %
        $diff = abs(1 - $source_duration / $track_duration);
        $weight = 1 - $diff;
        
        return $weight;
    }
    
    public static function get_tags_weight($tags){
        
        if( empty($tags) ) return false;
        
        $weight = 1; //default
        
        $positive_tags = array_intersect(self::$positive_tags,$tags);
        $negative_tags = array_intersect(self::$negative_tags,$tags);

        $weight += count($positive_tags) * .2;
        $weight -= count($negative_tags) * .2;
        
        return $weight;
        
    }
    
    /*
    Get the weight from the provider search results.
    Input : current index and total results
    Returns a value between 1 (first result) and 0 (last result)
    */
    protected static function get_relevance_weight($current,$total){
        return ( $total - $current ) / $total;
    }
    
}

class WPSSTM_Provider_Platform{
    
}