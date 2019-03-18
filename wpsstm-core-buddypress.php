<?php

class WPSSTM_Core_BuddyPress{

    function __construct() {

        define("WPSSTM_FAVORITE_TRACKS_SLUG", "favorite-tracks");
        define("WPSSTM_FAVORITE_TRACKLISTS_SLUG", "favorite-tracklists");
        
        add_action( 'bp_setup_nav', array($this,'register_music_menu'), 99 );
        add_action( 'wpsstm_queue_track', array($this,'queue_track_activity'), 10, 2 );
        add_action( 'wpsstm_love_tracklist', array($this,'love_tracklist_activity') );
    }
    
    function register_music_menu() {
        global $bp;
        
        $menu_args = array(
                'name'                      => __('Music','wpsstm'),
                'slug'                      => WPSSTM_BASE_SLUG,
                'default_subnav_slug'       => WPSSTM_PLAYLISTS_SLUG,
                'position'                  => 50,
                'show_for_displayed_user'   => true,
                //'screen_function' => array($this,'view_user_static_playlists'),
                'item_css_id'               => 'wpsstm-member-tracks-favorite'
        );

        bp_core_new_nav_item( $menu_args );
        
        /*
        Music Submenus
        */
        bp_core_new_subnav_item( array(
            'name'            => __( 'Playlists', 'wpsstm' ),
            'slug'            => WPSSTM_PLAYLISTS_SLUG,
            'parent_url'      => $bp->loggedin_user->domain . WPSSTM_BASE_SLUG . '/',
            'parent_slug'     => WPSSTM_BASE_SLUG,
            'position'        => 10,
            'screen_function' => array($this,'view_user_static_playlists'),
        ) );
        
        
        bp_core_new_subnav_item( array(
            'name'            => __( 'Radios', 'wpsstm' ),
            'slug'            => WPSSTM_LIVE_PLAYLISTS_SLUG,
            'parent_url'      => $bp->loggedin_user->domain . WPSSTM_BASE_SLUG . '/',
            'parent_slug'     => WPSSTM_BASE_SLUG,
            'position'        => 20,
            'screen_function' => array($this,'view_user_live_playlists'),
        ) );
        
        
        bp_core_new_subnav_item( array(
            'name'            => __( 'Favorited tracklists', 'wpsstm' ),
            'slug'            => WPSSTM_FAVORITE_TRACKLISTS_SLUG,
            'parent_url'      => $bp->loggedin_user->domain . WPSSTM_BASE_SLUG . '/',
            'parent_slug'     => WPSSTM_BASE_SLUG,
            'position'        => 30,
            'screen_function' => array($this,'view_user_favorite_tracklists'),
        ) );

        bp_core_new_subnav_item( array(
            'name'            => __( 'Favorited tracks', 'wpsstm' ),
            'slug'            => WPSSTM_FAVORITE_TRACKS_SLUG,
            'parent_url'      => $bp->loggedin_user->domain . WPSSTM_BASE_SLUG . '/',
            'parent_slug'     => WPSSTM_BASE_SLUG,
            'position'        => 40,
            'screen_function' => array($this,'view_user_favorite_tracks')
        ) );
        
        /*
        TOUFIX user settings
        bp_core_new_subnav_item( array(
            'name'            => __( 'Settings', 'buddypress' ),
            'slug'            => bp_get_settings_slug(),
            'parent_url'      => $bp->loggedin_user->domain . WPSSTM_BASE_SLUG . '/',
            'parent_slug'     => WPSSTM_BASE_SLUG,
            'position'        => 90,
            'screen_function' => array($this,'view_user_settings')
        ) );
        */
        

    }
    
    /*
    User static tracklists
    */

    function view_user_static_playlists() {
        add_action( 'bp_template_title', array($this,'user_static_playlists_subnav_title') );
        add_action( 'bp_template_content', array($this,'user_static_playlists_subnav_content') );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }
    
    function user_static_playlists_subnav_title(){
        $title = sprintf(__("%s's playlists",'wpsstm'),bp_get_displayed_user_fullname());
        if ( bp_is_my_profile() ) {
            $title = __('Playlists','wpsstm');
        }
        echo $title;
    }
    
    function user_static_playlists_subnav_content(){
        
        function displayed_user_playlists_manager_args($args){
            //member static playlists
            $new = array(
                'post_type' =>      wpsstm()->post_type_playlist,
                'author' =>         bp_displayed_user_id()
            );

            return wp_parse_args($new,$args);
        }
        
        add_filter( 'wpsstm_tracklist_list_query','displayed_user_playlists_manager_args' );
        wpsstm_locate_template( 'tracklists-list.php', true, false );
    }
    
    /*
    User live tracklists
    */
    
    function view_user_live_playlists() {
        add_action( 'bp_template_title', array($this,'user_live_playlists_subnav_title') );
        add_action( 'bp_template_content', array($this,'user_live_playlists_subnav_content') );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }
    
    function user_live_playlists_subnav_title(){
        $title = sprintf(__("%s's radios",'wpsstm'),bp_get_displayed_user_fullname());
        if ( bp_is_my_profile() ) {
            $title = __('Radios','wpsstm');
        }
        echo $title;
    }
    

    function user_live_playlists_subnav_content(){
        
        function displayed_user_live_playlists_manager_args($args){
            //member static playlists
            $new = array(
                'post_type' =>      wpsstm()->post_type_live_playlist,
                'author' =>         bp_displayed_user_id()
            );

            return wp_parse_args($new,$args);
        }
        
        add_filter( 'wpsstm_tracklist_list_query','displayed_user_live_playlists_manager_args' );
        wpsstm_locate_template( 'tracklists-list.php', true, false );
    }
    
