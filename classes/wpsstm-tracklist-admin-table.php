<?php


/**
 * Based on class WP_List_Table
 */
if(!class_exists('WP_SoundSystem_TracksList_Admin_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

    class WP_SoundSystem_TracksList_Admin_Table extends WP_List_Table {

        var $links_per_page = -1;
        var $can_manage_rows;

        function prepare_items() {
           
            global $post;

            $columns = $this->get_columns();
            $hidden = array();
            $sortable = $this->get_sortable_columns();
            $this->_column_headers = array($columns, $hidden, $sortable);

            $this->items = $this->items;
            
            //capability check
            //TO FIX
            /*
            $post_type_obj = get_post_type_object(wpsstm()->post_type_playlist);
            $required_cap = $post_type_obj->cap->edit_posts;
            */
            
            //TO FIX CAP
            $this->can_manage_rows = current_user_can( 'edit_posts' );

        }

        /**
         * Generate the tbody element for the list table.
         *
         * @since 3.1.0
         * @access public
         */
        public function display_rows_or_placeholder() {
            global $post;

            //append blank row
            if ( $this->can_manage_rows ){
                $blank_track = new WP_SoundSystem_Track();
                $blank_track->row_classes = array('metabox-table-row-new','metabox-table-row-edit');
                $this->single_row($blank_track);
            }

            parent::display_rows_or_placeholder();
        }

        /*
        override parent function so we can add attributes, etc.
        */
        public function single_row( $item ) {
            
            $item_classes = array();
            if ( property_exists($item, 'row_classes') ){
                $item_classes = $item->row_classes;
            }
            
            $attr_arr = array(
                'class' =>                      implode(' ',$item_classes),
                'data-wpsstm-track-id' =>       ($item->post_id) ? $item->post_id : null,
                'data-wpsstm-track-idx' =>      $item->order,
                'data-wpsstm-track-order' =>    $item->order
            );

            printf( '<tr %s>',wpsstm_get_html_attr($attr_arr) );
            $this->single_row_columns( $item );
            echo '</tr>';
        }

        /**
         * Generate the table navigation above or below the table
         *
         * @since 3.1.0
         * @access protected
         *
         * @param string $which
         */
        protected function display_tablenav( $which ) {

            // REMOVED NONCE -- INTERFERING WITH SAVING POSTS ON METABOXES
            // Add better detection if this class is used on meta box or not.
            /*
            if ( 'top' == $which ) {
                wp_nonce_field( 'bulk-' . $this->_args['plural'] );
            }
            */

            ?>
            <div class="tablenav <?php echo esc_attr( $which ); ?>">

                <div class="alignleft actions bulkactions">
                    <?php $this->bulk_actions( $which ); ?>
                </div>
                <?php
                $this->extra_tablenav( $which );
                $this->pagination( $which );
                ?>

                <br class="clear"/>
            </div>
        <?php
        }

        protected function extra_tablenav($which){
        ?>
                <div id="wpsstm-tracklist-actions" class="wpsstm-actions-list">
                    <?php
                    if ( $this->can_manage_rows ){  
                        if ( 'top' == $which ) {
                            ?>
                            <a class="alignright add-tracklist-track button"><?php echo esc_html_x('Add Row', 'link', 'wpsstm'); ?></a>
                            <a href="#wpsstm-metabox-scraper-wizard" class="alignright import-tracklist-tracks button"><?php _e('Import Tracks','wpsstm');?></a>
                            <?php
                        }
                    }
                    ?>
                </div>
        <?php
        }

        /** ************************************************************************
         * Optional. If you need to include bulk actions in your list table, this is
         * the place to define them. Bulk actions are an associative array in the format
         * 'slug'=>'Visible Title'
         * 
         * If this method returns an empty value, no bulk action will be rendered. If
         * you specify any bulk actions, the bulk actions box will be rendered with
         * the table automatically on display().
         * 
         * Also note that list tables are not automatically wrapped in <form> elements,
         * so you will need to create those manually in order for bulk actions to function.
         * 
         * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
         **************************************************************************/
        function get_bulk_actions() {
            global $post;
            
            //capability check
            $post_type = $post->post_type;
            $tracklist_obj = get_post_type_object($post_type);
            $post_type_track_obj = get_post_type_object(wpsstm()->post_type_track);
            
            $actions = array();
            
            if ( current_user_can($tracklist_obj->cap->edit_post,$post->post_id) ){ //can edit tracklist
                $actions['remove'] = __('Remove tracks','wpsstm');
            }
            
            if ( current_user_can($post_type_track_obj->cap->edit_posts) ){ //can edit tracks
                $actions['save'] = __('Save tracks','wpsstm');
                
                if ( $auto_id = ( wpsstm()->get_options('mb_auto_id') == "on" ) ){
                    $actions['mbid'] = __('Guess MBIDs','wpsstm');
                }
                
                $actions['delete'] = __('Delete tracks','wpsstm');
            }

            return apply_filters('wpsstm_get_tracklist_bulk_actions',$actions);
        }

        /**
         * Display the bulk actions dropdown.
         * Instanciated because we need a different name for the 'select' form elements (default is 'action' & 'action2') or it will interfer with WP.
         */
        function bulk_actions( $which = '' ) {
            if ( is_null( $this->_actions ) ) {
                $this->_actions = $this->get_bulk_actions();
                /**
                 * Filters the list table Bulk Actions drop-down.
                 *
                 * The dynamic portion of the hook name, `$this->screen->id`, refers
                 * to the ID of the current screen, usually a string.
                 *
                 * This filter can currently only be used to remove bulk actions.
                 *
                 * @since 3.5.0
                 *
                 * @param array $actions An array of the available bulk actions.
                 */
                $this->_actions = apply_filters( "wpsstm_tracklist_bulk_actions-{$this->screen->id}", $this->_actions );
                $two = '';
            } else {
                $two = '2';
            }

            if ( empty( $this->_actions ) )
                return;

            echo '<label for="wpsstm-tracklist-bulk-action-selector-' . esc_attr( $which ) . '" class="screen-reader-text">' . __( 'Select bulk action' ) . '</label>';
            echo '<select name="wpsstm-tracklist-action' . $two . '" id="wpsstm-tracklist-bulk-action-selector-' . esc_attr( $which ) . "\">\n";
            echo '<option value="-1">' . __( 'Bulk Actions' ) . "</option>\n";

            foreach ( $this->_actions as $name => $title ) {
                $class = 'edit' === $name ? ' class="hide-if-no-js"' : '';

                echo "\t" . '<option value="' . $name . '"' . $class . '>' . $title . "</option>\n";
            }

            echo "</select>\n";

            submit_button( __( 'Apply' ), 'action', '', false, array( 'id' => "wpsstm-tracklist-doaction$two" ) );
            echo "\n";
        }

        function get_columns(){
            $columns = array(
                'cb'                => '<input type="checkbox" />', //Render a checkbox instead of text
                'reorder'           => '',
                'trackitem_order'   => '#',
                'trackitem_artist'  => __('Artist','wpsstm'),
                'trackitem_track'   => __('Track','wpsstm'),
                'trackitem_album'   => __('Album','wpsstm'),
                'trackitem_mbid'    => __('MBID','wpsstm'),
                'trackitem_sources' => __('Sources','wpsstm'),
                'trackitem_action'  => __('Action','wpsstm')
            );
            
            if ( wpsstm()->get_options('musicbrainz_enabled') != 'on' ){
                unset($columns['trackitem_mbid']);
            }
            

            return apply_filters('wpsstm_tracklist_list_table_columns',$columns); //allow plugins to filter the columns
        }
        /*
        function get_sortable_columns(){
            return array();
        }
        */

        public function get_field_name( $item, $slug ) {
            return sprintf('wpsstm[tracklist][tracks][%d][%s]',$item->order,$slug);
        }


        /**
         * Handles the checkbox column output.
         *
         * This function SHOULD be overriden but we want to use column_defaut() as it is more handy, so use a trick here.
         */
        public function column_cb( $item ) {
            return $this->column_default( $item, 'cb');
        }

        /**
         * Handles the columns output.
         */
        function column_default( $item, $column_name ){
            
            $classes = array('metabox-table-cell-toggle');
            $display_classes = array_merge( $classes,array('metabox-table-cell-display') );
            $edit_classes = array_merge( $classes,array('metabox-table-cell-edit') );
            switch($column_name){

                case 'cb':

                    $input_cb = sprintf( '<input type="checkbox" name="%s" value="on"/>',
                        $this->get_field_name($item,'selected')
                    );
                    $input_track_id = sprintf( '<input type="hidden" name="%s" value="%s"/>',
                        $this->get_field_name($item,'post_id'),
                        $item->post_id
                    );

                    return $input_cb . $input_track_id;
                break;

                case 'reorder':

                    $classes = array(
                        'metabox-table-row-draghandle'
                    );
                    return sprintf('<div %s><i class="fa fa-arrows-v" aria-hidden="true"></i></div>',wpsstm_get_classes_attr($classes));

                break;

                case 'trackitem_order':
                    printf( '<input type="text" name="%s" value="%s" size="3"/>',$this->get_field_name($item,'track_order'),$item->order);
                break;

                case 'trackitem_artist':

                    $display_html = $item->artist;

                    //edit
                    $edit_el = sprintf('<input type="text" name="%s" value="%s"  class="cell-edit-value" />',
                                       $this->get_field_name($item,'artist'),
                                       $item->artist
                                      );

                    //display
                    $display_classes[] = 'ellipsis';

                    $display_el = sprintf( '<strong>%s</strong>',$display_html);

                    return sprintf( '<div%s>%s</div>',wpsstm_get_classes_attr($display_classes),$display_el ) . sprintf( '<div%s>%s</div>',wpsstm_get_classes_attr($edit_classes),$edit_el );

                break;

                case 'trackitem_track':

                    $display_html = $item->title;

                    //edit
                    $edit_el = sprintf('<input type="text" name="%s" value="%s"  class="cell-edit-value" />',
                                       $this->get_field_name($item,'title'),
                                       $item->title
                                      );

                    //display
                    $display_classes[] = 'ellipsis';

                    $display_el = sprintf( '<strong>%s</strong>',$display_html);

                    return sprintf( '<div%s>%s</div>',wpsstm_get_classes_attr($display_classes),$display_el ) . sprintf( '<div%s>%s</div>',wpsstm_get_classes_attr($edit_classes),$edit_el );

                break;

                case 'trackitem_album': //based on core function ion wp_link_category_checklist()

                    $display_html = $item->album;

                    //edit
                    $edit_el = sprintf('<input type="text" name="%s" value="%s"  class="cell-edit-value" />',$this->get_field_name($item,'album'),$item->album);

                    //display
                    $display_classes[] = 'ellipsis';

                    $display_el = sprintf( '<strong>%s</strong>',$display_html);

                    return sprintf( '<div%s>%s</div>',wpsstm_get_classes_attr($display_classes),$display_el ) . sprintf( '<div%s>%s</div>',wpsstm_get_classes_attr($edit_classes),$edit_el );

                break;
                    
                case 'trackitem_mbid':
                    
                    $mbid = $item->mbid;

                    //value
                    $field_value_name = $this->get_field_name($item,'mbid');
                    $edit_el = sprintf('<input type="text" name="%s" value="%s" class="cell-edit-value"/>',$field_value_name,$mbid);

                    //display
                    $display_classes[] = 'ellipsis';

                    $display_el = sprintf( '<strong>%s</strong>',$mbid);
                    
                    return sprintf( '<div%s>%s</div>',wpsstm_get_classes_attr($display_classes),$display_el ) . sprintf( '<div%s>%s</div>',wpsstm_get_classes_attr($edit_classes),$edit_el );
                    
                break;
                    
                case 'trackitem_sources':
                    
                    $sources_display =  $item->sources;
                    $display_el = ( $sources_display ) ?  count($sources_display) : '—';
                    $field_value_name = $this->get_field_name($item,'sources');

                    $ajax_url = add_query_arg( 
                        array( 
                            'action'        => 'wpsstm_track_sources_manager',
                            'track'         => array('post_id'=>$item->post_id,'artist'=>$item->artist,'title'=>$item->title,'album'=>$item->album),
                            'width'         => '600', 
                            'height'        => '550' 
                        ), 
                        admin_url( 'admin-ajax.php' )
                    );
                    
                    $sources_popup_link = sprintf('<a title="%s" href="%s" class="thickbox">%s</a>',__('Sources','wpsstm'),$ajax_url,__('Manage sources','wpsstm'));

                    $edit_el = $sources_popup_link;
                    
                    return sprintf( '<div%s>%s</div>',wpsstm_get_classes_attr($display_classes),$display_el ) . sprintf( '<div%s>%s</div>',wpsstm_get_classes_attr($edit_classes),$edit_el );
                break;
                    
                case 'trackitem_action':
                    //will be handled by handle_row_actions()
                break;

                default:
                    $output = null;
                    return apply_filters('wpsstm_trackitems_table_column_content',$output,$item,$column_name); //allow plugins to filter the content
                break;
            }

        }


        /**
         * Generates and displays row action links.
         *
         * @since 4.3.0
         * @access protected
         *
         * @param object $item        Link being acted upon.
         * @param string $column_name Current column name.
         * @param string $primary     Primary column name.
         * @return string Row action output for links.
         */
        protected function handle_row_actions( $item, $column_name, $primary ) {
            global $post;

            if ( 'trackitem_action' !== $column_name ) {
                return '';
            }

            //capability check
            $post_type = $post->post_type;
            $tracklist_obj = get_post_type_object($post_type);
            $post_type_track_obj = get_post_type_object(wpsstm()->post_type_track);

            $actions = array();

            //action link
            $action_url = add_query_arg(array('subtrack_id'=>$item->post_id),get_edit_post_link());
            $action_url = wp_nonce_url($action_url,'wpsstm_subtrack','wpsstm_subtrack_nonce');
            
            $row_actions = $subtrack_actions = array();
            
            if ( current_user_can($tracklist_obj->cap->edit_post,$post->post_id) ){ //can edit tracklist
                
                //remove from tracklist
                $subtrack_actions[] = array(
                    'slug'  => 'remove',
                    'text'  => __('Remove'),
                );
            }
            
            
            if ( current_user_can($post_type_track_obj->cap->edit_posts) ){ //can edit tracks
                
                //edit
                $subtrack_actions[] = array(
                    'slug'  => 'edit',
                    'text'  => __('Edit'),
                    'url'   => get_edit_post_link($item->post_id)
                );
                
                //save
                $subtrack_actions[] = array(
                    'slug'  => 'save',
                    'text'  => __('Save'),
                );
                
                //delete
                if($item->post_id){
                    $subtrack_actions[] = array(
                        'slug'  => 'delete',
                        'text'  => __('Delete'),
                    );
                }
                
            }
            
            foreach($subtrack_actions as $action){
                $slug = $action['slug'];
                
                $url = ( isset($action['url']) ) ? $action['url'] : null;
                if ( !$url ) $url = add_query_arg(array('subtrack_action'=>$slug),$action_url); //default subtrack action url
                        
                $row_actions[$slug] = sprintf('<a data-wpsstm-subtrack-action="%s" href="%s">%s</a>',$slug,$url,$action['text']);
            }


            return $this->row_actions( $row_actions, true );
        }

    }

}

?>