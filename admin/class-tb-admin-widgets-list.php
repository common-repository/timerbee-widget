<?php

if (!class_exists('WP_List_Table')) {
    require( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Tb_Admin_Widgets_List extends WP_List_Table {


    function get_columns() {
        //!! return format is: ‘internal-name’ => ‘table-col-title’
        //!! 'internal-name' must be name of column in database
        return $columns = array(
            'cb' => '<input type="checkbox" />',
            'shortname' => __('Short name','tb_portal'),
            'url' => __('url','tb_portal'),
            'apikey' => __('api key','tb_portal'),
            'test'=>__('Test Api','tb_portal')
        );
    }

     function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%s" />',
            $item->id
        );
    }


    function column_shortname($item) {
        $actions = array(
            'edit' => sprintf('<a href="?page=%s&id=%s">'.__("Edit","tb_portal").'</a>', 'widgets_form', $item->id),
            'delete' => sprintf('<a href="?page=%s&action=%s&id=%s">'.__("Delete","tb_portal").'</a>',$_REQUEST['page'],'delete',$item->id),
        );

        return sprintf('%1$s %2$s', $item->shortname, $this->row_actions($actions));
    }

    function column_test($item){
        $nonce = wp_create_nonce("ajax-test-api-nonce");
        $button = sprintf(
                '<button type="button"'
                . ' class="button button-primary btn-test-api"'
                . 'data-nonce="%s"'
                . ' id="%s">'
                . __("Test Api","tb_portal").'</button>',$nonce,$item->id);
        $loadingGif = sprintf('<div id="api-test-spinner-%s" class="api-test-spinner"></div>',$item->id);
        $resultContainer=sprintf('<div id="api-test-result-%s"></div>',$item->id);
        return $button . $loadingGif. $resultContainer;

    }

    function column_default($item, $column_name) {

     if(property_exists($item, $column_name)){
         //column data exists in db record
         return $item->$column_name;
     }

    }

   function prepare_items() {

        //prepare header data
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();

        global $wpdb;
        $table_name = $wpdb->prefix . 'tb_portal_widget';
        $query = "SELECT * FROM $table_name";

        //paging preparation
        $per_page = 5;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->query($query);

        //handle default/first page display
        if(empty($current_page) || !is_numeric($current_page) || $current_page<=0 ){
            $current_page=1;
        }
        $total_pages = ceil($total_items/$per_page);

        //calculate db select offset
        if (!empty($current_page) && !empty($per_page)) {
            $offset = ($current_page - 1) * $per_page;
            $query .= ' LIMIT ' . (int) $offset . ',' . (int) $per_page;
        }

        //init pagination
        $this->set_pagination_args(array(
            "total_items" => $total_items,
            "total_pages" => $total_pages,
            "per_page" => $per_page,
        ));

        $this->items = $wpdb->get_results($query);
    }

    function handle_row_actions( $item, $column_name, $primary ) {
	return $column_name === $primary ? '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details' ) . '</span></button>' : '';
    }

    function get_bulk_actions()
    {
        $actions = array(
            'delete' => __("Delete","tb_portal")
        );
        return $actions;
    }

    function process_bulk_action()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tb_portal_widget';

        if ('delete' === $this->current_action()) {
            $ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
            if (is_array($ids)) $ids = implode(',', $ids);

            if (!empty($ids)) {
                $wpdb->query("DELETE FROM $table_name WHERE id IN($ids)");
            }
        }
    }






}
