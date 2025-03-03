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
	protected static $query_args = null;

	/**
	 * Get filter query depth.
	 *
	 * @var int
	 */
	private $max_nesting_depth;

	/**
	 *
	 * Add actions and filter.
	 *
	 * @return void
	 */
	public function add_hooks(): void {
		add_action( 'graphql_register_types', [ $this, 'extend_wp_graphql_fields' ] );

		add_filter( 'graphql_RootQuery_fields', [ $this, 'apply_filters_input' ], 30 );
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

	/**
	 * $operator_mappings.
	 *
	 * @var array
	 */
	public $operator_mappings = array(
		'in'      => 'IN',
		'notIn'   => 'NOT IN',
		'eq'      => 'IN',
		'notEq'   => 'NOT IN',
		'like'    => 'IN',
		'notLike' => 'NOT IN',
	);

	/**
	 * $taxonomy_keys.
	 *
	 * @var array
	 */
	public $taxonomy_keys = [ 'tag', 'category' ];

	/**
	 * $relation_keys.
	 *
	 * @var array
	 */
	public $relation_keys = [ 'and', 'or' ];

	/**
	 * Check if operator is like or notLike
	 *
	 * @param array $filter_obj A Filter object, for wpQuery access, to build upon within each recursive call.
	 * @param int   $depth A depth-counter to track recusrive call depth.
	 *
	 * @throws FilterException Throws max nested filter depth exception, caught by wpgraphql response.
	 * @throws FilterException Throws and/or not allowed as siblings exception, caught by wpgraphql response.
	 * @throws FilterException Throws empty relation (and/or) exception, caught by wpgraphql response.
	 * @return array
	 */
	private function resolve_taxonomy( array $filter_obj, int $depth ): array {
		if ( $depth > $this->max_nesting_depth ) {
			throw new FilterException( 'The Filter\'s relation allowable depth nesting has been exceeded. Please reduce to allowable (' . $this->max_nesting_depth . ') depth to proceed' );
		} elseif ( array_key_exists( 'and', $filter_obj ) && array_key_exists( 'or', $filter_obj ) ) {
			throw new FilterException( 'A Filter can only accept one of an \'and\' or \'or\' child relation as an immediate child.' );
		}

		$temp_query = [];
		foreach ( $filter_obj as $root_obj_key => $value ) {
			if ( in_array( $root_obj_key, $this->taxonomy_keys, true ) ) {
				$attribute_array = $value;
				foreach ( $attribute_array as $field_key => $field_kvp ) {
					foreach ( $field_kvp as $operator => $terms ) {
						$mapped_operator  = $this->operator_mappings[ $operator ] ?? 'IN';
						$is_like_operator = $this->is_like_operator( $operator );
						$taxonomy         = $root_obj_key === 'tag' ? 'post_tag' : 'category';

						$terms = ! $is_like_operator ? $terms : get_terms(
							[
								'taxonomy'   => $taxonomy,
								'fields'     => 'ids',
								'name__like' => esc_attr( $terms ),
							]
						);

						$result = [
							'taxonomy' => $taxonomy,
							'field'    => ( $field_key === 'id' ) || $is_like_operator ? 'term_id' : 'name',
							'terms'    => $terms,
							'operator' => $mapped_operator,
						];

						$temp_query[] = $result;
					}
				}
			} elseif ( in_array( $root_obj_key, $this->relation_keys, true ) ) {
				$nested_obj_array = $value;
				$wp_query_array   = [];

				if ( count( $nested_obj_array ) === 0 ) {
					throw new FilterException( 'The Filter relation array specified has no children. Please remove the relation key or add one or more appropriate objects to proceed.' );
				}
				foreach ( $nested_obj_array as $nested_obj_index => $nested_obj_value ) {
					$wp_query_array[ $nested_obj_index ]             = $this->resolve_taxonomy( $nested_obj_value, ++$depth );
					$wp_query_array[ $nested_obj_index ]['relation'] = 'AND';
				}
				$wp_query_array['relation'] = strtoupper( $root_obj_key );
				$temp_query[]               = $wp_query_array;
			}
		}
		return $temp_query;
	}

	/**
	 * Apply facet filters using graphql_connection_query_args filter hook.
	 *
	 * @param array                      $query_args Arguments that come from previous filter and will be passed to WP_Query.
	 * @param AbstractConnectionResolver $connection_resolver Connection resolver.
	 *
	 * @return array
	 */
	public function apply_recursive_filter_resolver( array $query_args, AbstractConnectionResolver $connection_resolver ): array {
		$args                    = $connection_resolver->getArgs();
		$this->max_nesting_depth = $this->get_query_depth();

		if ( empty( $args['filter'] ) ) {
			return $query_args;
		}

		$filter_args_root = $args['filter'];

		$query_args['tax_query'][] = $this->resolve_taxonomy( $filter_args_root, 0, [] );

		self::$query_args = $query_args;
		return $query_args;
	}
	/**
	 * Return query args.
	 *
	 * @return array|null
	 */
	public static function get_query_args(): ?array {
		return self::$query_args;
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
					'and'      => [
						'type'        => [ 'list_of' => 'TaxonomyFilter' ],
						'description' => __( '\'AND\' Array of Taxonomy Objects Allowable For Filtering', 'wp-graphql-filter-query' ),
					],
					'or'       => [
						'type'        => [ 'list_of' => 'TaxonomyFilter' ],
						'description' => __( '\'OR\' Array of Taxonomy Objects Allowable For Filterin', 'wp-graphql-filter-query' ),
					],
				],
			]
		);
	}

	/**
	 * Check if custom wpgraphql depth is set, and, if so, what it is - else 10
	 *
	 * @return int
	 */
	private function get_query_depth(): int {
		$opt = get_option( 'graphql_general_settings' );
		if ( ! empty( $opt ) && $opt !== false && $opt['query_depth_enabled'] === 'on' ) {
			return $opt['query_depth_max'];
		}

		return 10;
	}

	/**
	 * Check if operator like or notLike
	 *
	 * @param string $operator Received operator - not mapped.
	 *
	 * @return bool
	 */
	private function is_like_operator( string $operator ): bool {
		return in_array( $operator, [ 'like', 'notLike' ], true );
	}
}
