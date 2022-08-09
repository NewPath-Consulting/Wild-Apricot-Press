<?php

/*
 * Plugin Name:       WildApricot Press
 * Plugin URI:        https://newpathconsulting.com/wap
 * Description:       Integrates your WildApricot-powered organization with a WordPress website! Powered by WildApricot's API.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            NewPath Consulting
 * Author URI:        https://newpathconsulting.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wap
 */

/*
Copyright (C) 2021-22 NewPath Consulting

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

For further inquires, contact NewPath Consulting at support@newpathconsulting.com
or at 5000 Yonge Street, Suite 1901, Toronto, Ontario, M2N 7E9, Canada.
*/

require_once plugin_dir_path(__FILE__) . 'src/class-activator.php';
require_once plugin_dir_path(__FILE__) . 'src/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'src/class-deactivator.php';
require_once plugin_dir_path(__FILE__) . 'src/class-wa-integration.php';
require_once plugin_dir_path(__FILE__) . 'src/helpers.php';

$activator = new WAWP\Activator(plugin_basename(__FILE__));
// Enqueue stylesheet
add_action('admin_enqueue_scripts', 'wawp_enqueue_admin_script');
function wawp_enqueue_admin_script($hook) {
    wp_enqueue_style('wawp-styles-admin', plugin_dir_url(__FILE__) . 'css/wawp-styles-admin.css', array(), '1.0');
}
// Enqueue stylesheet for shortcode
add_action('wp_enqueue_scripts', 'wawp_enqueue_shortcode_css');
function wawp_enqueue_shortcode_css() {
	wp_enqueue_style('wawp-styles-shortcode', plugin_dir_url(__FILE__) . 'css/wawp-styles-shortcode.css', array(), '1.0');
}

// Create settings page
$settings = new WAWP\Settings();

// Create WA Integration instance
$wa_integration_instance = new WAWP\WA_Integration();

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
	WAWP\Deactivator::deactivate();
} );

?>
