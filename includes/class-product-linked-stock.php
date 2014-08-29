<?php
/**
 * Plugin Name.
 *
 * @package   Product_Linked_Stock
 * @author    Paul Bunkham <paul.bunkham@gmail.com>
 * @license   GPL-2.0+
 * @link      http://paul.bunkham.com
 * @copyright 2014 Your Name or Company Name
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * public-facing side of the WordPress site.
 *
 * If you're interested in introducing administrative or dashboard
 * functionality, then refer to `class-plugin-name-admin.php`
 *
 *
 * @package   Product_Linked_Stock
 * @author    Paul Bunkham <paul.bunkham@gmail.com>
 */
class Product_Linked_Stock {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.0.0';

	/**
	 * Unique identifier for your plugin.
	 *
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'product-linked-stock';

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

  protected $cart_quantities_msg=array();
	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */

	private function __construct() {
    require_once( plugin_dir_path( __FILE__ ) . 'class-wc-product-linked-stock.php' );

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Load public-facing style sheet and JavaScript.
    /*
    add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    */

		add_action( 'woocommerce_process_product_meta_linked_stock', array( $this, 'process_meta' ) );
		//add_action( 'woocommerce_check_cart_items', array( $this, 'check_cart_stock' ),1 );

    // Allows the selection of the 'bundled product' type
    add_filter( 'product_type_selector', array( $this, 'product_selector_filter' ) );
    add_filter( 'woocommerce_product_data_tabs', array( $this, 'product_data_tabs' ) );
    add_filter( 'woocommerce_linked_stock_add_to_cart', 'woocommerce_variable_add_to_cart' );
    add_filter( 'woocommerce_product_class', array( $this, 'product_class'),10,4);
    add_filter( 'woocommerce_add_to_cart_handler', array( $this, 'add_to_cart_handler'));
    add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'add_to_cart_validation'),10,4); 
    add_filter( 'woocommerce_update_cart_validation', array( $this, 'update_cart_validation'),10,4);
    add_filter( 'woocommerce_update_cart_action_cart_updated',  array( $this, 'check_cart_stock'));

	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();

					restore_current_blog();
				}

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

					restore_current_blog();

				}

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();

	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private static function single_activate() {
		// @TODO: Define activation functionality here
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 */
	private static function single_deactivate() {
		// @TODO: Define deactivation functionality here
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' );

	}

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'assets/css/public.css', __FILE__ ), array(), self::VERSION );
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'assets/js/public.js', __FILE__ ), array( 'jquery' ), self::VERSION );
	}


	/**
	 * NOTE:  Filters are points of execution in which WordPress modifies data
	 *        before saving it or sending it to the browser.
	 *
	 *        Filters: http://codex.wordpress.org/Plugin_API#Filters
	 *        Reference:  http://codex.wordpress.org/Plugin_API/Filter_Reference
	 *
	 * @since    1.0.0
	 */
	public function product_selector_filter($options) {
    $options['linked_stock'] = __( 'Variable (Linked Stock)', 'product-linked-stock' );
    return $options;
	}

	public function product_data_tabs($tabs) {
    $tabs['inventory']['class'][] = 'show_if_linked_stock';
    $tabs['variations']['class'][] = 'show_if_linked_stock';
    return $tabs;
	}

	public function process_meta($post_id) {
    $_POST['variable_stock']=array(); //Remove any stock quantities at the variation level
    WC_Meta_Box_Product_Data::save_variations( $post_id, null ); //Doesn't use the post object so we pass in null.
	}

  public function product_class( $classname, $product_type, $post_type, $product_id ){
    $cls=$classname;
    if($product_type=='variation'){
      $terms=get_the_terms(wp_get_post_parent_id( $product_id ),'product_type');
      if(!empty($terms) && isset( current( $terms )->name ) && sanitize_title( current( $terms )->name )=='linked_stock'){
        $cls='WC_Product_Linked_Stock_Variation';
      } 
    }
    return $cls;
  }


  public function check_cart_items() {
    $result = $this->check_cart_item_validity();

    if ( is_wp_error( $result ) ) {
      wc_add_notice( $result->get_error_message(), 'error' );
    }

    // Check item stock
    $result = $this->check_cart_item_stock();

    if ( is_wp_error( $result ) ) { 
      wc_add_notice( $result->get_error_message(), 'error' );
    }
  }

  public function add_to_cart_handler($product_type){
    if($product_type=='linked_stock'){
      return 'variable';
    }
    return $product_type;
  }

  public function add_to_cart_validation($valid, $product_id, $quantity, $variation_id){
    $p=get_product($product_id);
    if($p->product_type=='linked_stock'){
      $v=get_product($variation_id);
      $units=0;
      foreach(WC()->cart->get_cart() as $key=>$values){
        if($values['product_id']==$product_id){
          $units+=get_product($values['variation_id'])->get_unit_stock($values['quantity']);
        }
      }
      if($units+$v->get_unit_stock($quantity)>$p->get_total_stock()){
        wc_add_notice( sprintf(__( 'Sorry, we do not have enough "%s" in stock to fulfill your order. We apologise for any inconvenience caused.', 'woocommerce' ), $p->get_title()) ,'error' );
        return false;
      }
    }
    return true;
  }


  public function check_cart_stock($valid){
    foreach($this->cart_quantities_msg as $l=>$msg){
      wc_add_notice($msg,'error');
      $valid=false;
    }
    $this->cart_quantities_msg=array();
    return $valid;
  }

  public function update_cart_validation( $valid, $cart_item_key, $val, $quantity ){
    $quantities=array();

    $item=get_product($val['product_id']);
    if($item->product_type=='linked_stock'){
      foreach(WC()->cart->get_cart() as $key=>$values){
        $p=get_product($values['product_id']);
        if($p->product_type=='linked_stock'){
          $v=get_product($values['variation_id']);
          $units=$v->get_unit_stock(($val['variation_id']==$values['variation_id']?$quantity:$values['quantity']));
          if(isset($quantities[$values['product_id']])){
            $quantities[$values['product_id']]+=$units;
          }else{
            $quantities[$values['product_id']]=$units;
          }
        }
      }
      if($quantities[$val['product_id']]>$item->get_total_stock()){
        $this->cart_quantities_msg[$val['product_id']]= sprintf(__( 'Sorry, we do not have enough "%s" in stock to fulfill your order. Please edit your cart and try again. We apologise for any inconvenience caused.', 'woocommerce' ), $p->get_title() );
        $valid=false;
      }
    }
    return $valid;
  }
}
