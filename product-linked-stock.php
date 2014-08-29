<?php
/**
 * The WordPress Plugin Boilerplate.
 *
 * A foundation off of which to build well-documented WordPress plugins that
 * also follow WordPress Coding Standards and PHP best practices.
 *
 * @package   Product_Linked_Stock
 * @author    Paul Bunkham <paul.bunkham@gmail.com>
 * @license   GPL-2.0+
 * @link      http://paul.bunkham.com
 * @copyright 2014 Paul Bunkham
 *
 * @wordpress-plugin
 * Plugin Name:       Product Linked Stock
 * Plugin URI:        http://paul.bunkham.com/product-linked-stock/
 * Description:       A plugin to link the stock quantities of a variable product in Woocommerce.
 * Version:           1.0.0
 * Author:            Paul Bunkham
 * Author URI:        http://paul.bunkham.com/
 * Text Domain:       product-linked-stock-locale
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/pablinos/product-linked-stock
 * WordPress-Plugin-Boilerplate: v2.6.1
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

if ( is_woocommerce_active() ) {
  /*----------------------------------------------------------------------------*
  * Public-Facing Functionality
  *----------------------------------------------------------------------------*/

  require_once( plugin_dir_path( __FILE__ ) . 'includes/class-product-linked-stock.php' );

  /*
  * Register hooks that are fired when the plugin is activated or deactivated.
  * When the plugin is deleted, the uninstall.php file is loaded.
  *
  */
  register_activation_hook( __FILE__, array( 'Product_Linked_Stock', 'activate' ) );
  register_deactivation_hook( __FILE__, array( 'Product_Linked_Stock', 'deactivate' ) );

  add_action( 'plugins_loaded', array( 'Product_Linked_Stock', 'get_instance' ), 9999 );

  /*----------------------------------------------------------------------------*
  * Dashboard and Administrative Functionality
  *----------------------------------------------------------------------------*/
  /*
  if ( is_admin() ) { //&& ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX )

    require_once( plugin_dir_path( __FILE__ ) . 'admin/class-product-linked-stock-admin.php' );
    add_action( 'plugins_loaded', array( 'Product_Linked_Stock_Admin', 'get_instance' ) );

  }
  */

}
