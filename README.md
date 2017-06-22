# facebook-api-wrapper
Buffer's Facebook PHP API wrapper

Our FB API wrapper library provides helpful utility methods to work with [FB Graph API](https://developers.facebook.com/docs/graph-api).

Methods supported in the wrapper:
- `setAccessToken($accessToken)`
- `getPageInsightsMetricsData($pageId, $insightsMetrics, $since, $until)`
- `getPagePostGraphMetricsData($pageId, $postId, $metrics)`
- `getPageBatchPostsGraphMetricsData($postIds, $metrics)`
- `getPagePostInsightsMetricData($pageId, $postId, $insightsMetrics)`
- `getPageBatchPostsInsightsMetricData($postIds, $insightsMetrics)`
- `getPagePosts($pageId, $since, $until, $limit = 100)`


Tests
-----
Before running the tests make sure you have installed all the dependancies with
`composer install`.

The tests can be executed by using this command from the base directory:

    bin/phpunit -c phpunit.xml --bootstrap vendor/autoload.php

Contributing
----

You're welcome to contribute to this repo.


## Did you find a bug?

If you found a bug then please go ahead and [open a GitHub issue](https://github.com/bufferapp/facebook-api-wrapper/issues), and we'll try to fix it as soon as possible.
