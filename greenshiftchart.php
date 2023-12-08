<?php
/*
Plugin Name: Greenshift Chart plugin
Plugin URI: https://greenshiftwp.com
Description: Chart addon for Greenshift plugin.
Author: Wpsoul
Author URI: https://wpsoul.com
Version: 1.2.1
License: 
License URI:
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/* Constant version */
define('GSCBN_VERSION', '1.2.1');

/* Constant slug */
define('GSCBN_SLUG', basename(plugin_dir_path(__FILE__)));

/* Constant path to the main file for activation call */
define('GSCBN_CORE_FILE', __FILE__);

/* Constant path to plugin directory */
define('GSCBN_PATH', trailingslashit(plugin_dir_path(__FILE__)));

/* Constant uri to plugin directory */
define('GSCBN_URI', trailingslashit(plugin_dir_url(__FILE__)));

require_once plugin_dir_path(__FILE__) . 'includes/class-init.php';

/* Initialization */
if (!function_exists('GSCBN_init')) :
	function GSCBN_init()
	{
		return GSCBN_Init::instance();
	}
endif;


function gspb_chart_is_parent_active()
{
	$active_plugins = get_option('active_plugins', array());

	if (is_multisite()) {
		$network_active_plugins = get_site_option('active_sitewide_plugins', array());
		$active_plugins         = array_merge($active_plugins, array_keys($network_active_plugins));
	}

	foreach ($active_plugins as $basename) {
		if (
			0 === strpos($basename, 'greenshift-animation-and-page-builder-blocks/') ||
			0 === strpos($basename, 'greenshift/')
		) {
			return true;
		}
	}

	return false;
}

if (gspb_chart_is_parent_active()) {

	if (!defined('EDD_CONSTANTS')) {
		require_once GREENSHIFT_DIR_PATH . 'edd/edd_constants.php';
	}

    add_filter( 'plugins_api', 'greenshiftchart_plugin_info', 20, 3 );
    add_filter( 'site_transient_update_plugins', 'greenshiftchart_push_update' );
    add_action( 'upgrader_process_complete', 'greenshiftchart_after_update', 10, 2 );
	add_action( 'after_plugin_row_' . plugin_basename(__FILE__), 'greenshiftchart_after_plugin_row', 10, 3 );

	GSCBN_init();
} else {
	add_action('admin_notices', 'greenshiftchart_admin_notice_warning');
}


//////////////////////////////////////////////////////////////////
// Plugin updater
//////////////////////////////////////////////////////////////////

function greenshiftchart_after_plugin_row( $plugin_file, $plugin_data, $status ) {
    $licenses = greenshift_edd_check_all_licenses();
	$is_active = ((!empty($licenses['all_in_one']) && $licenses['all_in_one'] == 'valid') || (!empty($licenses['chart_addon']) && $licenses['chart_addon'] == 'valid')) ? true : false;
    if(!$is_active){
        echo sprintf( '<tr class="active"><td colspan="4">%s <a href="%s">%s</a></td></tr>', 'Please enter a license to receive automatic updates', esc_url( admin_url('admin.php?page=' . EDD_GSPB_PLUGIN_LICENSE_PAGE) ), 'Enter License.' );
    }
}

function greenshiftchart_plugin_info( $res, $action, $args ) {

    // do nothing if this is not about getting plugin information
    if ($action !== 'plugin_information') {
        return false;
    }

    // do nothing if it is not our plugin
    if (plugin_basename( __DIR__ ) !== $args->slug) {
        return $res;
    }

    // trying to get from cache first, to disable cache comment 23,33,34,35,36
    if (false == $remote = get_transient( 'greenshiftchart_upgrade_pluginslug' )) {

        // info.json is the file with the actual information about plug-in on your server
        $remote = wp_remote_get( EDD_GSPB_STORE_URL_UPDATE.'/get-info.php?slug='.plugin_basename( __DIR__ ).'&action=info', array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json'
            ))
        );

        if (!is_wp_error( $remote ) && isset( $remote[ 'response' ][ 'code' ] ) && $remote[ 'response' ][ 'code' ] == 200 && !empty( $remote[ 'body' ] )) {
            set_transient( 'greenshiftchart_upgrade_pluginslug', $remote, 60000 );
        }
    }

    $remote = wp_remote_get( EDD_GSPB_STORE_URL_UPDATE.'/get-info.php?slug='.plugin_basename( __DIR__ ).'&action=info', array(
        'timeout' => 15,
        'headers' => array(
            'Accept' => 'application/json'
        ))
    );

    if (!is_wp_error( $remote )) {

        $remote = json_decode( wp_remote_retrieve_body($remote) );

        $res = new stdClass();
        $res->name = $remote->name;
        $res->slug = $remote->slug;
        $res->version = $remote->version;
        $res->tested = $remote->tested;
        $res->requires = $remote->requires;
        $res->author = $remote->author;
        $res->author_profile = $remote->author_homepage;
        $res->download_link = $remote->download_link;
        $res->trunk = $remote->download_link;
        $res->last_updated = $remote->last_updated;
        
        if(isset($remote->sections)){
            $res->sections = array(
                'description' => $remote->sections->description, // description tab
                'installation' => $remote->sections->installation, // installation tab
                'changelog' => isset($remote->sections->changelog) ? $remote->sections->changelog : '',
            );
        }
        if(isset($remote->banners)){
            $res->banners = array(
                'low' => $remote->banners->low,
                'high' => $remote->banners->high,
            );
        }

        return $res;
    }

    return false;

}

