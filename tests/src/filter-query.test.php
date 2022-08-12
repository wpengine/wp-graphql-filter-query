<?php

class FilterQueryTest extends WP_UnitTestCase {

	protected static $category_animal_id = null;
	protected static $category_feline_id = null;
	protected static $category_canine_id = null;

	protected static $tag_black_id = null;
	protected static $tag_big_id   = null;
	protected static $tag_small_id = null;
	protected static $mock_opt     = array(
		'graphql_endpoint'                     => 'graphql',
		'restrict_endpoint_to_logged_in_users' => 'off',
		'batch_queries_enabled'                => 'on',
		'batch_limit'                          => '5',
		'query_depth_enabled'                  => 'off',
		'query_depth_max'                      => '10',
		'graphiql_enabled'                     => 'on',
		'show_graphiql_link_in_admin_bar'      => 'on',
		'delete_data_on_deactivate'            => 'on',
		'debug_mode_enabled'                   => 'off',
		'tracing_enabled'                      => 'off',
		'tracing_user_role'                    => 'administrator',
		'query_logs_enabled'                   => 'off',
		'query_log_user_role'                  => 'administrator',
		'public_introspection_enabled'         => 'off',
	);

	private const CATEGORY_ANIMAL_ID_TO_BE_REPLACED = '{!#%_CATEGORY_ANIMAL_%#!}';
	private const CATEGORY_FELINE_ID_TO_BE_REPLACED = '{!#%_CATEGORY_FELINE_%#!}';
	private const CATEGORY_CANINE_ID_TO_BE_REPLACED = '{!#%_CATEGORY_CANINE_%#!}';
	private const TAG_BLACK_ID_TO_BE_REPLACED       = '{!#%_TAG_BLACK_%#!}';
	private const TAG_BIG_ID_TO_BE_REPLACED         = '{!#%_TAG_BIG_%#!}';
	private const TAG_SMALL_ID_TO_BE_REPLACED       = '{!#%_TAG_SMALL_%#!}';
	private const QUERY_DEPTH_DEFAULT               = 10;
	private const QUERY_DEPTH_CUSTOM                = 11;

