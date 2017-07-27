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
        add_action( 'save_post', array($this,'update_title_album'), 99);
        
        add_action( 'add_meta_boxes', array($this, 'metabox_album_register'));
        add_action( 'save_post', array($this,'metabox_album_save'), 5); 
        
        add_filter('manage_posts_columns', array($this,'column_album_register'), 10, 2 );
        add_action( 'manage_posts_custom_column', array($this,'column_album_content'), 10, 2 );
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

            if ( !$wp_query->get(wpsstm_tracks()->qvar_exclude_subtracks) ){
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
    
    /**
    Update the post title to match the artist/album/track, so we still have a nice post permalink
    **/
    
    function update_title_album( $post_id ) {
        
        //only for albums
        if (get_post_type($post_id) != wpsstm()->post_type_album) return;

        //check capabilities
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $has_cap = current_user_can('edit_post', $post_id);
        if ( $is_autosave || $is_autodraft || $is_revision || !$has_cap ) return;

        $artist = wpsstm_get_post_artist($post_id);
        $album = wpsstm_get_post_album($post_id);
        if ( !$artist || !$album ) return;
        
        $post_title = sanitize_text_field( sprintf('%s - "%s"',$artist,$album) );
        
        //no changes - use get_post_field here instead of get_the_title() so title is not filtered
        if ( $post_title == get_post_field('post_title',$post_id) ) return;

        //log
        wpsstm()->debug_log(array('post_id'=>$post_id,'title'=>$post_title),"update_title_album()"); 

        $args = array(
            'ID'            => $post_id,
            'post_title'    => $post_title
        );

        remove_action( 'save_post',array($this,'update_title_album'), 99 ); //avoid infinite loop - ! hook priorities
        wp_update_post( $args );
        add_action( 'save_post',array($this,'update_title_album'), 99 );

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

            'supports' => array( 'author','title','thumbnail','comments' ),
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
            'capability_type' => 'post', //album
            //'map_meta_cap'        => true,
            /*
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
            ),
            */
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