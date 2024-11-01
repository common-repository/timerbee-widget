<?php
/**
 * Plugin Name:     Timerbee Plugin
 * Plugin URI:      https://www.timerbee.de/timerbee-wp-plugin
 * Description:     Timerbee Worldpress Plugin
 * Author:          Imilia Interactive Mobile Applications GmbH
 * Author URI:      https://www.timerbee.de
 * Text Domain:     timerbee-widget
 * Domain Path:     /languages
 * Version:         0.3.0
 *
 * @package         Timerbee_Plugin
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'TIMERBEE_VERSION', '0.3.0' );
define( 'TIMERBEE_API_PATH', '/apipub/wp/widget' );
define( 'TIMERBEE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * create database tables
 *
 * @return void
 */
function tb_portal_widget_db_install() {
	global $wpdb;

	$installed_ver = get_option("tb_portal_widget_db_version");

	if ( !empty($installed_ver) && ($installed_ver != TIMERBEE_VERSION) ) {
		// this is an update


		update_option( "tb_portal_widget_db_version", TIMERBEE_VERSION );
	} else {
		// this is the initial install

		$table_name = $wpdb->prefix . 'tb_portal_widget';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			shortname varchar(255) NOT NULL,
			url varchar(255) NOT NULL,
			apikey varchar(255) NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$table_name = $wpdb->prefix . 'tb_portal_widget_errortext';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			errortext varchar(255) NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		add_option( 'tb_portal_widget_db_version', TIMERBEE_VERSION );
	}
}

/**
 * install database init data
 *
 * @return void
 */
function tb_portal_widget_db_install_data() {
	global $wpdb;

	$errortext = 'Der Dienst ist nicht verfügbar';

	$table_name = $wpdb->prefix . 'tb_portal_widget_errortext';

	$wpdb->insert(
		$table_name,
		array(
			'errortext' => $errortext,
		)
	);
}


/**
 * check if database needs update
 *
 * @return void
 */
function tb_portal_widget_update_db_check() {
    global $tb_portal_widget_db_version;
    if ( get_site_option( 'tb_portal_widget_db_version' ) != $tb_portal_widget_db_version ) {
        tb_portal_widget_db_install();
    }
}


/**
 * short code
 *
 * usage: [tb_portal_widget shortname="default"]
 *
 */
function tb_portal_widget_shortcode($atts, $content = null) {
    $a = shortcode_atts( array(
        'shortname' => 'shortName not set',
	), $atts );

	// this is a parameter of the short code
	$shortname = $a['shortname'];

        global $wpdb;
        //get widget data from db
        $tablename=$wpdb->prefix.'tb_portal_widget';
        $query="SELECT * FROM $tablename WHERE shortname='$shortname'";
        $widgetData=$wpdb->get_results($query);

        //get errotext from db
        $tablename=$wpdb->prefix.'tb_portal_widget_errortext';
        $query="SELECT * FROM $tablename";
        $errorText = $wpdb->get_results($query);
        if(!empty($errorText)){
            $errorText=$errorText[0]->errortext;
        }else{
            $errorText='Der Dienst ist nicht verfügbar';
        }

        //send request to timerbee server
        $html = "";
        if(!empty($widgetData)){
            $widgetData=$widgetData[0];
            try {
		$url = $widgetData->url . TIMERBEE_API_PATH;
		$args = array();

		$args['headers'] = array();
		$args['headers']['X-API-Key'] = $widgetData->apikey;
		$args['headers']['Content-Type'] =  'application/json';

		$args['body'] = json_encode(array('shortName' => $widgetData->shortname, 'pluginversion' => TIMERBEE_VERSION ));

		$data = wp_remote_post( $url, $args );

		if(is_wp_error($data)) {
			$html = $errorText;
		} else {
			$body = $data["body"];
			$code = $data["response"]["code"];
			if($code < 200 || $code > 299) {
				$html = $errorText;
			} else {
				$json = json_decode($body);
				$html = $json->{'html'};
			}
		}
            }catch(Exception $e) {
		$html = $errorText;
            }
        }else{
            $html = "Error: Timerbee widget with shortname <b>".$shortname."</b> is not defined";
        }

	return $html;
}


function tb_portal_plugin_load_textdomain() {
    load_plugin_textdomain( 'tb_portal', false, basename( dirname( __FILE__ ) ) . '/languages' );
}

register_activation_hook( __FILE__, 'tb_portal_widget_db_install' );
register_activation_hook( __FILE__, 'tb_portal_widget_db_install_data' );
add_shortcode('tb_portal_widget', 'tb_portal_widget_shortcode');
add_action( 'plugins_loaded', 'tb_portal_widget_update_db_check' );
add_action( 'plugins_loaded', 'tb_portal_plugin_load_textdomain' );

require_once( TIMERBEE_PLUGIN_DIR . 'admin/timerbee-widget-admin.php' );
