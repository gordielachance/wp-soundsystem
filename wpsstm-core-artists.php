<?php

class WP_SoundSystem_Core_Artists{

    public $metakey = '_wpsstm_artist';
    public $qvar_artist = 'lookup_artist';
    public $mbtype = 'artist'; //musicbrainz type, for lookups
    
    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSystem_Core_Artists;
                    self::$instance->init();
            }
            return self::$instance;
    }
    
    private function __construct() { /* Do nothing here */ }
    
    function init(){
        //add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
    }
    
    function setup_actions(){

        add_action( 'init', array($this,'register_post_type_artist' ));
        add_filter( 'query_vars', array($this,'add_query_var_artist') );
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_artist') );
        add_action( 'save_post', array($this,'update_title_artist'), 99);
        
        add_action( 'add_meta_boxes', array($this, 'metabox_artist_register'));
        add_action( 'save_post', array($this,'metabox_artist_save'), 5); 
        
        //add_filter( 'manage_posts_columns', array($this,'column_artist_register'), 10, 2 ); 
        //add_action( 'manage_posts_custom_column' , array($this,'column_artist_content'), 10, 2 );

    }

    function column_artist_register($defaults) {

        $post_types = array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_track,
            wpsstm()->post_type_album
        );
        
        $before = array();
        $after = array();
        
        if ( isset($_GET['post_type']) && in_array($_GET['post_type'],$post_types) ){
            $after['artist'] = __('Artist','wpsstm');
        }

        
        return array_merge($before,$defaults,$after);
    }
    
    function column_artist_content($column,$post_id){
        global $post;
        
        switch ( $column ) {
                case 'artist':
                    if (!$artist = wpsstm_get_post_artist_link_for_post($post_id) ){
                        $artist = '—';
                    }
                    echo $artist;
                break;
        }
    }


    function pre_get_posts_artist( $query ) {

        if ( $search = $query->get( $this->qvar_artist ) ){
            
            //$query->set( 'meta_key', $this->metakey );
            $query->set( 'meta_query', array(
                array(
                     'key'     => $this->metakey,
                     'value'   => $search,
                     'compare' => '='
                )
            ));
        }

        return $query;
    }
    
    /**
    Update the post title to match the artist/album/track, so we still have a nice post permalink
    **/
    
    function update_title_artist( $post_id ) {
        
        //only for albums
        if (get_post_type($post_id) != wpsstm()->post_type_artist) return;

        //check capabilities
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $has_cap = current_user_can('edit_post', $post_id);
        if ( $is_autosave || $is_autodraft || $is_revision || !$has_cap ) return;

        $post_title = wpsstm_get_post_artist($post_id);
        if ( !$post_title ) return;
        
        //use get_post_field here instead of get_the_title() so title is not filtered
        if ( $post_title == get_post_field('post_title',$post_id) ) return;

        //log
        wpsstm()->debug_log(array('post_id'=>$post_id,'title'=>$post_title),"update_title_artist()"); 

        $args = array(
            'ID'            => $post_id,
            'post_title'    => $post_title
        );

        remove_action( 'save_post',array($this,'update_title_artist'), 99 ); //avoid infinite loop - ! hook priorities
        wp_update_post( $args );
        add_action( 'save_post',array($this,'update_title_artist'), 99 );

    }


    function register_post_type_artist() {

        $labels = array( 
            'name' => _x( 'Artists', 'wpsstm' ),
            'singular_name' => _x( 'Artist', 'wpsstm' )
        );

        $args = array( 
            'labels' => $labels,
            'hierarchical' => false,

            'supports' => array( 'title','thumbnail', 'comments' ),
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
            'capability_type' => 'post', //artist
            //'map_meta_cap'        => true,
            /*
            'capabilities' => array(

                // meta caps (don't assign these to roles)
                'edit_post'              => 'edit_artist',
                'read_post'              => 'read_artist',
                'delete_post'            => 'delete_artist',

                // primitive/meta caps
                'create_posts'           => 'create_artists',

                // primitive caps used outside of map_meta_cap()
                'edit_posts'             => 'edit_artists',
                'edit_others_posts'      => 'manage_artists',
                'publish_posts'          => 'manage_artists',
                'read_private_posts'     => 'read',

                // primitive caps used inside of map_meta_cap()
                'read'                   => 'read',
                'delete_posts'           => 'manage_artists',
                'delete_private_posts'   => 'manage_artists',
                'delete_published_posts' => 'manage_artists',
                'delete_others_posts'    => 'manage_artists',
                'edit_private_posts'     => 'edit_artists',
                'edit_published_posts'   => 'edit_artists'
            ),
            */
        );

        register_post_type( wpsstm()->post_type_artist, $args );
    }
    
    function add_query_var_artist( $qvars ) {
        $qvars[] = $this->qvar_artist;
        return $qvars;
    }
    
    function metabox_artist_register(){
        
        $metabox_post_types = array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_track,
            wpsstm()->post_type_album
        );

        add_meta_box( 
            'wpsstm-artist', 
            __('Artist','wpsstm'),
            array($this,'metabox_artist_content'),
            $metabox_post_types, 
            'after_title', 
            'high' 
        );
        
    }

    function metabox_artist_content( $post ){

        $artist_name = get_post_meta( $post->ID, $this->metakey, true );
        
        ?>
        <input type="text" name="wpsstm_artist" class="wpsstm-fullwidth wpsstm-lookup-artist" value="<?php echo $artist_name;?>" placeholder="<?php printf("Enter artist here",'wpsstm');?>"/>
        <?php
        wp_nonce_field( 'wpsstm_artist_meta_box', 'wpsstm_artist_meta_box_nonce' );

    }
    
    /**
    Save artist field for this post
    **/
    
    function metabox_artist_save( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_artist_meta_box_nonce']);
        if ( !$is_metabox || $is_autodraft || $is_autosave || $is_revision ) return;
        
        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_album,wpsstm()->post_type_track);
        if ( !in_array($post_type,$allowed_post_types) ) return;

        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_artist_meta_box_nonce'], 'wpsstm_artist_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        //this should run only once (for the main post); so unset meta box nonce.
        //without this the function would be called for every subtrack if there was some.
        unset($_POST['wpsstm_artist_meta_box_nonce']);

        $artist = ( isset($_POST[ 'wpsstm_artist' ]) ) ? $_POST[ 'wpsstm_artist' ] : null;

        if (!$artist){
            delete_post_meta( $post_id, $this->metakey );
        }else{
            update_post_meta( $post_id, $this->metakey, $artist );
        }

    }

}

function wpsstm_artists() {
	return WP_SoundSystem_Core_Artists::instance();
}

wpsstm_artists();