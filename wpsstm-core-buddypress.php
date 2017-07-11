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
        require_once(wpsstm()->plugin_dir . 'lastfm/_inc/php/autoload.php');
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
		$favorite_track_count = $this->member_get_favorite_tracks_count($user_id);
		$favorite_track_class = ( 0 === $favorite_track_count ) ? 'no-count' : 'count';

		$menu_tracks_name = sprintf(
			__( 'Favorite tracks %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $favorite_track_class ),
				bp_core_number_format( $favorite_track_count )
			)
		);

        $args = array(
                'name'                      => $menu_tracks_name,
                'slug'                      => $menu_tracks_slug,
                'default_subnav_slug'       => $submenu_favorite_tracks_slug,
                'position'                  => 50,
                'show_for_displayed_user'   => true,
                'screen_function'           => array($this,'view_user_favorite_tracks'),
                'item_css_id'               => 'wpsstm-member-tracks-favorite'
        );

        bp_core_new_nav_item( $args );
        
        /*
        Tracks Submenus
        */

		$submenu_loved_tracks_name = sprintf(
			__( 'Favorite tracks %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $favorite_track_class ),
				bp_core_number_format( $favorite_track_count )
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
		$all_playlists_count = $this->member_get_playlist_count($user_id);
		$all_playlists_class = ( 0 === $all_playlists_count ) ? 'no-count' : 'count';

		$menu_playlists_name = sprintf(
			__( 'Playlists %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $all_playlists_class ),
				bp_core_number_format( $all_playlists_count )
			)
		);

        $args = array(
                'name'                      => $menu_playlists_name,
                'slug'                      => $menu_playlists_slug,
                'default_subnav_slug'       => $submenu_static_playlists_slug,
                'position'                  => 50,
                'show_for_displayed_user'   => true,
                'screen_function'           => array($this,'view_user_static_playlists'),
                'item_css_id'               => 'wpsstm-member-playlists'
        );

        bp_core_new_nav_item( $args );
        
        /*
        Playlists Submenus
        */

        //static
        
		$static_playlists_count = $this->member_get_playlist_count($user_id,'static');
		$static_playlists_class = ( 0 === $static_playlists_count ) ? 'no-count' : 'count';

		$submenu_playlists_name = sprintf(
			__( 'Playlists %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $static_playlists_class ),
				bp_core_number_format( $static_playlists_count )
			)
		);
        
        bp_core_new_subnav_item( array(
            'name'            => $submenu_playlists_name,
            'slug'            => $submenu_static_playlists_slug,
            'parent_url'      => $bp->loggedin_user->domain . $menu_playlists_slug . '/',
            'parent_slug'     => $menu_playlists_slug,
            'position'        => 10,
            'screen_function' => array($this,'view_user_favorite_playlists')
        ) );
        
        //live playlists
		$live_playlists_count = $this->member_get_playlist_count($user_id,'live');
		$live_playlists_class = ( 0 === $live_playlists_count ) ? 'no-count' : 'count';
        
        

		$submenu_live_playlists_name = sprintf(
			__( 'Live playlists %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $live_playlists_class ),
				bp_core_number_format( $live_playlists_count )
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
        
        //favorites
		$favorite_playlists_count = $this->member_get_playlist_count($user_id,'favorite');
		$favorite_playlists_class = ( 0 === $favorite_playlists_count ) ? 'no-count' : 'count';

		$submenu_favorite_playlists_name = sprintf(
			__( 'Favorites %s', 'wpsstm' ),
			sprintf(
				'<span class="%s">%s</span>',
				esc_attr( $favorite_playlists_class ),
				bp_core_number_format( $favorite_playlists_count )
			)
		);
        
        bp_core_new_subnav_item( array(
            'name'            => $submenu_favorite_playlists_name,
            'slug'            => $submenu_favorite_playlists_slug,
            'parent_url'      => $bp->loggedin_user->domain . $menu_playlists_slug . '/',
            'parent_slug'     => $menu_playlists_slug,
            'position'        => 30,
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
            $title = __('My playlists','wpsstm');
        }
        echo $title;
    }
    
    function user_live_playlists_subnav_title(){
        $title = sprintf(__("%s's live playlists",'wpsstm'),bp_get_displayed_user_fullname());
        if ( bp_is_my_profile() ) {
            $title = __('My live playlists','wpsstm');
        }
        echo $title;
    }
    
    function user_favorite_playlists_subnav_title(){
        $title = sprintf(__("%s's favorite playlists",'wpsstm'),bp_get_displayed_user_fullname());
        if ( bp_is_my_profile() ) {
            $title = __('My favorite playlists','wpsstm');
        }
        echo $title;
    }
    
    function user_static_playlists_subnav_content(){
        $this->user_playlists_loop('static');
    }
    
    function user_favorite_playlists_subnav_content(){
        $this->user_playlists_loop('favorite');
    }
    
    function user_live_playlists_subnav_content(){
        $this->user_playlists_loop('live');
    }

    function user_playlists_loop($type){
        $user_id = bp_displayed_user_id();
        $args = $this->member_get_playlists_query_args($user_id,$type);
        query_posts($args);
        
		if ( have_posts() ) { ?>

            <div class="wpsstm-playlists-loop">
                <?php get_template_part('loop', wpsstm()->post_type_live_playlist); ?>
            </div>

			<?php
            
			// Previous/next page navigation.
			the_posts_pagination( array(
				'prev_text'          => __( 'Previous page', 'twentyfifteen' ),
				'next_text'          => __( 'Next page', 'twentyfifteen' ),
				'before_page_number' => '<span class="meta-nav screen-reader-text">' . __( 'Page', 'twentyfifteen' ) . ' </span>',
			) );

		// If no content, include the "No posts found" template.
		}else{
			get_template_part( 'content', 'none' );
        }

        wp_reset_query();
    }
    
    function view_user_favorite_tracks(){
        add_action( 'bp_template_title', array($this,'user_favorite_tracks_subnav_title') );
        add_action( 'bp_template_content', array($this,'user_favorite_tracks_subnav_content') );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }
    
    function user_favorite_tracks_subnav_title(){
        $title = sprintf(__("%s's favorite tracks",'wpsstm'),bp_get_displayed_user_fullname());
        if ( bp_is_my_profile() ) {
            $title = __('My favorite tracks','wpsstm');
        }
        echo $title;
    }
    
    function user_favorite_tracks_subnav_content(){
        $user_id = bp_displayed_user_id();

        $track_args = array(
            'post_type'         => wpsstm()->post_type_track,

            'meta_query'        => array(
                array(
                     'key'     => wpsstm_tracks()->favorited_track_meta_key,
                     'value'   => $user_id
                )
            )

        );

        query_posts($track_args);
        
		if ( have_posts() ) { ?>

            <div class="wpsstm-tracks-loop">
                <?php get_template_part('content', wpsstm()->post_type_track); ?>
            </div>

			<?php
            
			// Previous/next page navigation.
			the_posts_pagination( array(
				'prev_text'          => __( 'Previous page', 'twentyfifteen' ),
				'next_text'          => __( 'Next page', 'twentyfifteen' ),
				'before_page_number' => '<span class="meta-nav screen-reader-text">' . __( 'Page', 'twentyfifteen' ) . ' </span>',
			) );

		// If no content, include the "No posts found" template.
		}else{
			get_template_part( 'content', 'none' );
        }

        wp_reset_query();
    }

    public function member_get_playlists_query_args($user_id = null, $type=array('static','live','favorite'),$args = array() ){
        $post_types = array();
        if ( is_string($type) ) $type = (array)$type;
        if (!$user_id) $user_id = get_current_user_id();
        
        $default_args = array();
        $args = wp_parse_args($args,$default_args);
        
        if ( in_array('static',$type) || in_array('favorite',$type) ){
            $args['post_type'][] = wpsstm()->post_type_playlist;
        }

        if ( in_array('live',$type) || in_array('favorite',$type) ){
            $args['post_type'][] = wpsstm()->post_type_live_playlist;
        }
        
        if ( !in_array('favorite',$type) ){
            $args['author'] = $user_id;
        }else{
            $meta_query = array();
            $args['meta_query'][] = array(
                 'key'     => wpsstm_tracklists()->favorited_tracklist_meta_key,
                 'value'   => $user_id
            );
        }
        
        if ( !isset($args['post_type']) ) return;
        
        return $args;
    }
    
    public function member_get_playlist_count($user_id = null, $type=array('static','live','favorite'),$args = array() ){
        $count = 0;
        $post_types = array();
        
        if ( is_string($type) ) $type = (array)$type;
        
        $defaults = array(
            'posts_per_page'    => -1,
            'fields'            => 'ids'
        );
        
        $args = wp_parse_args($args,$defaults);

        //static
        if ( in_array('static',$type) ){
            $pl_args = $this->member_get_playlists_query_args($user_id,'static',$args);
            $static_args = wp_parse_args($pl_args,$args);
            $query = new WP_Query( $static_args );
            $count += $query->found_posts;
        }
        
        //live
        if ( in_array('live',$type) ){
            $pl_args = $this->member_get_playlists_query_args($user_id,'live',$args);
            $live_args = wp_parse_args($pl_args,$args);
            $query = new WP_Query( $live_args );
            $count += $query->found_posts;
        }

        //favorite
        if ( in_array('favorite',$type) ){
            $pl_args = $this->member_get_playlists_query_args($user_id,'favorite',$args);
            $favorite_args = wp_parse_args($pl_args,$args);
            $query = new WP_Query( $favorite_args );
            $count += $query->found_posts;
        }

        return $count;

    }
    
    public function member_get_favorite_tracks_count($user_id = null,$args = array() ){
        $count = 0;

        $defaults = array(
            'post_status'       => 'any',
            'posts_per_page'    => -1,
            'fields'            => 'ids'
        );
        
        $args = wp_parse_args($args,$defaults);
            
        $track_args = array(
            'post_type'         => wpsstm()->post_type_track,
            'meta_query'        => array(
                array(
                     'key'     => wpsstm_tracks()->favorited_track_meta_key,
                     'value'   => $user_id
                )
            )
        );
        
        $track_args = wp_parse_args($track_args,$args);
        $query = new WP_Query( $track_args );
        $count += $query->found_posts;

        return $count;

    }
    

}


function wpsstm_buddypress() {
	return WP_SoundSystem_Core_BuddyPress::instance();
}

wpsstm_buddypress();