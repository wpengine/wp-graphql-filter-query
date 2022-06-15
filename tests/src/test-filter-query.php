<?php

class TestFilterQuery {
	protected function set_up() {
		new \WPGraphQLFilterQuery\FilterQuery();
	}

	public function test_filter_query_adds_customfield_to_schema() {
		$query = 'query  {
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
		}';

		$results = do_graphql_request( $query );

		$this->assertArrayNotHasKey( 'errors', $results, json_encode( $results ) );
		$this->assertNotEmpty( $results );
		// $this->assertEquals( [ 'customField' => 'value...' ], $results['data'] ); << This is presently for WPGraphQL fields check, not input one
	}
}
