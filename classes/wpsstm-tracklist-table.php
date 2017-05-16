<?php


/**
 * Based on class WP_List_Table
 */

class WP_SoundSytem_TracksList_Table{
    var $tracklist;
    var $page;
    var $per_page = 0;
    var $track_index = 0;
    var $paged_var = 'tracklist_page';
    var $no_items_label = null;
    var $can_player = true;
    var $sources_db_only = true;

    function __construct($tracklist){
        global $status, $page;
        
        $this->tracklist = $tracklist;
        
        $this->no_items_label = __( 'No tracks found.','wpsstm');

        $this->page = ( isset($_REQUEST[$this->paged_var]) ) ? $_REQUEST[$this->paged_var] : 1;

        $this->can_player = ( !is_admin() && wpsstm()->get_options('player_enabled') == 'on' );
    }
    
    function prepare_items() {
        
        /**
         * Instead of querying a database, we're going to fetch the example data
         * property we created for use in this plugin. This makes this example 
         * package slightly different than one you might build on your own. In 
         * this example, we'll be using array manipulation to sort and paginate 
         * our data. In a real-world implementation, you will probably want to 
         * use sort and pagination data to build a custom query instead, as you'll
         * be able to use your precisely-queried data immediately.
         */
        $tracks = $this->tracklist->tracks;
        
        /***********************************************************************
         * ---------------------------------------------------------------------
         * vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv
         * 
         * In a real-world situation, this is where you would place your query.
         *
         * For information on making queries in WordPress, see this Codex entry:
         * http://codex.wordpress.org/Class_Reference/wpdb
         * 
         * ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
         * ---------------------------------------------------------------------
         **********************************************************************/
        
                
        /**
         * REQUIRED for pagination. Let's figure out what page the user is currently 
         * looking at. We'll need this later, so you should always include it in 
         * your own package classes.
         */
        $current_page = $this->get_pagenum();
        
        /**
         * REQUIRED for pagination. Let's check how many items are in our data array. 
         * In real-world use, this would be the total number of items in your database, 
         * without filtering. We'll need this later, so you should always include it 
         * in your own package classes.
         */
        $total_items = count($tracks);
        
        
        /**
         * The WP_List_Table class does not handle pagination for us, so we need
         * to ensure that the data is trimmed to only the current page. We can use
         * array_slice() to 
         */
        if ($this->per_page > 0){
            $tracks = array_slice((array)$tracks,(($current_page-1)*$this->per_page),$this->per_page);
        }

        /**
         * REQUIRED. Now we can add our *sorted* data to the items property, where 
         * it can be used by the rest of the class.
         */
        $this->items = $tracks;
        

        /**
         * REQUIRED. Now we need to define our column headers. This includes a complete
         * array of columns to be displayed (slugs & titles), a list of columns
         * to keep hidden, and a list of columns that are sortable. Each of these
         * can be defined in another method (as we've done here) before being
         * used to build the value for our _column_headers property.
         */
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        
        /**
         * REQUIRED. Finally, we build an array to be used by the class for column 
         * headers. The $this->_column_headers property takes an array which contains
         * 3 other arrays. One for all columns, one for hidden columns, and one
         * for sortable columns.
         */
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        
        if ($this->per_page > 0) {
            $this->set_pagination_args( array(
                'total_items' => $total_items,                  //WE have to calculate the total number of items
                'per_page'    => $this->per_page,                     //WE have to determine how many items to show on a page
                'total_pages' => ceil($total_items/$this->per_page)   //WE have to calculate the total number of pages
            ) );
        }
    }
    
    function get_columns(){
        
        $columns['trackitem_order']     = '';

        //play button
        if ( $this->can_player ){
            foreach ($this->items as $item){
                $columns['trackitem_play_bt']     = '';
                break;
            }
        }
        
        if ( $this->show_property_column('image') ){
            $columns['trackitem_image']     = '';
        }
        
        $columns['trackitem_artist']    = __('Artist','wpsstm');
        $columns['trackitem_track']     = __('Title','wpsstm');

        
        if ( $this->show_property_column('album') ){
            $columns['trackitem_album']     = __('Album','wpsstm');
        }
        
        //favorite icon
        if ( wpsstm()->get_options('lastfm_favorites') ){
            $columns['favorite']     = '';
        }

        return $columns;
    }
    
