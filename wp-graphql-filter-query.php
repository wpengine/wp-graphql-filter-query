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
	$type_objects = array();

	$post_types = get_post_types(
		[
			'public'          => true,
			'show_in_graphql' => true,
		],
		'objects'
	);

	foreach ( $post_types as $post_type ) {
		$type_objects[ $post_type->name ] = array(
			'name'            => $post_type->name,
			'capitalize_name' => ucwords( $post_type->name ),
			'plural_name'     => strtolower( $post_type->label ),
		);
	}

	return $type_objects;
}


( new FilterQuery() )->add_hooks();
( new AggregateQuery() )->add_hooks();
