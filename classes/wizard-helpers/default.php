<?php
abstract class WP_Soundsystem_Wizard_Helper {
    var $slug;
    var $name;
    var $desc;
    function __construct(){
           
    }
    
    static function can_show_helper(){
        return true;
    }

    function get_output(){
        
    }
}