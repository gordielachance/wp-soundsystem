<?php
class WP_Soundsystem_Wizard_Artist_Widget extends WP_Soundsystem_Wizard_Widget{
    var $user;
    function __construct(){
        $this->slug = 'artist';
        $this->name = __('Artist','wpsstm');
    }
    
    function get_output(){
        $links = array();
        $links_str = array();
        $output = null;
            
        $this->user = new WP_SoundSystem_LastFM_User();
        $username = ( $this->user->is_user_api_logged() ) ? $this->user->user_api_metas['username'] : null;

        $form = sprintf('<input class="wpsstm-artist-autocomplete" type="text" placeholder="%s" value="%s" />',__('Artist name','wpsstm'),'');

        if ( in_array('WP_SoundSystem_LastFM_Artist_URL',wpsstm_live_playlists()->presets) ){
            $widget_link = sprintf('lastfm:user:%s:station:library', $username);
            $links['top-tracks'] = sprintf('<a href="#">%s</a>',__('Top tracks','wpsstm') );
        }

        if ( in_array('WP_SoundSystem_LastFM_Similar_Artist_Station',wpsstm_live_playlists()->presets) ){
            $widget_link = sprintf('lastfm:user:%s:station:recommended',$username );
            $links['similar'] = sprintf('<a href="#">%s</a>',__('Similar artists station','wpsstm') );
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

function register_artist_widget($helpers){
    $helpers[] = 'WP_Soundsystem_Wizard_Artist_Widget';
    return $helpers;
}

add_filter('wpsstm_get_wizard_widgets','register_artist_widget');