    function get_sortable_columns() {
        $sortable_columns = array(
            //'title'     => array('title',false),     //true means it's already sorted
        );
        return $sortable_columns;
    }
    
    /**
     * @param type $key
     * @return boolean
     */
    
    function show_property_column($key){
        
        //Check that at least one of the items has a certain property ($key) value set.
        $at_least_one_value = false;
        foreach((array)$this->items as $item){
            if( property_exists($item,$key ) && $item->$key ) {
                $at_least_one_value = true;
                break;
            }
        }
        
        if (!$at_least_one_value) return false;
        
        //count the number of different values for this property.  If they are all the same, do not show the column.
        foreach((array)$this->items as $item){
            if( property_exists($item,$key ) && $item->$key ) {
                $all_values[] = $item->$key;
            }
        }
        
        $all_values_count = count(array_unique($all_values));
 
        if ($all_values_count == 1) return false;
        
        return true;
    }
    
    /**
     * Get the current page number
     *
     * @since 3.1.0
     * @access public
     *
     * @return int
     */
    public function get_pagenum() {
        
            $pagenum = $this->page -1;

            if( isset( $this->_pagination_args['total_pages'] ) && $pagenum > $this->_pagination_args['total_pages'] )
                    $pagenum = $this->_pagination_args['total_pages'];

            return max( 1, $pagenum );
    }
    

    /**
     * An internal method that sets all the necessary pagination arguments
     *
     * @param array $args An associative array with information about the pagination
     * @access protected
     */
    protected function set_pagination_args( $args ) {
            $args = wp_parse_args( $args, array(
                    'total_items' => 0,
                    'total_pages' => 0,
                    'per_page' => 0,
            ) );

            if ( !$args['total_pages'] && $args['per_page'] > 0 )
                    $args['total_pages'] = ceil( $args['total_items'] / $args['per_page'] );

            $this->_pagination_args = $args;
    }

	/**
	 * Display the table
	 *
	 * @since 3.1.0
	 * @access public
	 */
	public function display() {
        ?>
        <div class="wpsstm-tracklist wpsstm-tracklist-table" itemscope itemtype="http://schema.org/MusicPlaylist" data-tracks-count="<?php echo $this->tracklist->tracks_count;?>">
            <?php $this->display_tablenav( 'top' );?>
            <table>
                    <thead>
                    <tr>
                            <?php $this->print_column_headers(); ?>
                    </tr>
                    </thead>

                    <tbody>
                            <?php $this->display_rows_or_placeholder(); ?>
                    </tbody>

                    <tfoot>
                    <tr>
                            <?php //$this->print_column_headers( false ); ?>
                    </tr>
                    </tfoot>

            </table>
            <?php //$this->display_tablenav( 'bottom' );?>
        </div>

        <?php

	}
    
