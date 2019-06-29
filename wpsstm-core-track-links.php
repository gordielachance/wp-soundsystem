<?php

class WPSSTM_Core_Track_Links{
    static $link_url_metakey = '_wpsstm_link_url';
    static $autolink_time_metakey = '_wpsstm_autolink_time'; //to store the musicbrainz datas
    function __construct() {
        global $wpsstm_link;

        //initialize global (blank) $wpsstm_link so plugin never breaks when calling it.
        $wpsstm_link = new WPSSTM_Track_Link();
        
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

        add_filter( sprintf("views_edit-%s",wpsstm()->post_type_track_link), array(wpsstm(),'register_community_view') );

        /*
        QUERIES
        */
        add_action( 'the_post', array($this,'the_track_link'),10,2);
        add_action( 'current_screen',  array($this, 'the_single_backend_link'));
        add_filter( 'pre_get_posts', array($this,'filter_backend_links_by_track_id') );
        
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
        return $qvars;
    }
    
    /*
    Register the global $wpsstm_link obj (hooked on 'the_post' action)
    */
    
    function the_track_link($post,$query){
        global $wpsstm_link;
        if ( $query->get('post_type') == wpsstm()->post_type_track_link ){
            //set global $wpsstm_link
            $wpsstm_link = new WPSSTM_Track_Link($post->ID);
        }
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
    
    function filter_backend_links_by_track_id($query){

        if ( !is_admin() ) return $query;
        if ( !$query->is_main_query() ) return $query;
        if ( $query->get('post_type') != wpsstm()->post_type_track_link ) return $query;

        $track_id = ( isset($_GET['post_parent']) ) ? $_GET['post_parent'] : null;
        
        if ( !$track_id ) return $query;
        
        $query->set('post_parent',$track_id);
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
                $track = new WPSSTM_Track($post->post_parent);
                if ($track->post_id){
                    printf('<p><strong>%s</strong> — %s</p>',$track->artist,$track->title);
                }
            ?>
        <label class="screen-reader-text" for="wpsstm_link_parent_id"><?php _e('Parent') ?></label>
        <input name="wpsstm_link_parent_id" type="number" value="<?php echo $post->post_parent;?>" />
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
        <p>
            <?php _e("If the player and the 'Autolink' setting are enabled, we'll try to find playable links automatically when the track is requested to play.",'wpsstm');?>
        </p>

        <?php

        if ( $then = get_post_meta( $wpsstm_track->post_id, self::$autolink_time_metakey, true ) ){
            $now = current_time( 'timestamp' );
            $refreshed = human_time_diff( $now, $then );
            $refreshed = sprintf(__('Last autolink query: %s ago.','wpsstm'),$refreshed);
            
            $unset_autolink_bt = sprintf('<input id="wpsstm-unset-autolink-bt" type="submit" name="wpsstm_unset_autolink" class="button" value="%s">',__('Clear','wpsstm'));
                ?>

                <?php
            
            echo '  ' . $refreshed .' ' . $unset_autolink_bt;
        }

        //track links
        ?>
        <div class="wpsstm-track-links">
            <?php wpsstm_locate_template( 'content-track-links.php', true, false );?>
        </div>
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
            //autolink
            if ( ( wpsstm()->get_options('autolink') ) && (WPSSTM_Core_Track_Links::can_autolink() === true) ){
                ?>
                <input id="wpsstm-autolink-bt" type="submit" name="wpsstm_track_autolink" class="button" value="<?php _e('Autolink','wpsstm');?>">
                <?php
            }
        
            //list links
            $post_links_url = admin_url(sprintf('edit.php?post_type=%s&post_parent=%s',wpsstm()->post_type_track_link,$wpsstm_track->post_id));
            printf('<a href="%s" class="button">%s</a>',$post_links_url,__('Backend listing','wpsstm'));

            //add links
            printf('<a id="wpsstm-add-link-url" href="#" class="button">%s</a>',__('Add link URL','wpsstm'));
            ?>
        </p>
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
            $success = $track->save_new_links();

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
        
        if ( isset($_GET['post_parent']) ){
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

        //community user
        $community_user_id = wpsstm()->get_options('community_user_id');
        if (!$community_user_id){
            return new WP_Error( 'wpsstm_autolink',__('Autolink requires a community user to be set.','wpsstm') );
        }
        
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

        //capability check
        $links_post_type_obj = get_post_type_object(wpsstm()->post_type_track_link);
        if ($links_post_type_obj){ //be sure post type is registered before doing that check - eg. it isn't when saving settings.
            $autolink_cap = $links_post_type_obj->cap->create_posts;
            if ( !$has_cap = user_can($community_user_id,$autolink_cap) ){
                $error = sprintf(__("Autolink requires the community user to have the %s capability granted.",'wpsstm'),'<em>'.$autolink_cap.'</em>');
                return new WP_Error( 'wpsstm_autolink',$error );
            }
        }
        
        //TOFIXKKK TO CHECK has links providers ?

        return true;
        
    }
    
    public static function get_orphan_link_ids(){
        global $wpdb;
        
        $querystr = $wpdb->prepare( "SELECT child.ID FROM `$wpdb->posts` AS child LEFT JOIN `$wpdb->posts` AS parent ON child.post_parent = parent.ID WHERE child.post_type = '%s' AND (child.post_status <> 'trash' AND child.post_status <> 'auto-draft') AND parent.ID is NULL", wpsstm()->post_type_track_link );

        return $wpdb->get_col( $querystr);

    }
    
    /*
    Flush orphan links (attached to no track)
    */
    static function trash_orphan_links(){
        
        if ( !current_user_can('manage_options') ){
            return new WP_Error('wpsstm_missing_capability',__("You don't have the capability required.",'wpsstm'));
        }

        $trashed = array();
        
        if ( $flushable_ids = self::get_orphan_link_ids() ){

            foreach( (array)$flushable_ids as $post_id ){
                $success = wp_trash_post($post_id);
                if ( !is_wp_error($success) ) $trashed[] = $post_id;
            }
        }

        WP_SoundSystem::debug_log( json_encode(array('flushable'=>count($flushable_ids),'trashed'=>count($trashed))),"Deleted orphan links");

        return $trashed;

    }
    
    /*
    Flush duplicate links (same post parent & URL)
    */
    static function trash_duplicate_links(){
        
        if ( !current_user_can('manage_options') ){
            return new WP_Error('wpsstm_missing_capability',__("You don't have the capability required.",'wpsstm'));
        }

        $trashed = array();
        
        if ( $flushable_ids = self::get_duplicate_link_ids() ){

            foreach( (array)$flushable_ids as $post_id ){
                $success = wp_trash_post($post_id);
                if ( !is_wp_error($success) ) $trashed[] = $post_id;
            }
        }

        WP_SoundSystem::debug_log( json_encode(array('flushable'=>count($flushable_ids),'trashed'=>count($trashed))),"Deleted duplicate links");

        return $trashed;

    }
    
    /*
    Trash temporary tracklists
    */
    static function trash_excluded_hosts(){
        
        $trashed = array();
        
        if ( !current_user_can('manage_options') ){
            return new WP_Error('wpsstm_missing_capability',__("You don't have the capability required.",'wpsstm'));
        }

        if ( $flushable_ids = WPSSTM_Core_Track_Links::get_excluded_host_link_ids() ){
            foreach((array)$flushable_ids as $post_id){
                $success = wp_trash_post($post_id);
                if ( !is_wp_error($success) ) $trashed[] = $post_id;
            }
        }

        WP_SoundSystem::debug_log( json_encode(array('flushable'=>count($flushable_ids),'trashed'=>count($trashed))),"Deleted duplicate links");
        
        return $trashed;
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
    
    static function get_excluded_host_link_ids(){
        global $wpdb;
        
        $excluded_hosts = wpsstm()->get_options('excluded_track_link_hosts');
        if (!$excluded_hosts) return;

        $exclude_query = array();
        
        foreach((array)$excluded_hosts as $domain){
            $exclude_query[] = sprintf( "`meta_value` LIKE '%%%s%%'",$domain);
        }

        $querystr = $wpdb->prepare( "SELECT post_id FROM `$wpdb->postmeta` WHERE `meta_key` = '%s'",'_wpsstm_link_url');
        $querystr .= ' AND ' . implode(' OR ',$exclude_query);

        return $wpdb->get_col( $querystr );

    }
}

function wpsstm_links_init(){
    new WPSSTM_Core_Track_Links();
}

add_action('wpsstm_init','wpsstm_links_init');