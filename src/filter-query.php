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
				'description' => __( 'String Field Match Arguments', 'your-textdomain' ),
				'fields'      => [
					'in'      => [
						'type'        => [ 'list_of' => 'String' ],
						'description' => __( 'For This To Be Truthy, At Least One Item Of The String Array Arg Passed Here Must Be Contained Within The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'your-textdomain' ),
					],
					'notIn'   => [
						'type'        => [ 'list_of' => 'String' ],
						'description' => __( 'For This To Be Truthy, Not One Item Of The String Array Arg Passed Here Can Be Contained Within The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'your-textdomain' ),
					],
					'like'    => [
						'type'        => 'String',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Relate To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'your-textdomain' ),
					],
					'notLike' => [
						'type'        => 'String',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Not Relate To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'your-textdomain' ),
					],
					'eq'      => [
						'type'        => 'String',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Be An Exact Match To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'your-textdomain' ),
					],
					'notEq'   => [
						'type'        => 'String',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Not Match To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'your-textdomain' ),
					],
				],
			]
		);

		register_graphql_input_type(
			'FilterFieldsInteger',
			[
				'description' => __( 'Integer Field Match Arguments', 'your-textdomain' ),
				'fields'      => [
					'in'      => [
						'type'        => [ 'list_of' => 'Integer' ],
						'description' => __( 'For This To Be Truthy, At Least One Item Of The String Array Arg Passed Here Must Be Contained Within The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'your-textdomain' ),
					],
					'notIn'   => [
						'type'        => [ 'list_of' => 'Integer' ],
						'description' => __( 'For This To Be Truthy, Not One Item Of The String Array Arg Passed Here Can Be Contained Within The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'your-textdomain' ),
					],
					'like'    => [
						'type'        => 'Integer',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Relate To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'your-textdomain' ),
					],
					'notLike' => [
						'type'        => 'Integer',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Not Relate To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'your-textdomain' ),
					],
					'eq'      => [
						'type'        => 'Integer',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Be An Exact Match To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'your-textdomain' ),
					],
					'notEq'   => [
						'type'        => 'Integer',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Not Match To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'your-textdomain' ),
					],
				],
			]
		);

		register_graphql_input_type(
			'TaxonomyFilterFields',
			[
				'description' => __( 'Taxonomy fields For Filtering', 'your-textdomain' ),
				'fields'      => [
					'name' => [
						'type'        => 'FilterFieldsString',
						'description' => __( 'ID field For Filtering, Of Type String', 'your-textdomain' ),
					],
					'id'   => [
						'type'        => 'FilterFieldsInteger',
						'description' => __( 'ID field For Filtering, Of Type Integer', 'your-textdomain' ),
					],
				],
			]
		);

		register_graphql_input_type(
			'TaxonomyFilter',
			[
				'description' => __( 'Taxonomies Where Filtering Supported', 'your-textdomain' ),
				'fields'      => [
					'tag'      => [
						'type'        => 'TaxonomyFilterFields',
						'description' => __( 'Tags Object Fields Allowable For Filtering', 'your-textdomain' ),
					],
					'category' => [
						'type'        => 'TaxonomyFilterFields',
						'description' => __( 'Category Object Fields Allowable For Filtering', 'your-textdomain' ),
					],
				],
			]
		);

		// Add { filter: TagOrCategory.TagOrCategoryFields.FilterFieldsInteger } input object in Posts.where args connector, until we figure how to add to root Posts object with args.
		$taxonomy_filter_supported_types = array('Post', 'Page', 'User');
		
		foreach ($taxonomy_filter_supported_types as &$type) {
			$graphql_single_name = $type;
			register_graphql_field(
				'RootQueryTo' . $graphql_single_name . 'ConnectionWhereArgs',
				'filter',
				[
					'type'        => 'TaxonomyFilter',
					'description' => __( 'Filtering Queried Results By Taxonomy Objects', 'your-textdomain' ),
				]
			);
		}
		unset($type);
	}
}
