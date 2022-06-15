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
		add_action( 'graphql_register_types', [ $this, 'extend_wp_graphql_fields' ] );
	}

	/**
	 * Register Nested Input objs.
	 *
	 * @return void
	 */
	public function extend_wp_graphql_fields() {
		register_graphql_input_type(
			'FilterFieldsString',
			[
				'description' => __( 'De', 'de-de' ),
				'fields'      => [
					'in'      => [
						'type'        => [ 'list_of' => 'String' ],
						'description' => __( 'De', 'de-de' ),
					],
					'notIn'   => [
						'type'        => [ 'list_of' => 'String' ],
						'description' => __( 'De', 'de-de' ),
					],
					'like'    => [
						'type'        => 'String',
						'description' => __( 'De', 'de-de' ),
					],
					'notLike' => [
						'type'        => 'String',
						'description' => __( 'De', 'de-de' ),
					],
					'eq'      => [
						'type'        => 'String',
						'description' => __( 'De', 'de-de' ),
					],
					'notEq'   => [
						'type'        => 'String',
						'description' => __( 'De', 'de-de' ),
					],
				],
			]
		);

		register_graphql_input_type(
			'FilterFieldsInteger',
			[
				'description' => __( 'De', 'de-de' ),
				'fields'      => [
					'in'      => [
						'type'        => [ 'list_of' => 'Integer' ],
						'description' => __( 'De', 'de-de' ),
					],
					'notIn'   => [
						'type'        => [ 'list_of' => 'Integer' ],
						'description' => __( 'De', 'de-de' ),
					],
					'like'    => [
						'type'        => 'Integer',
						'description' => __( 'De', 'de-de' ),
					],
					'notLike' => [
						'type'        => 'Integer',
						'description' => __( 'De', 'de-de' ),
					],
					'eq'      => [
						'type'        => 'Integer',
						'description' => __( 'De', 'de-de' ),
					],
					'notEq'   => [
						'type'        => 'Integer',
						'description' => __( 'De', 'de-de' ),
					],
				],
			]
		);

		register_graphql_input_type(
			'TagOrCategoryFields',
			[
				'description' => __( 'De', 'de-de' ),
				'fields'      => [
					'name' => [
						'type'        => 'FilterFieldsString',
						'description' => __( 'De', 'de-de' ),
					],
					'id'   => [
						'type'        => 'FilterFieldsInteger',
						'description' => __( 'De', 'de-de' ),
					],
				],
			]
		);

		register_graphql_input_type(
			'TagOrCategory',
			[
				'description' => __( 'De', 'de-de' ),
				'fields'      => [
					'tag'      => [
						'type'        => 'TagOrCategoryFields',
						'description' => __( 'De', 'de-de' ),
					],
					'category' => [
						'type'        => 'TagOrCategoryFields',
						'description' => __( 'De', 'de-de' ),
					],
				],
			]
		);

		// Add { filter: TagOrCategory.TagOrCategoryFields.FilterFieldsInteger } input object in Posts.where args connector, until we figure how to add to root Posts object with args.
		$graphql_single_name = 'Post';
		register_graphql_field(
			'RootQueryTo' . $graphql_single_name . 'ConnectionWhereArgs',
			'filter',
			[
				'type'        => 'TagOrCategory',
				'description' => __( 'Filtering for fields in result, not fields in args', 'your-textdomain' ),
				'resolve'     => function( $post, $args, $context, $info ) {
					graphql_debug( [ '[WPGraphQL Spy] Filter Args:' => $args ] ); // < MUST ENABLE DEBUG IN WPGraphQL SETTINGS.
					return 'value...' . implode( '', $args );
				},
			]
		);
	}
}
