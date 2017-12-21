<?php

class WP_SoundSystem_Core_BuddyPress{
    
    var $music_slug;
    var $favorite_tracks_slug;
    var $static_playlists_slug;
    var $live_playlists_slug;
    var $favorite_tracklists_slug;

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
        $this->music_slug = 'music';
        $this->favorite_tracks_slug = 'favorite-tracks';
        $this->static_playlists_slug = 'static';
        $this->live_playlists_slug = 'live';
        $this->favorite_tracklists_slug = 'favorite-tracklists';
    }

    function setup_actions(){
        add_action( 'bp_setup_nav', array($this,'register_music_menu'), 99 );
        add_action( 'wpsstm_love_track', array($this,'love_track_activity') );
        add_action( 'wpsstm_love_tracklist', array($this,'love_tracklist_activity') );
    }
    
    function register_music_menu() {
        global $bp;
        
        $menu_args = array(
                'name'                      => __('Music','wpsstm'),
                'slug'                      => $this->music_slug,
                'default_subnav_slug'       => $this->static_playlists_slug,
                'position'                  => 50,
                'show_for_displayed_user'   => true,
                //'screen_function' => array($this,'view_user_static_playlists'),
                'item_css_id'               => 'wpsstm-member-tracks-favorite'
        );

        bp_core_new_nav_item( $menu_args );
        
        /*
        Music Submenus
        */
        $this->register_static_playlists_submenu();
        $this->register_live_playlists_submenu();
        $this->register_favorite_tracklists_submenu();
        $this->register_favorite_tracks_submenu();

    }
    
    function register_static_playlists_submenu(){
        global $bp;
        
        //static query
        $query_args = array(
            'post_type' =>      wpsstm()->post_type_playlist,
            'author' =>         bp_displayed_user_id(),
            'posts_per_page' => -1,
            'post_status' =>    ( bp_displayed_user_id() == bp_loggedin_user_id() ) ? 'publish' : array('publish','private','future','pending','draft'),
            'orderby' =>        'title',
            'fields' =>         'ids',
        );
        $query = new WP_Query( $query_args );
        $count = $query->found_posts;
        
        ///
        
		$class = ( 0 === $count ) ? 'no-count' : 'count';

		$name = sprintf(
			__( 'Playlists %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $class ),
				bp_core_number_format( $count )
			)
		);
        
        bp_core_new_subnav_item( array(
            'name'            => $name,
            'slug'            => $this->static_playlists_slug,
            'parent_url'      => $bp->loggedin_user->domain . $this->music_slug . '/',
            'parent_slug'     => $this->music_slug,
            'position'        => 10,
            'screen_function' => array($this,'view_user_static_playlists'),
        ) );
    }
    
    function register_live_playlists_submenu(){
        global $bp;
        
        $query_args = array(
            'post_type' =>      wpsstm()->post_type_live_playlist,
            'author' =>         bp_displayed_user_id(),
            'posts_per_page' => -1,
            'post_status' =>    ( bp_displayed_user_id() == bp_loggedin_user_id() ) ? 'publish' : array('publish','private','future','pending','draft'),
            'orderby' =>        'title',
            'fields' =>         'ids',
        );
        $query = new WP_Query( $query_args );
        $count = $query->found_posts;
        ///
        
		$class = ( 0 === $count ) ? 'no-count' : 'count';

		$name = sprintf(
			__( 'Live playlists %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $class ),
				bp_core_number_format( $count )
			)
		);
        
        bp_core_new_subnav_item( array(
            'name'            => $name,
            'slug'            => $this->live_playlists_slug,
            'parent_url'      => $bp->loggedin_user->domain . $this->music_slug . '/',
            'parent_slug'     => $this->music_slug,
            'position'        => 20,
            'screen_function' => array($this,'view_user_live_playlists'),
        ) );
        
    }
    
    function register_favorite_tracklists_submenu(){
        global $bp;
        
        //favorite query
        $query_args = array(
            'post_type' =>      wpsstm_tracklists()->tracklist_post_types,
            wpsstm_tracklists()->qvar_user_favorites => bp_displayed_user_id(),
            'posts_per_page' => -1,
            'orderby' =>        'title',
            'fields' =>         'ids',
        );

        $query = new WP_Query( $query_args );
        $count = $query->found_posts;

		$class = ( 0 === $count ) ? 'no-count' : 'count';

		$name = sprintf(
			__( 'Favorites tracklists %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $class ),
				bp_core_number_format( $count )
			)
		);
        
        bp_core_new_subnav_item( array(
            'name'            => $name,
            'slug'            => $this->favorite_tracklists_slug,
            'parent_url'      => $bp->loggedin_user->domain . $this->music_slug . '/',
            'parent_slug'     => $this->music_slug,
            'position'        => 40,
            'screen_function' => array($this,'view_user_favorite_tracklists'),
        ) );
    }
    
    function register_favorite_tracks_submenu(){
        global $bp;
        
        //favorite tracks query
        $query_args = array(
            'post_type' =>      wpsstm()->post_type_track,
            'author' =>         bp_displayed_user_id(),
            'posts_per_page' => -1,
            wpsstm_tracks()->qvar_user_favorites => bp_displayed_user_id(),
            'fields' =>         'ids',
        );
        $query = new WP_Query( $query_args );
        $count = $query->found_posts;

		$class = ( 0 === $count ) ? 'no-count' : 'count';

		$name = sprintf(
			__( 'Favorite tracks %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $class ),
				bp_core_number_format( $count )
			)
		);
        bp_core_new_subnav_item( array(
            'name'            => $name,
            'slug'            => $this->favorite_tracks_slug,
            'parent_url'      => $bp->loggedin_user->domain . $this->music_slug . '/',
            'parent_slug'     => $this->music_slug,
            'position'        => 40,
            'screen_function' => array($this,'view_user_favorite_tracks')
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
    
    function view_user_favorite_tracklists() {
        add_action( 'bp_template_title', array($this,'user_favorite_tracklists_subnav_title') );
        add_action( 'bp_template_content', array($this,'user_favorite_tracklists_subnav_content') );
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
    
    function user_favorite_tracklists_subnav_title(){
        $title = sprintf(__("%s's favorite tracklists",'wpsstm'),bp_get_displayed_user_fullname());
        if ( bp_is_my_profile() ) {
            $title = __('Favorite tracklists','wpsstm');
        }
        echo $title;
    }
    
    function user_static_playlists_subnav_content(){
        global $tracklist_manager_query;

        //member static playlists
        $query_args = array(
            'post_type' =>      wpsstm()->post_type_playlist,
            'author' =>         bp_displayed_user_id(),
            'posts_per_page' => -1,
            'post_status' =>    ( bp_displayed_user_id() == bp_loggedin_user_id() ) ? 'publish' : array('publish','private','future','pending','draft'),
            'orderby' =>        'title',
        );
        
        $tracklist_manager_query = new WP_Query( $query_args );
        wpsstm_locate_template( 'list-tracklists.php', true, false );
    }
    
    function user_live_playlists_subnav_content(){
        global $tracklist_manager_query;
        
        //member live playlists
        $query_args = array(
            'post_type' =>      wpsstm()->post_type_live_playlist,
            'author' =>         bp_displayed_user_id(),
            'posts_per_page' => -1,
            'post_status' =>    ( bp_displayed_user_id() == bp_loggedin_user_id() ) ? 'publish' : array('publish','private','future','pending','draft'),
            'orderby' =>        'title',
        );
        
        $tracklist_manager_query = new WP_Query( $query_args );
        wpsstm_locate_template( 'list-tracklists.php', true, false );
    }
    
    function user_favorite_tracklists_subnav_content(){
        global $tracklist_manager_query;

        //member favorite playlists
        $query_args = array(
            'post_type' =>      wpsstm_tracklists()->tracklist_post_types,
            wpsstm_tracklists()->qvar_user_favorites => bp_displayed_user_id(),
            'posts_per_page' => -1,
            'orderby' =>        'title',
        );
        
        $tracklist_manager_query = new WP_Query( $query_args );
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
        $query_args = array(
            'post_type' =>      wpsstm()->post_type_track,
            'author' =>         bp_displayed_user_id(),
            'posts_per_page' => -1,
            wpsstm_tracks()->qvar_user_favorites => bp_displayed_user_id(),
            'fields' =>         'ids',
        );
        $query = new WP_Query( $query_args );
        
        //build playlist track args
        $track_args = array(
            'post__in'  => $query->posts
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
    
    function love_track_activity($track_id){
        $user_link = bp_core_get_userlink( get_current_user_id() );
        $track_link = sprintf('<a href="%s">%s</a>',get_permalink($track_id),get_the_title($track_id));
        $args = array(
            //'id' =>
            'action' =>         sprintf(__('%s loved the track %s','wpsstm'),$user_link,$track_link),
            //'content' =>
            'component' =>      $this->music_slug,
            'type' =>           'loved_track',
            'primary_link' =>   get_permalink($track_id),
            //'user_id' =>        
            'item_id' =>        $track_id,
            //'secondary_item_id' =>
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
            'component' =>      $this->music_slug,
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


function wpsstm_buddypress() {
	return WP_SoundSystem_Core_BuddyPress::instance();
}

wpsstm_buddypress();