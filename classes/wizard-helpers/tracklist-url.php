<?php
class WP_Soundsystem_Wizard_Tracklist_Url_Helper extends WP_Soundsystem_Wizard_Helper{

    function __construct(){
        $this->slug = 'tracklist-url';
        $this->name = __('Websites','wpsstm');
    }

    function get_output(){
        //supported URLs
        $presets_list = array();
        $presets_list_str = null;
        foreach ((array)wpsstm_live_playlists()->presets as $preset){
            if ( !$preset::$wizard_suggest ) continue;
            $preset_str = $preset->preset_name;
            if ($preset->preset_url){
                $preset_str = sprintf('<a data-wpsstm-wizard-hover="%s" href="%s" title="%s" target="_blank">%s</a>',$preset->preset_url,$preset->preset_url,$preset->preset_desc,$preset_str);
            }
            $presets_list[] = $preset_str;
        }
        
        if ( empty($presets_list) ) return;
        
        //wrap
        $presets_list = array_map(
           function ($el) {
              return "<li>{$el}</li>";
           },
           $presets_list
        );
        
        return sprintf('<ul>%s</ul>',implode("\n",$presets_list));
    }
}

function register_tracklist_url_helper($helpers){
    $helpers[] = 'WP_Soundsystem_Wizard_Tracklist_Url_Helper';
    return $helpers;
}

//add_filter('wpsstm_get_wizard_helpers','register_tracklist_url_helper');