<?php
namespace WooCommerce\Tests\Linked_Stock_Tests;

class Linked_Stock_Tests extends \WC_Unit_Test_Case {

	/**
	 * Test reduce_stock().
	 */
	public function test_reduce_stock() {
        $fabric = Linked_Stock_Test_Helpers::create_fabric_variation_product();

		$fabric->manage_stock = 'yes';
		$fabric->set_stock( 16 );

        $variations = $fabric->get_available_variations();
        $variation =null;
        do {
            $variation = array_shift($variations);
        }while(!empty($variation) && $variation['attributes']['attribute_pa_fabric-size']!='full-metre');
        
        wc_get_product($variation['variation_id'])->reduce_stock(1);

		$this->assertEquals( 12, wc_get_product($fabric->id)->get_total_stock( ) );

	}
    
    public function test_get_stock_quantity(){
        $fabric = Linked_Stock_Test_Helpers::create_fabric_variation_product();

		$fabric->manage_stock = 'yes';
		$fabric->set_stock( 16 );

        $variations = $fabric->get_available_variations();
        $variation =null;
        do {
            $variation = array_shift($variations);
        }while(!empty($variation) && $variation['attributes']['attribute_pa_fabric-size']!='full-metre');

        $this->assertEquals(16, wc_get_product($fabric->id)->get_stock_quantity());
        $this->assertEquals(4, wc_get_product($variation['variation_id'])->get_stock_quantity());
    }
        

}


class Linked_Stock_Test_Helpers {
	public static function create_fabric_variation_product() {
		global $wpdb;

		// Create the product
		$product_id = wp_insert_post( array(
			'post_title'  => 'Dummy Fabric',
			'post_type'   => 'product',
			'post_status' => 'publish',
		) );

		// Create all attribute related things
		$attribute_data = self::create_attribute($product_id);

		// Set it as variable.
		wp_set_object_terms( $product_id, 'linked_stock', 'product_type' );

		// Price related meta
		update_post_meta( $product_id, '_price', '5' );
		update_post_meta( $product_id, '_min_variation_price', '5' );
		update_post_meta( $product_id, '_max_variation_price', '15' );
		update_post_meta( $product_id, '_min_variation_regular_price', '5' );
		update_post_meta( $product_id, '_max_variation_regular_price', '' );

		// General meta
		update_post_meta( $product_id, '_sku', 'FABRIC1 SKU' );
		update_post_meta( $product_id, '_stock', '16');
		update_post_meta( $product_id, '_manage_stock', 'yes' );
		update_post_meta( $product_id, '_tax_status', 'taxable' );
		update_post_meta( $product_id, '_downloadable', 'no' );
		update_post_meta( $product_id, '_virtual', 'taxable' );
		update_post_meta( $product_id, '_visibility', 'visible' );
		update_post_meta( $product_id, '_stock_status', 'instock' );

		// Attributes
		update_post_meta( $product_id, '_default_attributes', array() );
		update_post_meta( $product_id, '_product_attributes', array(
			'pa_fabric-size' => array(
				'name'         => 'pa_fabric-size',
				'value'        => '',
				'position'     => '1',
				'is_visible'   => 0,
				'is_variation' => 1,
				'is_taxonomy'  => 1,
			),
		) );


		// Create the variations
		$variation_id = self::create_fabric_variation($product_id,1,5,'fat-quarter');
		// Add the variation meta to the main product
		update_post_meta( $product_id, '_min_price_variation_id', $variation_id );
		update_post_meta( $product_id, '_min_regular_price_variation_id', $variation_id );

		$variation_id = self::create_fabric_variation($product_id,1,10,'half-metre');
		$variation_id = self::create_fabric_variation($product_id,1,15,'full-metre');
        
		update_post_meta( $product_id, '_max_price_variation_id', $variation_id );
		update_post_meta( $product_id, '_max_regular_price_variation_id', $variation_id );

		return new \WC_Product_Linked_Stock( $product_id );
	}

    public static function create_fabric_variation($product_id,$num,$price,$size){
		// Create the variation
		$variation_id = wp_insert_post( array(
			'post_title'  => 'Variation #' . ( $product_id+$num ) . ' of Dummy Fabric',
			'post_type'   => 'product_variation',
			'post_parent' => $product_id,
			'post_status' => 'publish',
		) );

		// Price related meta
		update_post_meta( $variation_id, '_price', ''.$price );
		update_post_meta( $variation_id, '_regular_price', ''.$price );

		// General meta
		update_post_meta( $variation_id, '_sku', 'FABRIC SKU VARIABLE '.strtoupper($size) );
		update_post_meta( $variation_id, '_manage_stock', 'yes' );
		update_post_meta( $variation_id, '_downloadable', 'no' );
		update_post_meta( $variation_id, '_virtual', 'taxable' );
		update_post_meta( $variation_id, '_stock_status', 'instock' );

		// Attribute meta
		update_post_meta( $variation_id, 'attribute_pa_fabric-size', $size );

        return $variation_id;
    }

	/**
	 * Create a dummy attribute.
	 *
	 * @since 2.3
	 *
	 * @return array
	 */
	public static function create_attribute($product_id) {
		global $wpdb;

		$return = array();

		$attribute_name = 'fabric-size';

		// Create attribute
		$attribute = array(
			'attribute_label'   => $attribute_name,
			'attribute_name'    => $attribute_name,
			'attribute_type'    => 'select',
			'attribute_orderby' => 'menu_order',
			'attribute_public'  => 0,
		);
		$wpdb->insert( $wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute );
		$return['attribute_id'] = $wpdb->insert_id;

		// Register the taxonomy
		$name  = wc_attribute_taxonomy_name( $attribute_name );
		$label = $attribute_name;

		// Add the term
        $return=array_merge($return,self::add_term('Fat Quarter','fat-quarter',$product_id));
        $return=array_merge($return,self::add_term('Half Metre','half-metre',$product_id));
        $return=array_merge($return,self::add_term('Full Metre','full-metre',$product_id));

		// Delete transient
		delete_transient( 'wc_attribute_taxonomies' );

		return $return;
	}
    
    public static function add_term($name,$slug,$product_id) {
        global $wpdb;

        $return=array();

		$wpdb->insert( $wpdb->prefix . 'terms', array(
			'name'       => $name,
			'slug'       => $slug,
			'term_group' => 0,
		), array(
			'%s',
			'%s',
			'%d',
		) );
		$return['term_id_'.$slug] = $wpdb->insert_id;

		// Add the term_taxonomy
		$wpdb->insert( $wpdb->prefix . 'term_taxonomy', array(
			'term_id'     => $return['term_id_'.$slug],
			'taxonomy'    => 'pa_fabric-size',
			'description' => '',
			'parent'      => 0,
			'count'       => 1,
		) );
		$return['term_taxonomy_id_'.$slug] = $wpdb->insert_id;
        
		// Link the product to the attribute
		$wpdb->insert( $wpdb->prefix . 'term_relationships', array(
			'object_id'        => $product_id,
			'term_taxonomy_id' => $return['term_taxonomy_id_'.$slug],
			'term_order'       => 0,
		) );
		$return['term_relationship_id_'.$slug] = $wpdb->insert_id;

        return $return;

    }
}
?>