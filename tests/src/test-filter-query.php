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
		);

		$results1 = do_graphql_request( $query_and_results['query1'] );
		$this->assertArrayNotHasKey( $query_and_results['result1_IsNot'], $results1, json_encode( $results1 ) );
		$this->assertNotEmpty( $results1 );

		$results2 = do_graphql_request( $query_and_results['query2'] );
		$this->assertArrayNotHasKey( $query_and_results['result2_IsNot'], $results2, json_encode( $results2 ) );
		$this->assertNotEmpty( $results2 );
	}
}
