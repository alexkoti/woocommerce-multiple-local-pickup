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
        const VERSION = '1.0.1';
        
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
                
                // adicionar javascript
                add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
                
                // registrar ajax
                add_action( 'wc_ajax_' . $this->ajax_endpoint, array( $this, 'update_pickup_location' ) );
                
                // exibir location no frontend na tela de pedido
                add_filter( 'woocommerce_order_shipping_to_display', array( $this, 'shipping_to_display_order_frontend' ), 10, 2 );
                
                // exibir label do 'pickup_chosen_location' na individual de pedido no admin
                add_filter( 'woocommerce_attribute_label', array( $this, 'admin_order_location_label' ), 10, 3 );
                
                // exibir location na listagem de pedidos E email para usuário
                add_filter( 'woocommerce_order_shipping_method', array( $this, 'order_shipping_method' ), 10, 2 );
                
                // validação para permitir a finalização da compra
                add_action( 'woocommerce_review_order_before_cart_contents', array( $this, 'validate_order' ), 10 );
                add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_order' ), 10 );
                
                
                add_action( 'woocommerce_after_shipping_rate', array( $this, 'method_options' ), 10, 2 );
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
            if( isset($_POST['location']) ){
                $location = wc_clean($_POST['location']);
                WC()->session->set( 'pickup_chosen_location', $location );
                
                $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
                if( isset( $_POST['shipping_method'] ) && is_array( $_POST['shipping_method'] ) ){
                    foreach( $_POST['shipping_method'] as $i => $value ){
                        $chosen_shipping_methods[ $i ] = wc_clean( $value );
                    }
                }
                WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
            }
            die();
        }
        
        /**
         * Local de retirada:
         * --> no frontend, página do pedido para o usuário
         * --> email de notificação de novo pedido para usuário
         * 
         */
        function shipping_to_display_order_frontend( $string, $order ){
            $shippings = $order->get_items('shipping');
            if( !empty($shippings) ){
                foreach( $shippings as $shipping ){
                    $all_locations = WC_Shipping_Multiple_Local_Pickup::get_available_locations();
                    if( isset($shipping['pickup_chosen_location']) ){
                        $pickup_chosen_location = $shipping['pickup_chosen_location'];
                        if( isset($all_locations[ $pickup_chosen_location ]) ){
                            return "{$string} <div class='pickup-location'><strong>{$pickup_chosen_location}</strong>: {$all_locations[ $pickup_chosen_location ]} <br /><strong>Até {$shipping['additional_time']} dias úteis após o pagamento aprovado.</strong></div>";
                        }
                        return $string;
                    }
                }
            }
            
            return $string;
        }
        
        /**
         * Exibir label correto na tela individual de pedido no admin
         * 
         */
        function admin_order_location_label( $label, $name, $product ){
            switch( $name ){
                case 'pickup_chosen_location':
                    $label = 'Local';
                    break;
                    
                case 'additional_time':
                    $label = 'Prazo';
                    break;
                    
                default:
                    break;
                
            }
            return $label;
        }
        
        /**
         * Exibir o local de retirada no admin:
         * --> listagem de pedidos
         * 
         */
        function order_shipping_method( $string, $order ){
            if( is_admin() ){
                $shippings = $order->get_items('shipping');
                if( !empty($shippings) ){
                    $all_locations = self::get_available_locations();
                    foreach( $shippings as $shipping ){
                        if( isset($shipping['pickup_chosen_location']) ){
                            return "{$string}: {$shipping['pickup_chosen_location']}";
                        }
                    }
                }
            }
            
            return $string;
        }
        
        /**
         * Lista de locais disponíveis. Sempre é necessário preenchê-la via filter.
         * 
         */
        public static function get_available_locations(){
            return apply_filters( 'multiple_local_pickup_locations_list', array() );
        }
    
        /**
         * Opções mostradas no carrinho ao usuário
         * 
         */
        function method_options( $method, $index ){
            
            if( $method->method_id == 'multiple-local-pickup' ){
                
                //pre($method, "method_options: {$index}");
                //pre( $method, 'multiple-local-pickup PRE' );
                //pre( get_class_methods($method) );
                
                $class = 'brt-display-none';
                $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
                foreach( $chosen_shipping_methods as $shipping_method ){
                    if( $shipping_method == $method->id ){
                        $class = 'brt-display-block';
                    }
                }
                
                $meta_data = $method->get_meta_data();
                $all_locations = self::get_available_locations();
                //pre($all_locations, 'all_locations');
                //pre($meta_data, 'meta');
                
                if( !empty($all_locations) ){
                    $checked = $meta_data['pickup_chosen_location'];
                    
                    echo "<ul id='multiple-pickup-locations-list' class='pickup-locations {$class}'>";
                    foreach( $meta_data['pickup_locations'] as $key ){
                        $is_checked = checked( $key, $checked, false );
                        
                        // deixar marcado no form e na sessão caso exista apenas um local
                        if( count($meta_data['pickup_locations']) == 1 ){
                            $is_checked = checked( 1, 1, false );
                            WC()->session->set( 'pickup_chosen_location', $key );
                        }
                        echo "<li><label><input type='radio' name='pickup-location' value='{$key}' id='pickup-location-{$key}' {$is_checked} /> <strong>{$key}</strong>: {$all_locations[$key]} <strong>Até {$meta_data['additional_time']} dias úteis após o pagamento aprovado.</strong></label></li>";
                    }
                    echo "</ul>";
                }
            }
        }
        
        function validate_order( $posted ){
            $packages = WC()->shipping->get_packages();
            $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
            $pickup_chosen_location = WC()->session->get( 'pickup_chosen_location' );
            //pre($posted, 'validate_order: $posted');
            //pre($packages, 'validate_order: $packages', false);
            //pre($chosen_methods, 'validate_order: $chosen_methods', false);
            
            $is_multiple_local_pickup = false;
            
            if( is_array( $chosen_methods ) ){
                foreach( $packages as $i => $package ){
                    $method_name = explode(':', $chosen_methods[$i]);
                    if( $method_name[0] != 'multiple-local-pickup' ){
                        continue;
                    }
                    else{
                        $is_multiple_local_pickup = true;
                    }
                }
            }
            
            if( $is_multiple_local_pickup == true and is_null($pickup_chosen_location) ){
                $message = 'É preciso escolher um local de retirada nas opções disponíveis.';
                $messageType = "error";
                if( !wc_has_notice( $message, $messageType ) ) {
                    wc_add_notice( $message, $messageType );
                }
            }
        }
    }
    
    add_action( 'plugins_loaded', array( 'WC_Multiple_Local_Pickup', 'get_instance' ) );
}