	public static function setUpBeforeClass(): void {
		add_option( 'graphql_general_settings', self::$mock_opt );
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
	 * @dataProvider  filter_errors_data_provider
	 *
	 * @param string $query GraphQL query to test.
	 * @param string $expected_error What the error object of query return should be.
	 * @throws Exception
	 */
	public function test_schema_errors_for_filters( string $query, string $expected_error ) {
		$this->update_wpgraphql_query_depth( 'on', self::QUERY_DEPTH_CUSTOM );
		$query  = $this->replace_ids( $query );
		$result = graphql( array( 'query' => $query ) );
		$this->assertEquals( $expected_error, $result['errors'][0]['message'] );
	}

	public function filter_errors_data_provider(): array {
		return array(
			'and_plus_or_as_siblings_returns_error'      => [
				'query {
					posts(
						filter: {
							or: [],
							and: [],
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'A Filter can only accept one of an \'and\' or \'or\' child relation as an immediate child.',
			],
			'empty_filter_or_returns_error'              => [
				'query {
					posts(
						filter: {
							or: [],
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'The Filter relation array specified has no children. Please remove the relation key or add one or more appropriate objects to proceed.',
			],
			'empty_filter_and_returns_error'             => [
				'query {
					posts(
						filter: {
							and: [],
						}
					) {
						nodes {
							title
							content
						}
					}
				}',
				'The Filter relation array specified has no children. Please remove the relation key or add one or more appropriate objects to proceed.',
			],
			'relation_nesting_gt_11_should_return_error' => [
				'query {
					posts(
						filter: {
							or: [
								{
									or: [
										{
											or: [
												{
													or: [
														{
															or: [
																{
																	or: [
																		{
																			or: [
																				{
																					or: [
																						{
																							or: [
																								{
																									or: [
																										{
																											or: [
																												{
																													tag: {
																														name: {eq: "small"}
																													}
																												},
																												{
																													category: {
																														name: {eq: "feline"}
																													}
																												}
																											]
																										}
																									]
																								}
																							]
																						}
																					]
																				}
																			]
																		}
																	]
																}
															]
														}
													]
												}
											]
										}
									]
								},
							]
						}
					) {
						nodes {
							title
						}
					}
				}',
				'The Filter\'s relation allowable depth nesting has been exceeded. Please reduce to allowable (' . self::QUERY_DEPTH_CUSTOM . ') depth to proceed',
			],
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
		$this->update_wpgraphql_query_depth( 'off', self::QUERY_DEPTH_DEFAULT );
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

	/**
	 * Short function to update the WpGraphQL settings for custom query depth
	 *
	 * @param string $toggle_state on/off toggle for custom setting.
	 * @param int $depth_limit depth limit to set, when toggleState value is 'on'.
	 */
	private function update_wpgraphql_query_depth( string $toggle_state, int $depth_limit ): void {
		$wpgraphql_options                        = get_option( 'graphql_general_settings' );
		$wpgraphql_options['query_depth_enabled'] = $toggle_state;
		if ( $toggle_state === 'on' ) {
			$wpgraphql_options['query_depth_max'] = '' . $depth_limit;
		} else {
			$wpgraphql_options['query_depth_max'] = '10';
		}
		update_option( 'graphql_general_settings', $wpgraphql_options );
	}

	public function filters_data_provider(): array {
		return array(
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}, {"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}, {"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : []}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
			],
			'posts_valid_filter_category_name_eq_and_in'   => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									eq: "canine",
									in: ["animal"]
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
			],
			'posts_valid_filter_category_name_notEq_and_in' => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									notEq: "canine",
									in: ["animal"]
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
			],
			'posts_valid_filter_category_name_neq_and_notIn' => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									eq: "feline",
									notIn: ["red"]
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
			],
			'posts_valid_filter_category_name_neq_and_notIn_multiple' => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									eq: "feline",
									notIn: ["car", "animal"]
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
			],
			'posts_valid_filter_category_name_like'        => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									like: "nima",
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
			],
			'posts_valid_filter_category_name_notLike'     => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									notLike: "fel",
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
			],
			'posts_valid_filter_category_name_like_eq'     => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									like: "anim",
									eq: "canine"
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
			],
			'posts_valid_filter_category_name_like_notEq'  => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									like: "nim",
									notEq: "feline"
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
			],
			'posts_valid_filter_category_name_like_in_multiple' => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									like: "ani",
									in: ["canine", "feline"]
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
			],
			'posts_valid_filter_category_name_notLike_in_multiple' => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									notLike: "ani",
									in: ["canine", "feline"]
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
			],
			'posts_valid_filter_category_name_like_notIn'  => [
				'query {
					posts(
						filter: {
							category: {
								name: {
									like: "ani",
									notIn: ["feline"]
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
			],
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}, {"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}, {"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : []}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"} ]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"} ]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : []}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}, {"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"} ]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}, {"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : []}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" , "content" : "<p>this is a dog</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat" , "content" : "<p>this is a cat</p>\n"}]}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "posts": {"nodes" : []}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "pages": {"nodes" : []}}}',
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
						nodes {
							title
							content
						}
					}
				}',
				'{"data": { "zombies": {"nodes" : []}}}',
			],
			'posts_reject_invalid_tax_filter_args'         => [
				'query {
					posts(
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
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"errors": null}',
			],
			'pages_reject_invalid_tax_filter_args'         => [
				'query  {
					pages(
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
					) {
						nodes {
							title
							content
						}
					}
				}',
				'{"errors": null}',
			],
			'non_filterable_types_reject_all_filter_args'  => [
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
			],
			'OR_with_one_condition_should_return_cat'      => [
				'query {
					posts(
						filter: {
							or: [
								{ tag: { name: { eq: "small" } } }
							]
						}
					) {
						nodes {
							title
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat"}]}}}',
			],
			'OR_with_two_conditions_should_return_cat_and_dog' => [
				'query {
					posts(
						filter: {
							or: [
								{ category: { name: { eq: "feline" } } }
								{ category: { name: { eq: "canine" } } }
							]
						}
					) {
						nodes {
							title
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" },{"title": "cat"}]}}}',
			],
			'OR_with_two_nested_AND_one_root_condition_should_return_cat' => [
				'query {
					posts(
						filter: {
							or: [
								{ category: { name: { eq: "feline" } } }
								{ category: { name: { eq: "canine" } } }
							]
							tag: { name: { eq: "small" } }
						}
					) {
						nodes {
							title
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat"}]}}}',
			],
			'AND_with_one_condition_should_return_cat'     => [
				'query {
					posts(
						filter: {
							and: [
								{ tag: { name: { eq: "small" } } }
							]
						}
					) {
						nodes {
							title
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat"}]}}}',
			],
			'AND_with_two_separate_conditions_should_return_cat_and_dog' => [
				'query {
					posts(
						filter: {
							and: [
								{ tag: { name: { eq: "black" } } }
								{ category: { name: { eq: "animal" } } }
							]
						}
					) {
						nodes {
							title
						}
						aggregations {
							categories {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "dog" },{"title": "cat"}], "aggregations" : { "categories" : [ { "key" : "animal",  "count" : "2"}, { "key" : "canine",  "count" : "1"}, { "key" : "feline",  "count" : "1"}]}}}}',
			],
			'AND_with_one_nested_AND_one_root_condition_should_return_cat' => [
				'query {
					posts(
						filter: {
							and: [
								{ category: { name: { eq: "feline" } } }
							]
							tag: { name: { eq: "small" } }
						}
					) {
						nodes {
							title
						}
						aggregations {
							categories {
								key
								count
							}
						}
					}
				}',
				'{"data": { "posts": {"aggregations" : { "categories" : [ { "key" : "animal",  "count" : "1"}, { "key" : "feline",  "count" : "1"}]}, "nodes" : [{"title": "cat"}]}}}',
			],
			'AND_OR_with_both_relations_should_return_error' => [
				'query {
					posts(
						filter: {
							or: [
								{ tag: { name: { eq: "small" } } }
							]
							and: [
								{ category: { name: { eq: "feline" } } }
							]
						}
					) {
						nodes {
							title
						}
					}
				}',
				'{"errors": null}',
			],
			'AND_with_no_children_should_return_error'     => [
				'query {
					posts(
						filter: {
							and: []
						}
					) {
						nodes {
							title
						}
					}
				}',
				'{"errors": null}',
			],
			'OR_with_nesting_gt_10_should_return_error'    => [
				'query {
					posts(
						filter: {
							or: [
								{
									or: [
										{
											or: [
												{
													or: [
														{
															or: [
																{
																	or: [
																		{
																			or: [
																				{
																					or: [
																						{
																							or: [
																								{
																									or: [
																										{
																											tag: {
																												name: {eq: "small"}
																											}
																										},
																										{
																											category: {
																												name: {eq: "feline"}
																											}
																										}
																									]
																								}
																							]
																						}
																					]
																				}
																			]
																		}
																	]
																}
															]
														}
													]
												}
											]
										}
									]
								},
							]
						}
					) {
						nodes {
							title
						}
					}
				}',
				'{"errors": null}',
			],
			'OR_with_nesting_lt_10_should_return_cat'      => [
				'query {
					posts(
						filter: {
							or: [
								{
									or: [
										{
											or: [
												{
													or: [
														{
															or: [
																{
																	or: [
																		{
																			or: [
																				{
																					or: [
																						{
																							or: [
																								{
																									tag: {
																										name: {eq: "small"}
																									}
																								},
																								{
																									category: {
																										name: {eq: "feline"}
																									}
																								}
																							]
																						}
																					]
																				}
																			]
																		}
																	]
																}
															]
														}
													]
												}
											]
										}
									]
								},
							]
						}
					) {
						nodes {
							title
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat"}]}}}',
			],
			'OR_nested_with_one_root_AND_condition_should_return_cat' => [
				'query {
					posts(
						filter: {
							tag: { name: { eq: "small" } }
							or: [
								{
									or: [
										{
											or: [
												{
													or: [
														{
															or: [
																{
																	or: [
																		{
																			or: [
																				{
																					or: [
																						{
																							or: [
																								{
																									category: {
																										name: {eq: "feline"}
																									}
																								}
																							]
																						}
																					]
																				}
																			]
																		}
																	]
																}
															]
														}
													]
												}
											]
										}
									]
								},
							]
						}
					) {
						nodes {
							title
						}
					}
				}',
				'{"data": { "posts": {"nodes" : [{"title": "cat"}]}}}',
			],
		);
	}
}
