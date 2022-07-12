<?php
/**
 * Filter Query extension for WP-GraphQL
 *
 * @package WPGraphqlFilterQuery
 */

namespace WPGraphQLFilterQuery;

use WPGraphQL\Data\Connection\AbstractConnectionResolver;

/**
 * Main class.
 */
class FilterQuery {

	/**
	 * Get filter query args.
	 *
	 * @var array|null
	 */
	public static $query_args = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'graphql_register_types', [ $this, 'extend_wp_graphql_fields' ] );

		add_filter( 'graphql_RootQuery_fields', [ $this, 'apply_filters_input' ], 20 );
		add_filter( 'graphql_connection_query_args', [ $this, 'apply_filter_resolver' ], 10, 2 );
	}

	/**
	 * Extend RootQuery
	 *
	 * @param array $fields All fields in RootQuery.
	 *
	 * @return array
	 */
	public function apply_filters_input( array $fields ): array {
		$post_types = filter_query_get_supported_post_types();

		foreach ( $post_types as &$post_type ) {
			if ( isset( $fields[ $post_type['plural_name'] ] ) ) {
				$args = is_array( $fields[ $post_type['plural_name'] ]['args'] ) ? $fields[ $post_type['plural_name'] ]['args'] : [];

				$args['filter'] = [
					'type'        => 'TaxonomyFilter',
					'description' => __( 'Filtering Queried Results By Taxonomy Objects', 'wp-graphql-filter-query' ),
				];

				$fields[ $post_type['plural_name'] ]['args'] = $args;
			}
		}

		return $fields;
	}

	/**
	 * Apply facet filters using graphql_connection_query_args filter hook.
	 *
	 * @param array                      $query_args Arguments that come from previous filter and will be passed to WP_Query.
	 * @param AbstractConnectionResolver $connection_resolver Connection resolver.
	 *
	 * @return array|mixed
	 */
	public function apply_filter_resolver( array $query_args, AbstractConnectionResolver $connection_resolver ): array {
		$args = $connection_resolver->getArgs();

		if ( empty( $args['filter'] ) ) {
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
		foreach ( $args['filter'] as $taxonomy_input => $data ) {
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

		self::$query_args = $query_args;

		return $query_args;
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
}
