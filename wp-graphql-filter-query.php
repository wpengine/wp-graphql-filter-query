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

// Your code starts here.
use WPGraphQLFilterQuery\FilterQuery;

if ( ! defined( 'WPINC' ) ) {
	die;
}

require __DIR__ . '/vendor/autoload.php';


new FilterQuery();