function greenshiftchart_push_update( $transient ) {

    if (empty( $transient->checked )) {
        return $transient;
    }

    // trying to get from cache first, to disable cache comment 11,20,21,22,23
    if (false == $remote = get_transient( 'greenshiftchart_upgrade_pluginslug' )) {
        // info.json is the file with the actual plugin information on your server
        $remote = wp_remote_get( EDD_GSPB_STORE_URL_UPDATE.'/get-info.php?slug='.plugin_basename( __DIR__ ).'&action=info', array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            ))
        );

        if (!is_wp_error( $remote ) && isset( $remote[ 'response' ][ 'code' ] ) && $remote[ 'response' ][ 'code' ] == 200 && !empty( $remote[ 'body' ] )) {
            set_transient( 'greenshiftchart_upgrade_pluginslug', $remote, 60000 );
        }
    }

    if (!is_wp_error( $remote ) && $remote) {

        $remote = json_decode( $remote[ 'body' ] );

        // your installed plugin version should be on the line below! You can obtain it dynamically of course
        if ($remote && version_compare( GSCBN_VERSION, $remote->version, '<' ) && version_compare( $remote->requires, get_bloginfo( 'version' ), '<' )) {
            $res = new stdClass();
            $res->slug = plugin_basename( __DIR__ );
            $res->plugin = plugin_basename( __FILE__ ); // it could be just pluginslug.php if your plugin doesn't have its own directory
            $res->new_version = $remote->version;
            $res->tested = $remote->tested;
            $licenses = greenshift_edd_check_all_licenses();
            $is_active = ((!empty($licenses['all_in_one']) && $licenses['all_in_one'] == 'valid') || (!empty($licenses['chart_addon']) && $licenses['chart_addon'] == 'valid')) ? true : false;
            if($is_active){
                $res->package = $remote->download_link;
            }
            $transient->response[ $res->plugin ] = $res;
            //$transient->checked[$res->plugin] = $remote->version;
        }
    }
    return $transient;

}

function greenshiftchart_after_update( $upgrader_object, $options ) {
    if ($options[ 'action' ] == 'update' && $options[ 'type' ] === 'plugin') {
        // just clean the cache when new plugin version is installed
        delete_transient( 'greenshiftchart_upgrade_pluginslug' );
    }

}

function greenshiftchart_admin_notice_warning()
{
?>
	<div class="notice notice-warning">
		<p><?php printf(__('Please, activate %s plugin to use Chart Addon'), '<a href="https://wordpress.org/plugins/greenshift-animation-and-page-builder-blocks" target="_blank">Greenshift</a>'); ?></p>
	</div>
<?php
}


// function gspb_chart_plugin_updater()
// {
// 	// To support auto-updates, this needs to run during the wp_version_check cron job for privileged users.
// 	$doing_cron = defined('DOING_CRON') && DOING_CRON;
// 	if (!current_user_can('manage_options') && !$doing_cron) {
// 		return;
// 	}

// 	// retrieve our license key from the DB
// 	$license_key = greenshift_edd_get_license_for_addon('chart_addon');

// 	// setup the updater
// 	$edd_updater = new EDD_GSPB_Plugin_Updater(
// 		EDD_GSPB_STORE_URL,
// 		__FILE__,
// 		array(
// 			'version' => GSCBN_VERSION,                    // current version number
// 			'license' => $license_key,             // license key (used get_option above to retrieve from DB)
// 			'item_id' => EDD_CHART_ADDON_ID,       // ID of the product
// 			'author'  => 'Wpsoul', // author of this plugin
// 			'beta'    => false,
// 		)
// 	);
// }


/**
 * GreenShift Blocks Category
 */
if (!function_exists('gspb_greenShiftChart_category')) {
	function gspb_greenShiftChart_category($categories, $post)
	{
		return array_merge(
			array(
				array(
					'slug'  => 'greenShiftChart',
					'title' => __('GreenShift Chart'),
				),
			),
			$categories
		);
	}
}
add_filter('block_categories_all', 'gspb_greenShiftChart_category', 1, 2);