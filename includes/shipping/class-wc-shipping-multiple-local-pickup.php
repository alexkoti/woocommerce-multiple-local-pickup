<?php
/**
 * Multiple Pickup Locations shipping method.
 *
 * @package WC_Multiple_Local_Pickup/Classes/Shipping
 * @since   1.0.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Shipping_Multiple_Local_Pickup extends WC_Shipping_Method {
    
    
    public static $counter = 0;
    
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
        $this->enabled                = $this->get_option( 'enabled' );
        $this->title                  = $this->get_option( 'title' );
        $this->shipping_class         = $this->get_option( 'shipping_class' );
        $this->show_delivery_time     = $this->get_option( 'show_delivery_time' );
        $this->additional_time        = $this->get_option( 'additional_time' );
        $this->fee                    = $this->get_option( 'fee' );
        $this->pickup_locations       = $this->get_option( 'pickup_locations' );
        $this->debug                  = $this->get_option( 'debug' );

        // Save admin options.
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
        
        // Mostrar opções do método de entrega
        //add_action( 'woocommerce_after_shipping_rate', array( $this, 'method_options' ), 10, 2 );
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
            'pickup_locations' => array(
                'title'       => 'Locais',
                'type'        => 'multiselect',
                'description' => '',
                'desc_tip'    => true,
                'placeholder' => '',
                'default'     => '',
                'class'       => 'wc-enhanced-select',
                'options'     => self::get_available_locations(),
            ),
            'test' => array(
                'title'       => 'TEST',
                'type'        => 'brt_repeater',
                'description' => 'asasas',
                'desc_tip'    => true,
                'placeholder' => '',
                'default'     => '',
            ),
        );
    }
        
    public function is_available( $package = array() ){
        $is_available = true;
        $all_locations = self::get_available_locations();
        if( empty($all_locations) ){
            $is_available = false;
        }
        return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package );
    }
    
    public function calculate_shipping( $package = array() ){
        
        $this->add_rate( array(
            'label'     => $this->title,
            'cost'      => $this->fee,
            'taxes'     => false,
            'package'   => false,
            'meta_data' => array(
                'pickup_locations' => $this->pickup_locations,
                'pickup_chosen_location' => WC()->session->get( 'pickup_chosen_location' ),
                'additional_time' => $this->additional_time,
            ),
        ) );
        
        //pre( WC()->session, 'session' );
        //pre( $this->rates );
        //pre( $package, 'calculate_shipping: $package' );
    }
    
    /**
     * Opções mostradas no carrinho ao usuário
     * 
     */
    function method_options( $method, $index ){
        
        // Rodar apenas uma vez
        if( self::$counter > 0 ){
            return;
        }
        
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
            
            self::$counter++;
            //pre( $method, 'multiple-local-pickup POS' );
        }
    }
    
    /**
     * Lista de locais disponíveis. Sempre é necessário preenchê-la via filter.
     * 
     */
    public static function get_available_locations(){
        return apply_filters( 'multiple_local_pickup_locations_list', array() );
    }
    
    /**
     * Custom form field
     * 
     */
    function generate_brt_repeater_html( $key, $data ){
        $field_key = $this->get_field_key( $key );
        $defaults  = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args( $data, $defaults );

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
                <?php echo $this->get_tooltip_html( $data ); ?>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
                    <input 
                        class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" 
                        type="text" 
                        name="<?php echo esc_attr( $field_key ); ?>" 
                        id="<?php echo esc_attr( $field_key ); ?>" 
                        style="<?php echo esc_attr( $data['css'] ); ?>" 
                        value="<?php echo esc_attr( wc_format_localized_price( $this->get_option( $key ) ) ); ?>" 
                        placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" 
                        <?php disabled( $data['disabled'], true ); ?> 
                        <?php echo $this->get_custom_attribute_html( $data ); ?>
                    />
                    <?php echo $this->get_description_html( $data ); ?>
                    <p>ESTE É UM CAMPO DE TESTE DE LOCAIS DE RETIRADA</p>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }
    
    
    
}
