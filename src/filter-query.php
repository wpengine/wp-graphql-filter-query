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
			'TaxonomyFilterWithAndOrWrapper',
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
					'and' => [
						'type'        => [ 'list_of' => 'TaxonomyFilterWithAndOrWrapper' ],
						'description' => __( '\'AND\' Array of Taxonomy Objects Allowable For Filtering', 'wp-graphql-filter-query' ),
					],
					'or' => [
						'type'        => [ 'list_of' => 'TaxonomyFilterWithAndOrWrapper' ],
						'description' => __( '\'OR\' Array of Taxonomy Objects Allowable For Filterin', 'wp-graphql-filter-query' ),
					],
				],
			]
		);

		$taxonomy_filter_supported_types = filter_query_get_supported_post_types();

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
		$nestedAndOrValue  = NULL;

		// handle the root and/or arrays, if present
		if(array_key_exists('and', $args['where']['filter']) && array_key_exists('or', $args['where']['filter'])){
			// todo: we can't both and + or so throw err!!!!!!
		} else {
			$filter_args_root = $args['where']['filter'];
			foreach ($filter_args_root as $tax_or_nested_operation => $tax_value_or_op_array ) {
				if($this->is_nested_operation($tax_or_nested_operation)){
					foreach ( $tax_value_or_op_array as $tax_array_index => $nested_tax_item ) {
						foreach ( $nested_tax_item as $nested_tax_key => $nested_tax ) {
							foreach ( $nested_tax as $field_key => $field_kvp ) {
								foreach ( $field_kvp as $operator => $terms ) {
									// todo: abstract this to a new fn - START
									// fn todo: mapFilter($operator_mappings, $operator, $tax_or_nested_operation, $terms, $field_key, query_args)
									$mapped_operator  = $operator_mappings[ $operator ] ?? 'IN';
									$is_like_operator = $this->is_like_operator( $operator );
									$taxonomy         = $tax_or_nested_operation === 'tag' ? 'post_tag' : 'category';
			
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
										'field'    => ( $field_key === 'id' || $is_like_operator ) ? 'term_id' : 'name',
									);
			
									$query_args['tax_query'][] = $result;
									// todo: abstract this to a new fn - END
									$c++;
									$nestedAndOrValue = strtoupper($tax_or_nested_operation);
								}
							}
						}
					}
				} else {
					foreach ( $tax_value_or_op_array as $field_key => $field_kvp ) {
						foreach ( $field_kvp as $operator => $terms ) {
							$mapped_operator  = $operator_mappings[ $operator ] ?? 'IN';
							$is_like_operator = $this->is_like_operator( $operator );
							$taxonomy         = $tax_or_nested_operation === 'tag' ? 'post_tag' : 'category';

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
								'field'    => ( $field_key === 'id' || $is_like_operator ) ? 'term_id' : 'name',
							);

							$query_args['tax_query'][] = $result;
							$c++;
						}
					}
				}
			}
		}

		// cascading and/or takes precedence for children until resolved nested and/ors conversation
		if ( $nestedAndOrValue != NULL ) {
			$query_args['tax_query']['relation'] = $nestedAndOrValue;
		}
		else if($c > 1 ) {
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
		return in_array( $operator, [ 'like', 'notLike' ], true );
	}

	/**
	 * Checks if filter is a taxonomy or a nested and/or of taxonomies (true if the latter)
	 *
	 * @param string $key identifying key of each child arg of filter
	 *
	 * @return bool
	 */
	public function is_nested_operation( string $key ): bool {
		return  in_array( $key, [ 'and', 'or' ], true );
	}
}
