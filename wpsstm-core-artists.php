<?php

class WPSSTM_Core_Artists{

    static $artist_metakey = '_wpsstm_artist';
    static $qvar_artist_lookup = 'lookup_artist';
    static $artist_mbtype = 'artist'; //musicbrainz type, for lookups

    function __construct(){

        add_action( 'init', array($this,'register_post_type_artist' ));
        
        add_filter( 'query_vars', array($this,'add_query_var_artist') );
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_by_artist') );
        
        add_action( 'wpsstm_register_submenus', array( $this, 'backend_artists_submenu' ) );

        add_action( 'add_meta_boxes', array($this, 'metabox_artist_register'));
        add_action( 'save_post', array($this,'metabox_artist_save'), 5); 
        
        //add_filter( 'manage_posts_columns', array($this,'column_artist_register'), 10, 2 ); 
        //add_action( 'manage_posts_custom_column' , array($this,'column_artist_content'), 10, 2 );
        
        add_filter( 'the_title', array($this, 'the_artist_post_title'), 9, 2 );
        
        /*
        AJAX
        */
        add_action('wp_ajax_wpsstm_search_artists', array($this,'ajax_search_artists')); //for autocomplete
        add_action('wp_ajax_nopriv_wpsstm_search_artists', array($this,'ajax_search_artists')); //for autocomplete

    }
    
