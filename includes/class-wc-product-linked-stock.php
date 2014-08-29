<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class WC_Product_Linked_Stock extends WC_Product_Variable {

  public function __construct( $product ) {
    parent::__construct( $product );
    $this->product_type = 'linked_stock';
  }

} 

class WC_Product_Linked_Stock_Variation extends WC_Product_Variation {

	public function __construct( $variation, $args = array() ) {
    parent::__construct($variation,$args);
			$this->variation_has_stock = true;
			$this->manage_stock        = 'yes';
      $st = $this->parent->get_total_stock()/($this->variation_data['attribute_pa_fabric-size']=='full-metre'?4:($this->variation_data['attribute_pa_fabric-size']=='half-metre'?2:1));
			$this->stock               = $st;
  }

  public function get_unit_stock($qty){
      return $qty*($this->variation_data['attribute_pa_fabric-size']=='full-metre'?4:($this->variation_data['attribute_pa_fabric-size']=='half-metre'?2:1));
  }
  
	function set_stock( $amount = null, $force_variation_stock = false ) {
		if ( is_null( $amount ) )
			return;
    $this->parent->set_stock($this->get_unit_stock($amount));
    $this->stock=$amount;
    if($this->is_in_stock()){
      $this->stock_status='instock';
    }else{
      $this->stock_status='outofstock';
    }
  }
}
