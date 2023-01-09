<?php

namespace Buffer\Facebook;

use Facebook\Facebook as FacebookClient;

class Facebook
{
    protected $client = null;

    public function __construct()
    {
        $this->client = new FacebookClient([
            'app_id' => getenv('FACEBOOK_APP_ID'),
            'app_secret' => getenv('FACEBOOK_APP_SECRET'),
            'default_graph_version' => 'v14.0',
        ]);
    }

    /*
     * Set the default access token for the client.
     */
    public function setAccessToken($accessToken)
    {
        if (empty($accessToken)) {
            return false;
        }
        try {
            $this->client->setDefaultAccessToken($accessToken);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /*
     * Set facebook client to the given library.
     */
    public function setFacebookLibrary($facebookLibrary)
    {
        return $this->client = $facebookLibrary;
    }

    /*
     * Get page access token.
     */
    public function getPageAccessToken($pageId)
    {

        $response = $this->sendRequest("GET", "/{$pageId}", [
            "fields" => "access_token",
        ]);

        if (empty($response) || $response->isError()) {
            return null;
        }
        $data = $response->getDecodedBody();

        return $data["access_token"] ?? null;
    }

    /*
     * Get page insights data for the given page, metrics and range.
     */
    public function getPageInsightsMetricsData($pageId, $insightsMetrics, $since, $until, $period = null)
    {
        $params = [
            "metric" => $insightsMetrics,
            "until" => strtotime("now"),
        ];

        if (!is_null($period)) {
            $params["period"] = $period;
        }

        $data = [];
        $responses = [];

        if (is_null($since) && is_null($until)) {
            $responses[] = $this->sendRequest("GET", "/{$pageId}/insights", $params);
        } else {
            $intervals = $this->getIntervalsForPeriod($since, $until);

            foreach ($intervals as $interval) {
                $params["since"] = $interval['since'];
                $params["until"] = $interval['until'];
                $requests[] = $this->createRequest("GET", "/{$pageId}/insights", $params);
            }

            $responses = $this->sendBatchRequest($requests);
        }

        if (!empty($responses)) {
            foreach ($responses as $response) {
                $decodedBody = $response->getDecodedBody();
                if (!empty($decodedBody) && is_array($decodedBody)) {
                    $responseData = $this->extractPageInsightsMetricsFromResponse($decodedBody);
                    foreach ($responseData as $metricKey => $values) {
                        $existingValues = isset($data[$metricKey]) ?
                            $data[$metricKey] : [];
                        $data[$metricKey] = array_merge($existingValues, $values);
                    }
                }
            }
        }


        return $data;
    }

    /*
     * Get Instagram Graph node metadata
     */
    public function getInstagramGraphNodeMetadata($nodeId, $fields)
    {
        $fieldsString = join(",", $fields);

        $response = $this->sendRequest("GET", "/{$nodeId}", [
            "fields" => $fieldsString,
        ]);

        if (empty($response) || $response->isError()) {
            return [];
        }

        $data = $response->getDecodedBody();
        return $data;
    }


    /*
     * Get page post's graph metrics data (only comments, likes and reactions)
     */
    public function getPagePostGraphMetricsData($pageId, $postId, $metrics)
    {
        $result = [];

        $objectId = "{$pageId}_{$postId}";
        $fieldsString = join(".summary(true),", $metrics);
        $fieldsString .= ".summary(true)";

        $response = $this->sendRequest("GET", "/{$objectId}", [
            "fields" => $fieldsString,
        ]);

        if (!empty($response)) {
            $result = $this->processGraphResponse($response, $metrics);
        }

        return $result;
    }

    /*
     * Get page batch posts' graph metrics data (only comments, likes and reactions)
     */
    public function getPageBatchPostsGraphMetricsData($postIds, $metrics)
    {
        $result = [];
        $batchRequests = [];
        $fieldsString = join(".summary(true),", $metrics);
        $fieldsString .= ".summary(true)";
        foreach ($postIds as $postId) {
            $request = $this->createRequest("GET", "/{$postId}", [
                "fields" => $fieldsString,
            ]);
            $batchRequests[$postId] = $request;
        }
        $responses = $this->sendBatchRequest($batchRequests);
        if (!empty($responses)) {
            foreach ($responses as $key => $response) {
                $result[$key] = $this->processGraphResponse($response, $metrics);
            }
        }

        return $result;
    }

    /*
     * Get page post's insights data for the given post and metrics.
     */
    public function getPagePostInsightsMetricData($pageId, $postId, $insightsMetrics)
    {
        $result = [];

        $objectId = "{$pageId}_{$postId}";
        $insightsMetricsString = join(",", $insightsMetrics);
        $response = $this->sendRequest("GET", "/{$objectId}/insights", [
            "metric" => $insightsMetricsString,
            "period" => "lifetime",
            "until" => strtotime("now"),
        ]);

        if (!empty($response)) {
            $result = $this->processInsightsResponse($response);
        }

        return $result;
    }

    /*
     * Get page batch posts insights data for the given posts and metrics.
     */
    public function getPageBatchPostsInsightsMetricData($postIds, $insightsMetrics)
    {
        $result = [];
        $batchRequests = [];
        $insightsMetricsString = join(",", $insightsMetrics);
        foreach ($postIds as $postId) {
            $request = $this->createRequest("GET", "/{$postId}/insights", [
                "metric" => $insightsMetricsString,
                "period" => "lifetime",
                "until" => strtotime("now"),
            ]);
            $batchRequests[$postId] = $request;
        }

        $responses = $this->sendBatchRequest($batchRequests);
        if (!empty($responses)) {
            foreach ($responses as $key => $response) {
                $result[$key] = $this->processInsightsResponse($response);
            }
            return $result;
        }

        return [];
    }

    /*
     * Get page posts sent within since and until date times.
     * Returns an associative array of id => created_time elements where
     * id is the post id.
     */
    public function getPagePosts($pageId, $since, $until, $limit = 100)
    {
        $since = $since ? $since : strtotime("yesterday");
        $until = $until ? $until : strtotime("now");
        $fields = 'id,created_time,updated_time,attachments,message';

        $response = $this->client->get("/{$pageId}/posts?since={$since}&until={$until}&limit={$limit}&fields={$fields}");
        $rawResponse = json_decode($response->getBody(), true);

        if (!isset($rawResponse['data'])) {
            return [];
        }

        return $rawResponse['data'];
    }

    /*
     * Get all media objects for an Instagram Business Account sent within since and until
     */
    public function getUserMedias($userId, $since, $until)
    {
        $posts = [];

        $since = $since ? $since : strtotime("yesterday");
        $until = $until ? $until : strtotime("now");

        $mediaBasicMetrics = ['timestamp'];
        $mediaBasicMetricsString = join(",", $mediaBasicMetrics);

        $response = $this->client->get("/{$userId}/media?fields={$mediaBasicMetricsString}");
        $graphEdge = $response->getGraphEdge();

        while ($graphEdge) {
            foreach ($graphEdge as $post) {
                $timestamp = strtotime($post->getField("timestamp"));
                if ($this->isTimestampWitinDateRange($timestamp, $since, $until)) {
                    $posts[] = $post->getField("id");
                }
            }
            $graphEdge = $this->client->next($graphEdge);
        }

        return $posts;
    }

    private function isTimestampWitinDateRange($postTimestamp, $since, $until)
    {
        return $postTimestamp >= $since && $postTimestamp <= $until;
    }

    /*
     * Get Instagram medias' basic fields.
     */
    public function getBatchMediaBasicData($mediaIds, $fields)
    {
        $result = [];
        $batchRequests = [];
        $fieldsString = join(",", $fields);
        foreach ($mediaIds as $mediaId) {
            $request = $this->createRequest("GET", "/{$mediaId}", [
                "fields" => $fieldsString,
            ]);
            $batchRequests[$mediaId] = $request;
        }
        $responses = $this->sendBatchRequest($batchRequests);
        if (!empty($responses)) {
            foreach ($responses as $key => $response) {
                $result[$key] =  $response->getDecodedBody();
            }
        }

        return $result;
    }

    public function getInstagramUserStories($userId, $fields)
    {
        $fieldsString = join(",", $fields);

        $response = $this->sendRequest("GET", "/{$userId}/stories", [
            "fields" => $fieldsString,
        ]);


        if ($response->isError()) {
            return null;
        }
        $body = $response->getDecodedBody();

        return $body["data"];
    }

    public function getInstagramStoryInsights($storyId, $metrics)
    {
        $formattedMetrics = join(",", $metrics);

        $response = $this->sendRequest("GET", "/{$storyId}/insights", [
            "metric" => $formattedMetrics,
        ]);

        if ($response->isError()) {
            return null;
        }
        $insights = $this->processInsightsResponse($response);

        return $insights;
    }

    public function getMediaComment($commentId, $fields)
    {

        $response = $this->sendRequest("GET", "/{$commentId}", [
            "fields" => join(",", $fields),
        ]);

        if ($response->isError()) {
            return [];
        }

        return json_decode($response->getBody(), true);
    }

    /*
     * Create a single request
     */
    private function createRequest($method, $url, $params = [])
    {
        return $this->client->request($method, $url, $params);
    }

    /*
     * Given page insights daily data extract the metrics to a human readable format.
     * [Metric1 => [[Day1 => Value], [Day2 => Value]], Metric2 => [[Day1 => Value], [Day2 => Value]], ...]
     */
    private function extractPageInsightsMetricsFromResponse($response)
    {
        $result = [];
        $data = $response["data"];

        if (empty($data)) {
            return $result;
        }

        foreach ($data as $key => $metrics) {
            $metricName = $metrics["name"];
            // care only daily and lifetime metrics
            if (in_array($metrics["period"], ["day", "lifetime"])) {
                $result[$metricName] = [];
                $metricValuesByDay = $metrics["values"];
                foreach ($metricValuesByDay as $key => $value) {
                    if (isset($value["end_time"]) && isset($value["value"])) {
                        $result[$metricName][$value["end_time"]] =  $value["value"];
                    }
                }
            }
        }
        return $result;
    }

    /*
     * Make Graph API /insights response to a human readable associative array.
     */
    private function processInsightsResponse($response)
    {
        $result = [];
        $decodedBody = $response->getDecodedBody();
        if (!empty($decodedBody["data"]) && is_array($decodedBody["data"])) {
            $data = $decodedBody["data"];
            foreach ($data as $key => $metric) {
                $metricName = $metric["name"];
                // since the period for posts is lifetime there will be only one value
                $metricValue = $metric["values"][0]["value"];
                $result[$metricName] = $metricValue;
            }
        }
        return $result;
    }

    /*
     * Make Graph API response to a human readable associative array.
     */
    private function processGraphResponse($response, $metrics)
    {
        $result = [];
        $decodedBody = $response->getDecodedBody();
        if (!empty($decodedBody)) {
            foreach ($metrics as $metric) {
                // shares count requires a special care
                if ($metric === "shares") {
                    $result[$metric] = isset($decodedBody[$metric]["count"]) ? $decodedBody[$metric]["count"] : 0;
                } elseif (isset($decodedBody[$metric])) {
                    $result[$metric] = $decodedBody[$metric]["summary"]["total_count"];
                }
            }
        }
        return $result;
    }

    /*
     * Make a request to FB API.
     */
    private function sendRequest($method, $endpoint, $params = [])
    {
        return $this->client->sendRequest($method, $endpoint, $params);
    }

    /*
     * Make a batch request to FB API.
     */
    private function sendBatchRequest($requests)
    {
        return $this->client->sendBatchRequest($requests);
    }

    /*
     * Break an interval into chunks of maximum 30 days
     */
    public function getIntervalsForPeriod($since, $until)
    {
        $maxDaysPerRequest = 30;
        $numDays = abs($since - $until) / 60 / 60 / 24;


        if ($numDays <= $maxDaysPerRequest) {
            return [['since' => $since, 'until' => $until]];
        } elseif ($numDays > $maxDaysPerRequest) {
            $numIntervals = ceil($numDays / $maxDaysPerRequest);
            $intevals = [];
            $intevalUntil = $since;
            $intevalSince = $since;
            while (count($intevals) < $numIntervals) {
                $intevalUntil = strtotime("+{$maxDaysPerRequest} days", $intevalUntil);
                if ($intevalUntil > $until) {
                    $intevalUntil = $until;
                }
                $intevals[] = [
                    'since' => $intevalSince,
                    'until' => $intevalUntil,
                ];
                $intevalSince = $intevalUntil;
            }

            return $intevals;
        }
        return [];
    }

    public function subscribeToWebhook($pageId)
    {
        // With Graph API version 3.2, subscribed_apps requires the subscribed_fields parameter,
        // which currently does not support Instagram webhooks fields
        // as a workaround we are subscribing to the email fields, to get the webhooks up and running
        // so that it will return story_insights events
        $params = ["subscribed_fields" => 'email' ];
        return $this->sendRequest("POST", "/${pageId}/subscribed_apps", $params)->getDecodedBody();
    }

    public function unsubscribeFromWebhook($pageId)
    {
        return $this->sendRequest("DELETE", "/${pageId}/subscribed_apps")->getDecodedBody();
    }

    public function getMe()
    {
        return $this->sendRequest("GET", "/me")->getDecodedBody();
    }

    public function getAccounts()
    {
        $fieldsString = join(",", ['instagram_business_account', 'access_token']);

        return $this->sendRequest(
            "GET",
            "/me/accounts",
            [
                "fields" => $fieldsString
            ]
        )->getDecodedBody();
    }

    public function getAdAccounts($after = null)
    {
        $fieldsString = join(",", [
            'account_id',
            'currency',
            'promote_pages{id,instagram_business_account}'
        ]);

        $params = [
            "fields" => $fieldsString
        ];

        if ($after) {
            $params["after"] = $after;
        }

        return $this->sendRequest(
            "GET",
            "/me/adaccounts",
            $params
        )->getDecodedBody();
    }

    public function getTokenScopes($inputToken)
    {
        $fieldsString = join(",", [
            "scopes",
        ]);

        $params = [
            "fields" => $fieldsString,
            "input_token" => $inputToken
        ];

        return $this->sendRequest(
            "GET",
            "/debug_token",
            $params
        )->getDecodedBody();
    }
}
