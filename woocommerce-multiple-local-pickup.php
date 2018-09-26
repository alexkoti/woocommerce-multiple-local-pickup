<?php
/**
 * Plugin Name: WooCommerce Multiple Local Pickup
 * Plugin URI:  http://github.com/alexkoti/woocommerce-multiple-local-pickup
 * Description: Allow multiple pickup locations shipping method.
 * Author:      Alex Koti
 * Author       URI: http://alexkoti.com/
 * Version:     1.0.0
 * License:     GPLv2 or later
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
        const VERSION = '1.0.6';
        
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
        
        public static $counter = 0;

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
                
                // salvar pickup_location sem javascript
                add_action( 'woocommerce_before_calculate_totals', array( $this, 'nonjs_calculate_totals' ) );
                
                // exibir location no frontend na tela de pedido
                add_filter( 'woocommerce_order_shipping_to_display', array( $this, 'shipping_to_display_order_frontend' ), 10, 2 );
                
                // exibir label do 'pickup_chosen_location' na individual de pedido no admin
                add_filter( 'woocommerce_attribute_label', array( $this, 'admin_order_location_label' ), 10, 3 );
                
                // exibir location na listagem de pedidos E email para usuário
                add_filter( 'woocommerce_order_shipping_method', array( $this, 'order_shipping_method' ), 10, 2 );
                
                // validação para permitir a finalização da compra
                add_action( 'woocommerce_review_order_before_cart_contents', array( $this, 'validate_order' ), 10 );
                add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_order' ), 10 );
            } else {
                add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            }
        }
        
        function nonjs_calculate_totals(){
            
            // apenas acionar caso tenha sido usado o botão "update totals", que só aparece em noscript
            if( isset( $_POST['woocommerce_checkout_update_totals'] ) ){
                $locations = WC_Shipping_Multiple_Local_Pickup::get_available_locations();
                $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
                
                foreach( WC()->shipping->get_packages() as $i => $package ) {
                    // resetar cache de shipping rates
                    WC()->session->set( "shipping_for_package_{$i}", false );
                    
                    foreach( $package['rates'] as $rate ){
                        foreach( $chosen_shipping_methods as $i => $chosen_method ){
                            //error_log( "chosen_method INIT {$chosen_method}" );
                            //error_log( "rate->method_id {$rate->method_id}" );
                            //error_log( "rate->id {$rate->id}" );
                            
                            // o método escolhido é o multiple-local-pickup
                            if( $rate->method_id == 'multiple-local-pickup' AND $rate->id == $chosen_method ){
                                error_log('nonjs_calculate_totals 1');
                                // caso esteja vazio, aplicar a primeira loja
                                if( !isset($_POST['pickup-location']) or empty($_POST['pickup-location']) ){
                                    $location = key($locations);
                                }
                                else{
                                    $location = $_POST['pickup-location'];
                                }
                                WC()->session->set( 'pickup_chosen_location', $location );
                            }
                            else{
                                //error_log('nonjs_calculate_totals 2');
                                //// caso tenha sido selecionado algum radio de local, mas não o método de retirada, acionar a retirada em lojas
                                //if( isset($_POST['pickup-location']) and !empty($_POST['pickup-location']) and $rate->method_id == 'multiple-local-pickup' ){
                                //    wc_add_notice( 'Você selecionou um local de retirada, mas não marcou antes a opção "Retirar em loja"', 'error' );
                                //}
                                
                                WC()->session->set( 'pickup_chosen_location', false );
                            }
                        }
                    }
                }
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
            
            wp_enqueue_style( 'woocommerce-multiple-local-pickup', plugins_url( 'assets/css/frontend/woocommerce-multiple-local-pickup.css', WC_Multiple_Local_Pickup::get_main_file() ), false, WC_Multiple_Local_Pickup::VERSION, 'all' );
            
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
                $value = $_POST['location'];
                WC()->session->set( 'pickup_chosen_location', $value );
                
                foreach( WC()->shipping->get_packages() as $i => $package ) {
                    WC()->session->set( "shipping_for_package_{$i}", false );
                }
            }
            
            //error_log( "ajax:update_pickup_location pickup_chosen_location > {$value}" );
            print_r( "ajax:update_pickup_location pickup_chosen_location > {$value}\n" );
            print_r( WC()->session );
            
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
                    if( isset($shipping['item_meta']['pickup_chosen_location']) ){
                        $pickup_chosen_location = $shipping['item_meta']['pickup_chosen_location'][0];
                        if( isset($locations[ $pickup_chosen_location ]) ){
                            if( is_admin() ){
                                return "{$string} <div class='pickup-location'>{$locations[ $pickup_chosen_location ]}</div>";
                            }
                            else{
                                return "{$string} <div class='pickup-location'><strong>{$pickup_chosen_location}</strong>: {$locations[ $pickup_chosen_location ]}</div>";
                            }
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
            if( $name == 'pickup_chosen_location' ){
                return 'Local';
            }
            return $label;
        }
        
        /**
         * Exibir o local de retirada no admin:
         * --> listagem de pedidos
         * --> email de notificação de novo pedido para usuário
         * 
         */
        function order_shipping_method( $string, $order ){
            $shippings = $order->get_items('shipping');
            //pre($shippings, '$shippings', false);
            
            // Rodar apenas uma vez
            if( self::$counter > 0 ){
                //return;
            }
            
            if( is_admin() ){
                $shippings = $order->get_items('shipping');
                
                if( !empty($shippings) ){
                    $all_locations = self::get_available_locations();
                    foreach( $shippings as $shipping ){
                        $metas = $shipping->get_meta_data();
                        
                        //if( isset($_GET['post_type']) and $_GET['post_type'] == 'shop_order' ){
                        //    pre($shipping, '$shipping', false);
                        //}
                        
                        foreach( $metas as $meta ){
                            $m = $meta->get_data();
                            if( $m['key'] == 'pickup_chosen_location' ){
                                return "{$string}: {$m['value']}";
                            }
                        }
                    }
                }
            }
            
            self::$counter++;
            
            return $string;
        }
        
        function validate_order( $posted ){
            
            $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
            
            foreach( WC()->shipping->get_packages() as $i => $package ) {
                foreach( $package['rates'] as $rate ){
                    foreach( $chosen_shipping_methods as $i => $chosen_method ){
                        
                        // o método escolhido é o multiple-local-pickup
                        if( $rate->method_id == 'multiple-local-pickup' AND $rate->id == $chosen_method ){
                            $meta_data = $rate->get_meta_data();
                            if( empty($meta_data['pickup_chosen_location']) ){
                                $message = 'É preciso escolher um local de retirada nas opções disponíveis.';
                                if( !wc_has_notice( $message, $messageType ) ) {
                                    wc_add_notice( $message, 'error');
                                }
                            }
                        }
                    }
                }
            }
        }
        
        /**
         * Lista de locais disponíveis. Sempre é necessário preenchê-la via filter.
         * 
         */
        public static function get_available_locations(){
            return apply_filters( 'multiple_local_pickup_locations_list', array() );
        }
    }
    
    add_action( 'plugins_loaded', array( 'WC_Multiple_Local_Pickup', 'get_instance' ) );
}

