<?php
/**
 * Filter Query extension for WP-GraphQL
 *
 * @package WPGraphqlFilterQuery
 */

namespace WPGraphQLFilterQuery;

use \GraphQL\Error\ClientAware;

/**
 * Custom error exception.
 */
class FilterException extends \Exception implements ClientAware {
	/**
	 * Relay ClientAware fn.
	 *
	 * @return bool isSafe from ClientAware.
	 */
	public function isClientSafe(): bool {
		return true;
	}
}
