<?php

class TestFilterQuery extends WP_UnitTestCase {
	protected function setUp(): void {
		new \WPGraphQLFilterQuery\FilterQuery();
	}

	public function test_filter_query_adds_customField_to_schema() {
		$query = 'query  {
			customField
		}';

		$results = do_graphql_request( $query );

		$this->assertArrayNotHasKey( 'errors', $results, json_encode( $results ) );
		$this->assertNotEmpty( $results );
		$this->assertEquals( [ 'customField' => 'value...' ], $results['data'] );
	}
}