    //add custom admin submenu under WPSSTM
    function backend_artists_submenu($parent_slug){

        //capability check
        $post_type_slug = wpsstm()->post_type_artist;
        $post_type_obj = get_post_type_object($post_type_slug);
        
         add_submenu_page(
                $parent_slug,
                $post_type_obj->labels->name, //page title - TO FIX TO CHECK what is the purpose of this ?
                $post_type_obj->labels->name, //submenu title
                $post_type_obj->cap->edit_posts, //cap required
                sprintf('edit.php?post_type=%s',$post_type_slug) //url or slug
         );
        
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
                    if (!$artist = wpsstm_get_post_artist($post_id) ){
                        $artist = '—';
                    }
                    echo $artist;
                break;
        }
    }

    function pre_get_posts_by_artist( $query ) {

        if ( $search = $query->get( self::$qvar_artist_lookup ) ){
            
            $query->set( 'meta_query', array(
                array(
                     'key'     => self::$artist_metakey,
                     'value'   => $search,
                     'compare' => '='
                )
            ));
        }

        return $query;
    }

    function register_post_type_artist() {

        $labels = array(
            'name'                  => _x( 'Artists', 'Artists General Name', 'wpsstm' ),
            'singular_name'         => _x( 'Artist', 'Artist Singular Name', 'wpsstm' ),
            'menu_name'             => __( 'Artists', 'wpsstm' ),
            'name_admin_bar'        => __( 'Artist', 'wpsstm' ),
            'archives'              => __( 'Artist Archives', 'wpsstm' ),
            'attributes'            => __( 'Artist Attributes', 'wpsstm' ),
            'parent_item_colon'     => __( 'Parent Artist:', 'wpsstm' ),
            'all_items'             => __( 'All Artists', 'wpsstm' ),
            'add_new_item'          => __( 'Add New Artist', 'wpsstm' ),
            //'add_new'               => __( 'Add New', 'wpsstm' ),
            'new_item'              => __( 'New Artist', 'wpsstm' ),
            'edit_item'             => __( 'Edit Artist', 'wpsstm' ),
            'update_item'           => __( 'Update Artist', 'wpsstm' ),
            'view_item'             => __( 'View Artist', 'wpsstm' ),
            'view_items'            => __( 'View Artists', 'wpsstm' ),
            'search_items'          => __( 'Search Artists', 'wpsstm' ),
            //'not_found'             => __( 'Not found', 'wpsstm' ),
            //'not_found_in_trash'    => __( 'Not found in Trash', 'wpsstm' ),
            //'featured_image'        => __( 'Featured Image', 'wpsstm' ),
            //'set_featured_image'    => __( 'Set featured image', 'wpsstm' ),
            //'remove_featured_image' => __( 'Remove featured image', 'wpsstm' ),
            //'use_featured_image'    => __( 'Use as featured image', 'wpsstm' ),
            'insert_into_item'      => __( 'Insert into artist', 'wpsstm' ),
            'uploaded_to_this_item' => __( 'Uploaded to this artist', 'wpsstm' ),
            'items_list'            => __( 'Artists list', 'wpsstm' ),
            'items_list_navigation' => __( 'Artists list navigation', 'wpsstm' ),
            'filter_items_list'     => __( 'Filter artists list', 'wpsstm' ),
        );

        $args = array( 
            'labels' => $labels,
            'hierarchical' => false,

            'supports' => array( 'author','thumbnail', 'comments' ),
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
            'capability_type'     => 'artist', // string|array (defaults to 'post')

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
            )
        );

        register_post_type( wpsstm()->post_type_artist, $args );
    }
    
    function add_query_var_artist( $qvars ) {
        $qvars[] = self::$qvar_artist_lookup;
        return $qvars;
    }
    
    function metabox_artist_register(){

        add_meta_box( 
            'wpsstm-artist-settings', 
            __('Artist Settings','wpsstm'),
            array($this,'metabox_artist_settings_content'),
            wpsstm()->post_type_artist, 
            'after_title', 
            'high' 
        );
        
    }

    function metabox_artist_settings_content( $post ){

        echo self::get_edit_artist_input($post->ID); //artist

        wp_nonce_field( 'wpsstm_artist_settings_meta_box', 'wpsstm_artist_settings_meta_box_nonce' );

    }
    
    static function get_edit_artist_input($post_id = null){
        global $post;
        if (!$post) $post_id = $post->ID;
        
        $input_attr = array(
            'id' => 'wpsstm-artist',
            'name' => 'wpsstm_artist',
            'value' => get_post_meta( $post_id, self::$artist_metakey, true ),
            'icon' => '<i class="fa fa-user-o" aria-hidden="true"></i>',
            'label' => __("Artist",'wpsstm'),
            'placeholder' => __("Enter artist here",'wpsstm')
        );
        return wpsstm_get_backend_form_input($input_attr);
    }
    
    /**
    Save artist field for this post
    **/
    
    function metabox_artist_save( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_artist_settings_meta_box_nonce']);
        if ( !$is_metabox || $is_autodraft || $is_autosave || $is_revision ) return;
        
        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_album,wpsstm()->post_type_track);
        if ( !in_array($post_type,$allowed_post_types) ) return;

        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_artist_settings_meta_box_nonce'], 'wpsstm_artist_settings_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        //this should run only once (for the main post); so unset meta box nonce.
        //without this the function would be called for every subtrack if there was some.
        unset($_POST['wpsstm_artist_settings_meta_box_nonce']);

        /*artist*/
        $artist = ( isset($_POST[ 'wpsstm_artist' ]) ) ? $_POST[ 'wpsstm_artist' ] : null;
        self::save_meta_artist($post_id,$artist);



    }
    
    static function save_meta_artist($post_id, $value = null){
        $value = trim($value);
        if (!$value){
            delete_post_meta( $post_id, self::$artist_metakey );
        }else{
            update_post_meta( $post_id, self::$artist_metakey, $value );
        }
    }
    
    /*
    Use Musicbrainz API to search artists
    WARNING for partial search, you'll need a wildcard * !
    */
    
    function ajax_search_artists(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input' =>              $ajax_data,
            'message' =>            null,
            'success' =>            false
        );
        
        $search = $result['search'] = isset($ajax_data['search']) ? $ajax_data['search'] : null;
        if ($search){
            $results = WPSSTM_Core_Musicbrainz::get_musicbrainz_api_entry('artist',null,$search);
            if ( is_wp_error($results) ){
                $result['message'] = $results->get_error_message();
            }else{
                $result['data'] = $results;
                $result['success'] = true;
            }
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 

    }
    
    function the_artist_post_title($title,$post_id){

        //post type check
        $post_type = get_post_type($post_id);
        if ( $post_type !== wpsstm()->post_type_artist ) return $title;

        return get_post_meta( $post_id, self::$artist_metakey, true );
    }

}