<?php

class TestFilterQuery extends WP_UnitTestCase {
	protected function setUp(): void {
		new \WPGraphQLFilterQuery\FilterQuery();
	}

	/**
	 * @dataProvider  data_provider
	 *
	 * @param string $query GraphQL query to test.
	 * @param string $expected_result What the root object of query return should be.
	 * @throws Exception
	 */
	public function test_schema_exists_for_filters( string $query, string $expected_result ) {
		$result = do_graphql_request( $query );
		$this->assertArrayHasKey( $expected_result, $result, json_encode( $result ) );
		$this->assertNotEmpty( $result );
	}

	public function data_provider(): array {
		return array(
			'posts_accept_valid_tax_filter_args'          => array(
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
				'data',
			),
			'pages_accept_valid_tax_filter_args'          => array(
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
				'data',
			),
			'posts_reject_invalid_tax_filter_args'        => array(
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
				'errors',
			),
			'pages_reject_invalid_tax_filter_args'        => array(
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
				'errors',
			),
			'non_filterable_types_reject_all_filter_args' => array(
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
				'errors',
			),
		);
	}
}
