<?php
/**
 * Class SampleTest
 *
 * @package Wp_Graphql_Filter_Query
 */


/**
 * Sample test case using wp-graphql.
 */
class TestWPGraphQLSample extends WP_UnitTestCase {

	/**
	 * A single example test.
	 */
	public function test_sample_filter_returns_errors() {
		$query = 'query postsQuery($first:Int $last:Int $after:String $before:String $where:RootQueryToPostConnectionWhereArgs $filter:String ){
			posts( first:$first last:$last after:$after before:$before where:$where filter:$filter ) {
				nodes {
				  id
				  postId
				}
			}
		}';

		$variables = [
			'first' => 1,
		];

		$results = do_graphql_request( $query, 'postsQuery', $variables );

		$this->assertArrayNotHasKey( 'errors', $results );
		$this->assertNotEmpty( $results );
	}
}
