<?php
/**
 * Plugin Name: HoH Woo Donations
 * Description: Easily accept donations of varying amounts through the Hospices of Hope WooCommerce store.
 * Version: 1.1.10
 * Author: Step3 Digital
 * Author URI: https://step3.digital
 * License: GNU General Public License version 3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 * Text Domain: donations-for-woocommerce
 * Domain Path: /lang
 * WC tested up to: 7.8.0
 */

/*
    Potent Donations for WooCommerce
    Copyright (C) 2023  Step3 Digital

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

/* CREDITS:
 * This plugin contains code copied from and/or based on the following third-party products,
 * in addition to any others indicated in code comments or license files:
 *
 * WordPress, by Automattic, GPLv2+
 * WordPress Codex, Copyright WordPress.org, released under the GNU General Public License (https://codex.wordpress.org/Codex:About)
 * WooCommerce, by Automattic, GPLv3
 * Original Developer: WP Zone (https://wpzone.co/)
 *
 * See licensing and copyright information in the ./license directory.
 * Code from the sources listed above is used in this plugin under the terms of the GNU General Public License version 3 or later.
*/

// Add Instructions link in plugins list
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'hm_wcdon_action_links');
function hm_wcdon_action_links($links) {
	array_unshift($links, '<a href="'.esc_url(get_admin_url(null, 'admin.php?page=hm_wcdon')).'">'.esc_html__('Instructions & Settings', 'donations-for-woocommerce').'</a>');
	return $links;
}

// Add admin page
add_action('admin_menu', 'hm_wcdon_admin_menu');
function hm_wcdon_admin_menu() {
	add_submenu_page('woocommerce', esc_html__('Donations for WooCommerce', 'donations-for-woocommerce'), esc_html__('Donations Settings', 'donations-for-woocommerce'), 'manage_woocommerce', 'hm_wcdon', 'hm_wcdon_admin_page');
}

// Display admin page
function hm_wcdon_admin_page() {
	if (!empty($_POST['save'])) {
		check_admin_referer('hm_wcdon_settings');
		$checkboxFields = array('disable_cart_amount_field');
		foreach ($checkboxFields as $cbField) {
			$_POST[$cbField] = (empty($_POST[$cbField]) ? 0 : 1);
		}
		hm_wcdon_set_options($_POST);
		echo('<div class="notice notice-success"><p>'.esc_html__('The settings have been saved.', 'donations-for-woocommerce').'</p></div>');
	}

	include(dirname(__FILE__).'/admin.php');
}

// Disable price display in frontend for Donation products
add_filter('woocommerce_get_price_html', 'hm_wcdon_get_price_html', 10, 2);
function hm_wcdon_get_price_html($price, $product) {
	if ($product->get_type() == 'donation')
		return (is_admin() ? 'Variable' : '');
	else
		return $price;
}

