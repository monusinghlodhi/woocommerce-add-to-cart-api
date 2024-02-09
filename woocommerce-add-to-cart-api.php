<?php

/**
 * Plugin Name: WooCommerce Add to Cart API
 * Description: A custom REST API endpoint for adding products to the cart for logged-in users with JWT authentication.
 * Version: 1.0
 * Author: Monu Singh
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('WC_Add_To_Cart_API')) {
    class WC_Add_To_Cart_API
    {
        public function __construct()
        {
            add_action('rest_api_init', array($this, 'register_wc_add_to_cart_endpoint'));
        }

        public function register_wc_add_to_cart_endpoint()
        {
            register_rest_route('wc-add-to-cart/v1', '/add-to-cart', array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_wc_add_to_cart_request'),
                'permission_callback' => function () {
                    return current_user_can('read');
                },
            ));

            register_rest_route('wc-add-to-cart/v1', '/update-cart-item', array(
                'methods' => 'PUT',
                'callback' => array($this, 'handle_wc_update_cart_item_request'),
                'permission_callback' => function () {
                    return current_user_can('read');
                },
            ));

            register_rest_route('wc-add-to-cart/v1', '/remove-cart-item', array(
                'methods' => 'DELETE',
                'callback' => array($this, 'handle_wc_remove_cart_item_request'),
                'permission_callback' => function () {
                    return current_user_can('read');
                },
            ));
        }

        public function handle_wc_add_to_cart_request(WP_REST_Request $request)
        {
            if (!class_exists('WooCommerce')) {
                return new WP_Error('woocommerce_not_found', 'WooCommerce plugin is not installed or active', array('status' => 404));
            }

            $product_id = $request->get_param('product_id');
            $quantity = $request->get_param('quantity');

            if (!$product_id || !$quantity) {
                return new WP_Error('missing_parameters', 'Product ID and quantity are required parameters', array('status' => 400));
            }

            $product = wc_get_product($product_id);

            if (!$product) {
                return new WP_Error('product_not_found', 'Product not found', array('status' => 404));
            }

            // Check if the cart is empty and initialize it if necessary
            if (null === WC()->cart) {
                WC()->frontend_includes();
                WC()->session = new WC_Session_Handler();
                WC()->session->init();
                WC()->customer = new WC_Customer(get_current_user_id(), true);
                WC()->cart = new WC_Cart();
            }

            $cart = WC()->cart;

            // Check if the product is already in the cart
            $cart_item_quantities = $cart->get_cart_item_quantities();
            $product_in_cart = isset($cart_item_quantities[$product_id]);

            // If the product is already in the cart, update its quantity
            if ($product_in_cart) {
                foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                    if ($cart_item['product_id'] == $product_id) {
                        $current_quantity = $cart_item['quantity'];
                        $new_quantity = $current_quantity + $quantity;
                        $cart->set_quantity($cart_item_key, $new_quantity, true);
                        break;
                    }
                }
            } else {
                // If the product is not in the cart, add it
                $cart->add_to_cart($product_id, $quantity);
            }

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Product added or updated in cart',
                'cart_contents_count' => $cart->get_cart_contents_count(),
            ), 200);
        }


        public function handle_wc_remove_cart_item_request(WP_REST_Request $request) {
            if (!class_exists('WooCommerce')) {
                return new WP_Error('woocommerce_not_found', 'WooCommerce plugin is not installed or active', array('status' => 404));
            }
        
            $product_id = $request->get_param('product_id');
        
            if (!$product_id) {
                return new WP_Error('missing_parameters', 'Product ID is a required parameter', array('status' => 400));
            }
        
            if (null === WC()->cart) {
                WC()->frontend_includes();
                WC()->session = new WC_Session_Handler();
                WC()->session->init();
                WC()->customer = new WC_Customer(get_current_user_id(), true);
                WC()->cart = new WC_Cart();
            }
        
            $cart = WC()->cart;
            $cart_item_key_to_remove = false;
        
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                if ($cart_item['product_id'] == $product_id) {
                    $cart_item_key_to_remove = $cart_item_key;
                    break;
                }
            }
        
            if ($cart_item_key_to_remove) {
                $cart->remove_cart_item($cart_item_key_to_remove);
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Product removed from cart',
                    'cart_contents_count' => $cart->get_cart_contents_count(),
                ), 200);
            } else {
                return new WP_Error('product_not_in_cart', 'Product not found in cart', array('status' => 404));
            }
        }


        public function handle_wc_update_cart_item_request(WP_REST_Request $request) {
            if (!class_exists('WooCommerce')) {
                return new WP_Error('woocommerce_not_found', 'WooCommerce plugin is not installed or active', array('status' => 404));
            }
        
            $product_id = $request->get_param('product_id');
            $quantity = $request->get_param('quantity');
        
            if (!$product_id || !$quantity) {
                return new WP_Error('missing_parameters', 'Product ID and quantity are required parameters', array('status' => 400));
            }
        
            if (null === WC()->cart) {
                WC()->frontend_includes();
                WC()->session = new WC_Session_Handler();
                WC()->session->init();
                WC()->customer = new WC_Customer(get_current_user_id(), true);
                WC()->cart = new WC_Cart();
            }
        
            $cart = WC()->cart;
            $cart_item_key_to_update = false;
        
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                if ($cart_item['product_id'] == $product_id) {
                    $cart_item_key_to_update = $cart_item_key;
                    break;
                }
            }
        
            if ($cart_item_key_to_update) {
                $cart->set_quantity($cart_item_key_to_update, $quantity, true);
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Product quantity updated in cart',
                    'cart_contents_count' => $cart->get_cart_contents_count(),
                ), 200);
            } else {
                return new WP_Error('product_not_in_cart', 'Product not found in cart', array('status' => 404));
            }
        }
        

        
        
        
    }

    new WC_Add_To_Cart_API();
}


