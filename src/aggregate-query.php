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
		$this->define_public_hooks();
	}

	/**
	 * Define the hooks to register.
	 *
	 * @return void
	 */
	public function define_public_hooks() {
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

						if ( $info->fieldName === 'tags' ) {
							$taxonomy = 'post_tag';
						}

						if ( $info->fieldName === 'categories' ) {
							$taxonomy = 'category';
						}
						global $wpdb;

						// TODO Find a suitable implementation for caching here.
						//phpcs:disable
						$results = $wpdb->get_results(
							$wpdb->prepare(
								"SELECT terms.name,taxonomy.count
							FROM {$wpdb->prefix}terms AS terms
							INNER JOIN {$wpdb->prefix}term_taxonomy
							AS taxonomy
							ON (terms.term_id = taxonomy.term_id)
							WHERE taxonomy = %s AND taxonomy.count > 0;",
								$taxonomy
							)
						);

						$returns = [];

						if ( $results ) {
							foreach ( $results as $result ) {
								if ( $results ) {
									$returns[] = [
										'key'   => $result->name,
										'count' => $result->count,
									];
								}
							}
						}
						return $returns;
					},
				];
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
						return [];
					},
				]
			);
		}
	}
}
