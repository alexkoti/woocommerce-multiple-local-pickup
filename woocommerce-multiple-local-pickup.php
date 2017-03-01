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
         * Ajax endpoint.
         *
         * @var string
         */
        protected $ajax_endpoint = 'multiple_local_pickup';

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
                
                // adicionar javascript
                add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
                
                // registrar ajax
                add_action( 'wc_ajax_' . $this->ajax_endpoint, array( $this, 'update_pickup_location' ) );
                
                // exibir location no frontend
                add_filter( 'woocommerce_order_shipping_to_display', array( $this, 'shipping_to_display_order_frontend' ), 10, 2 );
                
                // exibir label do 'pickup_chosen_location' na individual de pedido no admin
                add_filter( 'woocommerce_attribute_label', array( $this, 'admin_order_location_label' ), 10, 3 );
                
                // exibir location na listagem de pedidos
                add_filter( 'woocommerce_order_shipping_method', array( $this, 'order_shipping_method' ), 10, 2 );
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
         * Include shipping methods to WooCommerce.
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

        /**
         * Get main file.
         *
         * @return string
         */
        public static function get_main_file() {
            return __FILE__;
        }
        
        /**
         * Adicionar javascript
         * 
         */
        public function enqueue_scripts(){
            wp_enqueue_script( 'woocommerce-multiple-local-pickup', plugins_url( 'assets/js/frontend/woocommerce-multiple-local-pickup.js', WC_Multiple_Local_Pickup::get_main_file() ), array( 'jquery' ), WC_Multiple_Local_Pickup::VERSION, true );
            
            wp_localize_script(
                'woocommerce-multiple-local-pickup',
                'WCMultipleLocalPickupParams',
                array(
                    'url' => WC_AJAX::get_endpoint( $this->ajax_endpoint ),
                )
            );
        }
        
        /**
         * Atualizar 'pickup_chosen_location' e salvar na sessão
         * 
         */
        function update_pickup_location(){
            $value = $_POST['location'];
            
            WC()->session->set( 'pickup_chosen_location', $value );
            
            die();
        }
        
        /**
         * Exibir local de retirada no frontend, página do pedido para o usuário
         * 
         */
        function shipping_to_display_order_frontend( $string, $order ){
            $shippings = $order->get_items('shipping');
            if( !empty($shippings) ){
                foreach( $shippings as $shipping ){
                    $locations = WC_Shipping_Multiple_Local_Pickup::get_available_locations();
                    $pickup_chosen_location = $shipping['item_meta']['pickup_chosen_location'][0];
                    return "{$string} <div class='pickup-location'>{$locations[ $pickup_chosen_location ]}</div>";
                }
            }
            
            return $string;
        }
        
        /**
         * Exibir label correto na tela individual de pedido no admin
         * 
         */
        function admin_order_location_label( $label, $name, $product ){
            if( $name == 'pickup_chosen_location' ){
                return 'Local';
            }
            return $label;
        }
        
        /**
         * Exibir o local de retirada no admin, na listagem de pedidos
         * 
         */
        function order_shipping_method( $string, $order ){
            if( is_admin() ){
                $shippings = $order->get_items('shipping');
                if( !empty($shippings) ){
                    foreach( $shippings as $shipping ){
                        if( isset($shipping['item_meta']['pickup_chosen_location']) ){
                            return "{$string}: {$shipping['item_meta']['pickup_chosen_location'][0]}";
                        }
                    }
                }
            }
            
            return $string;
        }
    }
    
    add_action( 'plugins_loaded', array( 'WC_Multiple_Local_Pickup', 'get_instance' ) );
}

