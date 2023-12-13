<?php

class Wp_GraphqlFilterQueryTest extends WP_UnitTestCase {

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

		register_post_type(
			'vampire',
			array(
				'labels'              => array(
					'name' => 'Vampires',
				),
				'public'              => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => false,
				/** WP GRAPHQL */
				'show_in_graphql'     => true,
				'hierarchical'        => true,
				'graphql_single_name' => 'vampire',
				'graphql_plural_name' => 'vampires',
			)
		);

		register_post_type(
			'rabbit',
			array(
				'labels'              => array(
					'name' => 'Rabbits',
				),
				'public'              => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => false,
				/** WP GRAPHQL */
				'show_in_graphql'     => false,
				'hierarchical'        => true,
				'graphql_single_name' => 'rabbit',
				'graphql_plural_name' => 'rabbits',
			)
		);
	}

	public function test_filter_query_get_supported_post_types() {
		$post_types     = filter_query_get_supported_post_types();
		$expected_types = [
			'post'       => [
				'name'            => 'post',
				'capitalize_name' => 'Post',
				'plural_name'     => 'posts',
			],
			'page'       => [
				'name'            => 'page',
				'capitalize_name' => 'Page',
				'plural_name'     => 'pages',
			],
			'zombie'     => [
				'name'            => 'zombie',
				'capitalize_name' => 'Zombie',
				'plural_name'     => 'zombies',
			],
			'vampire'    => [
				'name'            => 'vampire',
				'capitalize_name' => 'Vampire',
				'plural_name'     => 'vampires',
			],
			'attachment' => [
				'name'            => 'attachment',
				'capitalize_name' => 'Attachment',
				'plural_name'     => 'media',
			],
		];
		$this->assertEquals( $expected_types, $post_types );
	}

}
