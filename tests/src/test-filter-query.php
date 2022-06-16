<?php

class TestFilterQuery extends WP_UnitTestCase {
	protected function setUp() {
		new \WPGraphQLFilterQuery\FilterQuery();
	}

	public function test_filterable_types_accept_valid_tax_filter_args() {
		$query_and_results = array(
			'validPostQuery'             => 'query  {
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
			'validPostQueryResult_IsNot' => 'errors',
			'validPageQuery'             => 'query  {
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
			'validPageQueryResult_IsNot' => 'errors',
		);

		$post_result = do_graphql_request( $query_and_results['validPostQuery'] );
		$this->assertArrayNotHasKey( $query_and_results['validPostQueryResult_IsNot'], $post_result, json_encode( $post_result ) );
		$this->assertNotEmpty( $post_result );
		$page_result = do_graphql_request( $query_and_results['validPageQuery'] );
		$this->assertArrayNotHasKey( $query_and_results['validPageQueryResult_IsNot'], $page_result, json_encode( $page_result ) );
		$this->assertNotEmpty( $page_result );
	}

	public function test_filterable_types_reject_invalid_tax_filter_args() {
		$query_and_results = array(
			'invalidPostQuery'             => 'query  {
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
			'invalidPostQueryResult_IsNot' => 'data',
			'invalidPageQuery'             => 'query  {
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
			'invalidPageQueryResult_IsNot' => 'data',
		);

		$post_result = do_graphql_request( $query_and_results['invalidPostQuery'] );
		$this->assertArrayNotHasKey( $query_and_results['invalidPostQueryResult_IsNot'], $post_result, json_encode( $post_result ) );
		$this->assertNotEmpty( $post_result );
		$page_result = do_graphql_request( $query_and_results['invalidPageQuery'] );
		$this->assertArrayNotHasKey( $query_and_results['invalidPageQueryResult_IsNot'], $page_result, json_encode( $page_result ) );
		$this->assertNotEmpty( $page_result );
	}

	public function test_non_filterable_types_reject_all_filter_args() {
		$query_and_results = array(
			'unsupportedTagsFilterQuery'       => 'query  {
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
			'unsupportedTagsQueryResult_IsNot' => 'data',
		);

		$tags_result = do_graphql_request( $query_and_results['unsupportedTagsFilterQuery'] );
		$this->assertArrayNotHasKey( $query_and_results['unsupportedTagsQueryResult_IsNot'], $tags_result, json_encode( $tags_result ) );
		$this->assertNotEmpty( $tags_result );
	}
}