// Add amount field before add to cart button
add_action('woocommerce_before_add_to_cart_button', 'hm_wcdon_before_add_to_cart_button');
function hm_wcdon_before_add_to_cart_button() {
	global $product;
	if ($product->get_type() == 'donation') {
		echo('<div class="wc-donation-amount">
				<label for="donation_amount_field">'.esc_html__('Amount', 'donations-for-woocommerce').':</label>
				<input type="number" name="donation_amount" id="donation_amount_field" size="5" min="' . $product->get_donation_min_amount() . '" step="'.$product->get_donation_amount_increment().'" value="'.number_format($product->get_price(), 2, '.', '').'" class="input-text text" />
			</div>');
	}
}

// Add Donation product type option
add_filter('product_type_selector', 'hm_wcdon_product_type_selector');
function hm_wcdon_product_type_selector($productTypes) {
	$productTypes['donation'] = 'Donation';
	return $productTypes;
}

// Show all product data tabs for Donation products
add_filter('woocommerce_product_data_tabs', 'hm_wcdon_product_data_tabs', 10, 1);
function hm_wcdon_product_data_tabs($tabs) {
	foreach ($tabs as $tabId => $tabData) {
		if ($tabId != 'general' && $tabId != 'advanced' && $tabId != 'attribute' && $tabId != 'shipping') {
			$tabs[$tabId]['class'][] = 'show_if_donation';
		}
	}
	return $tabs;
}

// Create the WC_Product_Donation class
add_filter('plugins_loaded', 'hm_wcdon_plugins_loaded');
function hm_wcdon_plugins_loaded() {
	// Load translations - based on the example on the WordPress Codex, e.g. https://codex.wordpress.org/Function_Reference/load_plugin_textdomain and others
	load_plugin_textdomain('donations-for-woocommerce', false, basename(dirname(__FILE__)).'/lang');
	
	// Create Donation product
	if (class_exists('WC_Product_Simple')) {
		class WC_Product_Donation extends WC_Product_Simple {
			private $donationAmount = 0, $donationAmountIncrement;
			private $donationMin    = 0, $donationMinAmount;
			function __construct($product) {
				parent::__construct($product);
				$this->product_type = 'donation';
			}
			function get_type() {
				return 'donation';
			}
			function get_donation_amount_increment() {
				if (!isset($this->donationAmountIncrement)) {
					$this->donationAmountIncrement = get_post_meta($this->get_id(), '_donation_amount_increment', true);
					if (empty($this->donationAmountIncrement)) {
						$this->donationAmountIncrement = 0.01;
					}
				}
				return $this->donationAmountIncrement;
			}
			function get_donation_min_amount() {
				if (!isset($this->donationMinAmount)) {
					$this->donationMinAmount = get_post_meta($this->get_id(), '_donation_min_amount', true);
					if (empty($this->donationMinAmount)) {
						$this->donationMinAmount = get_post_meta($this->get_id(), '_price', true);
					}
				}
				return $this->donationMinAmount;
			}
			function is_taxable() { return (bool) hm_wcdon_get_option('show_tax_donation_product'); }
			function needs_shipping() { return (bool) hm_wcdon_get_option('show_shipping_donation_product'); }
			function is_virtual() { return !( hm_wcdon_get_option('show_shipping_donation_product') ); }
			function add_to_cart_url() { return get_permalink($this->id); }
		}
	}
}

// Disable AJAX add to cart for Donation products
add_filter('woocommerce_loop_add_to_cart_link', 'hm_wcdon_add_to_cart_link', 10, 2);
function hm_wcdon_add_to_cart_link($linkHtml, $product) {
	return ($product->get_type() == 'donation' ? str_replace('ajax_add_to_cart', '', $linkHtml) : $linkHtml);
}


// Add fields to the General product data tab
add_filter('woocommerce_product_options_general_product_data', 'hm_wcdon_product_options_general');
function hm_wcdon_product_options_general() {
	global $thepostid;
	echo('<div class="options_group show_if_donation">');
	woocommerce_wp_text_input(array('id' => 'donation_default_amount', 'label' => esc_html__('Default amount', 'donations-for-woocommerce'), 'value' => get_post_meta($thepostid, '_price', true), 'data_type' => 'price'));
	$donationAmountIncrement = get_post_meta($thepostid, '_donation_amount_increment', true);
	woocommerce_wp_text_input(array('id' => 'donation_amount_increment', 'label' => esc_html__('Amount increment', 'donations-for-woocommerce'), 'value' => (empty($donationAmountIncrement) ? 0.01 : $donationAmountIncrement), 'data_type' => 'decimal'));
	$donationMinAmount = get_post_meta($thepostid, '_donation_min_amount', true);
	woocommerce_wp_text_input(array('id' => 'donation_min_amount', 'label' => esc_html__('Min amount', 'donations-for-woocommerce'), 'value' => (empty($donationMinAmount) ? get_post_meta($thepostid, '_price', true) : $donationMinAmount), 'data_type' => 'decimal'));
	echo('</div>');
}

// Save donation product meta
add_action('woocommerce_process_product_meta_donation', 'hm_wcdon_process_product_meta');
function hm_wcdon_process_product_meta($productId) {
	$price = ($_POST['donation_default_amount'] === '') ? '' : wc_format_decimal($_POST['donation_default_amount']);
	update_post_meta($productId, '_price', $price);
	update_post_meta($productId, '_regular_price', $price);
	update_post_meta($productId, '_donation_amount_increment', (!empty($_POST['donation_amount_increment']) && is_numeric($_POST['donation_amount_increment']) ? number_format($_POST['donation_amount_increment'], 2, '.', '') : 0.01));
	update_post_meta($productId, '_donation_min_amount', (!empty($_POST['donation_min_amount']) && is_numeric($_POST['donation_min_amount']) ? number_format($_POST['donation_min_amount'], 2, '.', '') : get_post_meta($productId, '_price', true)));
}

// Process donation amount when a Donation product is added to the cart
add_filter('woocommerce_add_cart_item', 'hm_wcdon_add_cart_item');
function hm_wcdon_add_cart_item($item) {
	if ($item['data']->get_type() == 'donation') {
		if (isset($_POST['donation_amount']) && is_numeric($_POST['donation_amount']) && $_POST['donation_amount'] >= 0)
			$item['donation_amount'] = $_POST['donation_amount']*1;
		$item['data']->set_price($item['donation_amount']);
	}
	return $item;
}

// Use the Simple product type's add to cart button for Donation products
add_action('woocommerce_donation_add_to_cart', 'hm_wcdon_add_to_cart_template');
function hm_wcdon_add_to_cart_template() {
	do_action('woocommerce_simple_add_to_cart');
}

// Set Donation product price when loading the cart
add_filter('woocommerce_get_cart_item_from_session', 'hm_wcdon_get_cart_item_from_session');
function hm_wcdon_get_cart_item_from_session($session_data) {
	if ($session_data['data']->get_type() == 'donation' && isset($session_data['donation_amount']))
			$session_data['data']->set_price($session_data['donation_amount']);
	return $session_data;
}

// Get default plugin options
function hm_wcdon_default_options() {
	global $hm_wcdon_default_options;
	if (!isset($hm_wcdon_default_options)) {
		$hm_wcdon_default_options = array(
			'disable_cart_amount_field' => 0,
            'show_tax_donation_product' => 0,
            'show_shipping_donation_product' => 0
		);
	}
	return $hm_wcdon_default_options;
}

// Retrieve plugin options
function hm_wcdon_get_option($option) {
	global $hm_wcdon_options;
	if (!isset($hm_wcdon_options)) {
		$hm_wcdon_options = array_merge(hm_wcdon_default_options(), get_option('hm_wcdon_options', array()));
	}
	return (isset($hm_wcdon_options[$option]) ? $hm_wcdon_options[$option] : null);
}

// Save plugin options
function hm_wcdon_set_options($options) {
	$defaultOptions = hm_wcdon_default_options();
	return update_option('hm_wcdon_options', array_merge($defaultOptions, array_intersect_key($options, $defaultOptions)), false);
}

// Add stylesheet to frontend
function hm_wcdon_enqueue_scripts() {
	wp_enqueue_style('hm-wcdon-frontend-styles', plugins_url('css/frontend.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'hm_wcdon_enqueue_scripts');

// Show taxes options

function hm_variable_donation_admin_custom_js() {

    global $pagenow, $typenow;
    if ( isset($pagenow) && $pagenow == 'post.php' && isset($typenow) && $typenow == 'product' )   {
    ?>
    <script type='text/javascript'>

        jQuery(document).ready( function () {
            <?php  if ( hm_wcdon_get_option('show_tax_donation_product' )) { ?>
            jQuery('#general_product_data ._tax_status_field').parent().addClass('show_if_donation').show();
            <?php } ?>
            jQuery('#woocommerce-product-data .type_box label[for=_downloadable].tips').addClass('show_if_donation').show();
        })

    </script>
    <?php
    }
}

add_action('admin_footer', 'hm_variable_donation_admin_custom_js');

?>