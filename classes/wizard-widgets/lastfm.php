<?php
class WP_Soundsystem_Wizard_LastFM_UserStations_Widget extends WP_Soundsystem_Wizard_Widget{
    var $user;
    function __construct(){
        $this->slug = 'lastfm-user-stations';
        $this->name = __('Last.fm user stations');
    }
    function get_output(){
        $links = array();
        $links_str = array();
        $output = $form = null;
        
        if ( in_array('WP_SoundSystem_LastFM_User_Recommandations_Station',wpsstm_live_playlists()->presets) ){

            $this->user = new WP_SoundSystem_LastFM_User();
            $username = ( $this->user->is_user_api_logged() ) ? $this->user->user_api_metas['username'] : null;
            
            $form = sprintf('<input type="text" name="%s-input" value="%s" placeholder="%s" />',$this->slug,$username,__('Last.fm username','wpsstm'));

            $widget_link = sprintf('lastfm:user:%s:station:recommended',$username );
            $links['recommendations'] = sprintf('<a href="#">%s</a>',__('Recommendations station','wpsstm') );

            $widget_link = sprintf('lastfm:user:%s:station:library', $username);
            $links['library'] = sprintf('<a href="#">%s</a>',__('Library station','wpsstm') );

            $widget_link = sprintf('lastfm:user:%s:station:mix',$username );
            $links['mix'] = sprintf('<a href="#">%s</a>',__('Mix station','wpsstm') );

        }

        //check and run
        foreach((array)$links as $key=>$link){
            $links_str[] = sprintf('<li id="wpsstm-wizard-widget-%s-%s">%s</li>',$this->slug,$key,$link);
        }
        
        if ($links_str){
            return sprintf('<p>%s</p><ul>%s</ul>',$form,implode("\n",$links_str));
        }

    }
}

function register_lastfm_widgets($helpers){
    $helpers[] = 'WP_Soundsystem_Wizard_LastFM_UserStations_Widget';
    return $helpers;
}

add_filter('wpsstm_get_wizard_widgets','register_lastfm_widgets');