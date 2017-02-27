<?php
/**
 * Plugin Name: WooCommerce Multiple Local Pickup
 * Plugin URI: http://github.com/alexkoti/woocommerce-multiple-local-pickup
 * Description: Allow multiple pickup locations shipping method.
 * Author: Alex Koti
 * Author URI: http://alexkoti.com/
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: woocommerce-multiple-local-pickup
 * Domain Path: languages/
 * 
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if( !class_exists( 'WC_Multiple_Local_Pickup' ) ){
    
    class WC_Multiple_Local_Pickup {
        
        /**
         * Plugin version.
         *
         * @var string
         */
        const VERSION = '1.0.0';
        
        /**
         * Instance of this class.
         *
         * @var object
         */
        protected static $instance = null;

        /**
         * Return an instance of this class.
         *
         * @return object A single instance of this class.
         */
        public static function get_instance() {
            // If the single instance hasn't been set, set it now.
            if ( null === self::$instance ) {
                self::$instance = new self;
            }

            return self::$instance;
        }
        
        private function __construct(){
            add_action( 'init', array( $this, 'load_plugin_textdomain' ), -1 );

            // Checks with WooCommerce is installed.
            if ( class_exists( 'WC_Integration' ) ) {
                // include method file
                include_once dirname( __FILE__ ) . '/includes/shipping/class-wc-shipping-multiple-local-pickup.php';
                
                // add method
                add_filter( 'woocommerce_shipping_methods', array( $this, 'include_methods' ) );
                
                // action to show pickup locations
                add_action( 'woocommerce_after_shipping_rate', array( 'WC_Shipping_Multiple_Local_Pickup', 'method_options' ), 10, 2 );
            } else {
                add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            }
        }

        /**
         * Load the plugin text domain for translation.
         */
        public function load_plugin_textdomain() {
            load_plugin_textdomain( 'woocommerce-multiple-local-pickup', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
        }

        /**
         * WooCommerce fallback notice.
         */
        public function woocommerce_missing_notice() {
            include_once dirname( __FILE__ ) . '/includes/admin/views/html-admin-missing-dependencies.php';
        }
        
        /**
         * Include Correios shipping methods to WooCommerce.
         *
         * @param  array $methods Default shipping methods.
         *
         * @return array
         */
        public function include_methods( $methods ) {
            // add method
            $methods['multiple-local-pickup'] = 'WC_Shipping_Multiple_Local_Pickup';
            
            return $methods;
        }
    }
    
    add_action( 'plugins_loaded', array( 'WC_Multiple_Local_Pickup', 'get_instance' ) );
}
