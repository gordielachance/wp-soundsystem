<?php

// WP_List_Table is not loaded automatically so we need to load it in our application
if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
/**
 * Create a new table class that will extend the WP_List_Table
 */
class Wpsstm_Subtrack_List_Table extends WP_List_Table{
    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    
    var $data;
    
    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $data = $this->data;

        usort( $data, array( &$this, 'sort_data' ) );

        $perPage = get_option( 'posts_per_page' );
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);
        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ) );
        $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);
        $this->_column_headers = array($columns, $hidden, $sortable);

        foreach($data as $post){
            $this->items[] = new WPSSTM_Track($post);
        }

    }
    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */

    public function get_columns(){
        
        $screen = get_current_screen();
        $is_single_tracklist_edit = ( ($screen->base === 'post') && in_array($screen->post_type,wpsstm()->tracklist_post_types) );

        $columns = array(
            'cb'=>                  null,
            'position'=>            __('Position','wpsstm'),
            'track'=>               __('Track','wpsstm'),
            'links'=>               __('Links','wpsstm'),
            'tracklist'=>            __('Tracklist','wpsstm'),
            'from_tracklist'=>      __('From Tracklist','wpsstm'),
            'author'=>              __('By','wpsstm'),
            'time'=>                __('Added','wpsstm'),
        );

        if($is_single_tracklist_edit){ //do not show tracklist on a single tracklist page
            unset($columns['tracklist']);
        }

        return $columns;
    }
    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns()
    {
        return array();
    }
    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns()
    {
        return array(
            'position' =>       array('position', true),
            'track' =>          array('track', false),
            'author' =>         array('author', false),
            'time' =>           array('time', false),
            'links' =>          array('links', false),
        );
    }
    
	public function column_cb( $item ) {

        $input_attr = array(
            'type'=>        'checkbox',
            'name'=>        'wpsstm-subtracks[id][]',
            'value'=>       esc_attr( $item->subtrack_id ),
        );
        
        $input_attr = array_filter($input_attr);
        $input_attr = wpsstm_get_html_attr($input_attr);

        printf('<input %s />',$input_attr);

	}

    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default( $item, $column_name ){

        switch( $column_name ) {
            case 'id':
                return $item->subtrack_id;
            break;
            case 'position':
                return $item->position;
            break;
            case 'track':
                if ( !$id = $item->post_id) return;
                $url = get_edit_post_link($id);
                $title = get_the_title($id);
                $title_short = wpsstm_shorten_text($title);
                return sprintf('<a href="%s" title="%s">%s</a>',$url,$title,$title_short);
            break;
            case 'tracklist':
                if ( !$id = $item->tracklist->post_id ) return;
                $url = get_edit_post_link($id);
                $title = get_the_title($id);
                $title_short = wpsstm_shorten_text($title);
                return sprintf('<a href="%s" title="%s">%s</a>',$url,$title,$title_short);
            break;
            case 'from_tracklist':
                if ( !$id=$item->from_tracklist ) return;
                $url = get_edit_post_link($tracklist_id);
                $title = get_the_title($id);
                $title_short = wpsstm_shorten_text($title);
                return sprintf('<a href="%s" title="%s">%s</a>',$url,$title,$title_short);
            break;
            case 'author':
                if ( !$id = $item->subtrack_author ) return;
                return get_author_name($id);
            break;
            case 'links':
                
                $links_query = $item->query_links();
                $url = admin_url('edit.php');
                $url = add_query_arg( array('post_type'=>wpsstm()->post_type_track_link,'parent_track'=>$item->post_id,'post_status'=>'publish'),$url );
                return sprintf('<a href="%s">%d</a>',$url,$links_query->post_count);
            break;
            case 'time':
                return $item->subtrack_time;
            break;
            default:
                return print_r( $item, true ) ;
            break;
        }
    }
    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data( $a, $b ){
        // Set defaults
        $orderby = 'subtrack_order';
        $order = 'asc';
        // If orderby is set, use this as the sort column
        if(!empty($_GET['orderby'])){
            $orderby = $_GET['orderby'];
        }
        // If order is set use this as the order
        if(!empty($_GET['order'])){
            $order = $_GET['order'];
        }

        $result = strcmp( $a->$orderby, $b->$orderby );
        if($order === 'asc'){
            return $result;
        }
        return -$result;
    }
    
    protected function extra_tablenav( $which ) {
        global $wpsstm_tracklist;
        ?>
        <div class="alignleft actions">
        <?php
        if ( 'top' == $which && !is_singular() ) {
            
            if ($wpsstm_tracklist->tracklist_type === 'live'){
                //Sync
                //TOUFIX URGENT
                /*
                $link_args = array(
                    'page'      => 'pending-importation',
                    'action'    => 'import_pin'
                );

                printf(
                    '<a class="button" href="%1$s">%2$s</a>',
                    'URL',
                    __('Refresh Radio','wpsstm')
                );
                */
            }
        }
        ?>
        </div>
        <?php
    }
    
    function get_bulk_actions() {
        $actions = array(
            'unlink'    => __('Unlink','wpsstm'),
            'autolink'  => __('Autolink','wpsstm'),
            'delete'    => __('Delete','wpsstm'),
        );
        
        //data engine lookup
        foreach(wpsstm()->engines as $engine){
            $key = sprintf('lookup-%s',$engine->slug);
            $actions[$key] = sprintf(__('%s lookup','wpsstm'),$engine->name);
        }

        return $actions;
    }
    
    function process_bulk_action(){
        
        $ids = wpsstm_get_array_value('ids',$_GET);
        
        print_r($ids);die("zibb");
        
        switch ( $this->current_action() ){
      
            case 'delete':
                die("tizzz");
              //  wp_die('Items deleted (or they would be if we had items to delete)!');
               foreach($_GET['id'] as $id) {
                    //$id will be a string containing the ID of the video
                    //i.e. $id = "123";                
                    delete_this_video($id);
                }
            break;
        }
    }

}