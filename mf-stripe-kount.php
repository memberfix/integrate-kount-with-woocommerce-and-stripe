<?php

/**
 * Plugin Name:       Kount with Stripe for WooCommerce 
 * Plugin URI:        https://memberfix.rocks
 * Description:       An integration of Kount with Stripe through the Stripe integration plugin offered by PaymentPlugins for free in the WordPress repository
 * Version:           1.0
 * Requires at least: 5.5
 * Requires PHP:      7.2
 * Author:            Sorin Marta @ MemberFix
 * Author URI:        https://sorinmarta.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

class MF_Stripe_Kount{
     private $session_id;
     private $kount_id = 'ADD YOUR ID HERE';

     public function __construct(){
         $this->session_id = $this->generate_ses_id();
         add_action('wp_enqueue_scripts', array($this, 'enqueue_head'));
         add_action('wp_head', array($this, 'enqueue_footer'));
         add_action('woocommerce_before_checkout_form', array($this, 'add_ddc'));
         add_action( 'wc_stripe_before_process_payment', array($this,'kount_action'));
         require_once(plugin_dir_path(__FILE__) . 'vendor/kount/src/autoload.php');
     }

     private function generate_ses_id(){
         return time();
     }

     // Adds the Kount JS library
     public function enqueue_head(){
         setcookie('mf_ses_id', $this->session_id,time() + 86400, '/');
         echo "<script src='https://ssl.kaptcha.com/collect/sdk?m=$this->kount_id&s=$this->session_id'></script>";
     }

     // Adds the configuration file for the js library
     public function enqueue_footer(){
         wp_enqueue_script('mfsk-main','/wp-content/plugins/mf-stripe-kount/js/main.js',array(),null, true);
     }

     // Starts the Kount js SDK
     public function add_ddc(){
         ?>
         <script>
             var client=new ka.ClientSDK();
             client.autoLoadEvents();
         </script>
        <?php
     }

     // The action that happens on checkout
     public function kount_action($order){
             $order_total = intval($order->get_total()) * 100;
             $products = $order->get_items();
             $mf_ses_id = $_COOKIE['mf_ses_id'];

             $cart_products = array();

             foreach($products as $product){
                 array_push($cart_products, new Kount_Ris_Data_CartItem($product['type'], $product['name'], $product['desc'], $product['qty'], $order_total));
             }

             $inquiry = new Kount_Ris_Request_Inquiry();

             $inquiry->setSessionId($mf_ses_id);
             $inquiry->setPayment('NONE','');

             $inquiry->setTotal($order_total);
             $inquiry->setName($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
             $inquiry->setEmail($order->get_billing_email());
             $inquiry->setIpAddress($order->get_customer_ip_address());
             $inquiry->setMack('Y');
             $inquiry->setMode('Q');
             $inquiry->setOrderNumber($order->get_order_number());
             $inquiry->setUnique($order->get_customer_id());
             $inquiry->setWebsite("DEFAULT");
             $inquiry->setCart($cart_products);
             $inquiry->setAuth('A');

             $response = $inquiry->getResponse();
             $warnings = $response->getWarnings();

             $score = $response->getScore();
             $decision = $response->getAuto();

             if ($decision != 'A'){
                 wc_add_notice( 'There was an issue with your credit cart, for more details please contact us.', 'error' );
             }

             unset($_COOKIE['mf_ses_id']);
     }
 }

 new MF_Stripe_Kount();