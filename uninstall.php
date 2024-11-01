<?php
/**
  * remove database tables
  *
  * @return void
  */
function tb_portal_widget_db_remove() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'tb_portal_widget';
	$sql = "DROP TABLE IF EXISTS $table_name;";
	$wpdb->query($sql);

	$table_name = $wpdb->prefix . 'tb_portal_widget_errortext';
	$sql = "DROP TABLE IF EXISTS $table_name;";
	$wpdb->query($sql);

	delete_option("tb_portal_widget_db_version");
}

if( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();
tb_portal_widget_db_remove();

?>
