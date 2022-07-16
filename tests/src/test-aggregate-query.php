<?php

class TestAggregateQuery extends WP_UnitTestCase {

	protected static $category_animal_id = null;
	protected static $category_feline_id = null;
	protected static $category_canine_id = null;

	protected static $tag_black_id = null;
	protected static $tag_big_id   = null;
	protected static $tag_small_id = null;

	private const CATEGORY_ANIMAL_ID_TO_BE_REPLACED = '{!#%_CATEGORY_ANIMAL_%#!}';
	private const CATEGORY_FELINE_ID_TO_BE_REPLACED = '{!#%_CATEGORY_FELINE_%#!}';
	private const CATEGORY_CANINE_ID_TO_BE_REPLACED = '{!#%_CATEGORY_CANINE_%#!}';
	private const TAG_BLACK_ID_TO_BE_REPLACED       = '{!#%_TAG_BLACK_%#!}';
	private const TAG_BIG_ID_TO_BE_REPLACED         = '{!#%_TAG_BIG_%#!}';
	private const TAG_SMALL_ID_TO_BE_REPLACED       = '{!#%_TAG_SMALL_%#!}';

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
		$result = graphql( array( 'query' => $query ) );
		$this->assertArrayHasKey( $expected, $result, json_encode( $result ) );
		$this->assertNotEmpty( $result );
	}

	/**
	 * We need this function because dataproviders are called BEFORE setUp and setUpBeforeClass.
	 * For more info please see here https://phpunit.readthedocs.io/en/9.5/writing-tests-for-phpunit.html#testing-exceptions
	 *
	 * @param string $query Query with placeholders that need to be replaced.
	 *
	 * @return string Query with info replaced.
	 */
	private function replace_ids( string $query ): string {
		$search  = array(
			self::CATEGORY_ANIMAL_ID_TO_BE_REPLACED,
			self::CATEGORY_FELINE_ID_TO_BE_REPLACED,
			self::CATEGORY_CANINE_ID_TO_BE_REPLACED,
			self::TAG_BLACK_ID_TO_BE_REPLACED,
			self::TAG_BIG_ID_TO_BE_REPLACED,
			self::TAG_SMALL_ID_TO_BE_REPLACED,
		);
		$replace = array(
			self::$category_animal_id,
			self::$category_feline_id,
			self::$category_canine_id,
			self::$tag_black_id,
			self::$tag_big_id,
			self::$tag_small_id,
		);

		return str_replace( $search, $replace, $query );
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
	public function test_schema_exists_for_aggregations_with_filters( string $query, string $expected_result ) {
		$query               = $this->replace_ids( $query );
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
			'posts_valid_filter_category_name_eq'          => [
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
			'posts_valid_filter_category_name_in'          => [
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
			'posts_valid_filter_category_name_notEq'       => [
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
			'posts_valid_filter_category_name_notIn'       => [
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
			'posts_valid_filter_category_name_eq_and_in'   => [
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
			'posts_valid_filter_category_name_notEq_and_in' => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									notEq: "canine"
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
				'{"data": { "posts": {"aggregations" : { "categories" : [ { "key" : "animal",  "count" : "1"}, { "key" : "feline",  "count" : "1"}]}}}}',
			],
			'posts_valid_filter_category_name_eq_and_notIn' => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									eq: "feline"
									notIn: ["red"]
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
				'{"data": { "posts": {"aggregations" : { "categories" : [ { "key" : "animal",  "count" : "1"}, { "key" : "feline",  "count" : "1"}]}}}}',
			],
			'posts_valid_filter_category_name_eq_and_notIn_multiple' => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									eq: "feline"
									notIn: ["red", "animal"]
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
			'posts_valid_filter_category_name_like'        => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									like: "nima"
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
				'{"data": { "posts": {"aggregations" : { "categories" :  [ { "key" : "animal",  "count" : "2"}, { "key" : "canine",  "count" : "1"}, { "key" : "feline",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_category_name_not_like'    => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									like: "nima"
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
				'{"data": { "posts": {"aggregations" : { "categories" :  [ { "key" : "animal",  "count" : "2"}, { "key" : "canine",  "count" : "1"}, { "key" : "feline",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_category_name_like_eq'     => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									like: "anim"
									eq: "canine"
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
				'{"data": { "posts": {"aggregations" : { "categories" :  [ { "key" : "animal",  "count" : "1"}, { "key" : "canine",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_category_name_like_notEq'  => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									like: "anim"
									notEq: "canine"
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
				'{"data": { "posts": {"aggregations" : { "categories" :  [ { "key" : "animal",  "count" : "1"}, { "key" : "feline",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_category_name_notLike_in_multiple' => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									notLike: "ani"
									in: ["canine", "feline"]
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
				'{"data": { "posts": {"aggregations" : { "categories" :  [] }}}}',
			],
			'posts_valid_filter_category_name_like_notIn'  => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									like: "ani"
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
				'{"data": { "posts": {"aggregations" : { "categories" :  [ { "key" : "animal",  "count" : "1"}, { "key" : "canine",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_category_id_eq'            => [
				'query {
					posts(
						filter: {
							category: {
								id: {
									eq: ' . self::CATEGORY_CANINE_ID_TO_BE_REPLACED . '
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
				'{"data": { "posts": {"aggregations" : { "categories" :  [ { "key" : "animal",  "count" : "1"}, { "key" : "canine",  "count" : "1"} ] }}}}',
			],

			// =======================================================================================================================================
			'posts_valid_filter_category_id_notEq'         => [
				'query {
					posts(
						filter: {
							category: {
								id: {
									notEq: ' . self::CATEGORY_FELINE_ID_TO_BE_REPLACED . '
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
				'{"data": { "posts": {"aggregations" : { "categories" :  [ { "key" : "animal",  "count" : "1"}, { "key" : "canine",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_category_id_in'            => [
				'query {
					posts(
						filter: {
							category: {
								id: {
									in: [' . self::CATEGORY_FELINE_ID_TO_BE_REPLACED . ']
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
				'{"data": { "posts": {"aggregations" : { "categories" :  [ { "key" : "animal",  "count" : "1"}, { "key" : "feline",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_category_id_notIn'         => [
				'query {
					posts(
						filter: {
							category: {
								id: {
									notIn: [' . self::CATEGORY_CANINE_ID_TO_BE_REPLACED . ']
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
				'{"data": { "posts": {"aggregations" : { "categories" :  [ { "key" : "animal",  "count" : "1"}, { "key" : "feline",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_category_name_eq_id_notEq' => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									eq: "animal"
								},
								id: {
									notEq: ' . self::CATEGORY_FELINE_ID_TO_BE_REPLACED . '
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
				'{"data": { "posts": {"aggregations" : { "categories" :  [ { "key" : "animal",  "count" : "1"}, { "key" : "canine",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_category_name_eq_id_Eq'    => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									eq: "animal"
								},
								id: {
									eq: ' . self::CATEGORY_FELINE_ID_TO_BE_REPLACED . '
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
				'{"data": { "posts": {"aggregations" : { "categories" :  [ { "key" : "animal",  "count" : "1"}, { "key" : "feline",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_name_eq'               => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									eq: "black"
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "big",  "count" : "1"}, { "key" : "black",  "count" : "2"}, { "key" : "small",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_name_in'               => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									in: ["black", "small"]
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "big",  "count" : "1"}, { "key" : "black",  "count" : "2"}, { "key" : "small",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_name_notEq'            => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									notEq: "black"
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [] }}}}',
			],
			'posts_valid_filter_tag_name_notIn'            => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									notIn: ["small"]
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "big",  "count" : "1"}, { "key" : "black",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_name_eq_and_in'        => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									eq: "big",
									in: ["black"]
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "big",  "count" : "1"}, { "key" : "black",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_name_notEq_and_in'     => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									notEq: "big",
									in: ["black"]
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "black",  "count" : "1"}, { "key" : "small",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_name_neq_and_notIn'    => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									eq: "small",
									notIn: ["red"]
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "black",  "count" : "1"}, { "key" : "small",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_name_neq_and_notIn_multiple' => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									eq: "small",
									notIn: ["red", "black"]
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [] }}}}',
			],
			'posts_valid_filter_tag_name_like'             => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									like: "bl",
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "big",  "count" : "1"}, { "key" : "black",  "count" : "2"}, { "key" : "small",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_name_notLike'          => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									notLike: "sm",
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "big",  "count" : "1"}, { "key" : "black",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_name_like_eq'          => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									like: "bl",
									eq: "big"
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "big",  "count" : "1"}, { "key" : "black",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_name_like_notEq'       => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									like: "lac",
									notEq: "small"
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "big",  "count" : "1"}, { "key" : "black",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_name_like_in_multiple' => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									like: "bl",
									in: ["big", "small"]
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "big",  "count" : "1"}, { "key" : "black",  "count" : "2"}, { "key" : "small",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_name_notLike_in_multiple' => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									notLike: "bl",
									in: ["big", "small"]
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [] }}}}',
			],
			'posts_valid_filter_tag_name_like_notIn'       => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									like: "bl",
									notIn: ["small"]
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "big",  "count" : "1"}, { "key" : "black",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_id_eq'                 => [
				'query {
					posts(
						filter: {
							tag: {
								id: {
									eq: ' . self::TAG_BIG_ID_TO_BE_REPLACED . '
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "big",  "count" : "1"}, { "key" : "black",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_id_notEq'              => [
				'query {
					posts(
						filter: {
							tag: {
								id: {
									notEq: ' . self::TAG_SMALL_ID_TO_BE_REPLACED . '
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "big",  "count" : "1"}, { "key" : "black",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_id_in'                 => [
				'query {
					posts(
						filter: {
							tag: {
								id: {
									in: [' . self::TAG_SMALL_ID_TO_BE_REPLACED . ']
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "black",  "count" : "1"}, { "key" : "small",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_id_notIn'              => [
				'query {
					posts(
						filter: {
							tag: {
								id: {
									notIn: [' . self::TAG_BIG_ID_TO_BE_REPLACED . ']
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "black",  "count" : "1"}, { "key" : "small",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_name_eq_id_notEq'      => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									eq: "black"
								},
								id: {
									notEq: ' . self::TAG_SMALL_ID_TO_BE_REPLACED . '
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "big",  "count" : "1"}, { "key" : "black",  "count" : "1"} ] }}}}',
			],
			'posts_valid_filter_tag_name_eq_id_Eq'         => [
				'query {
					posts(
						filter: {
							tag: {
								name: {
									eq: "black"
								},
								id: {
									eq: ' . self::TAG_SMALL_ID_TO_BE_REPLACED . '
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [ { "key" : "black",  "count" : "1"}, { "key" : "small",  "count" : "1"} ] }}}}',
			],
			'posts_accept_valid_tax_filter_args'           => [
				'query {
					posts(
						filter: {
							category: {
								id: {
									eq: 10
								},
								name: {
									eq: "foo"
								}
							},
							tag: {
								name: {
									in: ["foo", "bar"],
									like: "tst"
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "tags" :  [] }}}}',
			],
			'pages_accept_valid_tax_filter_args'           => [
				'query {
					pages(
						filter: {
							category: {
								id: {
									eq: 10
								},
								name: {
									eq: "foo"
								}
							},
							tag: {
								name: {
									in: ["foo", "bar"],
									like: "tst"
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							},
							categories {
								key
								count
							}
						}
					}
				}',
				'{"data": { "pages": {"aggregations" : { "tags" :  [], "categories" :  [] }}}}',
			],
			'zombies_accept_valid_tax_filter_args'         => [
				'query {
					zombies(
						filter: {
							category: {
								id: {
									eq: 10
								},
								name: {
									eq: "foo"
								}
							},
							tag: {
								name: {
									in: ["foo", "bar"],
									like: "tst"
								}
							}
						}
					) {
						aggregations {
							tags {
								key
								count
							},
							categories {
								key
								count
							}
						}
					}
				}',
				'{"data": { "zombies": {"aggregations" : { "tags" :  [],  "categories" :  [] }}}}',
			],
		];
	}
}
