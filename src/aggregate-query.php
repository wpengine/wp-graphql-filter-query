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
					'resolve' => function( $root, $args, $context, $info ) {
						$taxonomy = $info->fieldName;

						$a = FilterQuery::$query_args;
						if ( $info->fieldName === 'tags' ) {
							$taxonomy = 'post_tag';
						} else if ( $info->fieldName === 'categories' ) {
							$taxonomy = 'category';
						}

						$r = new \WP_Query($a);
						$new_sql = $this->replace_sql_select( $r->request, 'wt.name as \'key\', count(wp_posts.ID) as count' );
						$new_sql = $this->append_sql_from( $new_sql, 'LEFT JOIN wp_term_taxonomy wtt on wp_term_relationships.term_taxonomy_id = wtt.term_taxonomy_id LEFT JOIN wp_terms wt on wtt.term_id = wt.term_id' );
						$new_sql = $this->replace_sql_group_by( $new_sql, 'wt.name' );
						$new_sql = $this->remove_sql_order_by($new_sql);

						global $wpdb;

						return $wpdb->get_results( $new_sql, 'ARRAY_A' );
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
	 * @param string $sql Sql string to have select replaced.
	 * @param string $sql_select_new
	 *
	 * @return string Sql with new select clause.
	 */
	private function replace_sql_select( string $sql, string $sql_select_new ): string {
		$sql_select = trim( $this->clause_to_be_modified( $sql, 'SELECT', 'FROM' ) );

		return str_replace( $sql_select, 'SELECT    ' . $sql_select_new, $sql );
	}

	/**
	 * @param string $sql Sql string to have select replaced.
	 * @param string $sql_group_by_new
	 *
	 * @return string Sql with new select clause.
	 */
	private function replace_sql_group_by( string $sql, string $sql_group_by_new ): string {
		$sql_select = trim( $this->clause_to_be_modified( $sql, 'GROUP BY', 'ORDER BY' ) );

		return str_replace( $sql_select, 'GROUP BY ' . $sql_group_by_new, $sql );
	}

	/**
	 * @param string $sql Sql string to have select replaced.
	 * @param string $sql_from_new
	 *
	 * @return string Sql with new select clause.
	 */
	private function append_sql_from( string $sql, string $sql_from_new ): string {
		$sql_select = trim( $this->clause_to_be_modified( $sql, 'FROM', 'WHERE' ) );
		$sql_from_new = $sql_select . ' ' . $sql_from_new;

		return str_replace( $sql_select, $sql_from_new, $sql );
	}

	/**
	 * @param string $sql Sql string to have order by remnoved
	 *
	 * @return string
	 */
	private function remove_sql_order_by( string $sql ): string {
		$sql_order_by = $this->clause_to_be_modified( $sql, 'ORDER', 'LIMIT' );

		return str_replace( $sql_order_by, '', $sql );
	}

	/**
	 * @param string $sql Sql string to have select replaced.
	 * @param string $from
	 * @param string $to
	 *
	 * @return string Sql with new select clause.
	 */
	private function clause_to_be_modified( string $sql, string $from = '', string $to = ''): string {
		$sql_select_with_select_from = $this->extract_substring( $sql, $from, $to, true );

		return str_replace($to, '', $sql_select_with_select_from);
	}

	/**
	 * @param string $subject
	 * @param string $from
	 * @param string $to
	 *
	 * @return string
	 */
	private function extract_substring( string $subject, string $from = '', string $to = '', bool $include_from_to = false): string {
		preg_match("#{$from}(.*?){$to}#s",$subject,$matches);

		return $include_from_to ? ( $matches[0] ?? '' ) : $matches[1] ?? '';
	}


}