    protected function extra_tablenav($which){
        global $post;
        ?>
        <div>
            <?php
            if ($post->ID == $this->tracklist->post_id){ //don't show title if post = tracklist
                printf('<meta itemprop="name" content="%s" />',$this->tracklist->title);
            }else{
                $tracklist_link = sprintf('<a href="%s">%s</a>',get_permalink($this->tracklist->post_id),$this->tracklist->title);
                printf('<strong class="wpsstm-tracklist-title" itemprop="name">%s</strong>',$tracklist_link);
            }
            
            printf('<meta itemprop="numTracks" content="%s" />',$this->tracklist->tracks_count);
        
        
            if ( $this->tracklist->timestamp ){

                $date = get_date_from_gmt( date( 'Y-m-d H:i:s', $this->tracklist->timestamp ), get_option( 'date_format' ) );
                $time = get_date_from_gmt( date( 'Y-m-d H:i:s', $this->tracklist->timestamp ), get_option( 'time_format' ) );

                $text = sprintf(__('on  %s - %s','wpsstm'),$date,$time);

                printf('<small> <i class="fa fa-clock-o" aria-hidden="true"></i> %s</small>',$text);
                    
            }
        

            ?>
        

            <div class="alignright actions wpsstm-tracklist-actions">
                <?php

                    if ( !is_admin() ){
                        ?>
                        <a href="<?php echo wpsstm_get_tracklist_link($this->tracklist->post_id);?>" target="_blank" class="wpsstm-tracklist-action-share"><i class="fa fa-share-alt" aria-hidden="true"></i> <?php _e('Share', 'wpsstm'); ?></a>
                        <a href="<?php echo wpsstm_get_tracklist_link($this->tracklist->post_id,true);?>" class="wpsstm-tracklist-action-xspf"><i class="fa fa-rss" aria-hidden="true"></i> <?php _e('XSPF', 'wpsstm'); ?></a>

                        <a href="<?php echo wpsstm_get_tracklist_link($this->tracklist->post_id,true,true);?>" class="wpsstm-tracklist-action-xspf-download"><i class="fa fa-download" aria-hidden="true"></i> <?php _e('Download XSPF', 'wpsstm'); ?></a>
                        <?php
                    }

                ?>
            </div>
        </div>

    <?php
    }
        
    protected function display_tablenav( $which ) {
        
        $post_type = get_post_type($this->tracklist->post_id);
        
        $classes = array(
            'tracklist-nav',
            'tracklist-' . $post_type,
            esc_attr( $which )
            
        );

        ?>
        <div <?php echo wpsstm_get_classes_attr($classes);?>>

        <?php
                $this->extra_tablenav( $which );
                $this->pagination( $which );
        ?>

                <br class="clear" />
        </div>
        <?php
    }
    
    /**
     * Display the pagination.
     *
     * @since 3.1.0
     * @access protected
     *
     * @param string $which
     */
    protected function pagination( $which ) {
            if ( empty( $this->_pagination_args ) ) {
                    return;
            }

            $total_items = $this->_pagination_args['total_items'];
            $total_pages = $this->_pagination_args['total_pages'];
            $infinite_scroll = false;
            if ( isset( $this->_pagination_args['infinite_scroll'] ) ) {
                    $infinite_scroll = $this->_pagination_args['infinite_scroll'];
            }

            $output = '<span class="displaying-num">' . sprintf( _n( '1 item', '%s items', $total_items ), number_format_i18n( $total_items ) ) . '</span>';

            $current = $this->get_pagenum();

            $current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

            $current_url = remove_query_arg( array( 'hotkeys_highlight_last', 'hotkeys_highlight_first' ), $current_url );

            $page_links = array();

            $disable_first = $disable_last = '';
            if ( $current == 1 ) {
                    $disable_first = ' disabled';
            }
            if ( $current == $total_pages ) {
                    $disable_last = ' disabled';
            }
            $page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
                    'first-page' . $disable_first,
                    esc_attr__( 'Go to the first page' ),
                    esc_url( remove_query_arg( $this->page, $current_url ) ),
                    '&laquo;'
            );

