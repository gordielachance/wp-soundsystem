<?php


/**
 * Based on class WP_List_Table
 */
if(!class_exists('WP_SoundSytem_TracksList_Admin_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

    class WP_SoundSytem_TracksList_Admin_Table extends WP_List_Table {

        var $current_track_idx = -1;
        var $links_per_page = -1;
        var $can_manage_rows;

        function prepare_items() {
            global $post;

            $columns = $this->get_columns();
            $hidden = array();
            $sortable = $this->get_sortable_columns();
            $this->_column_headers = array($columns, $hidden, $sortable);

            $current_page = $this->get_pagenum();
            $total_items = count($this->items);

            if ($this->links_per_page > 0){
                $this->items = array_slice((array)$this->items,(($current_page-1)*$this->links_per_page),$this->links_per_page);
            }

            $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $this->links_per_page
            ) );
            $this->items = $this->items;
            
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
                $args['tracklist_id'] = $post->ID;
                $blank_track = new WP_SoundSystem_Subtrack($args);
                $blank_track->row_classes = array('metabox-table-row-new','metabox-table-row-edit');
                $this->single_row($blank_track);
            }

            parent::display_rows_or_placeholder();
        }

        /*
        override parent function so we can add attributes, etc.
        */
        public function single_row( $item ) {
            $this->current_track_idx ++;
            
            $item_classes = array();
            if ( property_exists($item, 'row_classes') ){
                $item_classes = $item->row_classes;
            }

            printf( '<tr %s data-track-key="%s" data-track-order="%s">',wpsstm_get_classes_attr($item_classes),$this->current_track_idx,$item->subtrack_order );
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
                <div class="alignleft actions">
                    <?php
                    if ( $this->can_manage_rows ){   
                        ?>
                        <a class="add-tracklist-track button"><?php echo esc_html_x('Add Row', 'link', 'wpsstm'); ?></a>
                        <?php
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
            
            //TO FIX capabilities
            
            $actions = array();

            $actions['remove']  = __('Remove tracks','wpsstm');
            $actions['save']    = __('Save tracks','wpsstm');
            $actions['delete']  = __('Delete tracks','wpsstm');
            

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
                'trackitem_mbid'      => __('MBID','wpsstm'),
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

        public function get_field_name( $slug ) {
            return sprintf('wpsstm[tracklist][tracks][%d][%s]',$this->current_track_idx,$slug);
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
            global $post;

            $classes = array('metabox-table-cell-toggle');
            $display_classes = array_merge( $classes,array('metabox-table-cell-display') );
            $edit_classes = array_merge( $classes,array('metabox-table-cell-edit') );
            switch($column_name){

                case 'cb':

                    $input_cb = sprintf( '<input type="checkbox" name="%s" value="on"/>',
                        $this->get_field_name('selected')
                    );
                    $input_track_id = sprintf( '<input type="hidden" name="%s" value="%s"/>',
                        $this->get_field_name('post_id'),
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
                    printf( '<input type="text" name="%s" value="%s" size="3"/>',$this->get_field_name('track_order'),$item->subtrack_order);
                break;

                case 'trackitem_artist':

                    $artist = $item->artist;
                    $display_html = wpsstm_get_post_artist_link_by_name($artist,true);

                    //edit
                    $edit_el = sprintf('<input type="text" name="%s" value="%s"  class="cell-edit-value" />',
                                       $this->get_field_name('artist'),
                                       $item->artist
                                      );

                    //display
                    $display_classes[] = 'ellipsis';

                    $display_el = sprintf( '<strong>%s</strong>',$display_html);

                    return sprintf( '<p%s>%s</p>',wpsstm_get_classes_attr($display_classes),$display_el ) . sprintf( '<span%s>%s</span>',wpsstm_get_classes_attr($edit_classes),$edit_el );

                break;

                case 'trackitem_track':

                    $artist = $item->artist;
                    $track = $item->title;
                    $display_html = wpsstm_get_post_track_link_by_name($artist,$track,null,true);

                    //edit
                    $edit_el = sprintf('<input type="text" name="%s" value="%s"  class="cell-edit-value" />',
                                       $this->get_field_name('title'),
                                       $item->title
                                      );

                    //display
                    $display_classes[] = 'ellipsis';

                    $display_el = sprintf( '<strong>%s</strong>',$display_html);

                    return sprintf( '<p%s>%s</p>',wpsstm_get_classes_attr($display_classes),$display_el ) . sprintf( '<span%s>%s</span>',wpsstm_get_classes_attr($edit_classes),$edit_el );

                break;

                case 'trackitem_album': //based on core function ion wp_link_category_checklist()

                    $artist = $item->artist;
                    $album = $item->album;
                    $display_html = wpsstm_get_post_album_link_by_name($album,$artist,true);

                    //edit
                    $edit_el = sprintf('<input type="text" name="%s" value="%s"  class="cell-edit-value" />',$this->get_field_name('album'),$item->album);

                    //display
                    $display_classes[] = 'ellipsis';

                    $display_el = sprintf( '<strong>%s</strong>',$display_html);

                    return sprintf( '<p%s>%s</p>',wpsstm_get_classes_attr($display_classes),$display_el ) . sprintf( '<span%s>%s</span>',wpsstm_get_classes_attr($edit_classes),$edit_el );

                break;
                    
                case 'trackitem_mbid':
                    
                    $mbid = $item->mbid;

                    //value
                    $field_value_name = $this->get_field_name('mbid');
                    $edit_el = sprintf('<input type="text" name="%s" value="%s" class="cell-edit-value"/>',$field_value_name,$mbid);

                    //display
                    $display_classes[] = 'ellipsis';

                    $display_el = sprintf( '<strong>%s</strong>',$mbid);
                    
                    return sprintf( '<p%s>%s</p>',wpsstm_get_classes_attr($display_classes),$display_el ) . sprintf( '<span%s>%s</span>',wpsstm_get_classes_attr($edit_classes),$edit_el );
                    
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

            if ( 'trackitem_action' !== $column_name ) {
                return '';
            }

            $actions = array();
            $is_attached = ($item->post_id);
            
            //action link
            $action_url = add_query_arg(array('subtrack_id'=>$item->post_id),get_edit_post_link());
            $action_url = wp_nonce_url($action_url,'wpsstm_subtrack','wpsstm_subtrack_nonce');
            
            //edit
            $edit_url = get_edit_post_link($item->post_id);
            $actions['edit'] = sprintf('<a class="%s" href="%s">%s</a>','wpsstm-subtrack-action-edit',$edit_url,__('Edit'));
            
            //TO FIX ajax functions not working yet
            /*

            //save
            $save_url = add_query_arg(array('subtrack_action'=>'save'),$action_url);
            $actions['save'] = sprintf('<a class="%s" href="%s">%s</a>','wpsstm-subtrack-action-save',$save_url,__('Save'));
            
            //remove
            $remove_url = add_query_arg(array('subtrack_action'=>'remove'),$action_url);
            $actions['remove'] = sprintf('<a class="%s" href="%s">%s</a>','wpsstm-subtrack-action-save',$remove_url,__('Remove'));
            
            //delete
            if ( $is_attached ){
                $delete_url = add_query_arg(array('subtrack_action'=>'delete'),$action_url);
                $delete_url = wp_nonce_url($delete_url,'wpsstm_subtrack','wpsstm_subtrack_nonce');
                $actions['delete'] = sprintf('<a class="%s" href="%s">%s</a>','wpsstm-subtrack-action-delete',$delete_url,__('Delete'));
            }
            
            */

            return $this->row_actions( $actions, true );
        }

    }

}

?>