<?php
/**
 * Filter Query extension for WP-GraphQL
 *
 * @package WPGraphqlFilterQuery
 */

namespace WPGraphQLFilterQuery;

/**
 * Main class.
 */
class FilterQuery {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->define_public_hooks();
	}


	/**
	 * Define the hooks to register.
	 *
	 * @return void
	 */
	public function define_public_hooks() {
		add_action( 'graphql_register_types', [ $this, 'extend_wp_graphql_schema' ] );
	}

	/**
	 * Extends WP Graphql schema.
	 *
	 * @return void
	 */
	public function extend_wp_graphql_schema() {
		register_graphql_field(
			'RootQuery',
			'customField',
			[
				'type'        => 'String',
				'description' => __( 'Describe what the field should be used for', 'your-textdomain' ),
				'resolve'     => function() {
					return 'value...';
				},
			]
		);
	}
}
