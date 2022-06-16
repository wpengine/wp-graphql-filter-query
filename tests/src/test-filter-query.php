<?php

class TestFilterQuery extends WP_UnitTestCase {
	protected function setUp() {
		new \WPGraphQLFilterQuery\FilterQuery();
	}

	public function test_filter_query_adds_customfield_to_schema() {
		$query_and_results = array(
			'query1'        => 'query  {
				posts(
					where: {
						filter: {
							category: {
								id: {eq: 10},
								name: {eq: "foo"}
							},
							tag: {
								name: {
									in: ["foo", "bar"],
									like: "tst"}
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
			'result1_IsNot' => 'errors',
			'query2'        =>
				'query  {
					posts(
						where: {
							filter: {
								category: {
									id: {eq: "10"},
									name: {eq: "foo"}
								},
								tag: {
									name: {
										in: ["foo", "bar"],
										like: "tst"}
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
			'result2_IsNot' => 'data',
			'query3'        =>
				'query  {
					users(
						where: {
							filter: {
								category: {
									id: {eq: 10},
									name: {eq: "foo"}
								},
								tag: {
									name: {
										in: ["foo", "bar"],
										like: "tst"}
								}
							}
						}
					) {
						nodes {
							name
						}
					}
				}',
			'result3_IsNot' => 'errors',
			'query4'        =>
				'query  {
					tags(
						where: {
							filter: {
								category: {
									id: {eq: 10},
									name: {eq: "foo"}
								},
								tag: {
									name: {
										in: ["foo", "bar"],
										like: "tst"}
								}
							}
						}
					) {
						nodes {
							slug
						}
					}
				}',
			'result4_IsNot' => 'data',
		);
		for ( $x = 1; $x <= count($query_and_results)/2; $x++ ) {
			$results = do_graphql_request( $query_and_results['query' . $x] );
			$this->assertArrayNotHasKey( $query_and_results['result' . $x . '_IsNot'], $results, json_encode( $results ) );
			$this->assertNotEmpty( $results );
		}
	}
}
