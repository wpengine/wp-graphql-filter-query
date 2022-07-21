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
	$built_ins    = [ 'post', 'page' ];
	$type_objects = array();

 	$cpt_type_names = get_post_types(
		[
			'public'          => true,
			'_builtin'        => false,
			'show_in_graphql' => true
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


new FilterQuery();
new AggregateQuery();
