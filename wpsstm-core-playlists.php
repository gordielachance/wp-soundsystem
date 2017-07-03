<?php

class WP_SoundSytem_Core_Playlists{

    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSytem_Core_Playlists;
                    self::$instance->init();
            }
            return self::$instance;
    }
    
    private function __construct() { /* Do nothing here */ }
    
    function init(){
        
        require wpsstm()->plugin_dir . 'scraper/wpsstm-live-tracklist-class.php';
        
        //add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
    }

    
    function setup_actions(){

        add_action( 'init', array($this,'register_post_type_playlist' ));
        
        add_filter('manage_posts_columns', array($this,'tracks_column_playlist_register'), 10, 2 );
        add_action( 'manage_posts_custom_column', array($this,'tracks_column_playlist_content'), 10, 2 );

    }

    function register_post_type_playlist() {

        $labels = array( 
            'name' => _x( 'Playlists', 'wpsstm' ),
            'singular_name' => _x( 'Playlist', 'wpsstm' )
        );

        $args = array( 
            'labels' => $labels,
            'hierarchical' => false,

            'supports' => array( 'title','editor','author','thumbnail', 'comments' ),
            'taxonomies' => array( 'post_tag' ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_nav_menus' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'has_archive' => true,
            'query_var' => true,
            'can_export' => true,
            'rewrite' => true,
            //http://justintadlock.com/archives/2013/09/13/register-post-type-cheat-sheet
            'capability_type' => 'post', //playlist
            //'map_meta_cap'        => true,
            /*
            'capabilities' => array(

                // meta caps (don't assign these to roles)
                'edit_post'              => 'edit_playlist',
                'read_post'              => 'read_playlist',
                'delete_post'            => 'delete_playlist',

                // primitive/meta caps
                'create_posts'           => 'create_playlists',

                // primitive caps used outside of map_meta_cap()
                'edit_posts'             => 'edit_playlists',
                'edit_others_posts'      => 'manage_playlists',
                'publish_posts'          => 'manage_playlists',
                'read_private_posts'     => 'read',

                // primitive caps used inside of map_meta_cap()
                'read'                   => 'read',
                'delete_posts'           => 'manage_playlists',
                'delete_private_posts'   => 'manage_playlists',
                'delete_published_posts' => 'manage_playlists',
                'delete_others_posts'    => 'manage_playlists',
                'edit_private_posts'     => 'edit_playlists',
                'edit_published_posts'   => 'edit_playlists'
            ),
            */
        );

        register_post_type( wpsstm()->post_type_playlist, $args );
    }

    function tracks_column_playlist_register($defaults) {
        global $post;
        global $wp_query;

        $post_types = array(
            wpsstm()->post_type_track
        );
        
        $before = array();
        $after = array();
        
        if ( isset($_GET['post_type']) && in_array($_GET['post_type'],$post_types) ){

            if ( !$wp_query->get(wpsstm_tracks()->qvar_subtracks_hide) ){
                $after['playlist'] = __('Playlist','wpsstm');
            }
            
        }
        
        return array_merge($before,$defaults,$after);
    }
    
    function tracks_column_playlist_content($column,$post_id){
        global $post;

        switch ( $column ) {
            case 'playlist':
                
                $track = new WP_SoundSystem_Subtrack( array('post_id'=>$post_id) );
                $tracklist_ids = $track->get_parent_ids();
                $links = array();

                foreach((array)$tracklist_ids as $tracklist_id){

                    $tracklist_post_type = get_post_type($tracklist_id);
                    if ( $tracklist_post_type != wpsstm()->post_type_playlist ) continue;

                    $playlist_url = get_permalink($tracklist_id);
                    $playlist_name = ( $title = get_the_title($tracklist_id) ) ? $title : sprintf('#%s',$tracklist_id);
                    
                    $links[] = sprintf('<a href="%s">%s</a>',$playlist_url,$playlist_name);
                }
                
                
                
                if ($links){
                    echo implode(',',$links);
                }else{
                    echo '—';
                }

                
            break;
        }
    }
    

}

function wpsstm_playlists() {
	return WP_SoundSytem_Core_Playlists::instance();
}

wpsstm_playlists();