<?php

class TestAggregateQuery extends WP_UnitTestCase {
	protected function setUp(): void {
		register_post_type(
			'zombie',
			array(
				'labels'              => array(
					'name' => 'Zombies',
				),
				'public'              => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => false,
				/** WP GRAPHQL */
				'show_in_graphql'     => true,
				'hierarchical'        => true,
				'graphql_single_name' => 'zombie',
				'graphql_plural_name' => 'zombies',
			)
		);
	}

	public function data_for_schema_exists_for_aggregations(): array {
		return [
			'posts_have_aggregations'   => [
				'query {
					posts {
						nodes {
							title
						}
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'data',
			],
			'pages_have_aggregations'   => [
				'query {
					pages {
						nodes {
							title
						}
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'data',
			],
			'zombies_have_aggregations' => [
				'query {
					zombies {
						nodes {
							title
						}
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'data',
			],
			'non_existing_type_should_not_have_aggregations' => [
				'query {
					doesNotExist {
						nodes {
							title
						}
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'errors',
			],
		];
	}

	/**
	 *
	 * @dataProvider data_for_schema_exists_for_aggregations
	 * @return void
	 * @throws Exception
	 */
	public function test_schema_exists_for_aggregations( $query, $expected ) {
		$result = do_graphql_request( $query );
		$this->assertArrayHasKey( $expected, $result, json_encode( $result ) );
		$this->assertNotEmpty( $result );
	}


	public function test_get_tags_aggregations() {
		$post_id = wp_insert_post(
			[
				'post_title'   => 'test',
				'post_content' => 'test',
				'post_status'  => 'publish',
			]
		);

		wp_set_object_terms( $post_id, [ 'apple', 'wow' ], 'post_tag', true );

		$query = 'query {
			posts {
				nodes {
					title
				}
				aggregations {
					tags {
						key
						count
					}
					categories {
						key
						count
					}
				}
			}
		}';

		$expected_tags = [
			[
				'key'   => 'apple',
				'count' => 1,
			],
			[
				'key'   => 'wow',
				'count' => 1,
			],
		];

		$expected_categories = [
			[
				'key'   => 'Uncategorized',
				'count' => 1,
			],
		];

		$result = do_graphql_request( $query );
		$this->assertArrayHasKey( 'data', $result, json_encode( $result ) );
		$this->assertNotEmpty( $result['data']['posts']['aggregations'] );
		$this->assertEquals( $expected_tags, $result['data']['posts']['aggregations']['tags'] );
		$this->assertEquals( $expected_categories, $result['data']['posts']['aggregations']['categories'] );
	}
}