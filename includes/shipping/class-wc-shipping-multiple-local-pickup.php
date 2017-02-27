<?php
/**
 * Correios Carta Registrada shipping method.
 *
 * @package WC_Multiple_Local_Pickup/Classes/Shipping
 * @since   1.0.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Shipping_Multiple_Local_Pickup extends WC_Shipping_Method {
    
    /**
     * Initialize
     *
     * @param int $instance_id Shipping zone instance.
     */
    public function __construct( $instance_id = 0 ) {
        $this->instance_id        = absint( $instance_id );
        $this->id                 = 'multiple-local-pickup';
        $this->method_title       = __( 'Multiple Local Pickup', 'woocommerce-multiple-local-pickup' );
        $this->method_description = sprintf( __( '%s is a custom shipping method for As Baratas.', 'woocommerce-multiple-local-pickup' ), $this->method_title );
        $this->supports           = array(
            'shipping-zones',
            'instance-settings',
        );
        
        // Load the form fields.
        $this->init_form_fields();
        
        // Define user set variables.
        $this->enabled            = $this->get_option( 'enabled' );
        $this->title              = $this->get_option( 'title' );
        $this->shipping_class     = $this->get_option( 'shipping_class' );
        $this->show_delivery_time = $this->get_option( 'show_delivery_time' );
        $this->additional_time    = $this->get_option( 'additional_time' );
        $this->fee                = $this->get_option( 'fee' );
        $this->debug              = $this->get_option( 'debug' );

        // Save admin options.
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * Get shipping classes options.
     *
     * @return array
     */
    protected function get_shipping_classes_options() {
        $shipping_classes = WC()->shipping->get_shipping_classes();
        $options          = array(
            '' => __( '-- Select a shipping class --', 'woocommerce-multiple-local-pickup' ),
        );

        if ( ! empty( $shipping_classes ) ) {
            $options += wp_list_pluck( $shipping_classes, 'name', 'slug' );
        }

        return $options;
    }
    
    public function init_form_fields(){
        $this->instance_form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'woocommerce-multiple-local-pickup' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable this shipping method', 'woocommerce-multiple-local-pickup' ),
                'default' => 'yes',
            ),
            'title' => array(
                'title'       => __( 'Title', 'woocommerce-multiple-local-pickup' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-multiple-local-pickup' ),
                'desc_tip'    => true,
                'default'     => $this->method_title,
            ),
            'shipping_class' => array(
                'title'       => __( 'Shipping Class', 'woocommerce-multiple-local-pickup' ),
                'type'        => 'select',
                'description' => __( 'Select for which shipping class this method will be applied.', 'woocommerce-multiple-local-pickup' ),
                'desc_tip'    => true,
                'default'     => '',
                'class'       => 'wc-enhanced-select',
                'options'     => $this->get_shipping_classes_options(),
            ),
            'show_delivery_time' => array(
                'title'       => __( 'Delivery Time', 'woocommerce-multiple-local-pickup' ),
                'type'        => 'checkbox',
                'label'       => __( 'Show estimated delivery time', 'woocommerce-multiple-local-pickup' ),
                'description' => __( 'Display the estimated delivery time in working days.', 'woocommerce-multiple-local-pickup' ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'additional_time' => array(
                'title'       => __( 'Delivery Days', 'woocommerce-multiple-local-pickup' ),
                'type'        => 'text',
                'description' => __( 'Working days to the estimated delivery.', 'woocommerce-multiple-local-pickup' ),
                'desc_tip'    => true,
                'default'     => '0',
                'placeholder' => '0',
            ),
            'fee' => array(
                'title'       => __( 'Handling Fee', 'woocommerce-multiple-local-pickup' ),
                'type'        => 'price',
                'description' => __( 'Enter an amount, e.g. 2.50, or a percentage, e.g. 5%. Leave blank to disable.', 'woocommerce-multiple-local-pickup' ),
                'desc_tip'    => true,
                'placeholder' => '0.00',
                'default'     => '',
            ),
        );
    }
        
    public function is_available( $package = array() ){
        $is_available = true;
        return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package );
    }
    
    public function calculate_shipping( $package = array() ){
        
        $this->add_rate( array(
            'label'     => $this->title,
            'cost'      => $this->fee,
            'taxes'     => false,
            'package'   => $package,
            'meta_data' => array(
                'pickup_locations' => array(
                    'loja-1' => 'Loja 1',
                    'loja-2' => 'Loja 2',
                    'loja-3' => 'Loja 3',
                ),
                'pickup_chosen_location' => WC()->session->get( 'pickup_chosen_location' ),
            ),
        ) );
        
        //pre( WC()->session, 'session' );
        //pre( $this->rates );
        //pre( $package, 'calculate_shipping: $package' );
    }
    
    public static function method_options( $method, $index ){
        
        if( $method->method_id == 'multiple-local-pickup' ){
            
            //pre( $method, 'multiple-local-pickup PRE' );
            
            $class = 'brt-display-none';
            $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
            if( $chosen_shipping_methods[0] == $method->id ){
                $class = 'brt-display-block';
            }
            
            //pre($method->get_meta_data());
            //$method->add_meta_data( 'location', 'lorem' );
            
            $meta_data = $method->get_meta_data();
            
            // aplicar padrão caso ainda não tenha sido escolhido o location
            //$checked = !empty($meta_data['pickup_chosen_location']) ? $meta_data['pickup_chosen_location'] : key($meta_data['pickup_locations']);
            $checked = $meta_data['pickup_chosen_location'];
            
            echo "<ul class='pickup-locations {$class}'>";
            foreach( $meta_data['pickup_locations'] as $key => $label ){
                $is_checked = checked( $key, $checked, false );
                echo "<li><label><input type='radio' name='pickup-location' value='{$key}' id='pickup-location-{$key}' {$is_checked} /> {$label}</label></li>";
            }
            echo "</ul>";
            
            //pre( $method, 'multiple-local-pickup POS' );
        }
    }
}
