<?php

require_once( TIMERBEE_PLUGIN_DIR . 'admin/class-tb-admin-widgets-list.php' );

//action functions
function tb_portal_widget_setup_menu(){
    //add "Timerbee widget" in admin menu
    add_menu_page( 'Timberbee Widget Admin', 'Timberbee Widget', 'activate_plugins', 'tb-widgets', 'tb_portal_widget_admin' );
    //Add widgets list also to submenu
    add_submenu_page('tb-widgets', 'Timberbee Widget Admin', __('Widgets','tb_portal'), 'activate_plugins', 'tb-widgets', 'tb_portal_widget_admin' );
    //Add new widget form submenu
    add_submenu_page('tb-widgets', __('Add new','tb_portal'), __('Add new','tb_portal'), 'activate_plugins', 'widgets_form', 'tb_portal_form_page_handler');
}


function tb_portal_widget_admin(){
    echo' <div class="wrap">';
        displayWidgetsTable();
        displayErrorTextEditForm();
    echo' </div>';

}


function tb_portal_test_api_handler() {
    //its endpoint for ajax call on "test api"button click
    $request=$_REQUEST;

    global $wpdb;
    //get widget data from db
    $tablename=$wpdb->prefix.'tb_portal_widget';
    $query="SELECT * FROM $tablename WHERE id=".$request['id'];
    $widgetData=$wpdb->get_results($query);
    $html="";
    $nonceValid=check_ajax_referer("ajax-test-api-nonce", 'nonce' ); // check for security purposes
    if($nonceValid){
        if(!empty($widgetData)){
                $widgetData=$widgetData[0];
                try {
                    $url = $widgetData->url . TIMERBEE_API_PATH;
                    $args = array();

                    $args['headers'] = array();
                    $args['headers']['X-API-Key'] = $widgetData->apikey;
					$args['body'] = array('shortName' => $widgetData->shortname, 'pluginversion' => TIMERBEE_VERSION );

                    $data = wp_remote_post( $url, $args );

                    if(is_wp_error($data)) {
                            $html = "<b>".__("ERROR","tb_portal").":</b>".__("Service is unreachable","tb_portal");
                    } else {
                            $code = $data["response"]["code"];
                            if($code < 200 || $code > 299) {
                                    $html = "<b>".__("ERROR","tb_portal").":</b>".__("api responded with code","tb_portal")." ".$code;
                            } else {
                                    $html = "<b>".__("OK","tb_portal").":</b>".__("api responded with code","tb_portal")." ".$code;
                            }
                    }
                }catch(Exception $e) {
                    $html = __("Undefined Exception raised during doing test request","tb_portal");
                }

        }
    }

    echo $html;

    wp_die();
}

function tb_portal_form_page_handler(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tb_portal_widget';

    $message = '';
    $notice = '';

    $default = array(
        'id' => 0,
        'shortname' => '',
        'url' => '',
        'apikey' => ''
    );
     $item = shortcode_atts($default, $_REQUEST);


    if ( isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
		//trim api url
		$item['url']=trim($item['url']);

        $item_valid = tb_portal_validate_widget($item);
        if ($item_valid === true) {
			/* +1 comes from http(s);// */
			if(strpos($item['url'], '/') + 1 != strrpos($item['url'], '/')) {
				// we have a trainling "/subpage" string
				$item['url']=substr($item['url'], 0, strrpos( $item['url'], '/'));
			}

            if ($item['id'] == 0) {
                $result = $wpdb->insert($table_name, $item);
                $item['id'] = $wpdb->insert_id;
                if ($result) {
                    $message = __('Item was successfully saved','tb_portal');
                } else {
                    $notice = __('There was an error while saving item','tb_portal');
                }
            } else {
                $result = $wpdb->update($table_name, $item, array('id' => $item['id']));
                if ($result) {
                    $message = __('Item was successfully updated','tb_portal');
                }
            }
        } else {
            $notice = $item_valid;
        }
    }
    else {

        $item = $default;
        if (isset($_REQUEST['id'])) {
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $_REQUEST['id']), ARRAY_A);
            if (!$item) {
                $item = $default;
                $notice = __('Item not found', 'tb_portal');
            }
        }
    }


     add_meta_box('widget_data_form_meta_box', __('Widget data', 'tb_portal'), 'tb_portal_widgets_form_meta_box_handler', 'widget', 'normal', 'default');
    ?>
    <div class="wrap">
        <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
        <h2><?=__("Add new timerbee widget","tb_portal") ?><a class="add-new-h2"
                                    href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=tb-widgets'); ?>"><?=__("Back to list","tb_portal")?></a>
        </h2>

    <?php if (!empty($notice)): ?>
            <div id="notice" class="error"><p><?php echo $notice ?></p></div>
    <?php endif; ?>
    <?php if (!empty($message)): ?>
            <div id="message" class="updated"><p><?php echo $message ?></p></div>
    <?php endif; ?>

        <form id="form" method="POST">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce(basename(__FILE__)) ?>"/>

            <input type="hidden" name="id" value="<?php echo $item['id'] ?>"/>

            <div class="metabox-holder" id="poststuff">
                <div id="post-body">
                    <div id="post-body-content">
                        <?php
                        do_meta_boxes('widget', 'normal', $item);
                        ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <?php
}

