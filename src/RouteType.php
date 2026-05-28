<?php
declare(strict_types=1);

namespace Meraki\Http;

/**
 * Classifies a Route by the semantics of its action class name suffix.
 *
 * - Collection: handler whose class ends in the plural indicator (e.g. GetAllAction).
 *   Represents a RESTful collection: list a resource, create-in-collection.
 *
 * - Item: handler whose class ends in the singular indicator (e.g. GetOneAction).
 *   Represents a single item from a RESTful collection, addressed by its ID.
 *
 * - Action: handler whose class has no indicator suffix (e.g. GetAction).
 *   Represents a static/verb route with no implied RESTful semantics.
 */
enum RouteType: string
{
	case Collection = 'collection';
	case Item = 'item';
	case Action = 'action';
}
