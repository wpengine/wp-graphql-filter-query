<?php
/**
 *
 * The plugin public information
 *
 * @link              https://developers.wpengine.com/
 * @since             0.1.0
 * @package           WPGraphqlFilterQuery
 *
 * @wordpress-plugin
 * Plugin Name:       WPGraphQL Filter Query
 * Plugin URI:        https://developers.wpengine.com/
 * Description:       Adds taxonomy filtering and aggregation support to WPGraphQL
 * Version:           0.1.0
 * Author:            WP Engine
 * Author URI:        https://wpengine.com/
 * Requires PHP:      7.4
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-graphql-filter-query
 * Domain Path:       /languages
 */

namespace WPGraphQL\FILTER_QUERY;

/**
 * Define constants
 */
const WPGRAPHQL_REQUIRED_MIN_VERSION = '0.4.0';

/**
 * Load files
 */
require_once dirname( __DIR__ ) . '/wp-graphql/vendor/webonyx/graphql-php/src/Error/ClientAware.php';

require_once __DIR__ . '/src/aggregate-query.php';
require_once __DIR__ . '/src/filter-exception.php';
require_once __DIR__ . '/src/filter-query.php';

use WPGraphQLFilterQuery\AggregateQuery;
use WPGraphQLFilterQuery\FilterQuery;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * If either WPGraphQL is not active, show the admin notice and return.
 */
if ( false === can_load_plugin() ) {
	// Show the admin notice.
	add_action( 'admin_init', __NAMESPACE__ . '\show_admin_notice' );

	return;
}

( new FilterQuery() )->add_hooks();
( new AggregateQuery() )->add_hooks();

/**
 * Get the supported post types.
 *
 * @return array
 */
function filter_query_get_supported_post_types(): array {
	$built_ins    = [ 'post', 'page' ];
	$type_objects = array();

	$cpt_type_names = get_post_types(
		[
			'public'          => true,
			'_builtin'        => false,
			'show_in_graphql' => true,
		],
		'names'
	);

	$type_names = array_merge( $built_ins, $cpt_type_names );

	foreach ( $type_names as $type_name ) {
		$type_objects[ $type_name ] = array(
			'name'            => $type_name,
			'capitalize_name' => ucwords( $type_name ),
			'plural_name'     => strtolower( get_post_type_object( $type_name )->label ),
		);
	}

	return $type_objects;
}

/**
 * Show admin notice to admins if this plugin is active but WPGraphQL not.
 *
 * @return bool
 */
function show_admin_notice() {

	/**
	 * For users with lower capabilities, don't show the notice.
	 */
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}

	add_action(
		'admin_notices',
		function() {
			?>
			<div class="error notice">
				<p>
					<?php
					// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
					esc_html_e( 'WPGraphQL (v' . WPGRAPHQL_REQUIRED_MIN_VERSION . '+)  must be active for "wp-graphql-filter-query" to work' );
					?>
				</p>
			</div>
			<?php
		}
	);
}


/**
 * Check whether WPGraphQL is active, and whether the minimum version requirement has been met.
 *
 * @return bool
 */
function can_load_plugin() {

	// // Is WPGraphQL active?
	// if ( ! class_exists( 'WPGraphQL' ) ) {
	// 	return false;
	// }

	// // Do we have a WPGraphQL version to check against?
	// if ( empty( defined( 'WPGRAPHQL_VERSION' ) ) ) {
	// 	return false;
	// }

	// // Have we met the minimum version requirement?
	// if ( true === version_compare( WPGRAPHQL_VERSION, WPGRAPHQL_REQUIRED_MIN_VERSION, 'lt' ) ) {
	// 	return false;
	// }

	return true;
}
