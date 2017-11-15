<?php
class WP_Soundsystem_Wizard_LastFM_UserStations_Helper extends WP_Soundsystem_Wizard_Helper{
    var $user;
    function __construct(){
        $this->slug = 'lastfm-user-stations';
        $this->name = __('Last.FM user stations');
    }
    function get_output(){
        $links = array();
        $links_str = array();
        $output = null;
        if ( wpsstm_live_playlists()->is_preset_loaded('last-fm-station-user') ){
            
            $this->user = new WP_SoundSystem_LastFM_User();
            $username = ( $this->user->is_user_api_logged() ) ? $this->user->user_api_metas['username'] : null;
            
            $form = sprintf('<input name="%s-input" value="%s" />',$this->slug,$username);

            if ( $this->user->is_user_api_logged() ){
               
                $widget_link = sprintf('lastfm:user:%s:station:recommended',$username );
                $links['recommendations'] = sprintf('<a href="#">%s</a>',__('Recommendations station','wpsstm') );

                $widget_link = sprintf('lastfm:user:%s:station:library', $username);
                $links['library'] = sprintf('<a href="#">%s</a>',__('Library station','wpsstm') );

                $widget_link = sprintf('lastfm:user:%s:station:mix',$username );
                $links['mix'] = sprintf('<a href="#">%s</a>',__('Mix station','wpsstm') );


            }
        }

        //check and run
        foreach((array)$links as $key=>$link){
            $links_str[] = sprintf('<li id="wpsstm-helper-%s-%s">%s</li>',$this->slug,$key,$link);
        }
        
        return sprintf('<p>%s</p><ul>%s</ul>',$form,implode("\n",$links_str));
        
    }
}

function register_lastfm_helpers($helpers){
    $helpers[] = 'WP_Soundsystem_Wizard_LastFM_UserStations_Helper';
    return $helpers;
}

add_filter('wpsstm_get_wizard_helpers','register_lastfm_helpers');