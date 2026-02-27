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


( new FilterQuery() )->add_hooks();
( new AggregateQuery() )->add_hooks();