function tb_portal_enqueue_js_handler(){
    wp_enqueue_script( 'timerbee-apitest', plugins_url('/js/timerbee_api_test.js', __FILE__));
}

function tb_portal_enqueue_styles_handler(){
    wp_enqueue_style( 'timerbee-widget', plugins_url('/css/timerbee-widget-admin.css', __FILE__) );
}


//helper/partial functions

function displayWidgetsTable(){
    $widgets_list= new Tb_Admin_Widgets_List();
        $widgets_list->prepare_items();

        $message = '';
         if ('delete' === $widgets_list->current_action()) {
             $message = '<div class="updated below-h2" id="message"><p>' .__("Items Deleted: ","tb_portal").count($_REQUEST['id']) . '</p></div>';
		}

        $message = '';
         if ('delete' === $widgets_list->current_action()) {
             $message = '<div class="updated below-h2" id="message"><p>' .__("Items Deleted: ","tb_portal").count($_REQUEST['id']) . '</p></div>';
        }

        ?>
                <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
                <h2><?=__("TimerBee Widgets","tb_portal")?><a class="add-new-h2"
                    href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=widgets_form');?>"><?=__("Add new","tb_portal")?></a>
                </h2>

				<div id="message" class="notice-info notice">
					<p><strong><?=__("The widget is included as shortcode","tb_portal")?></strong></p>
					<p>
						<?=__("usage: [tb_portal_widget shortname=\"default\"]","tb_portal")?>
					</p>
				</div>

                <?php echo $message; ?>

                <form id="widgets-table" method="POST">
                    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
                    <?php $widgets_list->display(); ?>
                </form>
                <hr>
                <?php

}

function displayErrorTextEditForm(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'tb_portal_widget_errortext';

    $request = $_REQUEST;
    $errorTextUpdateMessage="";
    if (isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
         if (!empty($request['id']) && !empty($request['errortext']) && ($request['action'] == 'update_errortext')) {
             $result = $wpdb->update($table_name, array("errortext" => $request['errortext']), array('id' => $request['id']));
             if ($result) {
                 $errorTextUpdateMessage = '<div class="updated below-h2" id="errtext-message"><p>'.__("Error Text updated","tb_portal").'</p></div>';
             }
         }
     }

    $query = "SELECT * FROM $table_name";
    //probably should handle non existing db record ?
    $errorTextItem=$wpdb->get_results($query)[0];
    echo $errorTextUpdateMessage; ?>
                    <div>
                        <h2 >
                            <span><?= __('Edit error message text','tb_portal')?></span>
                        </h2>
                        <h5><?=__("Error text message will be displayed in widget area in case of Timerbee service is inacessible","tb_portal")?></h5>
                        <form autocomplete="off" id="errortext-form" method="POST" action="<?= 'admin.php?page=' . $_REQUEST['page'] . '&action=update_errortext' ?>">
                            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce(basename(__FILE__)) ?>"/>
                            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
                            <input type="hidden" name="id" value="<?= $errorTextItem->id ?>"/>
                            <input type="text" class="errortext-edit-input" name="errortext" id="errortext" value="<?= $errorTextItem->errortext ?>" ></textarea>
                            <button class="button button-primary"><?= __("Update error message","tb_portal")?></button>
                        </form>
                    </div>
            <?php

}

function tb_portal_validate_widget($item){
    $messages = array();

    if (empty($item['shortname']))
    {
        $messages[] = __("Shortname is required","tb_portal");
    }
    if (! (substr($item['url'], 0, 7)=="http://" || substr($item['url'], 0, 8)=="https://" ) ){
        $messages[] = __('Api url format invalid-must starts with "http://" or "https://" ', "tb_portal");
    }
    if (empty($item['apikey']) ){
        $messages[] = __('Api key is required',"tb_portal");
    }

    if (empty($messages)) return true;
    return implode('<br />', $messages);
}

function tb_portal_widgets_form_meta_box_handler($item) {
    ?>
    <tbody >
    	<style>
        div.postbox {width: 70%; margin-left: 73px;}
    	</style>

    	<div class="formdata">
			<form >
				<p>
					<label for="name"><?= __("Shortname","tb_portal")?></label>
				<br>
					<input id="shortname" name="shortname" type="text" style="width: 60%" value="<?php echo esc_attr($item['shortname']) ?>"
							required>
				</p><p>
					<label for="lastname"><?= __("Api url","tb_portal") ?></label>
				<br>
					<input id="apiurl" name="url" type="text" style="width: 60%" value="<?php echo esc_attr($item['url']) ?>"
							required>
				</p><p>
					<label for="email"><?= __("Api key","tb_portal") ?></label>
				<br>
					<input id="apikey" name="apikey" type="text" style="width: 60%" value="<?php echo esc_attr($item['apikey']) ?>"
						required>
				</p><p>
				<br>
					<div style="text-align: right;">
						<input type="submit"  value="<?= __('Save', 'tb_portal') ?>" id="submit" class="button-primary" name="submit">
					</div>
				</p>
			</form>
		</div>
    </tbody>
    <?php
}



add_action('admin_menu', 'tb_portal_widget_setup_menu');
add_action('wp_ajax_tb_test_api', 'tb_portal_test_api_handler' ); //route for api testing
add_action('admin_enqueue_scripts','tb_portal_enqueue_js_handler'); //include js for api testing
add_action('admin_enqueue_scripts','tb_portal_enqueue_styles_handler'); //include js for api testing