    /*
    User favorite tracklists
    */
    
    function view_user_favorite_tracklists() {
        //TOUFIX broken
        add_action( 'bp_template_title', array($this,'user_favorite_tracklists_subnav_title') );
        add_action( 'bp_template_content', array($this,'user_favorite_tracklists_subnav_content') );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }
    
    function user_favorite_tracklists_subnav_title(){
        $title = sprintf(__("%s's favorited tracklists",'wpsstm'),bp_get_displayed_user_fullname());
        if ( bp_is_my_profile() ) {
            $title = __('Favorited tracklists','wpsstm');
        }
        echo $title;
    }
    function user_favorite_tracklists_subnav_content(){
        
        function displayed_user_favorite_tracklist_manager_args($args){
            //member static playlists
            $new = array(
                'post_type' =>                  wpsstm()->tracklist_post_types,
                'author' =>                     null,
                'tracklists-favorited-by' =>    bp_displayed_user_id(),
                'post_type' =>      wpsstm()->post_type_live_playlist,
                //'author' =>         bp_displayed_user_id()
            );

            return wp_parse_args($new,$args);
        }
        
        add_filter( 'wpsstm_tracklist_list_query','displayed_user_favorite_tracklist_manager_args' );
        wpsstm_locate_template( 'tracklists-list.php', true, false );
    }
    
    /*
    User favorite tracs
    */
    
    function view_user_favorite_tracks(){
        add_action( 'bp_template_title', array($this,'user_favorite_tracks_subnav_title') );
        add_action( 'bp_template_content', array($this,'user_favorite_tracks_subnav_content') );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }
    
    function user_favorite_tracks_subnav_title(){
        $user_id = bp_displayed_user_id();
        $tracklist_id = wpsstm()->user->favorites_id;
        echo get_the_title($tracklist_id);
    }

    function user_favorite_tracks_subnav_content(){
        global $wpsstm_tracklist;
        
        //set global $wpsstm_tracklist
        $user_id = bp_displayed_user_id();
        $tracklist_id = wpsstm()->user->favorites_id;
        $wpsstm_tracklist = new WPSSTM_Post_Tracklist($tracklist_id);
        echo $wpsstm_tracklist->get_tracklist_html();
    }
    
    /*
    User music settings
    */
    
    function view_user_settings(){
        //TOUFIX TOCHECK useful ? add_action( 'bp_template_title', array($this,'user_settings_subnav_title') );
        add_action( 'bp_template_content', array($this,'user_settings_subnav_content') );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }
    
    function user_settings_subnav_title(){
        return __( 'Settings', 'buddypress' );
    }

    function user_settings_subnav_content(){
        wpsstm_locate_template( 'user-settings.php', true );
    }

    function queue_track_activity($track,$tracklist_id){
        
        $user_link = bp_core_get_userlink( get_current_user_id() );
        $track_link = sprintf('<strong>%s</strong>',(string)$track);
        $tracklist_link = sprintf('<a href="%s">%s</a>',get_permalink($tracklist_id),get_the_title($tracklist_id));
        
        $action_text = sprintf(__('%s queued the track %s to %s','wpsstm'),$user_link,$track_link,$tracklist_link);
        
        //from tracklist
        if ($track->from_tracklist && get_post_type($track->from_tracklist) ){
            $from_tracklist_link = sprintf('<a href="%s">%s</a>',get_permalink($track->from_tracklist),get_the_title($track->from_tracklist));
            $action_text = sprintf(__('%s spotted the track %s in %s and queued it to %s','wpsstm'),$user_link,$track_link,$from_tracklist_link,$tracklist_link);
        }

        $args = array(
            //'id' =>
            'action' =>         $action_text,
            //'content' =>
            'component' =>      WPSSTM_BASE_SLUG,
            'type' =>           'queue_track',
            'primary_link' =>   get_permalink($track->post_id),
            //'user_id' =>        
            'item_id' =>        $track->subtrack_id,
            'secondary_item_id' => $tracklist_id,
            //'recorded_time' =>
            //'hide_sitewide' =>
            //'is_spam' =>
            
        );
        $activity_id = bp_activity_add($args);
    }
    
    function love_tracklist_activity($tracklist_id){
        $user_link = bp_core_get_userlink( get_current_user_id() );
        $tracklist_link = sprintf('<a href="%s">%s</a>',get_permalink($tracklist_id),get_the_title($tracklist_id));

        //TO FIX 
        //switch different action depending on the post type ?
        
        $args = array(
            //'id' =>
            'action' =>         sprintf(__('%s loved the tracklist %s','wpsstm'),$user_link,$tracklist_link),
            //'content' =>
            'component' =>      WPSSTM_BASE_SLUG,
            'type' =>           'loved_tracklist',
            'primary_link' =>   get_permalink($tracklist_id),
            //'user_id' =>        
            'item_id' =>        $tracklist_id,
            //'secondary_item_id' =>
            //'recorded_time' =>
            //'hide_sitewide' =>
            //'is_spam' =>
            
        );
        $activity_id = bp_activity_add($args);
    }
    
}

function wpsstm_buddypress_init(){
    new WPSSTM_Core_BuddyPress();
}

add_action('bp_include','wpsstm_buddypress_init');