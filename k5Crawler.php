<?php
/*
Plugin Name: k5Crawler
Plugin URI: https://github.com/phpgcs
Description:
Author: liuyuan@ebuinfo.com
Author URI: https://github.com/phpgcs
Text Domain: k5Crawler
Version: 1.0.0
*/

/****************************
* Global variables
****************************/
$k5_version = "1.0.0";
$page_validation_failed = 0;
$k5_tables = array(
					'sites' => "draft_url",
                );

/****************************
* Includes
****************************/

if(is_admin()){
	// Admin page
	require_once('admin_main.php');
}else{
	// Not admin page
}

/****************************
* Actions and hooks
****************************/
add_action('init', 'k5_init');
register_activation_hook(__FILE__, 'k5_install'); 						// Plugin activation and installation
add_action('admin_init', 'k5_admin_init');								// Initialize administration menu
add_action('admin_menu', 'k5_admin_menu'); 								// Administration menu
//add_action('wp_head', 'k5_load_output_stylesheets');					// Add stylesheets to our header

//The "the_content" filter is used to filter the content of the post after it
//is retrieved from the database and before it is printed to the screen.
add_filter('the_content', 'k5_public_content');							// Initialize the content filter
//Applied to the widget text of the WordPress Text widgets
add_filter('widget_text', 'k5_public_content');
// Plugin Meta Info in plugins-list
add_filter('plugin_row_meta', 'k5_row_meta', 10, 2);
// Plugin Activate Link
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'k5_plugin_actlinks');

add_action('wp_ajax_nopriv_k5_ajax_output', 'k5_ajax_output');
add_action('wp_ajax_k5_ajax_output', 'k5_ajax_output');
add_action('wp_ajax_k5_ajax_admin_stylesheet_name', 'k5_ajax_admin_stylesheet_name');
add_action('wp_ajax_k5_ajax_admin_stylesheet_get_data', 'k5_ajax_admin_stylesheet_get_data');

/****************************
* Installation and activation
****************************/
function k5_install()
{
	global $wpdb;
	global $k5_version;

	// Create the table to save urls
	$table = 'draft_url'; //$this->k5_tables['sites'];
	if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        $structure = "CREATE TABLE IF NOT EXISTS `draft_url` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `url` varchar(600) NOT NULL DEFAULT '',
          `title` varchar(600) NOT NULL DEFAULT '',
          `url_md5` varchar(64) NOT NULL DEFAULT '',
          `crawled` tinyint(4) NOT NULL DEFAULT '0',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `user_id` int(10) unsigned NOT NULL DEFAULT '1',
          PRIMARY KEY (`id`),
          UNIQUE KEY `url_md5` (`url_md5`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5 ;";
        $wpdb->query($structure);
	}

	add_option("k5_version", $k5_version);
}

/****************************
* Wordpress initialization
****************************/
function k5_init(){
	$plugin_dir = basename(dirname(__FILE__));
	// Load text-domain
    // 加载翻译后的插件字符串。
	load_plugin_textdomain('k5Crawler', null, $plugin_dir);

	// AJAX scripts
	// embed javascript file that makes the AJAX request
	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'json2' );

	// declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
	wp_localize_script('k5_ajax_output', 'k5CrawlerAJAX', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'pluginurl' => get_bloginfo('url').'/wp-content/plugins/rexcrawler/' ) );
}

/****************************
* Links in the Wordpress Plugin-page
****************************/
// Meta links (Near Author, Plugin URL etc)
function k5_row_meta($links, $file){
	$plugin = plugin_basename(__FILE__);

	if($file == $plugin){
		$k5_links[] = '<a href="https://github.com/phpgcs" title="' . __( 'Documentation', 'k5Crawler' ) . '">' . __( 'Documentation', 'k5Crawler' ) . '</a>';

		$links = array_merge($links, $k5_links);
	}

	return $links;
}

// Action-links (Near Deactivate, edit etc)
function k5_plugin_actlinks($links){
	$k5_options = '<a href="admin.php?page=k5_options">'.__('Settings', 'k5Crawler').'</a>';
	array_unshift($links, $k5_options);

	return $links;
}


/****************************
* Wordpress stylesheets
****************************/
function k5_load_output_stylesheets(){
	global $wpdb;
	global $k5_tables;

	// Get the different stylesheets
	$stylesheets = $wpdb->get_results("SELECT `title`, `style` FROM `$k5_tables[styles]` ORDER BY `id` ASC");

	if(stylesheets != null){
		$output = "<style type='text/css'>\n";

		// Loop through and register stylesheets
		foreach($stylesheets as $style){
			// Add our admin-stylesheet
			$output .= "/***** REXCRAWLER STYLESHEET: $style->title *****/\n";
			$output .= "\n/***** REXCRAWLER STYLESHEET END *****/\n\n";
			$output .= $style->style;
		}

		$output .= "</style>\n";

		echo $output;
	}
}

/****************************
* AJAX output implementation
****************************/

// The PHP function run when generating output-tables
function k5_ajax_output(){
	global $wpdb; // this is how you get access to the database

	$output = new k5_Output();
	$output->parseOptions(urldecode($_POST['k5_output_options']));
	// Checking if we have set anything
	echo $output->getOutput(0);

	die();
}

