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
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'graphql_register_types', [ $this, 'extend_wp_graphql_fields' ] );

		add_filter( 'graphql_RootQuery_fields', [ $this, 'apply_filters_input' ], 20 );
		add_filter( 'graphql_connection_query_args', [ $this, 'apply_recursive_filter_resolver' ], 10, 2 );
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

	public $operator_mappings = array(
		'in'      => 'IN',
		'notIn'   => 'NOT IN',
		'eq'      => 'IN',
		'notEq'   => 'NOT IN',
		'like'    => 'IN',
		'notLike' => 'NOT IN',
	);

	public $TAXONOMY_KEYS = ['tag','category'];
	public $NESTING_RELATION_KEYS = ['and','or'];
	public $MAX_FILTER_DEPTH = 10;
	public $wpQuery = 10;
	public $filterObjCounter = 0;

	/**
	 * Check if operator is like or notLike
	 *
	 * @param array $filterObj
	 * @param int $depth
	 * @param array $workingWpQuery
	 *
	 * @return bool
	 */
	private function resolveTaxonomy(array $filterObj, int $depth): array {
		$tempQuery = [];
		foreach ($filterObj as $rootObjKey => $value) {
			$this->filterObjCounter++;
			if (in_array($rootObjKey, $this->TAXONOMY_KEYS)) {
				$attributeArray = $value;
				foreach ( $attributeArray as $field_key => $field_kvp ) {
					foreach ( $field_kvp as $operator => $terms ) {
						$mapped_operator          = $this->operator_mappings[ $operator ] ?? 'IN';
						// $is_like_operator         = $this->is_like_operator( $operator );
						$taxonomy                 = $rootObjKey === 'tag' ? 'post_tag' : 'category';

						// $terms = ! $is_like_operator ? $terms  : get_terms(// called when 'like' is passed as operator... This seems buggy, as 'in' is the one with multiple value options, not 'like'!!!!!!
						// 	array(
						// 		'taxonomy'   => $taxonomy,
						// 		'fields'     => 'ids',
						// 		'name__like' => esc_attr( $terms ),
						// 	)
						// );

						$result = array(
							'taxonomy' => $taxonomy,
							'field'    => ( $field_key === 'id' ) ? 'term_id' : 'name',// commented out '|| is_like_operator' second part of ternary check
							'terms'    => $terms,
							'operator' => $mapped_operator,
						);

						$tempQuery[] = $result;
					}
				}
			}
			else if(in_array($rootObjKey, $this->NESTING_RELATION_KEYS) && $depth < ($this->MAX_FILTER_DEPTH-1)){
				$nestedObjArray = $value;
				$wpQueryArray = [];
				
				foreach($nestedObjArray as $nestedObjIndex => $nestedObjValue){
					$wpQueryArray[$nestedObjIndex] = $this->resolveTaxonomy($nestedObjValue, ++$depth);
					$wpQueryArray[$nestedObjIndex]['relation'] = 'AND';
				}
				$wpQueryArray['relation'] = strtoupper($rootObjKey);
				$tempQuery[] = $wpQueryArray;
			}
		}
		return $tempQuery;
	}

	/**
	 * Apply facet filters using graphql_connection_query_args filter hook.
	 *
	 * @param array                      $query_args Arguments that come from previous filter and will be passed to WP_Query.
	 * @param AbstractConnectionResolver $connection_resolver Connection resolver.
	 *
	 * @return array|mixed
	 */
	public function apply_recursive_filter_resolver( array $query_args, AbstractConnectionResolver $connection_resolver ): array {
		$args              = $connection_resolver->getArgs();
		$filter_args_root  = $args['filter'];

		if ( empty( $filter_args_root ) ) {
			return $query_args;
		} else if( array_key_exists('and', $filter_args_root) && array_key_exists('or', $filter_args_root)){
			// todo: we can't both and + or so throw err!!!!!! NOT getting here now... 
			//$nestedAndOrValue  = NULL;
			return $query_args;
		}

		$query_args['tax_query'][] = $this->resolveTaxonomy($filter_args_root, 0, [] );
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
					'and' => [
						'type'        => [ 'list_of' => 'TaxonomyFilter' ],
						'description' => __( '\'AND\' Array of Taxonomy Objects Allowable For Filtering', 'wp-graphql-filter-query' ),
					],
					'or' => [
						'type'        => [ 'list_of' => 'TaxonomyFilter' ],
						'description' => __( '\'OR\' Array of Taxonomy Objects Allowable For Filterin', 'wp-graphql-filter-query' ),
					],
				],
			]
		);
	}

	// /**
	//  * Check if operator is in or notIn
	//  *
	//  * @param string $operator Received operator - not mapped.
	//  *
	//  * @return bool
	//  */
	// private function is_like_operator( string $operator ): bool {
	// 	return in_array( $operator, [ 'like', 'notLike' ], true );
	// }

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
