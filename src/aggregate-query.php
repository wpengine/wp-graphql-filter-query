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
class AggregateQuery {

	/**
	 * Constructor.
	 */
	public function __construct() {
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

		$post_types = filter_query_get_supported_post_types();

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
				$aggregate_graphql[ $field ] = [
					'type'    => array( 'list_of' => 'BucketItem' ),
					'resolve' => function ( $root, $args, $context, $info ) {
						global $wpdb;
						$taxonomy = $info->fieldName;
						if ( $info->fieldName === 'tags' ) {
							$taxonomy = 'post_tag';
						} elseif ( $info->fieldName === 'categories' ) {
							$taxonomy = 'category';
						}

						if ( empty( FilterQuery::$query_args ) ) {
							$sql = "SELECT terms.name as 'key' ,taxonomy.count as count
							FROM {$wpdb->prefix}terms AS terms
							INNER JOIN {$wpdb->prefix}term_taxonomy
							AS taxonomy
							ON (terms.term_id = taxonomy.term_id)
							WHERE taxonomy = %s AND taxonomy.count > 0;";
						} else {
							$r       = new \WP_Query( FilterQuery::$query_args );
							$sub_sql = $this->remove_sql_group_by( $r->request );
							$sub_sql = $this->remove_sql_order_by( $sub_sql );
							$sub_sql = $this->remove_sql_limit( $sub_sql );

							$sql = "SELECT wt.name as 'key', count({$wpdb->prefix}posts.ID) as count
							FROM {$wpdb->prefix}posts
							         LEFT JOIN {$wpdb->prefix}term_relationships ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}term_relationships.object_id)
							         LEFT JOIN {$wpdb->prefix}term_taxonomy wtt ON ({$wpdb->prefix}term_relationships.term_taxonomy_id = wtt.term_taxonomy_id AND wtt.taxonomy = %s )
							         LEFT JOIN {$wpdb->prefix}terms wt ON wtt.term_id = wt.term_id
							WHERE  wt.name IS NOT NULL AND {$wpdb->prefix}posts.ID = ANY ( {$sub_sql} )
							GROUP BY wt.name
							LIMIT 0, 40";
						}

						return $wpdb->get_results( $wpdb->prepare( $sql, $taxonomy ), 'ARRAY_A' ); //phpcs:disable
					},
				];
			}

			// store object name in a variable to DRY up code.
			$aggregate_for_type_name = 'AggregatesFor' . $post_type['capitalize_name'];

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
				'RootQueryTo' . $post_type['capitalize_name'] . 'Connection',
				'aggregations',
				[
					'type'    => $aggregate_for_type_name,
					'resolve' => function( $root, $args, $context, $info ) {
						return [];
					},
				]
			);
		}
	}

	/**
	 * Replace SELECT SQL clause from a WP_Query formatted SQL.
	 *
	 * @param string $sql Sql string to have select replaced.
	 * @param string $sql_select_new New sql string to replace SELECT.
	 *
	 * @return string Sql with new select clause.
	 */
	private function replace_sql_select( string $sql, string $sql_select_new ): string {
		$sql_select = trim( $this->clause_to_be_modified( $sql, 'SELECT', 'FROM' ) );

		return str_replace( $sql_select, 'SELECT    ' . $sql_select_new, $sql );
	}

	/**
	 * Replace GROUP BY SQL clause from a WP_Query formatted SQL.
	 *
	 * @param string $sql Sql string to have select replaced.
	 * @param string $sql_group_by_new New sql string to replace GROUP BY.
	 *
	 * @return string Sql with new select clause.
	 */
	private function replace_sql_group_by( string $sql, string $sql_group_by_new ): string {
		$sql_select = trim( $this->clause_to_be_modified( $sql, 'GROUP BY', 'ORDER BY' ) );

		return str_replace( $sql_select, 'GROUP BY ' . $sql_group_by_new, $sql );
	}


	/**
	 * Append to FROM SQL clause from a WP_Query formatted SQL.
	 *
	 * @param string $sql Sql string to have select replaced.
	 * @param string $wpdb_prefix WordPress DB table prefix.
	 *
	 * @return string Sql with new select clause.
	 */
	private function handle_sql_from( string $sql, string $wpdb_prefix ): string {
		$sql_from_new = "LEFT JOIN {$wpdb_prefix}term_taxonomy wtt ON ( {$wpdb_prefix}term_relationships.term_taxonomy_id = wtt.term_taxonomy_id AND wtt.taxonomy = %s ) LEFT JOIN {$wpdb_prefix}terms wt ON wtt.term_id = wt.term_id";

		$sql_from = trim( $this->clause_to_be_modified( $sql, 'FROM', 'WHERE' ) );
		if ( ! str_contains( $sql_from, "LEFT JOIN {$wpdb_prefix}term_relationships" ) ) {
			$sql_from_new = "LEFT JOIN {$wpdb_prefix}term_relationships ON ({$wpdb_prefix}posts.ID = {$wpdb_prefix}term_relationships.object_id) " . $sql_from_new;
		}
		$sql_from_new = $sql_from . ' ' . $sql_from_new;

		return str_replace( $sql_from, $sql_from_new, $sql );
	}

	/**
	 * Append to WHERE SQL clause from a WP_Query formatted SQL.
	 *
	 * @param string $sql Sql string to have select replaced.
	 * @param string $sql_where_new New sql string to append WHERE.
	 *
	 * @return string Sql with new select clause.
	 */
	private function append_sql_where( string $sql, string $sql_where_new ): string {
		$sql_where     = trim( $this->clause_to_be_modified( $sql, 'WHERE', 'GROUP BY' ) );
		$sql_where_new = $sql_where . ' ' . $sql_where_new;

		return str_replace( $sql_where, $sql_where_new, $sql );
	}

	/**
	 * Remove GROUP BY SQL clause from a WP_Query formatted SQL.
	 *
	 * @param string $sql Sql string to have order by removed.
	 *
	 * @return string
	 */
	private function remove_sql_group_by( string $sql ): string {
		$sql_order_by = $this->clause_to_be_modified( $sql, 'GROUP BY', 'ORDER BY' );

		return str_replace( $sql_order_by, '', $sql );
	}

	/**
	 * Remove LIMIT SQL clause from a WP_Query formatted SQL.
	 *
	 * @param string $sql Sql string to have order by removed.
	 *
	 * @return string
	 */
	private function remove_sql_limit( string $sql ): string {
		$sql_order_by = $this->clause_to_be_modified( $sql, 'LIMIT', "\n\t\t" );

		return str_replace( $sql_order_by . "\n\t\t", '', $sql );
	}

	/**
	 * Remove ORDER BY SQL clause from a WP_Query formatted SQL.
	 *
	 * @param string $sql Sql string to have order by removed.
	 *
	 * @return string
	 */
	private function remove_sql_order_by( string $sql ): string {
		$sql_order_by = $this->clause_to_be_modified( $sql, 'ORDER BY', 'LIMIT' );

		return str_replace( $sql_order_by, '', $sql );
	}

	/**
	 * Returns the clause to be modified at a WP_Query formatted SQL.
	 * Examples:
	 * For $sql = "SELECT id FROM wp_posts WHERE 1=1;"
	 * if :  $from = 'SELECT', $to = 'FROM', method will return 'SELECT id'
	 * if :  $from = 'SELECT', $to = 'WHERE', method will return 'SELECT id FROM wp_posts'
	 *
	 * @param string $sql Sql string to have select replaced.
	 * @param string $from Start SQL clause from a WP_Query formatted SQL.
	 * @param string $to End SQL clause from a WP_Query formatted SQL.
	 *
	 * @return string Sql with new select clause.
	 */
	private function clause_to_be_modified( string $sql, string $from = '', string $to = '' ): string {
		$sql_select_with_select_from = $this->extract_substring( $sql, $from, $to, true );

		return str_replace( $to, '', $sql_select_with_select_from );
	}

	/**
	 * Extracts a substring between two strings.
	 *
	 * @param string $subject String to searched.
	 * @param string $from From string.
	 * @param string $to To string.
	 * @param bool   $include_from_to Substring includes $from, $to or not.
	 *
	 * @return string
	 */
	private function extract_substring( string $subject, string $from = '', string $to = '', bool $include_from_to = false ): string {
		preg_match( "#{$from}(.*?){$to}#s", $subject, $matches );

		return $include_from_to ? ( $matches[0] ?? '' ) : $matches[1] ?? '';
	}


}