// PHP Function used to generate an AJAX sanitized stylesheet name
function k5_ajax_admin_stylesheet_name(){
	global $wpdb;

	echo sanitize_title($_POST['styleName'], __('[Not generated]', 'k5Crawler'));

	die();
}

function k5_ajax_admin_stylesheet_get_data(){
	global $wpdb;
	global $k5_tables;

	// Default values
	$style['css'] = __('Error getting data. Please try again.', 'k5Crawler');
	$style['rows'] = '';
	$style['col1'] = '';
	$style['col2'] = '';

	// Get the ID if possible
	if(isset($_POST['styleID'])){
		// Saving the ID
		$style_id = $_POST['styleID'];

		// Checking if we need to fetch data about the stylesheet
		if($style_id != 0){
			$style_obj = $wpdb->get_results($wpdb->prepare("SELECT `style`, `rows`, `col1`, `col2` FROM `$k5_tables[styles]` WHERE `id` = %d", $style_id));

			if($style_obj != null){
				$style['css'] = $style_obj[0]->style;
				$style['rows'] = $style_obj[0]->rows;
				$style['col1'] = $style_obj[0]->col1;
				$style['col2'] = $style_obj[0]->col2;
			}
		}
	}

	// Return data
	echo json_encode($style);
	die();
}

/****************************
* Adding filters for content
****************************/
function k5_public_content($content = ''){
	// Checking if the page has been disabled!
	if(!preg_match('/\[k5Crawler\|OFF\]/i', $content)){
		$pattern = '/(\[k5Crawler[^\]]*\])/';
		preg_match_all($pattern, $content, $match);

		// Run through all matches
		$onlyone = false; // If we're filtering data, we only want to show 1 table
		foreach($match[0] as $table){
			if(!$onlyone){
				$output = new k5_Output();
				// Checking if we need to fetch a custom table or not
				if(strpos($table, "|", 11) === false){
					// Just show the standard table
					$content = str_replace($table, $output->getOutput(), $content);
				}else{
					// Custom table
					// Get the options
					$options = substr($table, strpos($table, "|")+1, strlen($table)-strpos($table, "|")-2);
					$output->parseOptions($options);
					$content = str_replace($table, $output->getOutput(), $content);
				}
				if(isset($_POST['k5_output_submitted'])){
					$onlyone = true;
				}
			}else{
				$content = str_replace($table, '', $content);
			}
		}
	}else{
		// Remove the [k5Crawler|OFF]-shortcode from the page
		$content = preg_replace('/\[k5Crawler\|OFF\]/i', '', $content);
	}

	return $content;
}

/****************************
* Adding administration page
****************************/
// Functions adds custom stylesheets/javascripts to our admin pages
function k5_add_admin_styles(){
	wp_enqueue_style('k5_style_admin');
}

// Administration menu initialization
function k5_admin_init(){
	// Add our admin-stylesheet
	$src = plugin_dir_url( __FILE__ ).'css/admin.css';

	// Register stylesheet
	wp_register_style('k5_style_admin', $src);

	// Add our admin javascript
}

// Creating the administration menu
function k5_admin_menu()
{
	// Creating our admin-pages
	$main = add_menu_page(__('k5Crawler Options', 'k5Crawler'), __('k5Crawler', 'k5Crawler'), 'delete_posts', 'k5_options', 'k5_options');
//	$sub = add_submenu_page('k5_options', __('Start crawler', 'k5Crawler'), __('Start crawler', 'k5Crawler'), 'update_plugins', 'k5_start_crawler', 'k5_start_crawler');
//	$sub2 = add_submenu_page('k5_options', __('Data output', 'k5Crawler'), __('Data output', 'k5Crawler'), 'update_plugins', 'k5_data_output', 'k5_data_output');
//	$sub3 = add_submenu_page('k5_options', __('Output options', 'k5Crawler'), __('Output options', 'k5Crawler'), 'update_plugins', 'k5_data_output_options', 'k5_data_output_options');
//	$sub4 = add_submenu_page('k5_options', __('Search-pattern tester', 'k5Crawler'), __('Search-pattern tester', 'k5Crawler'), 'update_plugins', 'k5_regex_test', 'k5_regex_test');

	// Fetching stylesheets for our admin pages
	add_action('admin_print_styles-' . $main, 'k5_add_admin_styles');
//	add_action('admin_print_styles-' . $sub, 'k5_add_admin_styles');
//	add_action('admin_print_styles-' . $sub2, 'k5_add_admin_styles');
//	add_action('admin_print_styles-' . $sub3, 'k5_add_admin_styles');
}


// Returns two radio-buttons with Yes and No-answers
function k5_output_yesno($name, $default_value){
	return "<input type='radio' name='$name' id='".$name."_1' value='1'".($default_value == "1" ? " checked" : "")." /> <label for='".$name."_1'>Yes</label> <input type='radio' name='$name' id='".$name."_0' value='0'".($default_value == "0" ? " checked" : "")."> <label for='".$name."_0'>No</label>";
}
?>
