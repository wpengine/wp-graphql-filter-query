<?php
/**
 * Filter Query extension for WP-GraphQL
 *
 * @package WPGraphqlFilterQuery
 */

namespace WPGraphQLFilterQuery;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

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
		add_action( 'graphql_register_types', [ $this, 'extend_wp_graphql_aggregation_fields' ] );
	}

	/**
	 * Define the objects for aggregates.
	 *
	 * @return void
	 */
	public function extend_wp_graphql_aggregation_fields() {
		register_graphql_object_type(
			'BucketItem',
			[
				'description' => 'aggregate',
				'fields'      => [
					'key'   => [
						'type' => 'String',
					],
					'count' => [
						'type' => 'Integer',
					],
				],
			]
		);

		$post_types = $this->get_supported_post_types();

		// iterate through all the supported models.
		foreach ( $post_types as $post_type ) {
			// pick out the aggregate fields this model.
			$fields = [ 'categories', 'tags' ];

			// if none continue.
			if ( count( $fields ) < 1 ) {
				continue;
			}

			// next we are generating the aggregates block for each model.
			$aggregate_graphql = [];
			foreach ( $fields as $field ) {
				$aggregate_graphql[ $field ] = [ 'type' => array( 'list_of' => 'BucketItem' ) ];
			}

			// store object name in a variable to DRY up code.
			$aggregate_for_type_name = 'AggregatesFor' . ucfirst( $post_type );

			// finally, register the type.
			register_graphql_object_type(
				$aggregate_for_type_name,
				[
					'description' => 'aggregate',
					'fields'      => $aggregate_graphql,
				]
			);

			// here we are registering the root `aggregates` field onto each model
			// that has aggregate fields defined.
			register_graphql_field(
				'RootQueryTo' . ucfirst( $post_type ) . 'Connection',
				'aggregations',
				[
					'type'    => $aggregate_for_type_name,
					'resolve' => function( $root, $args, $context, $info ) {
						return [
							'categories' => [
								[
									'key'   => 'soccer',
									'count' => 25,
								],
								[
									'key'   => 'rugby',
									'count' => 6,
								],
							],
							'tags'       => [
								[
									'key'   => 'adidas',
									'count' => 2,
								],
								[
									'key'   => 'nike',
									'count' => 6,
								],
							],
							'seasons'    => [
								[
									'key'   => 'spring',
									'count' => 2,
								],
								[
									'key'   => 'summer',
									'count' => 6,
								],
								[
									'key'   => 'autumn',
									'count' => 2,
								],
								[
									'key'   => 'winter',
									'count' => 30,
								],
							],
							'labels'     => [
								[
									'key'   => 'richard',
									'count' => 2,
								],
							],
						];
					},
				]
			);
		}
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
					'in'    => [
						'type'        => [ 'list_of' => 'Integer' ],
						'description' => __( 'For This To Be Truthy, At Least One Item Of The String Array Arg Passed Here Must Be Contained Within The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
					'notIn' => [
						'type'        => [ 'list_of' => 'Integer' ],
						'description' => __( 'For This To Be Truthy, Not One Item Of The String Array Arg Passed Here Can Be Contained Within The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
					'eq'    => [
						'type'        => 'Integer',
						'description' => __( 'For This To Be Truthy, The Arg Passed Here Must Be An Exact Match To The Calling Taxonomy Field, By Way Of Predefined Aggregates', 'wp-graphql-filter-query' ),
					],
					'notEq' => [
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
		$taxonomy_filter_supported_types = $this->get_supported_post_types();

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

		add_filter(
			'graphql_post_object_connection_query_args',
			[ $this, 'apply_filters' ],
			5,
			10
		);
	}

	/**
	 * Apply facet filters using graphql_post_object_connection_query_args filter hook.
	 *
	 * @param array       $query_args arguments that come from previous filter and will be passed to WP_Query.
	 * @param mixed       $source Not used.
	 * @param array       $args WPGraphQL input arguments.
	 * @param AppContext  $context Not used.
	 * @param ResolveInfo $info Not used.
	 *
	 * @return array|mixed
	 */
	public function apply_filters( $query_args, $source, $args, $context, $info ) {
		if ( empty( $args['where']['filter'] ) ) {
			return $query_args;
		}
		$operator_mappings = array(
			'in'      => 'IN',
			'notIn'   => 'NOT IN',
			'eq'      => 'IN',
			'notEq'   => 'NOT IN',
			'like'    => 'IN',
			'notLike' => 'NOT IN',
		);
		$c                 = 0;
		foreach ( $args['where']['filter'] as $taxonomy_input => $data ) {
			foreach ( $data as $field_name => $field_data ) {
				foreach ( $field_data as $operator => $terms ) {
					$mapped_operator  = $operator_mappings[ $operator ] ?? 'IN';
					$is_like_operator = $this->is_like_operator( $operator );
					$taxonomy         = $taxonomy_input === 'tag' ? 'post_tag' : 'category';

					$terms = ! $is_like_operator ? $terms : get_terms(
						array(
							'name__like' => esc_attr( $terms ),
							'fields'     => 'ids',
							'taxonomy'   => $taxonomy,
						)
					);

					$result = array(
						'terms'    => $terms,
						'taxonomy' => $taxonomy,
						'operator' => $mapped_operator,
						'field'    => ( $field_name === 'id' || $is_like_operator ) ? 'term_id' : 'name',
					);

					$query_args['tax_query'][] = $result;
					$c++;
				}
			}
		}

		if ( $c > 1 ) {
			$query_args['tax_query']['relation'] = 'AND';
		}

		return $query_args;
	}

	/**
	 * Check if operator is like or notLike
	 *
	 * @param string $operator Received operator - not mapped.
	 *
	 * @return bool
	 */
	private function is_like_operator( string $operator ): bool {
		return in_array( $operator, [ 'like', 'notLike' ] );
	}

	/**
	 * Get the supported post types.
	 *
	 * @return array
	 */
	private function get_supported_post_types(): array {
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
}
