<?php

class WP_SoundSystem_Core_BuddyPress{

    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSystem_Core_BuddyPress;
                    self::$instance->init();
            }
            return self::$instance;
    }
    
    private function __construct() { /* Do nothing here */ }
    
    function init(){
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
        
    }
    
    function setup_globals(){

    }

    function setup_actions(){
        add_action( 'bp_setup_nav', array($this,'register_member_playlists_menu'), 99 );
    }
    
    function register_member_playlists_menu() {
        global $bp;
        
        // Determine user to use.
        $user_id = bp_displayed_user_id();
        
        $menu_tracks_slug = 'tracks';
        $submenu_favorite_tracks_slug = 'favorite';
        $menu_playlists_slug = 'playlists';
        $submenu_static_playlists_slug = 'static';
        $submenu_live_playlists_slug = 'live';
        $submenu_favorite_playlists_slug = 'favorite';
        
		/*
        Tracks Menu
        */
        
        //favorite tracks query
        $favorite_tracks_args = array(
            'post_type' =>      wpsstm()->post_type_track,
            'author' =>         bp_displayed_user_id(),
            'posts_per_page' => -1,
            wpsstm_tracks()->qvar_user_favorites => bp_displayed_user_id(),
            'fields' =>         'ids',
        );
        $member_favorite_tracks = new WP_Query( $favorite_tracks_args );

		$favorite_track_class = ( 0 === $member_favorite_tracks->found_posts ) ? 'no-count' : 'count';

		$menu_tracks_name = sprintf(
			__( 'Tracks %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $favorite_track_class ),
				bp_core_number_format( $member_favorite_tracks->found_posts )
			)
		);

        $tracks_menu_args = array(
                'name'                      => $menu_tracks_name,
                'slug'                      => $menu_tracks_slug,
                'default_subnav_slug'       => $submenu_favorite_tracks_slug,
                'position'                  => 50,
                'show_for_displayed_user'   => true,
                //'screen_function'           => array($this,'view_user_favorite_tracks'),
                'item_css_id'               => 'wpsstm-member-tracks-favorite'
        );

        bp_core_new_nav_item( $tracks_menu_args );
        
        /*
        Tracks Submenus
        */

		$submenu_loved_tracks_name = sprintf(
			__( 'Favorite tracks %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $favorite_track_class ),
				bp_core_number_format( $member_favorite_tracks->found_posts )
			)
		);
        
        bp_core_new_subnav_item( array(
            'name'            => $submenu_loved_tracks_name,
            'slug'            => $submenu_favorite_tracks_slug,
            'parent_url'      => $bp->loggedin_user->domain . $menu_playlists_slug . '/',
            'parent_slug'     => $menu_tracks_slug,
            'position'        => 10,
            'screen_function' => array($this,'view_user_favorite_tracks')
        ) );
        
        
		/*
        Playlist Menu
        */
        
        //static query
        $static_playlists_args = array(
            'post_type' =>      wpsstm()->post_type_playlist,
            'author' =>         bp_displayed_user_id(),
            'posts_per_page' => -1,
            'post_status' =>    ( bp_displayed_user_id() == bp_loggedin_user_id() ) ? 'publish' : array('publish','private','future','pending','draft'),
            'orderby' =>        'title',
            'fields' =>         'ids',
        );
        $member_static_playlists = new WP_Query( $static_playlists_args );
        
        //live query
        $live_playlists_args = array(
            'post_type' =>      wpsstm()->post_type_live_playlist,
            'author' =>         bp_displayed_user_id(),
            'posts_per_page' => -1,
            'post_status' =>    ( bp_displayed_user_id() == bp_loggedin_user_id() ) ? 'publish' : array('publish','private','future','pending','draft'),
            'orderby' =>        'title',
            'fields' =>         'ids',
        );
        $member_live_playlists = new WP_Query( $live_playlists_args );
        
        //favorite query
        $favorite_playlists_args = array(
            'post_type' =>      wpsstm()->post_type_playlist,
            wpsstm_tracklists()->qvar_user_favorites => bp_displayed_user_id(),
            'posts_per_page' => -1,
            'orderby' =>        'title',
            'fields' =>         'ids',
        );

        $member_favorite_playlists = new WP_Query( $favorite_playlists_args );
        
        
        //total playlists
		$all_playlists_count = $member_static_playlists->found_posts + $member_live_playlists->found_posts + $member_favorite_playlists->found_posts;
		$all_playlists_class = ( 0 === $all_playlists_count ) ? 'no-count' : 'count';

		$menu_playlists_name = sprintf(
			__( 'Tracklists %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $all_playlists_class ),
				bp_core_number_format( $all_playlists_count )
			)
		);

        $playlists_menu_args = array(
                'name'                      => $menu_playlists_name,
                'slug'                      => $menu_playlists_slug,
                'default_subnav_slug'       => $submenu_static_playlists_slug,
                'position'                  => 50,
                'show_for_displayed_user'   => true,
                //'screen_function' => array($this,'view_user_static_playlists'),
                'item_css_id'               => 'wpsstm-member-tracks-favorite'
        );

        bp_core_new_nav_item( $playlists_menu_args );
        
        /*
        Playlists Submenus
        */

        //member static playlists
		$static_playlists_class = ( 0 === $member_static_playlists->found_posts ) ? 'no-count' : 'count';

		$submenu_playlists_name = sprintf(
			__( 'Playlists %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $static_playlists_class ),
				bp_core_number_format( $member_static_playlists->found_posts )
			)
		);
        
        bp_core_new_subnav_item( array(
            'name'            => $submenu_playlists_name,
            'slug'            => $submenu_static_playlists_slug,
            'parent_url'      => $bp->loggedin_user->domain . $menu_playlists_slug . '/',
            'parent_slug'     => $menu_playlists_slug,
            'position'        => 10,
            'screen_function' => array($this,'view_user_static_playlists'),
        ) );
        
        //member live playlists
		$live_playlists_class = ( 0 === $member_live_playlists->found_posts ) ? 'no-count' : 'count';
        
        

		$submenu_live_playlists_name = sprintf(
			__( 'Live playlists %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $live_playlists_class ),
				bp_core_number_format( $member_live_playlists->found_posts )
			)
		);
        
        bp_core_new_subnav_item( array(
            'name'            => $submenu_live_playlists_name,
            'slug'            => $submenu_live_playlists_slug,
            'parent_url'      => $bp->loggedin_user->domain . $menu_playlists_slug . '/',
            'parent_slug'     => $menu_playlists_slug,
            'position'        => 20,
            'screen_function' => array($this,'view_user_live_playlists'),
        ) );
        
        //member favorite playlists
		$favorite_playlists_class = ( 0 === $member_favorite_playlists->found_posts ) ? 'no-count' : 'count';

		$submenu_favorite_playlists_name = sprintf(
			__( 'Favorites %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $favorite_playlists_class ),
				bp_core_number_format( $member_favorite_playlists->found_posts )
			)
		);
        
        bp_core_new_subnav_item( array(
            'name'            => $submenu_favorite_playlists_name,
            'slug'            => $submenu_favorite_playlists_slug,
            'parent_url'      => $bp->loggedin_user->domain . $menu_playlists_slug . '/',
            'parent_slug'     => $menu_playlists_slug,
            'position'        => 20,
            'screen_function' => array($this,'view_user_favorite_playlists'),
        ) );
    }

    function view_user_static_playlists() {
        add_action( 'bp_template_title', array($this,'user_static_playlists_subnav_title') );
        add_action( 'bp_template_content', array($this,'user_static_playlists_subnav_content') );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }
    
    function view_user_live_playlists() {
        add_action( 'bp_template_title', array($this,'user_live_playlists_subnav_title') );
        add_action( 'bp_template_content', array($this,'user_live_playlists_subnav_content') );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }
    
    function view_user_favorite_playlists() {
        add_action( 'bp_template_title', array($this,'user_favorite_playlists_subnav_title') );
        add_action( 'bp_template_content', array($this,'user_favorite_playlists_subnav_content') );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }

    function user_static_playlists_subnav_title(){
        $title = sprintf(__("%s's playlists",'wpsstm'),bp_get_displayed_user_fullname());
        if ( bp_is_my_profile() ) {
            $title = __('Playlists','wpsstm');
        }
        echo $title;
    }
    
    function user_live_playlists_subnav_title(){
        $title = sprintf(__("%s's live playlists",'wpsstm'),bp_get_displayed_user_fullname());
        if ( bp_is_my_profile() ) {
            $title = __('Live playlists','wpsstm');
        }
        echo $title;
    }
    
    function user_favorite_playlists_subnav_title(){
        $title = sprintf(__("%s's favorite playlists",'wpsstm'),bp_get_displayed_user_fullname());
        if ( bp_is_my_profile() ) {
            $title = __('Favorite playlists','wpsstm');
        }
        echo $title;
    }
    
    function user_static_playlists_subnav_content(){
        global $tracklist_manager_query;

        //member static playlists
        $static_playlists_args = array(
            'post_type' =>      wpsstm()->post_type_playlist,
            'author' =>         bp_displayed_user_id(),
            'posts_per_page' => -1,
            'post_status' =>    ( bp_displayed_user_id() == bp_loggedin_user_id() ) ? 'publish' : array('publish','private','future','pending','draft'),
            'orderby' =>        'title',
        );
        
        $tracklist_manager_query = new WP_Query( $static_playlists_args );
        wpsstm_locate_template( 'list-tracklists.php', true, false );
    }
    
    function user_live_playlists_subnav_content(){
        global $tracklist_manager_query;
        
        //member live playlists
        $live_playlists_args = array(
            'post_type' =>      wpsstm()->post_type_live_playlist,
            'author' =>         bp_displayed_user_id(),
            'posts_per_page' => -1,
            'post_status' =>    ( bp_displayed_user_id() == bp_loggedin_user_id() ) ? 'publish' : array('publish','private','future','pending','draft'),
            'orderby' =>        'title',
        );
        
        $tracklist_manager_query = new WP_Query( $live_playlists_args );
        wpsstm_locate_template( 'list-tracklists.php', true, false );
    }
    
    function user_favorite_playlists_subnav_content(){
        global $tracklist_manager_query;

        //member favorite playlists
        $favorite_playlists_args = array(
            'post_type' =>      wpsstm()->post_type_playlist,
            wpsstm_tracklists()->qvar_user_favorites => bp_displayed_user_id(),
            'posts_per_page' => -1,
            'orderby' =>        'title',
        );
        
        $tracklist_manager_query = new WP_Query( $favorite_playlists_args );
        wpsstm_locate_template( 'list-tracklists.php', true, false );
    }
    
    function view_user_favorite_tracks(){
        add_action( 'bp_template_title', array($this,'user_favorite_tracks_subnav_title') );
        add_action( 'bp_template_content', array($this,'user_favorite_tracks_subnav_content') );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }
    
    function user_favorite_tracks_subnav_title(){
        $title = sprintf(__("%s's favorite tracks",'wpsstm'),bp_get_displayed_user_fullname());
        if ( bp_is_my_profile() ) {
            $title = __('Favorite tracks','wpsstm');
        }
        echo $title;
    }
    
    function user_favorite_tracks_subnav_content(){
        global $wpsstm_tracklist;
        $wpsstm_tracklist = $this->member_get_favorite_tracks_playlist();
        echo $wpsstm_tracklist->get_tracklist_html();
    }
    
    /*
    TO FIX in the end, this should be part of core-playlists.php
    */

    public function member_get_favorite_tracks_playlist(){

        //favorite tracks query
        $favorite_tracks_args = array(
            'post_type' =>      wpsstm()->post_type_track,
            'author' =>         bp_displayed_user_id(),
            'posts_per_page' => -1,
            wpsstm_tracks()->qvar_user_favorites => bp_displayed_user_id(),
            'fields' =>         'ids',
        );
        $member_favorite_tracks = new WP_Query( $favorite_tracks_args );
        
        //build playlist track args
        $track_args = array(
            'post__in'  => $member_favorite_tracks->posts
        );

        $user_datas = get_userdata( bp_displayed_user_id() );
        $display_name = $user_datas->display_name;
        $tracklist_title = sprintf(__("%s's favorite tracks",'wpsstm'),$display_name);
        
        $tracklist = new WP_SoundSystem_Tracklist();
        $tracklist->title = $tracklist_title;
        $tracklist->author = $display_name;
        
        //WIP TO FIX NOT WORKING YET
        $tracklist->populate_subtracks($track_args);
        return $tracklist;
    }
    

}


function wpsstm_buddypress() {
	return WP_SoundSystem_Core_BuddyPress::instance();
}

wpsstm_buddypress();