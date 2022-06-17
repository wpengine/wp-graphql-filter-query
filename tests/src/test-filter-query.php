<?php

class TestFilterQuery extends WP_UnitTestCase {
	protected function setUp(): void {
		new \WPGraphQLFilterQuery\FilterQuery();
	}

	/**
	 * @dataProvider  data_provider
	 *
	 * @param string $query GraphQL query to test.
	 * @param string $expected_result what the root object of query return should be.
	 * @param string $expected_not_result what the root object of query return should not be.
	 */
	public function test_schema_exists_for_filters( $query, $expected_not_result ) {
		$result = do_graphql_request( $query );
		$this->assertArrayNotHasKey( $expected_not_result, $result, json_encode( $result ) );
		$this->assertNotEmpty( $result );
	}

	public function data_provider() {
		return array(
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
	}
}