            $page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
                    'prev-page' . $disable_first,
                    esc_attr__( 'Go to the previous page' ),
                    esc_url( add_query_arg( $this->page, max( 1, $current-1 ), $current_url ) ),
                    '&lsaquo;'
            );

            $html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
            $page_links[] = '<span class="paging-input">' . sprintf( _x( '%1$s of %2$s', 'paging' ), $current, $html_total_pages ) . '</span>';

            $page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
                    'next-page' . $disable_last,
                    esc_attr__( 'Go to the next page' ),
                    esc_url( add_query_arg( $this->page, min( $total_pages, $current+1 ), $current_url ) ),
                    '&rsaquo;'
            );

            $page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
                    'last-page' . $disable_last,
                    esc_attr__( 'Go to the last page' ),
                    esc_url( add_query_arg( $this->page, $total_pages, $current_url ) ),
                    '&raquo;'
            );

            $pagination_links_class = 'pagination-links';
            if ( ! empty( $infinite_scroll ) ) {
                    $pagination_links_class = ' hide-if-js';
            }
            $output .= "\n<span class='$pagination_links_class'>" . join( "\n", $page_links ) . '</span>';

            if ( $total_pages ) {
                    $page_class = $total_pages < 2 ? ' one-page' : '';
            } else {
                    $page_class = ' no-pages';
            }
            $this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";

            echo $this->_pagination;
    }
    
    /**
     * Print column headers, accounting for hidden and sortable columns.
     *
     * @since 3.1.0
     * @access public
     *
     * @param bool $with_id Whether to set the id attribute or not
     */
    public function print_column_headers( $with_id = true ) {
            list( $columns, $hidden, $sortable ) = $this->get_column_info();

            $current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
            $current_url = remove_query_arg( $this->page, $current_url );

            if ( isset( $_GET['orderby'] ) )
                    $current_orderby = $_GET['orderby'];
            else
                    $current_orderby = '';

            if ( isset( $_GET['order'] ) && 'desc' == $_GET['order'] )
                    $current_order = 'desc';
            else
                    $current_order = 'asc';

            foreach ( $columns as $column_key => $column_display_name ) {
                    $class = array( 'manage-column', "column-$column_key" );

                    $style = '';
                    if ( in_array( $column_key, $hidden ) )
                            $style = 'display:none;';

                    $style = ' style="' . $style . '"';

                    if ( isset( $sortable[$column_key] ) ) {
                            list( $orderby, $desc_first ) = $sortable[$column_key];

                            if ( $current_orderby == $orderby ) {
                                    $order = 'asc' == $current_order ? 'desc' : 'asc';
                                    $class[] = 'sorted';
                                    $class[] = $current_order;
                            } else {
                                    $order = $desc_first ? 'desc' : 'asc';
                                    $class[] = 'sortable';
                                    $class[] = $desc_first ? 'asc' : 'desc';
                            }

                            $column_display_name = '<a href="' . esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
                    }

                    $id = $with_id ? "id='$column_key'" : '';

                    if ( !empty( $class ) )
                            $class = "class='" . join( ' ', $class ) . "'";

                    echo "<th scope='col' $id $class $style>$column_display_name</th>";
            }
    }
    
    /**
     * Get a list of all, hidden and sortable columns, with filter applied
     *
     * @since 3.1.0
     * @access protected
     *
     * @return array
     */
    protected function get_column_info() {
            if ( isset( $this->_column_headers ) )
                    return $this->_column_headers;

            $columns = get_column_headers( $this->screen );
            $hidden = get_hidden_columns( $this->screen );

            $sortable_columns = $this->get_sortable_columns();
            /**
             * Filter the list table sortable columns for a specific screen.
             *
             * The dynamic portion of the hook name, `$this->screen->id`, refers
             * to the ID of the current screen, usually a string.
             *
             * @since 3.5.0
             *
             * @param array $sortable_columns An array of sortable columns.
             */
            $_sortable = apply_filters('spiff_manage_tracklist_sortable_columns', $sortable_columns );

            $sortable = array();
            foreach ( $_sortable as $id => $data ) {
                    if ( empty( $data ) )
                            continue;

                    $data = (array) $data;
                    if ( !isset( $data[1] ) )
                            $data[1] = false;

                    $sortable[$id] = $data;
            }

            $this->_column_headers = array( $columns, $hidden, $sortable );

            return $this->_column_headers;
    }
    
    /**
     * Generate the tbody element for the list table.
     *
     * @since 3.1.0
     * @access public
     */
    public function display_rows_or_placeholder() {
            if ( $this->has_items() ) {
                    $this->display_rows();
            } else {
                    echo '<tr class="no-items"><td class="colspanchange" colspan="' . $this->get_column_count() . '">';
                    $this->no_items();
                    echo '</td></tr>';
            }
    }
    
    /**
     * Whether the table has items to display or not
     *
     * @since 3.1.0
     * @access public
     *
     * @return bool
     */
    public function has_items() {
            return !empty( $this->items );
    }
    
    /**
     * Generate the table rows
     *
     * @since 3.1.0
     * @access public
     */
    public function display_rows() {
            foreach ( $this->items as $item )
                    $this->single_row( $item );
    }
    
    /**
     * Generates content for a single row of the table
     *
     * @since 3.1.0
     * @access public
     *
     * @param object $item The current item
     */
    public function single_row( $item ) {
        
            $data_attr_str = null;
        
            if ( $this->can_player ){
                $sources_attr_arr = wpsstm_player()->get_playable_sources($item,$this->sources_db_only);

                if ($sources_attr_arr){
                    $data_attr_str = esc_attr( json_encode($sources_attr_arr) );
                }
            }
        
            $track_classes = array();
            printf( '<tr itemprop="track" itemscope itemtype="http://schema.org/MusicRecording" %s data-wpsstm-sources="%s">',wpsstm_get_classes_attr($track_classes),$data_attr_str);
            $this->single_row_columns( $item );
            echo '</tr>';
    }
    
    /**
     * Generates the columns for a single row of the table
     *
     * @since 3.1.0
     * @access protected
     *
     * @param object $item The current item
     */
    protected function single_row_columns( $item ) {
            list( $columns, $hidden ) = $this->get_column_info();

            foreach ( $columns as $column_name => $column_display_name ) {
                
                $attr_str = null;
                $attr = array();

                $classes = array($column_name,'column-'.$column_name);
                $attr['class'] = implode(' ',$classes);

                $attr['style'] = ( in_array( $column_name, $hidden ) ) ? 'display:none;' : null;

                switch($column_name){
                    case 'trackitem_artist':
                        $attr['itemprop'] = 'byArtist';
                    break;
                    case 'trackitem_track':
                        $attr['itemprop'] = 'name';
                    break;
                    case 'trackitem_album':
                        $attr['itemprop'] = 'inAlbum';
                    break;
                }

                foreach ($attr as $key=>$value){
                    $attr_str.=sprintf(' %s="%s"',$key,$value);
                }

                printf('<td %s>',$attr_str);

                if ( method_exists( $this, 'column_' . $column_name ) ) {
                    echo call_user_func( array( $this, 'column_' . $column_name ), $item );
                }else {
                    echo $this->column_default( $item, $column_name );
                }

                echo '</td>';
            }
    }
    
    function column_default($item, $column_name){
        switch($column_name){
            case 'trackitem_order':
                $this->track_index++;
                return $this->track_index;
            case 'trackitem_play_bt':
                return wpsstm_player()->get_track_button($item);
            case 'trackitem_track':
                $artist = $item->artist;
                $track = $item->title;
                return wpsstm_get_post_track_link_by_name($artist,$track,null);
            break;
            case 'trackitem_artist':
                $artist = wpsstm_get_post_artist_link_by_name($item->artist);
                return $artist;
            break;
            case 'trackitem_album':
                $artist = $item->artist;
                $album = $item->album;
                return wpsstm_get_post_album_link_by_name($album,$artist,true);
            break;
            case 'trackitem_image':
                if ( $item->image ){
                    return sprintf('<img src="%s"/>',$item->image);
                }
            break;
            case 'favorite':
                $favorite_icon = '<i class="wpsstm-favorite-icon fa fa-heart-o" aria-hidden="true"></i>';
                $unfavorite_icon = '<i class="wpsstm-unfavorite-icon fa fa-heart" aria-hidden="true"></i>';
                return $favorite_icon.$unfavorite_icon;
            default:
                if ( !is_admin() ) break;
                return print_r($item,true); //Show the whole array for troubleshooting purposes
        }
    }
    
    public function get_column_count() {
            list ( $columns, $hidden ) = $this->get_column_info();
            $hidden = array_intersect( array_keys( $columns ), array_filter( $hidden ) );
            return count( $columns ) - count( $hidden );
    }
    
    /**
     * Message to be displayed when there are no items
     *
     * @since 3.1.0
     * @access public
     */
    public function no_items() {
        echo $this->no_items_label;
    }
    
    
    
}
?>
