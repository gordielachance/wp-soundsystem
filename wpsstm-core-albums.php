<?php

class WP_SoundSystem_Core_Albums{

    public $album_metakey = '_wpsstm_release';
    public $qvar_album_lookup = 'lookup_release';
    public $album_mbtype = 'release'; //musicbrainz type, for lookups
    
    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSystem_Core_Albums;
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
        add_action( 'init', array($this,'register_post_type_album' ));
        add_filter( 'query_vars', array($this,'add_query_var_album') );
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_by_album') );
        
        add_action( 'wpsstm_register_submenus', array( $this, 'backend_albums_submenu' ) );
        add_action( 'add_meta_boxes', array($this, 'metabox_album_register'));
        add_action( 'save_post', array($this,'metabox_album_save'), 5); 
        
        add_filter('manage_posts_columns', array($this,'column_album_register'), 10, 2 );
        add_action( 'manage_posts_custom_column', array($this,'column_album_content'), 10, 2 );
    }
    
    //add custom admin submenu under WPSSTM
    function backend_albums_submenu($parent_slug){

        //capability check
        $post_type_slug = wpsstm()->post_type_album;
        $post_type_obj = get_post_type_object($post_type_slug);
        
         add_submenu_page(
                $parent_slug,
                $post_type_obj->labels->name, //page title - TO FIX TO CHECK what is the purpose of this ?
                $post_type_obj->labels->name, //submenu title
                $post_type_obj->cap->edit_posts, //cap required
                sprintf('edit.php?post_type=%s',$post_type_slug) //url or slug
         );
        
    }
    
    function column_album_register($defaults) {
        global $post;
        global $wp_query;

        $post_types = array(
            wpsstm()->post_type_track
        );
        
        $before = array();
        $after = array();
        
        if ( isset($_GET['post_type']) && in_array($_GET['post_type'],$post_types) ){

            if ( !$wp_query->get('subtracks_exclude') ){
                $after['album'] = __('Album','wpsstm');
            }
            
            
        }
        
        return array_merge($before,$defaults,$after);
    }
    
    function column_album_content($column,$post_id){
        global $post;

        switch ( $column ) {
            case 'album':

                $album = wpsstm_get_post_album($post_id);
                
                if ($album){
                    echo $album;
                }else{
                    echo '—';
                }

                
            break;
        }
    }

    function pre_get_posts_by_album( $query ) {

        if ( $album = $query->get( $this->qvar_album_lookup ) ){

            $query->set( 'meta_query', array(
                array(
                     'key'     => $this->album_metakey,
                     'value'   => $album,
                     'compare' => '='
                )
            ));
        }

        return $query;
    }

    function register_post_type_album() {

        $labels = array(
            'name'                  => _x( 'Albums', 'Albums General Name', 'wpsstm' ),
            'singular_name'         => _x( 'Album', 'Album Singular Name', 'wpsstm' ),
            'menu_name'             => __( 'Albums', 'wpsstm' ),
            'name_admin_bar'        => __( 'Album', 'wpsstm' ),
            'archives'              => __( 'Album Archives', 'wpsstm' ),
            'attributes'            => __( 'Album Attributes', 'wpsstm' ),
            'parent_item_colon'     => __( 'Parent Album:', 'wpsstm' ),
            'all_items'             => __( 'All Albums', 'wpsstm' ),
            'add_new_item'          => __( 'Add New Album', 'wpsstm' ),
            //'add_new'               => __( 'Add New', 'wpsstm' ),
            'new_item'              => __( 'New Album', 'wpsstm' ),
            'edit_item'             => __( 'Edit Album', 'wpsstm' ),
            'update_item'           => __( 'Update Album', 'wpsstm' ),
            'view_item'             => __( 'View Album', 'wpsstm' ),
            'view_items'            => __( 'View Albums', 'wpsstm' ),
            'search_items'          => __( 'Search Albums', 'wpsstm' ),
            //'not_found'             => __( 'Not found', 'wpsstm' ),
            //'not_found_in_trash'    => __( 'Not found in Trash', 'wpsstm' ),
            //'featured_image'        => __( 'Featured Image', 'wpsstm' ),
            //'set_featured_image'    => __( 'Set featured image', 'wpsstm' ),
            //'remove_featured_image' => __( 'Remove featured image', 'wpsstm' ),
            //'use_featured_image'    => __( 'Use as featured image', 'wpsstm' ),
            'insert_into_item'      => __( 'Insert into album', 'wpsstm' ),
            'uploaded_to_this_item' => __( 'Uploaded to this album', 'wpsstm' ),
            'items_list'            => __( 'Albums list', 'wpsstm' ),
            'items_list_navigation' => __( 'Albums list navigation', 'wpsstm' ),
            'filter_items_list'     => __( 'Filter albums list', 'wpsstm' ),
        );

        $args = array( 
            'labels' => $labels,
            'hierarchical' => false,

            'supports' => array( 'author','thumbnail','comments' ),
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
            /**
             * A string used to build the edit, delete, and read capabilities for posts of this type. You 
             * can use a string or an array (for singular and plural forms).  The array is useful if the 
             * plural form can't be made by simply adding an 's' to the end of the word.  For example, 
             * array( 'box', 'boxes' ).
             */
            'capability_type'     => 'album', // string|array (defaults to 'post')

            /**
             * Whether WordPress should map the meta capabilities (edit_post, read_post, delete_post) for 
             * you.  If set to FALSE, you'll need to roll your own handling of this by filtering the 
             * 'map_meta_cap' hook.
             */
            'map_meta_cap'        => true, // bool (defaults to FALSE)

            /**
             * Provides more precise control over the capabilities than the defaults.  By default, WordPress 
             * will use the 'capability_type' argument to build these capabilities.  More often than not, 
             * this results in many extra capabilities that you probably don't need.  The following is how 
             * I set up capabilities for many post types, which only uses three basic capabilities you need 
             * to assign to roles: 'manage_examples', 'edit_examples', 'create_examples'.  Each post type 
             * is unique though, so you'll want to adjust it to fit your needs.
             */
            'capabilities' => array(

                // meta caps (don't assign these to roles)
                'edit_post'              => 'edit_album',
                'read_post'              => 'read_album',
                'delete_post'            => 'delete_album',

                // primitive/meta caps
                'create_posts'           => 'create_albums',

                // primitive caps used outside of map_meta_cap()
                'edit_posts'             => 'edit_albums',
                'edit_others_posts'      => 'manage_albums',
                'publish_posts'          => 'manage_albums',
                'read_private_posts'     => 'read',

                // primitive caps used inside of map_meta_cap()
                'read'                   => 'read',
                'delete_posts'           => 'manage_albums',
                'delete_private_posts'   => 'manage_albums',
                'delete_published_posts' => 'manage_albums',
                'delete_others_posts'    => 'manage_albums',
                'edit_private_posts'     => 'edit_albums',
                'edit_published_posts'   => 'edit_albums'
            )
        );

        register_post_type( wpsstm()->post_type_album, $args );
    }
    
    function add_query_var_album( $qvars ) {
        $qvars[] = $this->qvar_album_lookup;
        return $qvars;
    }
    
    function metabox_album_register(){
        
        $metabox_post_types = array(
            wpsstm()->post_type_album,
            wpsstm()->post_type_track
        );

        add_meta_box( 
            'wpsstm-album', 
            __('Album','wpsstm'),
            array($this,'metabox_album_content'),
            $metabox_post_types, 
            'after_title', 
            'high' 
        );
        
    }
    
    function metabox_album_content( $post ){

        $album_title = get_post_meta( $post->ID, $this->album_metakey, true );
        
        ?>
        <input type="text" name="wpsstm_album" class="wpsstm-fullwidth" value="<?php echo $album_title;?>" placeholder="<?php printf("Enter album title here",'wpsstm');?>"/>
        <?php
        wp_nonce_field( 'wpsstm_album_meta_box', 'wpsstm_album_meta_box_nonce' );

    }
    
    /**
    Save album field for this post
    **/
    
    function metabox_album_save( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_album_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;
        
        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_album,wpsstm()->post_type_track);
        if ( !in_array($post_type,$allowed_post_types) ) return;

        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_album_meta_box_nonce'], 'wpsstm_album_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        //this should run only once (for the main post); so unset meta box nonce.
        //without this the function would be called for every subtrack if there was some.
        unset($_POST['wpsstm_album_meta_box_nonce']);

        $album = ( isset($_POST[ 'wpsstm_album' ]) ) ? $_POST[ 'wpsstm_album' ] : null;

        if (!$album){
            delete_post_meta( $post_id, $this->album_metakey );
        }else{
            update_post_meta( $post_id, $this->album_metakey, $album );
        }

    }
    
}

function wpsstm_albums() {
	return WP_SoundSystem_Core_Albums::instance();
}

wpsstm_albums();