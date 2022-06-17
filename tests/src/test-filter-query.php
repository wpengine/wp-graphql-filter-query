<?php

class TestFilterQuery extends WP_UnitTestCase {
	protected function setUp() {
		new \WPGraphQLFilterQuery\FilterQuery();
	}

	/**
	 * @dataProvider  make_request_and_assert
	 *
	 * @param string $query GraphQL query to test.
	 * @param string $expected_result what the root object of query return should be.
	 * @param string $expected_not_result what the root object of query return should not be.
	 */
	public function make_request_and_assert( $query, $expected_not_result ) {
		$result = do_graphql_request( $query );
		$this->assertArrayNotHasKey( $expected_not_result, $result, json_encode( $result ) );
		$this->assertNotEmpty( $result );
	}

	public function test_filterable_types_accept_valid_tax_filter_args() {
		$query_and_results = array(
			array(
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
				'errors',
			),
			array(
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
				'errors',
			),
		);

		$this->make_request_and_assert( ...$query_and_results[0] );
		$this->make_request_and_assert( ...$query_and_results[1] );
	}

	public function test_filterable_types_reject_invalid_tax_filter_args() {
		$query_and_results = array(
			array(
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
				'data',
			),
			array(
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
				'data',
			),
		);

		$this->make_request_and_assert( ...$query_and_results[0] );
		$this->make_request_and_assert( ...$query_and_results[1] );
	}

	public function test_non_filterable_types_reject_all_filter_args() {
		$query_and_results = array(
			array(
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
				'data',
			),
		);

		$this->make_request_and_assert( ...$query_and_results[0] );
	}
}
