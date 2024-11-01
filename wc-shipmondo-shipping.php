<?php
/**
 * Plugin Name: Multi-Carrier Shipmondo Shipping for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/wc-shipmondo-shipping/
 * Description: Adds Shipmondo shippping methods to Woocommerce.
 * Version: 1.2.14
 * Tested up to: 6.6
 * Requires PHP: 7.3
 * Author: OneTeamSoftware
 * Author URI: http://oneteamsoftware.com/
 * Developer: OneTeamSoftware
 * Developer URI: http://oneteamsoftware.com/
 * Text Domain: wc-shipmondo-shipping
 * Domain Path: /languages
 * Copyright: © 2024 FlexRC, Canada.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace OneTeamSoftware\WooCommerce\Shipping;

defined('ABSPATH') || exit;

require_once(__DIR__ . '/includes/autoloader.php');
	
(new Plugin(
		__FILE__, 
		'Shipmondo', 
		sprintf('<div class="notice notice-info inline"><p>%s<br/><li><a href="%s" target="_blank">%s</a><br/><li><a href="%s" target="_blank">%s</a></p></div>', 
			__('Real-time Shipmondo live shipping rates', 'wc-shipmondo-shipping'),
			'https://1teamsoftware.com/contact-us/',
			__('Do you have any questions or requests?', 'wc-shipmondo-shipping'),
			'https://wordpress.org/plugins/wc-shipmondo-shipping/', 
			__('Do you like our plugin and can recommend to others?', 'wc-shipmondo-shipping')),
		'1.2.14'
	)
)->register();
