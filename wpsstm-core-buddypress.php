<?php

class WPSSTM_Core_BuddyPress{
    
    static $music_slug = 'music';
    static $favorite_tracks_slug = 'favorite-tracks';
    static $static_playlists_slug = 'static';
    static $live_playlists_slug = 'live';
    static $favorite_tracklists_slug = 'favorite-tracklists';

    function __construct() {
        add_action( 'bp_setup_nav', array($this,'register_music_menu'), 99 );
        add_action( 'wpsstm_love_track', array($this,'love_track_activity') );
        add_action( 'wpsstm_love_tracklist', array($this,'love_tracklist_activity') );
    }
    
    function register_music_menu() {
        global $bp;
        
        $menu_args = array(
                'name'                      => __('Music','wpsstm'),
                'slug'                      => self::$music_slug,
                'default_subnav_slug'       => self::$static_playlists_slug,
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
            'slug'            => self::$static_playlists_slug,
            'parent_url'      => $bp->loggedin_user->domain . self::$music_slug . '/',
            'parent_slug'     => self::$music_slug,
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
            'slug'            => self::$live_playlists_slug,
            'parent_url'      => $bp->loggedin_user->domain . self::$music_slug . '/',
            'parent_slug'     => self::$music_slug,
            'position'        => 20,
            'screen_function' => array($this,'view_user_live_playlists'),
        ) );
        
    }
    
    function register_favorite_tracklists_submenu(){
        global $bp;
        
        //favorite query
        $query_args = array(
            'post_type' =>                                      wpsstm()->tracklist_post_types,
            WPSSTM_Core_Tracklists::$qvar_loved_tracklists =>   true,
            'author' =>                                         bp_displayed_user_id(),
            'posts_per_page' =>                                 -1,
            'orderby' =>                                        'title',
            'fields' =>                                         'ids',
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
            'slug'            => self::$favorite_tracklists_slug,
            'parent_url'      => $bp->loggedin_user->domain . self::$music_slug . '/',
            'parent_slug'     => self::$music_slug,
            'position'        => 40,
            'screen_function' => array($this,'view_user_loved_tracklists'),
        ) );
    }
    
    function register_favorite_tracks_submenu(){
        global $bp;
        
        //favorite tracks query
        //TOUFIX broken
        $query_args = array(
            'post_type' =>      wpsstm()->post_type_track,
            'posts_per_page' => -1,
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
            'slug'            => self::$favorite_tracks_slug,
            'parent_url'      => $bp->loggedin_user->domain . self::$music_slug . '/',
            'parent_slug'     => self::$music_slug,
            'position'        => 40,
            'screen_function' => array($this,'view_user_loved_tracks')
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
    
    function view_user_loved_tracklists() {
        add_action( 'bp_template_title', array($this,'user_loved_tracklists_subnav_title') );
        add_action( 'bp_template_content', array($this,'user_loved_tracklists_subnav_content') );
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
    
    function user_loved_tracklists_subnav_title(){
        $title = sprintf(__("%s's favorite tracklists",'wpsstm'),bp_get_displayed_user_fullname());
        if ( bp_is_my_profile() ) {
            $title = __('Favorite tracklists','wpsstm');
        }
        echo $title;
    }
    
    function displayed_user_playlists_manager_args($args){
        //member static playlists
        $new = array(
            'post_type' =>      wpsstm()->post_type_playlist,
            'author' =>         bp_displayed_user_id()
        );
        
        return wp_parse_args($new,$args);
    }
    
    function displayed_user_live_playlists_manager_args($args){
        //member static playlists
        $new = array(
            'post_type' =>      wpsstm()->post_type_live_playlist,
            'author' =>         bp_displayed_user_id()
        );
        
        return wp_parse_args($new,$args);
    }
    
    function displayed_user_loved_tracklists_manager_args($args){
        //member static playlists
        $new = array(
            WPSSTM_Core_Tracklists::$qvar_loved_tracklists =>       true,
            'author' =>                                             bp_displayed_user_id()
        );
        
        return wp_parse_args($new,$args);
    }

    function user_static_playlists_subnav_content(){
        add_filter( 'wpsstm_tracklists_manager_query',array($this->displayed_user_playlists_manager_args) );
        wpsstm_locate_template( 'tracklists-list.php', true, false );
    }
    
    function user_live_playlists_subnav_content(){
        add_filter( 'wpsstm_tracklists_manager_query',array($this->displayed_user_live_playlists_manager_args) );
        wpsstm_locate_template( 'tracklists-list.php', true, false );
    }
    
    function user_loved_tracklists_subnav_content(){
        add_filter( 'wpsstm_tracklists_manager_query',array($this->displayed_user_loved_tracklists_manager_args) );
        wpsstm_locate_template( 'tracklists-list.php', true, false );
    }
    
    function view_user_loved_tracks(){
        add_action( 'bp_template_title', array($this,'user_loved_tracks_subnav_title') );
        add_action( 'bp_template_content', array($this,'user_loved_tracks_subnav_content') );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }
    
    function user_loved_tracks_subnav_title(){
        $title = sprintf(__("%s's favorite tracks",'wpsstm'),bp_get_displayed_user_fullname());
        if ( bp_is_my_profile() ) {
            $title = __('Favorite tracks','wpsstm');
        }
        echo $title;
    }
    
    function user_loved_tracks_subnav_content(){
        global $wpsstm_tracklist;
        
        //set global $wpsstm_tracklist
        $wpsstm_tracklist = $this->member_get_favorite_tracks_playlist();
        echo $wpsstm_tracklist->get_tracklist_html();
    }
    
    /*
    TO FIX in the end, this should be part of core-playlists.php
    */

    //TOUFIX broken
    //instead of this, we should have a true tracklist post to store the user favorite tracks, would be easier to handle.
    public function member_get_favorite_tracks_playlist(){
        
        //get tracklist
        $user_id = bp_displayed_user_id();
        $tracklist_id = WPSSTM_Core_Tracklists::get_user_favorites_id($user_id);
        
        if ( !$tracklist_id || is_wp_error($tracklist_id) ){
            return $tracklist_id;
        }
        
        return new WPSSTM_Post_Tracklist($tracklist_id);
    }
    
    function love_track_activity($track_id){
        $user_link = bp_core_get_userlink( get_current_user_id() );
        $track_link = sprintf('<a href="%s">%s</a>',get_permalink($track_id),get_the_title($track_id));
        $args = array(
            //'id' =>
            'action' =>         sprintf(__('%s loved the track %s','wpsstm'),$user_link,$track_link),
            //'content' =>
            'component' =>      self::$music_slug,
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
            'component' =>      self::$music_slug,
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