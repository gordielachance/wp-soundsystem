<?php
class WP_Soundsystem_Wizard_Artist_Helper extends WP_Soundsystem_Wizard_Helper{
    var $user;
    function __construct(){
        $this->slug = 'artist';
        $this->name = __('Artist','wpsstm');
    }
    
    static function can_show_helper(){
        return in_array('WP_SoundSystem_Preset_LastFM_Station_User_Recommandations_Scraper',wpsstm_live_playlists()->presets);
    }
    
    function get_output(){
        $links = array();
        $links_str = array();
        $output = null;
            
        $this->user = new WP_SoundSystem_LastFM_User();
        $username = ( $this->user->is_user_api_logged() ) ? $this->user->user_api_metas['username'] : null;

        $form = sprintf('<input class="wpsstm-artist-autocomplete" type="text" placeholder="%s" value="%s" />',__('Artist name','wpsstm'),'');

        if ( in_array('WP_SoundSystem_Preset_LastFM_Artist_Scraper',wpsstm_live_playlists()->presets) ){
            $widget_link = sprintf('lastfm:user:%s:station:library', $username);
            $links['top-tracks'] = sprintf('<a href="#">%s</a>',__('Top tracks','wpsstm') );
        }

        if ( in_array('WP_SoundSystem_Preset_LastFM_Station_Similar_Artist_Scraper',wpsstm_live_playlists()->presets) ){
            $widget_link = sprintf('lastfm:user:%s:station:recommended',$username );
            $links['similar'] = sprintf('<a href="#">%s</a>',__('Similar artists station','wpsstm') );
        }

        //check and run
        foreach((array)$links as $key=>$link){
            $links_str[] = sprintf('<li id="wpsstm-wizard-helper-%s-%s">%s</li>',$this->slug,$key,$link);
        }
        
        return sprintf('<p>%s</p><ul>%s</ul>',$form,implode("\n",$links_str));
        
    }
}

function register_artist_helpers($helpers){
    $helpers[] = 'WP_Soundsystem_Wizard_Artist_Helper';
    return $helpers;
}

add_filter('wpsstm_get_wizard_helpers','register_artist_helpers');