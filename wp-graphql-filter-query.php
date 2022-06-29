<?php
/**
 * Plugin Name:     Wp Graphql Filter Query
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     PLUGIN DESCRIPTION HERE
 * Author:          YOUR NAME HERE
 * Author URI:      YOUR SITE HERE
 * Text Domain:     wp-graphql-filter-query
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         WPGraphqlFilterQuery
 */

use WPGraphQLFilterQuery\AggregateQuery;
use WPGraphQLFilterQuery\FilterQuery;

if ( ! defined( 'WPINC' ) ) {
	die;
}

require __DIR__ . '/vendor/autoload.php';

/**
 * Get the supported post types.
 *
 * @return array
 */
function filter_query_get_supported_post_types(): array {
	$built_ins      = [ 'Post', 'Page' ];
	$cpt_type_names = get_post_types(
		[
			'public'   => true,
			'_builtin' => false,
		],
		'names'
	);

	foreach ( $cpt_type_names as $name ) {
		$cpt_type_names[ $name ] = ucwords( $name );
	}

	return array_merge( $built_ins, $cpt_type_names );
}


new FilterQuery();
new AggregateQuery();
