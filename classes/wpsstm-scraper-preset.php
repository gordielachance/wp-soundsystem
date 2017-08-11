<?php

abstract class WP_SoundSystem_Live_Playlist_Preset extends WP_SoundSystem_Remote_Tracklist{
    var $preset_slug =      null;
    var $preset_url =       null;
    var $preset_name =      null;
    var $preset_desc =      null;
    var $preset_options =   array();
    var $pattern =          null; //regex pattern that would match an URL
    var $redirect_url =     null; //real URL of the tracklist; can use the values from the regex groups captured with the pattern above.
    var $variables =        array(); //list of slugs that would match the regex groups captured with the pattern above - eg. array('username','playlist-id')
    
    var $can_use_preset =   true; //if this preset requires special conditions (eg. an API key or so), override this in your preset class.
    var $wizard_suggest =   true; //suggest or not this preset in the wizard

    public function __construct($post_id = null){
        
        parent::__construct($post_id);
        
        if ($post_id){
            
            //populate variables from URL
            if ($this->feed_url && $this->pattern){

                preg_match($this->pattern, $this->feed_url, $url_matches);
                if ( $url_matches ){

                    array_shift($url_matches); //remove first item (full match)
                    $this->populate_variable_values($url_matches);
                }
            }
            
        }
        
    }
    
    function get_default_options(){
        $defaults = parent::get_default_options();
        return array_replace_recursive((array)$defaults,(array)$this->preset_options); //last one has priority
    }
    
    /*
    Update a string and replace all the %variable-key% parts of it with the value of that variable if it exists.
    */
    
    private function variables_fill_string($str){

        foreach($this->variables as $variable_slug => $variable_value){
            $pattern = '%' . $variable_slug . '%';
            $value = $variable_value;
            
            if ($value) {
                $str = str_replace($pattern,$value,$str);
            }
        }

        return $str;
    }
    
    /**
    Fill the preset $variables.
    The array keys from the preset $variables and the input $values_arr have to match.
    **/

    private function populate_variable_values($values_arr){
        
        $key = 0;

        foreach((array)$this->variables as $variable_slug=>$variable){
            $value = ( isset($values_arr[$key]) ) ? $values_arr[$key] : null;
            
            if ($value){
                $this->set_variable_value($variable_slug,$value);
            }
            
            $key++;
        }

    }

    protected function set_variable_value($slug,$value='null'){
        $this->variables[$slug] = $value;
    }
    
    protected function get_variable_value($slug){

        foreach($this->variables as $variable_slug => $variable){
            
            if ( $variable_slug == $slug ){
                return $variable;
            }
        }

    }
    
    protected function get_request_url(){
        
        if ($this->redirect_url){
            $this->redirect_url = $this->variables_fill_string($this->redirect_url);
            return $this->redirect_url;
        }else{
            return $this->feed_url;
        }

    }
    
    /*
    Override this functions if your preset needs to filter the tracks.  
    Don't forget the call to the parent function at the end.
    */
    
    protected function validate_tracks($tracks){
        return parent::validate_tracks($tracks);
    }

    /**
    If $url_matches is empty, it means that the feed url does not match the pattern.
    **/
    
    public function can_load_tracklist_url($url){

        if (!$this->pattern) return true;

        preg_match($this->pattern, $url, $url_matches);

        return (bool)$url_matches;
    }

}
 