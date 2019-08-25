<?php
class WPSSTM_Core_Track_Links{
    static $link_url_metakey = '_wpsstm_link_url';
    static $autolink_time_metakey = '_wpsstm_autolink_time'; //to store the musicbrainz datas
    static $excluded_hosts_link_ids_transient_name = '_wpsstm_excluded_hosts_link_ids';
    
    function __construct() {
        global $wpsstm_link;

        /*
        populate single global track link.
        Be sure it works frontend, backend, and on post-new.php page
        */
        $wpsstm_link = new WPSSTM_Track_Link();
        add_action( 'the_post', array($this,'populate_global_track_link_loop'),10,2);
        
        add_filter( 'query_vars', array($this,'add_query_vars_track_link') );
        add_action( 'wpsstm_init_post_types', array($this,'register_track_link_post_type' ));

        add_action( 'wpsstm_register_submenus', array( $this, 'backend_links_submenu' ) );
        add_action( 'add_meta_boxes', array($this, 'metabox_link_register'));
        
        add_action( 'save_post', array($this,'metabox_parent_track_save')); 
        add_action( 'save_post', array($this,'metabox_link_url'));
        add_action( 'save_post', array($this,'metabox_save_track_links') );

        add_action( 'wp_enqueue_scripts', array( $this, 'register_links_scripts_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_links_scripts_styles' ) );

        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_track_link), array(__class__,'link_columns_register'), 10, 2 );
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_track_link), array(__class__,'link_columns_content'), 10, 2 );

        add_filter( sprintf("views_edit-%s",wpsstm()->post_type_track_link), array(__class__,'register_orphan_track_links_view') );
        add_filter( sprintf("views_edit-%s",wpsstm()->post_type_track_link), array(wpsstm(),'register_community_view') );
        
        add_filter( sprintf("views_edit-%s",wpsstm()->post_type_track_link), array(__class__,'register_excluded_hosts_links_view') );
        add_action( 'current_screen', array( $this, 'build_excluded_hosts_cache_bt' ) );
        add_action('admin_notices', array(__class__,'build_excluded_hosts_cache_notice') );

        /*
        QUERIES
        */
        add_action( 'current_screen',  array($this, 'the_single_backend_link'));
        add_filter( 'pre_get_posts', array($this,'filter_track_links_by_parent') );
        add_filter( 'pre_get_posts', array($this,'filter_track_links_by_excluded_hosts') );
        
        /*
        AJAX
        */
        
        //delete link
        add_action('wp_ajax_wpsstm_trash_link', array($this,'ajax_trash_link'));
    }

    function register_track_link_post_type() {

        $labels = array(
            'name'                  => _x( 'Tracks Links', 'Tracks Links General Name', 'wpsstm' ),
            'singular_name'         => _x( 'Track Link', 'Track Link Singular Name', 'wpsstm' ),
            'menu_name'             => __( 'Tracks Links', 'wpsstm' ),
            'name_admin_bar'        => __( 'Track Link', 'wpsstm' ),
            'archives'              => __( 'Track Link Archives', 'wpsstm' ),
            'attributes'            => __( 'Track Link Attributes', 'wpsstm' ),
            'parent_item_colon'     => __( 'Parent Track:', 'wpsstm' ),
            'all_items'             => __( 'All Tracks Links', 'wpsstm' ),
            'add_new_item'          => __( 'Add New Track Link', 'wpsstm' ),
            //'add_new'               => __( 'Add New', 'wpsstm' ),
            'new_item'              => __( 'New Track Link', 'wpsstm' ),
            'edit_item'             => __( 'Edit Track Link', 'wpsstm' ),
            'update_item'           => __( 'Update Track Link', 'wpsstm' ),
            'view_item'             => __( 'View Track Link', 'wpsstm' ),
            'view_items'            => __( 'View Tracks Links', 'wpsstm' ),
            'search_items'          => __( 'Search Tracks Links', 'wpsstm' ),
            //'not_found'             => __( 'Not found', 'wpsstm' ),
            //'not_found_in_trash'    => __( 'Not found in Trash', 'wpsstm' ),
            //'featured_image'        => __( 'Featured Image', 'wpsstm' ),
            //'set_featured_image'    => __( 'Set featured image', 'wpsstm' ),
            //'remove_featured_image' => __( 'Remove featured image', 'wpsstm' ),
            //'use_featured_image'    => __( 'Use as featured image', 'wpsstm' ),
            'insert_into_item'      => __( 'Insert into track link', 'wpsstm' ),
            'uploaded_to_this_item' => __( 'Uploaded to this track link', 'wpsstm' ),
            'items_list'            => __( 'Tracks Links list', 'wpsstm' ),
            'items_list_navigation' => __( 'Tracks Links list navigation', 'wpsstm' ),
            'filter_items_list'     => __( 'Filter track links list', 'wpsstm' ),
        );

        $args = array( 
            'labels' => $labels,
            'hierarchical' => true,
            'supports' => array( 'author','title'),
            'taxonomies' => array(),
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_nav_menus' => true,
            'public' => true,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'has_archive' => true,
            'query_var' => true,
            'can_export' => true,
            'rewrite' => array(
                'slug' => sprintf('%s/%s',WPSSTM_BASE_SLUG,WPSSTM_LINKS_SLUG),
                'with_front' => FALSE
            ),
            /**
             * A string used to build the edit, delete, and read capabilities for posts of this type. You 
             * can use a string or an array (for singular and plural forms).  The array is useful if the 
             * plural form can't be made by simply adding an 's' to the end of the word.  For example, 
             * array( 'box', 'boxes' ).
             */
            'capability_type'     => 'track', // string|array (defaults to 'post')

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
                'edit_post'              => 'edit_track',
                'read_post'              => 'read_track',
                'delete_post'            => 'delete_track',

                // primitive/meta caps
                'create_posts'           => 'create_tracks',

                // primitive caps used outside of map_meta_cap()
                'edit_posts'             => 'edit_tracks',
                'edit_others_posts'      => 'manage_tracks',
                'publish_posts'          => 'manage_tracks',
                'read_private_posts'     => 'read',

                // primitive caps used inside of map_meta_cap()
                'read'                   => 'read',
                'delete_posts'           => 'manage_tracks',
                'delete_private_posts'   => 'manage_tracks',
                'delete_published_posts' => 'manage_tracks',
                'delete_others_posts'    => 'manage_tracks',
                'edit_private_posts'     => 'edit_tracks',
                'edit_published_posts'   => 'edit_tracks'
            )
        );

        register_post_type( wpsstm()->post_type_track_link, $args );
    }
    
    function add_query_vars_track_link($qvars){
        $qvars[] = 'parent_track';
        $qvars[] = 'no_excluded_hosts';
        $qvars[] = 'only_excluded_hosts';
        return $qvars;
    }
    
    /*
    Register the global within posts loop
    */
    
    function populate_global_track_link_loop($post,$query){
        global $wpsstm_link;
        
        if ( $query->get('post_type') != wpsstm()->post_type_track_link ) return;
        
        //set global
        $is_already_populated = ($wpsstm_link && ($wpsstm_link->post_id == $post->ID) );
        if ($is_already_populated) return;

        $wpsstm_link = new WPSSTM_Track_Link($post->ID);
    }

    function the_single_backend_link(){
        global $post;
        global $wpsstm_link;
        $screen = get_current_screen();
        if ( ( $screen->base == 'post' ) && ( $screen->post_type == wpsstm()->post_type_track_link )  ){
            $post_id = isset($_GET['post']) ? $_GET['post'] : null;
            //set global $wpsstm_link
            $wpsstm_link = new WPSSTM_Track_Link($post_id);
        }
    }
    
    //add custom admin submenu under WPSSTM
    function backend_links_submenu($parent_slug){

        //capability check
        $post_type_slug = wpsstm()->post_type_track_link;
        $post_type_obj = get_post_type_object($post_type_slug);
        
         add_submenu_page(
                $parent_slug,
                $post_type_obj->labels->name, //page title - TO FIX TO CHECK what is the purpose of this ?
                $post_type_obj->labels->name, //submenu title
                $post_type_obj->cap->edit_posts, //cap required
                sprintf('edit.php?post_type=%s',$post_type_slug) //url or slug
         );
        
    }

    /*
    On backend links listings, allow to filter by track ID
    */
    
    function filter_track_links_by_parent($query){

        if ( $query->get('post_type') != wpsstm()->post_type_track_link ) return $query;
        
        $track_id = $query->get( 'parent_track' );

        $track_id = is_numeric($track_id) ? (int)$track_id : null; //do not use get_query_var here

        if ( $track_id === null ) return $query;

        $query->set('post_parent',$track_id);
    }
    
    function filter_track_links_by_excluded_hosts($query){
        global $wpdb;
        
        
        if ( $query->get('post_type') != wpsstm()->post_type_track_link ) return $query;

        $no_excluded_hosts = $query->get('no_excluded_hosts');
        $only_excluded_hosts = $query->get('only_excluded_hosts');
        if (!$no_excluded_hosts && !$only_excluded_hosts) return $query;

        $excluded_links_ids = self::get_excluded_hosts_link_ids();
        if ( !$excluded_links_ids ) return $query;

        if ($no_excluded_hosts){
            $query->set('post__not_in',$excluded_links_ids);
        }elseif ($only_excluded_hosts){
            $query->set('post__in',$excluded_links_ids);
        }

        return $query;
        
    }
    
    function metabox_link_register(){
        global $post;

        $track_post_type_obj = get_post_type_object(wpsstm()->post_type_track);
        
        add_meta_box(
            'wpsstm-parent-track', 
            $track_post_type_obj->labels->parent_item_colon, 
            array($this,'parent_track_content'),
            wpsstm()->post_type_track_link, 
            'side', 
            'core'
        );

        add_meta_box( 
            'wpsstm-link-urls', 
            __('Track Link URL','wpsstm'),
            array($this,'metabox_link_content'),
            wpsstm()->post_type_track_link, 
            'after_title', 
            'high' 
        );
        
        add_meta_box( 
            'wpsstm-metabox-track-links', 
            __('Tracks Links','wpsstm'),
            array($this,'metabox_track_links_content'),
            wpsstm()->post_type_track, 
            'normal', //context
            'default' //priority
        );
        
    }
    
    function parent_track_content( $post ){
        ?>
        <div style="text-align:center">
            <?php
        
                $parent_id = ( $post->post_parent && ( get_post_type( $post->post_parent ) === wpsstm()->post_type_track_link ) ) ? $post->post_parent : 0;
                
                if ($parent_id){
                    $track = new WPSSTM_Track($parent_id);
                    printf('<p><strong>%s</strong> — %s</p>',$track->artist,$track->title);
                }
            ?>
        <label class="screen-reader-text" for="wpsstm_link_parent_id"><?php _e('Parent') ?></label>
        <input name="wpsstm_link_parent_id" type="number" value="<?php echo $parent_id;?>" />
        </div>
        <?php
        wp_nonce_field( 'wpsstm_track_parent_meta_box', 'wpsstm_track_parent_meta_box_nonce' );
    }
    
    /**
    Save link URL field for this post
    **/
    
    function metabox_parent_track_save( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_valid_nonce = ( isset($_POST['wpsstm_track_parent_meta_box_nonce']) && wp_verify_nonce( $_POST['wpsstm_track_parent_meta_box_nonce'], 'wpsstm_track_parent_meta_box' ) );
        if ( !$is_valid_nonce || $is_autodraft || $is_autosave || $is_revision ) return;
        
        unset($_POST['wpsstm_track_parent_meta_box_nonce']); //so we avoid the infinite loop

        $parent_id = ( isset($_POST[ 'wpsstm_link_parent_id' ]) ) ? $_POST[ 'wpsstm_link_parent_id' ] : null;
        $parent_post_type = get_post_type($parent_id);
        if ( $parent_post_type != wpsstm()->post_type_track ) $parent_id = null;

        $success = wp_update_post(array(
            'ID' =>             $post_id,
            'post_parent' =>    $parent_id,
        ),true);

    }

    function metabox_link_content( $post ){
        
        $link = new WPSSTM_Track_Link($post->ID);

        ?>
        <p>
            <h2><?php _e('URL','wpsstm');?></h2>
            <input type="text" name="wpsstm_link_url" class="wpsstm-fullwidth" value="<?php echo $link->permalink_url;?>" />
        </p>
        <?php
        wp_nonce_field( 'wpsstm_link_meta_box', 'wpsstm_link_meta_box_nonce' );

    }
    
    function metabox_track_links_content( $post ){
        global $wpsstm_track;

        $track_type_obj = get_post_type_object(wpsstm()->post_type_track);
        $can_edit_track = current_user_can($track_type_obj->cap->edit_post,$wpsstm_track->post_id);
        ?>

        <div>
            <h3><?php _e('Auto','wpsstm');?></h3>
            <?php

            //autolink
            $is_premium = WPSSTM_Core_API::is_premium();
            $is_enabled = wpsstm()->get_options('autolink');
            $can_autolink = ( WPSSTM_Core_Track_Links::can_autolink() === true);
            $sleeping = get_post_meta( $wpsstm_track->post_id, self::$autolink_time_metakey, true );
        
        
            if( $is_enabled ){
                
                //desc
                $desc = sprintf("If this track doesn't have any links, we'll try to get them automatically when the track is played.",'wpsstm');
                
                if (!$is_premium){
                    $premium_desc = sprintf("API key required.",'wpsstm');
                    $desc .= sprintf('  <strong>%s</strong>',$premium_desc);
                }
                
                echo $desc;
                
                
                //has been autolinked notice
                if ( $sleeping ){
                    $now = current_time( 'timestamp' );
                    $next_refresh = $now + ( wpsstm()->get_options('autolink_lock_hours') * HOUR_IN_SECONDS);

                    $refreshed = human_time_diff( $now, $next_refresh );
                    $refreshed = sprintf(__('This track has been autolinked already.  Wait %s before next request.','wpsstm'),$refreshed);

                    $unset_autolink_bt = sprintf('<input id="wpsstm-unset-autolink-bt" type="submit" name="wpsstm_unset_autolink" class="button" value="%s">',__('Release','wpsstm'));

                    printf('<p class="wpsstm-notice">%s %s</p>',$refreshed,$unset_autolink_bt);
                }
                
            }

            ?>
            <p class="wpsstm-submit-wrapper">
                <?php

                $classes = array(
                    'button'
                );

                if ( !$can_autolink ){
                    $classes[] = 'wpsstm-freeze';
                }

                //input
                $input_attr = array(
                    'id' =>             'wpsstm-autolink-bt',
                    'type' =>           'submit',
                    'name' =>           'wpsstm_track_autolink',
                    'class' =>          implode(' ',array_filter($classes)),
                    'value' =>          __('Autolink','wpsstm'),
                    'title' =>          __('Find links automatically for this track'),

                );

                if ( !$is_premium ){
                    $input_attr['title'] .= sprintf(' [%s]',__('premium','wpsstm'));
                }


                $attr_str = wpsstm_get_html_attr($input_attr);
                $autolink_bt = sprintf('<input %s/>',$attr_str);

                echo $autolink_bt;
                ?>
            </p>
        </div>
        <div>
            <h3><?php _e('Manual','wpsstm');?></h3>
        
            <div class="wpsstm-track-links">
                <?php wpsstm_locate_template( 'content-track-links.php', true, false );?>
                <p class="wpsstm-new-links-container">
                    <?php
                    $input_attr = array(
                        'id' => 'wpsstm-new_track-links',
                        'name' => 'wpsstm_new_track_links[]',
                        'icon' => '<i class="fa fa-link" aria-hidden="true"></i>',
                        'placeholder' => __("Enter a link URL",'wpsstm')
                    );
                    echo $input = wpsstm_get_backend_form_input($input_attr);
                    ?>
                </p>
                <p class="wpsstm-submit-wrapper">
                    <?php
                    //add links
                    printf('<a id="wpsstm-add-link-url" href="#" class="button">%s</a>',__('Add row','wpsstm'));
        
                    if ( $wpsstm_track->have_links() ){

                        //edit links bt
                        $post_links_url = $wpsstm_track->get_backend_links_url();
                        printf('<a href="%s" class="button">%s</a>',$post_links_url,__('Filter links','wpsstm'));

                    }
        
                    ?>
                    
                    
                    
                </p>
            </div>
        </div>

        <?php
        wp_nonce_field( 'wpsstm_track_links_meta_box', 'wpsstm_track_links_meta_box_nonce' );
    }

    /**
    Save link URL field for this post
    **/
    
    function metabox_link_url( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_valid_nonce = ( isset($_POST['wpsstm_link_meta_box_nonce']) && wp_verify_nonce( $_POST['wpsstm_link_meta_box_nonce'], 'wpsstm_link_meta_box' ) );
        if ( !$is_valid_nonce || $is_autodraft || $is_autosave || $is_revision ) return;

        $link_url = ( isset($_POST[ 'wpsstm_link_url' ]) ) ? $_POST[ 'wpsstm_link_url' ] : null;
        
        //TO FIX validate URL

        if (!$link_url){
            delete_post_meta( $post_id, self::$link_url_metakey );
        }else{
            update_post_meta( $post_id, self::$link_url_metakey, $link_url );
        }

    }
    
    function metabox_save_track_links( $post_id ) {
        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_track_links_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;
        
        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_track);
        if ( $post_type != wpsstm()->post_type_track ) return;

        //nonce
        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_track_links_meta_box_nonce'], 'wpsstm_track_links_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        $track = new WPSSTM_Track($post_id);

        //new link URLs
        $link_urls = isset($_POST['wpsstm_new_track_links']) ? array_filter($_POST['wpsstm_new_track_links']) : null;

        if ( $link_urls ) {
            $new_links = array();

            foreach((array)$link_urls as $url){
                $link = new WPSSTM_Track_Link(null);
                $link->permalink_url = $url;
                $new_links[] = $link;
            }

            $track->add_links($new_links);
            $success = $track->batch_create_links();

        }
        
        //unset autolink
        if ( isset($_POST['wpsstm_unset_autolink']) ){
            delete_post_meta( $track->post_id, WPSSTM_Core_Track_Links::$autolink_time_metakey );
        }

        //autolink & save
        if ( isset($_POST['wpsstm_track_autolink']) ){
            $track->did_autolink = false;
            $success = $track->autolink();
        }
    }
    
    
    function register_links_scripts_styles(){
        //CSS
        //JS
        wp_register_script( 'wpsstm-links', wpsstm()->plugin_url . '_inc/js/wpsstm-track-links.js', array('jquery','jquery-core','jquery-ui-core','jquery-ui-sortable','wpsstm-functions'),wpsstm()->version, true );
    }

    
    public static function link_columns_register($defaults) {
        global $post;

        $before = array();
        $after = array();
        
        $after['track_link_url'] = __('URL','wpsstm');
        $after['parent_track'] = __('Track','wpsstm');
        
        if ( isset($_GET['parent_track']) ){
            $after['order'] = __('Order','wpsstm');
        }
        
        return array_merge($before,$defaults,$after);
    }

    public static function link_columns_content($column,$post_id){
        global $post;
        global $wpsstm_link;
        
        switch ( $column ) {
            case 'track_link_url':
                
                $link  = sprintf('<a class="wpsstm-link-provider" href="%s" target="_blank" title="%s">%s</a>',$wpsstm_link->permalink_url,$wpsstm_link->title,$wpsstm_link->permalink_url);
                
                echo $link;
                
            break;
            case 'order':
                if ($wpsstm_link->index !== null){
                    echo $wpsstm_link->index;
                }else{
                    echo '—';
                }
            break;
            case 'parent_track':

                if ( $track = new WPSSTM_Track($post->post_parent) ){
                    
                    if ($track->artist && $track->artist){
                        
                        $links_url = $track->get_backend_links_url();
                        
                        $track_label = sprintf('<strong>%s</strong> — %s',$track->artist,$track->title);
                        
                        $track_edit_url = get_edit_post_link( $track->post_id );
                        $track_edit_link = sprintf('<a href="%s" alt="%s">%s</a>',$track_edit_url,__('Edit track','wpsstm'),$track_label);
                        
                        $track_links_link = sprintf('<a href="%s" alt="%s">%s</a>',$links_url,__('Filter links','wpsstm'),'<i class="fa fa-filter" aria-hidden="true"></i>');

                        printf('<p>%s %s</p>',$track_edit_link,$track_links_link);
                    }

                }else{
                    echo '—';
                }
            break;
        }
    }
    
    function ajax_trash_link(){
        $ajax_data = wp_unslash($_POST);
        
        $post_id = wpsstm_get_array_value(array('post_id'),$ajax_data);
        
        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false
        );

        $link = new WPSSTM_Track_Link($post_id);
        $success = $link->trash_link();
        
        if ( is_wp_error($success) ){
            
            $result['message'] = $success->get_error_message();
            
        }else{
            
            $result['success'] = true;
            
        }
        
        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    static function can_autolink(){
        global $wpsstm_spotify;
        
        //check community user
        $can_community = wpsstm()->is_community_user_ready();
        if ( is_wp_error($can_community) ) return $can_community;

        //Spotify API
        $has_spotify_api = $wpsstm_spotify->can_spotify_api();

        if ( $has_spotify_api !== true ){
            return new WP_Error( 'wpsstm_spotify_api_missing',__('This requires a Spotify API key & secret.','wpsstm') );
        }
        
        //wpssstm API
        $is_premium = WPSSTM_Core_API::is_premium();
        
        if ( $is_premium !== true ){
            return new WP_Error( 'wpsstm_premium_missing',__('This requires you to be premium.','wpsstm') );
        }

        //TOFIXKKK TO CHECK has links providers ?

        return true;
        
    }
    
    public static function get_orphan_link_ids(){
        global $wpdb;
        
        $query_args = array(
            'post_type' =>      wpsstm()->post_type_track_link,
            'posts_per_page' => -1,
            'post_status' =>    'any',
            'fields' =>         'ids',
            'parent_track' =>    0,
        );
        
        $query = new WP_Query( $query_args );
        return $query->posts;

    }
    
    public static function get_excluded_host_link_ids(){
        global $wpdb;
        
        $query_args = array(
            'post_type' =>              wpsstm()->post_type_track_link,
            'posts_per_page' =>         -1,
            'post_status' =>            'any',
            'only_excluded_hosts' =>    true,
        );
        
        $query = new WP_Query( $query_args );

        return $query->posts;
    }

    /*
    Flush duplicate links (same post parent & URL)
    */
    static function delete_duplicate_links(){
        
        if ( !current_user_can('manage_options') ){
            return new WP_Error('wpsstm_missing_capability',__("You don't have the capability required.",'wpsstm'));
        }

        $deleted = array();
        
        if ( $flushable_ids = self::get_duplicate_link_ids() ){

            foreach( (array)$flushable_ids as $post_id ){
                $success = wp_delete_post($post_id,true);
                if ( $success ) $deleted[] = $post_id;
            }
        }

        WP_SoundSystem::debug_log( json_encode(array('flushable'=>count($flushable_ids),'trashed'=>count($deleted))),"Deleted duplicate links");

        return $deleted;

    }
    
    /*
    Get the duplicate links (by post parent and url)
    https://wordpress.stackexchange.com/questions/340474/sql-query-that-returns-a-list-of-duplicate-posts-ids-comparing-their-post-paren
    */
    
    static function get_duplicate_link_ids(){
        global $wpdb;
        
        $duplicate_ids = array();
        
        $querystr = $wpdb->prepare( "
        SELECT post_parent,url, GROUP_CONCAT(DISTINCT post_id SEPARATOR ',') as post_ids FROM (SELECT posts.ID AS post_id,posts.post_parent,metas.meta_value AS url 
            FROM `$wpdb->posts` AS posts 
            INNER JOIN `$wpdb->postmeta` AS metas ON ( posts.ID = metas.post_id )
            WHERE `post_type`='wpsstm_track_link'
            AND (`post_status` <> 'trash' AND `post_status` <> 'auto-draft')
            AND metas.meta_key='%s' ORDER BY posts.ID ASC) as links
            GROUP BY links.post_parent,links.url
            having count(*) > 1",'_wpsstm_link_url');

        $results = $wpdb->get_results ( $querystr );

        foreach($results as $row){
            $row_ids = explode(',',$row->post_ids);
            array_shift($row_ids); //remove first one to keep only duplicates
            $duplicate_ids = array_merge($duplicate_ids, $row_ids);
            
        }
        
        return $duplicate_ids;
    }
    
    static function register_orphan_track_links_view($views){

        $screen =                   get_current_screen();
        $post_type =                $screen->post_type;
        $parent_track =             get_query_var('parent_track');
        $parent_track =             is_numeric($parent_track) ? (int)$parent_track : null;

        $link = add_query_arg( array('post_type'=>$post_type,'parent_track'=>0),admin_url('edit.php') );
        $count = count(WPSSTM_Core_Track_Links::get_orphan_link_ids());
        
        $attr = array(
            'href' =>   $link,
        );

        if ($parent_track === 0){
            $attr['class'] = 'current';
        }

        $views['orphan'] = sprintf('<a %s>%s <span class="count">(%d)</span></a>',wpsstm_get_html_attr($attr),__('Orphan','wpsstm'),$count);
        
        return $views;
    }
    
    static function get_excluded_hosts_link_ids(){
        global $wpdb;
        
        $link_ids = get_transient( self::$excluded_hosts_link_ids_transient_name );

        if (false === $link_ids){
            
            if ( $excluded_hosts = wpsstm()->get_options('excluded_track_link_hosts') ){
                
                $exclude_query = array();

                foreach((array)$excluded_hosts as $domain){
                    $exclude_query[] = sprintf( "`meta_value` LIKE '%%%s%%'",$domain);
                }

                $querystr = $wpdb->prepare( "SELECT post_id FROM `$wpdb->postmeta` WHERE `meta_key` = '%s'",'_wpsstm_link_url');
                $querystr .= ' AND ( ' . implode(' OR ',$exclude_query) . ')';

                $link_ids = $wpdb->get_col( $querystr );
                
            }
            
            set_transient( self::$excluded_hosts_link_ids_transient_name, $link_ids, 1 * WEEK_IN_SECONDS );
            
            WP_SoundSystem::debug_log(array('hosts'=>count($excluded_hosts),'matches'=>count($link_ids)),"Has built excluded hosts link cache");

        }
        
        return $link_ids;
    }
    
    static function rebuild_excluded_hosts_cache(){
        delete_transient( self::$excluded_hosts_link_ids_transient_name );
        self::get_excluded_hosts_link_ids();
    }

    function build_excluded_hosts_cache_bt($screen){
        if ( ($screen->base != 'edit') || ($screen->post_type != wpsstm()->post_type_track_link) ) return;
        if ( !$is_cache_build = wpsstm_get_array_value('build_excluded_hosts_cache',$_GET) ) return;

        self::rebuild_excluded_hosts_cache();
    }
    
    static function build_excluded_hosts_cache_notice(){
        
        $screen =                   get_current_screen();
        
        if ( ($screen->base != 'edit') || ($screen->post_type != wpsstm()->post_type_track_link) ) return;
        if (!$excluded_hosts = get_query_var('only_excluded_hosts') ) return;
        if ( $is_cache_build = wpsstm_get_array_value('build_excluded_hosts_cache',$_GET) ) return;
        
        $link_url = add_query_arg(
            array(
                'post_type' =>                  wpsstm()->post_type_track_link,
                'only_excluded_hosts' =>        true,
                'build_excluded_hosts_cache' => true,
            ),
            admin_url('edit.php')
        );
        $link_el = sprintf('<a class="button" href="%s">%s</a>',$link_url,__('Rebuild cache','wpsstm'));
        $notice = __('In order to accelerate queries, a cache of track links matching excluded hosts is built every week.','wpsstm') . '  ' . $link_el;
        printf('<div class="notice notice-warning is-dismissible"><p>%s</p></div>',$notice);
    }
    
    static function register_excluded_hosts_links_view($views){
        $excluded_hosts =           get_query_var('only_excluded_hosts');

        $link = add_query_arg( array('post_type'=>wpsstm()->post_type_track_link,'only_excluded_hosts'=>true),admin_url('edit.php') );
        $count = count(WPSSTM_Core_Track_Links::get_excluded_host_link_ids());
        
        $attr = array(
            'href' =>   $link,
        );

        if ($excluded_hosts){
            $attr['class'] = 'current';
        }

        $views['exclude_hosts'] = sprintf('<a %s>%s <span class="count">(%d)</span></a>',wpsstm_get_html_attr($attr),__('Excluded hosts','wpsstm'),$count);
        
        return $views;
    }
    
}

function wpsstm_links_init(){
    new WPSSTM_Core_Track_Links();
}

add_action('wpsstm_init','wpsstm_links_init');