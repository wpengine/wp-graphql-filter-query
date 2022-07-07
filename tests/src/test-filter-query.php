<?php

class TestFilterQuery extends WP_UnitTestCase {

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

	/**
	 * @dataProvider  filters_data_provider
	 *
	 * @param string $query GraphQL query to test.
	 * @param string $expected_result What the root object of query return should be.
	 * @throws Exception
	 */
	public function test_schema_exists_for_filters( string $query, string $expected_result ) {
		$query               = $this->replace_ids( $query );
		$result              = graphql( array( 'query' => $query ) );
		$expected_result_arr = json_decode( $expected_result, true );
		$this->assertNotEmpty( $result );
		$expected_result_key = array_key_first( $expected_result_arr );
		$this->assertArrayHasKey( $expected_result_key, $result, json_encode( $result ) );

		if ( $expected_result_key !== 'errors' ) {

			/**
			 * Seems to be a bug with duplicate values in graphql().
			 * Till its addressed I will just use remove_dups function
			 */
			$this->assertEquals( $expected_result_arr[ $expected_result_key ], $result[ $expected_result_key ] );
		}
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

	public function filters_data_provider(): array {
		return array(
			'posts_valid_filter_category_name_eq'          => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										eq: "animal"
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}, {"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
			),

			'posts_valid_filter_category_name_in'          => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										in: ["animal", "feline"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}, {"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
			),
			'posts_valid_filter_category_name_notEq'       => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										notEq: "animal"
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : []}}}',
			),
			'posts_valid_filter_category_name_notIn'       => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										notIn: ["feline"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
			),
			'posts_valid_filter_category_name_eq_and_in'   => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										eq: "canine",
										in: ["animal"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
			),
			'posts_valid_filter_category_name_notEq_and_in' => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										notEq: "canine",
										in: ["animal"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"} ]}}}',
			),
			'posts_valid_filter_category_name_neq_and_notIn' => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										eq: "feline",
										notIn: ["red"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"} ]}}}',
			),
			'posts_valid_filter_category_name_neq_and_notIn_multiple' => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										eq: "feline",
										notIn: ["car", "animal"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : []}}}',
			),
			'posts_valid_filter_category_name_like'        => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										like: "nima",
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}, {"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
			),
			'posts_valid_filter_category_name_notLike'     => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										notLike: "fel",
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
			),
			'posts_valid_filter_category_name_like_eq'     => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										like: "anim",
										eq: "canine"
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
			),
			'posts_valid_filter_category_name_like_notEq'  => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										like: "nim",
										notEq: "feline"
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
			),
			'posts_valid_filter_category_name_like_in_multiple' => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										like: "ani",
										in: ["canine", "feline"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}, {"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
			),
			'posts_valid_filter_category_name_notLike_in_multiple' => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										notLike: "ani",
										in: ["canine", "feline"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : []}}}',
			),
			'posts_valid_filter_category_name_like_notIn'  => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									name: {
										like: "ani",
										notIn: ["feline"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
			),
			'posts_valid_filter_category_id_eq'            => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									id: {
										eq: ' . self::CATEGORY_CANINE_ID_TO_BE_REPLACED . '
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
			),
			'posts_valid_filter_category_id_notEq'         => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									id: {
										notEq: ' . self::CATEGORY_FELINE_ID_TO_BE_REPLACED . '
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
			),
			'posts_valid_filter_category_id_in'            => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									id: {
										in: [' . self::CATEGORY_FELINE_ID_TO_BE_REPLACED . ']
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
			),
			'posts_valid_filter_category_id_notIn'         => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									id: {
										notIn: [' . self::CATEGORY_CANINE_ID_TO_BE_REPLACED . ']
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
			),
			'posts_valid_filter_category_name_eq_id_notEq' => array(
				'query {
					posts(
						where: {
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
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
			),
			'posts_valid_filter_category_name_eq_id_Eq'    => array(
				'query {
					posts(
						where: {
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
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
			),
			'posts_valid_filter_tag_name_eq'               => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										eq: "black"
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}, {"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
			),
			'posts_valid_filter_tag_name_in'               => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										in: ["black", "small"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}, {"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
			),
			'posts_valid_filter_tag_name_notEq'            => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										notEq: "black"
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : []}}}',
			),
			'posts_valid_filter_tag_name_notIn'            => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										notIn: ["small"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
			),
			'posts_valid_filter_tag_name_eq_and_in'        => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										eq: "big",
										in: ["black"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
			),
			'posts_valid_filter_tag_name_notEq_and_in'     => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										notEq: "big",
										in: ["black"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"} ]}}}',
			),
			'posts_valid_filter_tag_name_neq_and_notIn'    => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										eq: "small",
										notIn: ["red"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"} ]}}}',
			),
			'posts_valid_filter_tag_name_neq_and_notIn_multiple' => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										eq: "small",
										notIn: ["red", "black"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : []}}}',
			),
			'posts_valid_filter_tag_name_like'             => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										like: "bl",
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}, {"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
			),
			'posts_valid_filter_tag_name_notLike'          => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										notLike: "sm",
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
			),
			'posts_valid_filter_tag_name_like_eq'          => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										like: "bl",
										eq: "big"
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
			),
			'posts_valid_filter_tag_name_like_notEq'       => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										like: "lac",
										notEq: "small"
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
			),
			'posts_valid_filter_tag_name_like_in_multiple' => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										like: "bl",
										in: ["big", "small"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}, {"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
			),
			'posts_valid_filter_tag_name_notLike_in_multiple' => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										notLike: "bl",
										in: ["big", "small"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : []}}}',
			),
			'posts_valid_filter_tag_name_like_notIn'       => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									name: {
										like: "bl",
										notIn: ["small"]
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
			),
			'posts_valid_filter_tag_id_eq'                 => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									id: {
										eq: ' . self::TAG_BIG_ID_TO_BE_REPLACED . '
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
			),
			'posts_valid_filter_tag_id_notEq'              => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									id: {
										notEq: ' . self::TAG_SMALL_ID_TO_BE_REPLACED . '
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
			),
			'posts_valid_filter_tag_id_in'                 => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									id: {
										in: [' . self::TAG_SMALL_ID_TO_BE_REPLACED . ']
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
			),
			'posts_valid_filter_tag_id_notIn'              => array(
				'query {
					posts(
						where: {
							filter: {
								tag: {
									id: {
										notIn: [' . self::TAG_BIG_ID_TO_BE_REPLACED . ']
									}
								}
							}
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
			),
			'posts_valid_filter_tag_name_eq_id_notEq'      => array(
				'query {
					posts(
						where: {
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
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
			),
			'posts_valid_filter_tag_name_eq_id_Eq'         => array(
				'query {
					posts(
						where: {
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
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
			),
			'posts_accept_valid_tax_filter_args'           => array(
				'query {
					posts(
						where: {
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
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : []}}}',
			),
			'pages_accept_valid_tax_filter_args'           => array(
				'query {
					pages(
						where: {
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
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "pages": {"nodes" : []}}}',
			),
			'zombies_accept_valid_tax_filter_args'         => array(
				'query {
					zombies(
						where: {
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
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "zombies": {"nodes" : []}}}',
			),
			'posts_reject_invalid_tax_filter_args'         => array(
				'query {
					posts(
						where: {
							filter: {
								category: {
									id: {
										eq: "10"
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
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"errors": null}',
			),
			'pages_reject_invalid_tax_filter_args'         => array(
				'query  {
					pages(
						where: {
							filter: {
								category: {
									id: {
										eq: "10"
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
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"errors": null}',
			),
			'non_filterable_types_reject_all_filter_args'  => array(
				'query  {
					tags(
						where: {
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
						}
					) {
						nodes {
							slug
						}
					}
				}',
				'{"errors": null}',
			),
		);
	}
}
