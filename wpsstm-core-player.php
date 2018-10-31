<?php

class WPSSTM_Core_Player{

    function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_player_scripts_styles_shared' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_player_scripts_styles_shared' ) );

        add_action( 'wp_footer', array($this,'player_html'));
        add_action( 'admin_footer', array($this,'player_html'));

    }

    function player_html(){
	   global $wp_query;
        
        if ( !did_action('init_playable_tracklist') ) return;
        
        ?>
        <div id="wpsstm-bottom-wrapper">
            <div id="wpsstm-bottom">
                <div id="wpsstm-bottom-track-wrapper">
                    <span id="wpsstm-bottom-track"></span>
                    <?php
                    //player actions
                    if ( $actions = $this->get_player_links() ){
                        $list = get_actions_list($actions,'player');
                        echo $list;
                    }                       
                    ?>
                </div>
                <table id="wpsstm-bottom-player-wrapper">
                    <tr>
                        <td id="wpsstm-player-extra-previous-track" class="wpsstm-player-extra"><a href="#"><i class="fa fa-backward" aria-hidden="true"></i></a></td>
                        <td id="wpsstm-player"></td>
                        <td id="wpsstm-player-extra-next-track" class="wpsstm-player-extra"><a href="#"><i class="fa fa-forward" aria-hidden="true"></i></a></td>
                        <td id="wpsstm-player-loop" class="wpsstm-player-extra"><a title="<?php _e('Loop','wpsstm');?>" href="#"><i class="fa fa-refresh" aria-hidden="true"></i></a></td>
                        <td id="wpsstm-player-shuffle" class="wpsstm-player-extra"><a title="<?php _e('Random Wisdom','wpsstm');?>" href="#"><i class="fa fa-random" aria-hidden="true"></i></a></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    function enqueue_player_scripts_styles_shared(){
        //TO FIX load only if player is loaded (see hook init_playable_tracklist ) ?
        
        //CSS
        wp_enqueue_style('wp-mediaelement');

        //JS
        wp_enqueue_script( 'wpsstm-player', wpsstm()->plugin_url . '_inc/js/wpsstm-player.js', array('wpsstm','wp-mediaelement'),wpsstm()->version, true);
        
        //localize vars
        $localize_vars=array(
            'leave_page_text'       =>      __('A track is currently playing.  Are u sure you want to leave ?','wpsstm'),
            'plugin_path'           =>      trailingslashit( get_bloginfo('url') ) . WPINC . '/js/mediaelement/', //do not forget final slash here
            'default_tracklist_options' =>  WPSSTM_Static_Tracklist::get_default_options(),
        );

        wp_localize_script('wpsstm-player','wpsstmPlayer', $localize_vars);
        
    }

    function get_track_button(){
        //https://wordpress.stackexchange.com/a/162945/70449
        $link = '<a class="wpsstm-icon wpsstm-icon-link" href="#"><i class="wpsstm-player-icon wpsstm-player-icon-error fa fa-exclamation-triangle" aria-hidden="true"></i><i class="wpsstm-player-icon wpsstm-player-icon-pause fa fa-pause" aria-hidden="true"></i><i class="wpsstm-player-icon wpsstm-player-icon-play fa fa-play" aria-hidden="true"></i></a>';

        return $link;

    }
    
    function get_player_links(){
        $actions = array();
        return apply_filters('wpsstm_get_player_actions',$actions);
    }
    
}