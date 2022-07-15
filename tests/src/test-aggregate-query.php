<?php

class TestAggregateQuery extends WP_UnitTestCase {

	protected static $category_animal_id = null;
	protected static $category_feline_id = null;
	protected static $category_canine_id = null;

	protected static $tag_black_id = null;
	protected static $tag_big_id   = null;
	protected static $tag_small_id = null;

	public static function setUpBeforeClass() {
		$cat_post_id = wp_insert_post(
			array(
				'post_title'   => 'cat',
				'post_content' => 'this is a cat',
				'post_status'  => 'publish',
			)
		);

		self::$category_animal_id = wp_create_category( 'animal' );
		self::$category_feline_id = wp_create_category( 'feline' );
		self::$category_canine_id = wp_create_category( 'canine' );
		wp_set_post_categories( $cat_post_id, array( self::$category_animal_id, self::$category_feline_id ) );
		$ids                = wp_add_post_tags( $cat_post_id, array( 'black', 'small' ) );
		self::$tag_black_id = $ids[0];
		self::$tag_small_id = $ids[1];

		$dog_post_id = wp_insert_post(
			array(
				'post_title'   => 'dog',
				'post_content' => 'this is a dog',
				'post_status'  => 'publish',
			)
		);
		wp_set_post_categories( $dog_post_id, array( self::$category_animal_id, self::$category_canine_id ) );
		$ids              = wp_add_post_tags( $dog_post_id, array( 'black', 'big' ) );
		self::$tag_big_id = $ids[1];
	}

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
				'key'   => 'black',
				'count' => 2,
			],
			[
				'key'   => 'small',
				'count' => 1,
			],
			[
				'key'   => 'big',
				'count' => 1,
			],
		];

		$expected_categories = [
			[
				'key'   => 'animal',
				'count' => 2,
			],
			[
				'key'   => 'feline',
				'count' => 1,
			],
			[
				'key'   => 'canine',
				'count' => 1,
			],
		];

		$result = do_graphql_request( $query );
		$this->assertArrayHasKey( 'data', $result, json_encode( $result ) );
		$this->assertNotEmpty( $result['data']['posts']['aggregations'] );
		$this->assertEquals( $expected_tags, $result['data']['posts']['aggregations']['tags'] );
		$this->assertEquals( $expected_categories, $result['data']['posts']['aggregations']['categories'] );
	}


	/**
	 * @dataProvider  filter_aggregations_data_provider
	 *
	 * @param string $query GraphQL query to test.
	 * @param string $expected_result What the root object of query return should be.
	 * @throws Exception
	 */
	public function test_schema_exists_for_filters( string $query, string $expected_result ) {
		$result              = graphql( array( 'query' => $query ) );
		$expected_result_arr = json_decode( $expected_result, true );
		$this->assertNotEmpty( $result );
		$expected_result_key = array_key_first( $expected_result_arr );
		$this->assertArrayHasKey( $expected_result_key, $result, json_encode( $result ) );

		if ( $expected_result_key !== 'errors' ) {
			$this->assertEquals( $expected_result_arr[ $expected_result_key ], $result[ $expected_result_key ] );
		}
	}

	public function filter_aggregations_data_provider(): array {
		return [
			'posts_valid_filter_category_name_eq'        => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									eq: "animal"
								}
							}
						}
					) {
						aggregations {
					        categories {
					            key
					            count
					        }
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "categories" : [ { "key" : "animal",  "count" : "2"}, { "key" : "canine",  "count" : "1"}, { "key" : "feline",  "count" : "1"} ]}}}}',
			],
			'posts_valid_filter_category_name_in'        => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									in: ["animal", "feline"]
								}
							}
						}
					) {
						aggregations {
					        categories {
					            key
					            count
					        }
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "categories" : [ { "key" : "animal",  "count" : "2"}, { "key" : "canine",  "count" : "1"}, { "key" : "feline",  "count" : "1"} ]}}}}',
			],
			'posts_valid_filter_category_name_notEq'     => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									notEq: "animal"
								}
							}
						}
					) {
						aggregations {
					        categories {
					            key
					            count
					        }
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "categories" : []}}}}',
			],
			'posts_valid_filter_category_name_notIn'     => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									notIn: ["feline"]
								}
							}
						}
					) {
						aggregations {
					        categories {
					            key
					            count
					        }
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "categories" : [{ "key" : "animal",  "count" : "1"}, { "key" : "canine",  "count" : "1"}]}}}}',
			],
			'posts_valid_filter_category_name_eq_and_in' => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									eq: "canine"
									in: ["animal"]
								}
							}
						}
					) {
						aggregations {
					        categories {
					            key
					            count
					        }
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "categories" : [ { "key" : "animal",  "count" : "1"}, { "key" : "canine",  "count" : "1"}]}}}}',
			],
		];
	}
}
