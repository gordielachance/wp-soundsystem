<?php

class WPSSTM_Artist{
    public $artist;
    public $musicbrainz_id = null;
    public $spotify_id = null;
    
    function to_array(){
        $arr = array(
            'artist' =>             $this->artist,
        );
        
        return array_filter($arr);
    }
    
}
