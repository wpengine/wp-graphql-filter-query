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
				'description' => __( 'String Field Match Arguments', 'wp-graphql-filter-query' ),
				'fields'      => [
					'in'      => [
						'type'        => [ 'list_of' => 'String' ],
						'description' => __( 'For This To Be Truthy, At Least One Item Of The String Array Arg Passed Here Must Be Contained Within The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
					'notIn'   => [
						'type'        => [ 'list_of' => 'String' ],
						'description' => __( 'For This To Be Truthy, Not One Item Of The String Array Arg Passed Here Can Be Contained Within The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
					'like'    => [
						'type'        => 'String',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Relate To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
					'notLike' => [
						'type'        => 'String',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Not Relate To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
					'eq'      => [
						'type'        => 'String',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Be An Exact Match To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
					'notEq'   => [
						'type'        => 'String',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Not Match To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
				],
			]
		);

		register_graphql_input_type(
			'FilterFieldsInteger',
			[
				'description' => __( 'Integer Field Match Arguments', 'wp-graphql-filter-query' ),
				'fields'      => [
					'in'      => [
						'type'        => [ 'list_of' => 'Integer' ],
						'description' => __( 'For This To Be Truthy, At Least One Item Of The String Array Arg Passed Here Must Be Contained Within The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
					'notIn'   => [
						'type'        => [ 'list_of' => 'Integer' ],
						'description' => __( 'For This To Be Truthy, Not One Item Of The String Array Arg Passed Here Can Be Contained Within The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
					'like'    => [
						'type'        => 'Integer',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Relate To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
					'notLike' => [
						'type'        => 'Integer',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Not Relate To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
					'eq'      => [
						'type'        => 'Integer',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Be An Exact Match To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
					'notEq'   => [
						'type'        => 'Integer',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Not Match To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
				],
			]
		);

		register_graphql_input_type(
			'TaxonomyFilterFields',
			[
				'description' => __( 'Taxonomy fields For Filtering', 'wp-graphql-filter-query' ),
				'fields'      => [
					'name' => [
						'type'        => 'FilterFieldsString',
						'description' => __( 'ID field For Filtering, Of Type String', 'wp-graphql-filter-query' ),
					],
					'id'   => [
						'type'        => 'FilterFieldsInteger',
						'description' => __( 'ID field For Filtering, Of Type Integer', 'wp-graphql-filter-query' ),
					],
				],
			]
		);

		register_graphql_input_type(
			'TaxonomyFilter',
			[
				'description' => __( 'Taxonomies Where Filtering Supported', 'wp-graphql-filter-query' ),
				'fields'      => [
					'tag'      => [
						'type'        => 'TaxonomyFilterFields',
						'description' => __( 'Tags Object Fields Allowable For Filtering', 'wp-graphql-filter-query' ),
					],
					'category' => [
						'type'        => 'TaxonomyFilterFields',
						'description' => __( 'Category Object Fields Allowable For Filtering', 'wp-graphql-filter-query' ),
					],
				],
			]
		);

		// Add { filter: TagOrCategory.TagOrCategoryFields.FilterFieldsInteger } input object in Posts.where args connector, until we figure how to add to root Posts object with args.
		$default_supported_wp_types = array( 'Post', 'Page' );
		$cpt_type_names             = get_post_types(
			array(
				'public'   => true,
				'_builtin' => false,
			),
			'names'
		);

		foreach ( $cpt_type_names as $name ) {
			$cpt_type_names[ $name ] = ucwords( $name );
		}

		$taxonomy_filter_supported_types = array_merge( $default_supported_wp_types, $cpt_type_names );

		foreach ( $taxonomy_filter_supported_types as &$type ) {
			$graphql_single_name = $type;
			register_graphql_field(
				'RootQueryTo' . $graphql_single_name . 'ConnectionWhereArgs',
				'filter',
				[
					'type'        => 'TaxonomyFilter',
					'description' => __( 'Filtering Queried Results By Taxonomy Objects', 'wp-graphql-filter-query' ),
				]
			);
		}
		unset( $type );
	}
}
