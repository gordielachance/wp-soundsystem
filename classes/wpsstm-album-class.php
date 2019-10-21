<?php

class WPSSTM_Album{
    public $artist;
    public $title;
    public $duration; //in milliseconds
    public $musicbrainz_id = null;
    public $spotify_id = null;
    
    function to_array(){
        $arr = array(
            'artist' =>             $this->artist,
            'title' =>              $this->title,
            'duration' =>           $this->duration,
        );
        
        return array_filter($arr);
    }
    
}
