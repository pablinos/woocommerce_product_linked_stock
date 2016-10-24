<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class WC_Product_Linked_Stock extends WC_Product_Variable {

    public function __construct( $product ) {
        parent::__construct( $product );
        $this->product_type = 'linked_stock';
        if($this->manage_stock!='yes'){
			$this->manage_stock        = 'yes';
        }
    }
    
} 

class WC_Product_Linked_Stock_Variation extends WC_Product_Variation {

	public function __construct( $variation, $args = array() ) {
        parent::__construct($variation,$args);
        $this->variation_has_stock = true;
        $this->manage_stock        = 'yes';
        $st = $this->get_variant_stock($this->parent->get_total_stock());
        $this->stock               = $st;
		$this->total_stock = $this->stock;
    }

    public function get_unit_stock($qty){
        return $qty*($this->variation_data['attribute_pa_fabric-size']=='full-metre'?4:($this->variation_data['attribute_pa_fabric-size']=='half-metre'?2:1));
    }

    public function get_variant_stock($qty){
        return floor($qty/($this->variation_data['attribute_pa_fabric-size']=='full-metre'?4:($this->variation_data['attribute_pa_fabric-size']=='half-metre'?2:1)));
    }
  
	public function set_stock( $amount = null, $mode = 'set' ) {
        global $wpdb;

		if ( is_null( $amount ) )
			return;
        $this->parent->set_stock($this->get_unit_stock($amount),$mode);
        $this->stock=$this->get_variant_stock($this->parent->get_total_stock());
		$this->total_stock = $this->stock;
        $this->manage_stock        = 'yes';

        // Update meta
			// Ensure key exists
        add_post_meta( $this->variation_id, '_stock', 0, true );

        // Update stock in DB directly
        switch ( $mode ) {
        case 'add' :
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + %f WHERE post_id = %d AND meta_key='_stock'", $amount, $this->variation_id ) );
            break;
        case 'subtract' :
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = meta_value - %f WHERE post_id = %d AND meta_key='_stock'", $amount, $this->variation_id ) );
            break;
        default :
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = %f WHERE post_id = %d AND meta_key='_stock'", $amount, $this->variation_id ) );
            break;
        }

        // Clear caches
        wp_cache_delete( $this->variation_id, 'post_meta' );

        // Clear total stock transient
        delete_transient( 'wc_product_total_stock_' . $this->id . WC_Cache_Helper::get_transient_version( 'product' ) );

        // Stock status
        $this->check_stock_status();

        // Sync the parent
        WC_Product_Variable::sync( $this->id );

        // Trigger action
        do_action( 'woocommerce_variation_set_stock', $this );
        
        if($this->is_in_stock()){
            $this->set_stock_status('instock');
        }else{
            $this->set_stock_status('outofstock');
        }
    }
}
