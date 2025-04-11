<?php

require_once(__DIR__ . "/mocks/GraphNode.php");

use Buffer\Facebook\Facebook;
use Mockery as m;

use \Facebook\FacebookRequest;
use \Facebook\GraphNodes\GraphEdge;

class FacebookTest extends \PHPUnit\Framework\TestCase
{
    const FB_POST_ID = '11111_22222';
    const FB_PAGE_ID = '2222222';
    const IG_USER_ID = '55556666';

    private $facebook = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->facebook = new Facebook();
    }

    public function tearDown(): void
    {
        m::close();
    }

    public function testSetAccessToken()
    {
        $mockedFacebookLibrary = m::mock('\Facebook\Facebook');
        $mockedFacebookLibrary->shouldReceive('setDefaultAccessToken')->once()->getMock();
        $this->facebook->setFacebookLibrary($mockedFacebookLibrary);

        $this->assertTrue($this->facebook->setAccessToken('test_token'));
    }

    public function testSetAccessTokenWithNullToken()
    {
        $mockedFacebookLibrary = m::mock('\Facebook\Facebook');
        $mockedFacebookLibrary->shouldNotReceive('setDefaultAccessToken')->getMock();
        $this->facebook->setFacebookLibrary($mockedFacebookLibrary);

        $this->assertFalse($this->facebook->setAccessToken(null));
    }

    public function testGetPageInsightsMetricsData()
    {
        $decodedInsightsResponseData = [
            'data' => [
                0 => [
                    'name' => 'page_views_total',
                    'period' => 'day',
                    'values' => [
                        0 => [
                            'value' => 123,
                            'end_time' => '2017-04-27T07:00:00+0000',
                        ],
                        1 => [
                            'value' => 222,
                            'end_time' => '2017-04-28T07:00:00+0000',
                        ],
                        2 => [
                            'value' => 111,
                            'end_time' => '2017-04-29T07:00:00+0000',
                        ],
                    ],
                ],
                1 => [
                    'name' => 'page_fans',
                    'period' => 'lifetime',
                    'values' => [
                        0 => [
                            'value' => 444,
                            'end_time' => '2017-04-27T07:00:00+0000',
                        ],
                        1 => [
                            'value' => 555,
                            'end_time' => '2017-04-28T07:00:00+0000',
                        ],
                        2 => [
                            'value' => 666,
                            'end_time' => '2017-04-29T07:00:00+0000',
                        ],
                    ],
                ],
                2 => [
                    'name' => 'page_fan_adds',
                    'period' => 'week',
                    'values' => [
                        0 => [
                            'value' => 444,
                            'end_time' => '2017-04-27T07:00:00+0000',
                        ],
                        1 => [
                            'value' => 555,
                            'end_time' => '2017-05-4T07:00:00+0000',
                        ],
                    ],
                ],
            ]
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedInsightsResponseData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $insightsData = $facebook->getPageInsightsMetricsData(
            self::FB_PAGE_ID,
            [
                'page_views_total',
                'page_fans',
            ],
            null,
            null
        );
        $this->assertEquals($insightsData["page_views_total"]["2017-04-27T07:00:00+0000"], 123);
        $this->assertEquals($insightsData["page_views_total"]["2017-04-28T07:00:00+0000"], 222);
        $this->assertEquals($insightsData["page_views_total"]["2017-04-29T07:00:00+0000"], 111);

        $this->assertEquals($insightsData["page_fans"]["2017-04-27T07:00:00+0000"], 444);
        $this->assertEquals($insightsData["page_fans"]["2017-04-28T07:00:00+0000"], 555);
        $this->assertEquals($insightsData["page_fans"]["2017-04-29T07:00:00+0000"], 666);

        // week period metrics should not be in the response
        $this->assertArrayNotHasKey("page_fan_adds", $insightsData);
    }

    public function testGetPageInsightsMetricsDataShouldReturnEmpty()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn([])
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $insightsData = $facebook->getPageInsightsMetricsData(
            self::FB_PAGE_ID,
            [
                'page_posts_impressions_unique',
                'page_posts_impressions',
            ],
            null,
            null
        );
        $this->assertEquals($insightsData, []);
    }

    public function testGetPageInsightsMetricsDataShouldReturnEmptyWhenDataIsNull()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn(['data' => null])
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $insightsData = $facebook->getPageInsightsMetricsData(
            self::FB_PAGE_ID,
            [
                'page_posts_impressions_unique',
                'page_posts_impressions',
            ],
            null,
            null
        );
        $this->assertEquals($insightsData, []);
    }

    public function testGetPageInsightsMetricsDataShouldAcceptAnOptionalPeriodParameter()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn([])
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $since  = null;
        $until = null;
        $period = 'day';
        $params = [
            "metric" => ['page_posts_impressions_unique'],
            "until" => strtotime("now"),
            "period" => $period,
        ];
        $expectedGetParams = ["GET", "/2222222/insights", $params];

        $facebookMock->shouldReceive('sendRequest')
            ->withArgs($expectedGetParams)
            ->once()
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $facebook->getPageInsightsMetricsData(
            self::FB_PAGE_ID,
            ['page_posts_impressions_unique'],
            $since,
            $until,
            $period
        );
    }

    public function testGetPageInsightsMetricsDataShouldUseRightSinceAndUntil()
    {
        $decodedInsightsResponseData = [
            'data' => [
                0 => [
                    'name' => 'page_posts_impressions_unique',
                    'period' => 'day',
                    'values' => [
                        0 => [
                            'value' => 123,
                            'end_time' => '2017-04-27T07:00:00+0000',
                        ],
                        1 => [
                            'value' => 222,
                            'end_time' => '2017-04-28T07:00:00+0000',
                        ],
                        2 => [
                            'value' => 111,
                            'end_time' => '2017-04-29T07:00:00+0000',
                        ],
                    ],
                ],
            ]
        ];
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedInsightsResponseData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $since  = "1493826552";
        $until = "1496418552";
        $params = [
            "metric" => ['page_posts_impressions_unique'],
            "until" => $until,
            "since" => $since,
        ];
        $expectedGetParams = ["GET", "/2222222/insights", $params];
        $getIteratorMock = new ArrayIterator([$responseMock]);

        $responseBatchMock = m::mock('\Facebook\FacebookBatchResponse')
            ->shouldReceive('getIterator')
            ->once()
            ->andReturn($getIteratorMock)
            ->getMock();

        $requestMock = m::mock('\Facebook\FacebookRequest');

        $facebookMock->shouldReceive('request')->once()->andReturn($requestMock);

        $facebookMock->shouldReceive('sendBatchRequest')->once()->andReturn($responseBatchMock);

        $facebook->setFacebookLibrary($facebookMock);

        $facebook->getPageInsightsMetricsData(
            self::FB_PAGE_ID,
            ['page_posts_impressions_unique'],
            $since,
            $until
        );
    }

    public function testGetPageInsightsMetricsDataShouldBatchLongPeriodRequests()
    {
        $decodedInsightsResponseData1 = [
            'data' => [
                0 => [
                    'name' => 'page_fans',
                    'period' => 'day',
                    'values' => [
                        0 => [
                            'value' => 123,
                            'end_time' => '2017-04-27T07:00:00+0000',
                        ],
                        1 => [
                            'value' => 222,
                            'end_time' => '2017-04-28T07:00:00+0000',
                        ],
                        2 => [
                            'value' => 111,
                            'end_time' => '2017-04-29T07:00:00+0000',
                        ],
                    ],
                ],
            ]
        ];

        $decodedInsightsResponseData2 = [
            'data' => [
                0 => [
                    'name' => 'page_fans',
                    'period' => 'day',
                    'values' => [
                        0 => [
                            'value' => 123,
                            'end_time' => '2017-05-27T07:00:00+0000',
                        ],
                        1 => [
                            'value' => 222,
                            'end_time' => '2017-05-28T07:00:00+0000',
                        ],
                        2 => [
                            'value' => 111,
                            'end_time' => '2017-05-29T07:00:00+0000',
                        ],
                    ],
                ],
            ]
        ];
        $until = strtotime('today');
        $since = strtotime('- 42 days');

        $facebook = new Facebook();
        $responseMock1 = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedInsightsResponseData1)
            ->getMock();
        $responseMock2 = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedInsightsResponseData2)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $getIteratorMock = new ArrayIterator([$responseMock1, $responseMock2]);
        $responseBatchMock = m::mock('\Facebook\FacebookBatchResponse')
            ->shouldReceive('getIterator')
            ->once()
            ->andReturn($getIteratorMock)
            ->getMock();

        $requestMock = m::mock('\Facebook\FacebookRequest');

        $facebookMock->shouldReceive('request')->twice()->andReturn($requestMock);

        $facebookMock->shouldReceive('sendBatchRequest')->once()->andReturn($responseBatchMock);

        $facebook->setFacebookLibrary($facebookMock);

        $insightsData = $facebook->getPageInsightsMetricsData(
            self::FB_PAGE_ID,
            ['page_posts_impressions_unique'],
            $since,
            $until
        );

        $this->assertEquals($insightsData["page_fans"]["2017-04-27T07:00:00+0000"], 123);
        $this->assertEquals($insightsData["page_fans"]["2017-04-28T07:00:00+0000"], 222);
        $this->assertEquals($insightsData["page_fans"]["2017-04-29T07:00:00+0000"], 111);
        $this->assertEquals($insightsData["page_fans"]["2017-05-27T07:00:00+0000"], 123);
        $this->assertEquals($insightsData["page_fans"]["2017-05-28T07:00:00+0000"], 222);
        $this->assertEquals($insightsData["page_fans"]["2017-05-29T07:00:00+0000"], 111);
    }

    public function testGetPageInsightsAudienceData()
    {
        $decodedAudienceData = [
            'data' => [
                0 => [
                    'name' => 'follower_demographics',
                    'period' => 'lifetime',
                    'total_value' => [
                        'breakdowns' => [
                            0 => [
                                'dimension_keys' => ['city'],
                                'results' => [
                                    0 => [
                                        'dimension_values' => ['Sydney, New South Wales'],
                                        'value' => 631
                                    ],
                                    1 => [
                                        'dimension_values' => ['London, England'],
                                        'value' => 1142
                                    ],
                                    2 => [
                                        'dimension_values' => ['Casablanca, Grand Casablanca'],
                                        'value' => 321
                                    ],
                                ]
                            ]
                        ],
                    ],
                ],
            ]
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedAudienceData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $audienceData = $facebook->getPageInsightsAudienceData(
            self::FB_PAGE_ID,
            'follower_demographics',
            'city'
        );

        $this->assertEquals(['city'], $audienceData['dimension_keys']);
    }

    public function testGetPageInsightsAudienceDataEmptyResponse()
    {
        $decodedAudienceData = [
            'data' => []
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedAudienceData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $audienceData = $facebook->getPageInsightsAudienceData(
            self::FB_PAGE_ID,
            'follower_demographics',
            'city'
        );

        $this->assertEquals([], $audienceData);
    }

    public function testGetPagePostGraphMetricsData()
    {
        $decodedGraphResponseData = [
            'reactions' => [
                'data' => [],
                'summary' => ['total_count' => 123],
            ],
            'comments' => [
                'data' => [],
                'summary' => ['total_count' => 12],
            ],
            'likes' => [
                'data' => [],
                'summary' => ['total_count' => 30],
            ],
            'shares' => [
                'count' => 10,
            ],
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedGraphResponseData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $graphData = $facebook->getPagePostGraphMetricsData(
            self::FB_PAGE_ID,
            self::FB_POST_ID,
            ['comments', 'reactions', 'likes', 'shares']
        );

        $this->assertEquals($graphData['comments'], 12);
        $this->assertEquals($graphData['reactions'], 123);
        $this->assertEquals($graphData['likes'], 30);
        $this->assertEquals($graphData['shares'], 10);
    }

    // Test the case when shares count is not in the response.
    // In this case we default shares count to 0.
    public function testGetPagePostGraphMetricsDataShouldHaveSharesCount()
    {
        $decodedGraphResponseData = [
            'reactions' => [
                'data' => [],
                'summary' => ['total_count' => 123],
            ],
            'comments' => [
                'data' => [],
                'summary' => ['total_count' => 12],
            ],
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedGraphResponseData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $graphData = $facebook->getPagePostGraphMetricsData(
            self::FB_PAGE_ID,
            self::FB_POST_ID,
            ['comments', 'reactions', 'likes', 'shares']
        );

        $this->assertEquals($graphData['comments'], 12);
        $this->assertEquals($graphData['reactions'], 123);
        $this->assertEquals($graphData['shares'], 0);
    }

    public function testGetPagePostGraphMetricsShouldReturnEmptyIfNoResponse()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn([])
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $graphData = $facebook->getPagePostGraphMetricsData(
            self::FB_PAGE_ID,
            self::FB_POST_ID,
            ['comments', 'reactions']
        );
        $this->assertEquals($graphData, []);
    }

    public function testGetPageBatchPostsGraphMetricsData()
    {
        $facebook = new Facebook();

        $decodedGraphResponseData1 = [
            'reactions' => [
                'summary' => ['total_count' => 123],
            ],
            'comments' => [
                'summary' => ['total_count' => 12],
            ],
            'shares' => [
                'count' => 3
            ]
        ];

        $decodedGraphResponseData2 = [
            'reactions' => [
                'data' => [],
                'summary' => ['total_count' => 111],
            ],
            'comments' => [
                'data' => [],
                'summary' => ['total_count' => 2222],
            ],
            'shares' => [
                'count' => 6
            ]
        ];

        $responseMock1 = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedGraphResponseData1)
            ->getMock();
        $responseMock2 = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedGraphResponseData2)
            ->getMock();

        $getIteratorMock = new ArrayIterator([
            "11111_22222" => $responseMock1,
            "33333_444444" => $responseMock2
        ]);

        $responseBatchMock = m::mock('\Facebook\FacebookBatchResponse')
            ->shouldReceive('getIterator')
            ->once()
            ->andReturn($getIteratorMock)
            ->getMock();

        $requestMock1 = m::mock('\Facebook\FacebookRequest');
        $requestMock2 = m::mock('\Facebook\FacebookRequest');

        $postIds = ['11111_22222', '33333_444444'];
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebook->setFacebookLibrary($facebookMock);


        $facebookMock->shouldReceive('request')->twice()->andReturn($requestMock1, $requestMock2);

        $facebookMock->shouldReceive('sendBatchRequest')->once()->andReturn($responseBatchMock);

        $graphData = $facebook->getPageBatchPostsGraphMetricsData($postIds, ['comments', 'reactions', 'shares']);

        $this->assertEquals($graphData['11111_22222']['comments'], 12);
        $this->assertEquals($graphData['11111_22222']['reactions'], 123);
        $this->assertEquals($graphData['11111_22222']['shares'], 3);

        $this->assertEquals($graphData['33333_444444']['comments'], 2222);
        $this->assertEquals($graphData['33333_444444']['reactions'], 111);
        $this->assertEquals($graphData['33333_444444']['shares'], 6);
    }

    public function testGetPageBatchPostsInsightsMetricsData()
    {
        $facebook = new Facebook();

        $decodedResponseData1 = [
            'data' => [
                [
                    'name' => 'post_impressions',
                    'period' => 'lifetime',
                    'values' => [['value' => 5]]
                ],
                [
                    'name' => 'post_fan_reach',
                    'period' => 'lifetime',
                    'values' => [['value' => 12]]
                ],
                [
                    'name' => 'post_consumptions',
                    'period' => 'lifetime',
                    'values' => [['value' => 4]]
                ],
            ]
        ];

        $decodedResponseData2 = [
            'data' => [
                [
                    'name' => 'post_impressions',
                    'period' => 'lifetime',
                    'values' => [['value' => 14]]
                ],
                [
                    'name' => 'post_fan_reach',
                    'period' => 'lifetime',
                    'values' => [['value' => 28]]
                ],
                [
                    'name' => 'post_consumptions',
                    'period' => 'lifetime',
                    'values' => [['value' => 42]]
                ],
            ]
        ];

        $responseMock1 = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedResponseData1)
            ->getMock();
        $responseMock2 = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedResponseData2)
            ->getMock();

        $getIteratorMock = new ArrayIterator([
            "11111_22222" => $responseMock1,
            "33333_444444" => $responseMock2
        ]);

        $responseBatchMock = m::mock('\Facebook\FacebookBatchResponse')
            ->shouldReceive('getIterator')
            ->once()
            ->andReturn($getIteratorMock)
            ->getMock();

        $requestMock1 = m::mock('\Facebook\FacebookRequest');
        $requestMock2 = m::mock('\Facebook\FacebookRequest');

        $postIds = ['11111_22222', '33333_444444'];
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebook->setFacebookLibrary($facebookMock);


        $facebookMock->shouldReceive('request')->twice()->andReturn($requestMock1, $requestMock2);

        $facebookMock->shouldReceive('sendBatchRequest')->once()->andReturn($responseBatchMock);

        $graphData = $facebook->getPageBatchPostsInsightsMetricData($postIds, ['post_impressions', 'post_fan_reach', 'post_consumptions']);

        $this->assertEquals($graphData['11111_22222']['post_impressions'], 5);
        $this->assertEquals($graphData['11111_22222']['post_fan_reach'], 12);
        $this->assertEquals($graphData['11111_22222']['post_consumptions'], 4);

        $this->assertEquals($graphData['33333_444444']['post_impressions'], 14);
        $this->assertEquals($graphData['33333_444444']['post_fan_reach'], 28);
        $this->assertEquals($graphData['33333_444444']['post_consumptions'], 42);
    }

    public function testGetPageBatchPostsInsightsMetricDataWithTotalValues()
    {
        $facebook = new Facebook();

        $decodedResponseData1 = [
            'data' => [
                [
                    'name' => 'post_impressions',
                    'period' => 'lifetime',
                    'total_value' => 150,
                    'values' => [['value' => 150]]
                ],
                [
                    'name' => 'post_fan_reach',
                    'period' => 'lifetime',
                    'total_value' => 300,
                    'values' => [['value' => 300]]
                ],
                [
                    'name' => 'post_consumptions',
                    'period' => 'lifetime',
                    'total_value' => 75,
                    'values' => [['value' => 75]]
                ],
            ]
        ];

        $decodedResponseData2 = [
            'data' => [
                [
                    'name' => 'post_impressions',
                    'period' => 'lifetime',
                    'total_value' => 250,
                    'values' => [['value' => 250]]
                ],
                [
                    'name' => 'post_fan_reach',
                    'period' => 'lifetime',
                    'total_value' => 500,
                    'values' => [['value' => 500]]
                ],
                [
                    'name' => 'post_consumptions',
                    'period' => 'lifetime',
                    'total_value' => 125,
                    'values' => [['value' => 125]]
                ],
            ]
        ];

        $responseMock1 = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedResponseData1)
            ->getMock();
        $responseMock2 = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedResponseData2)
            ->getMock();

        $getIteratorMock = new ArrayIterator([
            "11111_22222" => $responseMock1,
            "33333_444444" => $responseMock2
        ]);

        $responseBatchMock = m::mock('\Facebook\FacebookBatchResponse')
            ->shouldReceive('getIterator')
            ->once()
            ->andReturn($getIteratorMock)
            ->getMock();

        $requestMock1 = m::mock('\Facebook\FacebookRequest');
        $requestMock2 = m::mock('\Facebook\FacebookRequest');

        $postIds = ['11111_22222', '33333_444444'];
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebook->setFacebookLibrary($facebookMock);

        $facebookMock->shouldReceive('request')->twice()->andReturn($requestMock1, $requestMock2);
        $facebookMock->shouldReceive('sendBatchRequest')->once()->andReturn($responseBatchMock);

        $graphData = $facebook->getPageBatchPostsInsightsMetricData(
            $postIds, 
            ['post_impressions', 'post_fan_reach', 'post_consumptions'],
            'total_value'
        );

        $this->assertEquals($graphData['11111_22222']['post_impressions'], 150);
        $this->assertEquals($graphData['11111_22222']['post_fan_reach'], 300);
        $this->assertEquals($graphData['11111_22222']['post_consumptions'], 75);

        $this->assertEquals($graphData['33333_444444']['post_impressions'], 250);
        $this->assertEquals($graphData['33333_444444']['post_fan_reach'], 500);
        $this->assertEquals($graphData['33333_444444']['post_consumptions'], 125);
    }

    public function testGetPagePostInsightsMetricDataReturnsEmptyOnNullResponse()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturnNull()
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $data = $facebook->getPagePostInsightsMetricData(
            self::FB_PAGE_ID,
            self::FB_POST_ID,
            ['post_impressions', 'post_fan_reach', 'post_consumptions']
        );

        $this->assertEquals($data, []);
    }

    public function testGetPagePostInsightsMetricData()
    {
        $decodedResponseData = [
            'data' => [
                [
                    'name' => 'post_impressions',
                    'period' => 'lifetime',
                    'values' => [['value' => 5]]
                ],
                [
                    'name' => 'post_fan_reach',
                    'period' => 'lifetime',
                    'values' => [['value' => 12]]
                ],
                [
                    'name' => 'post_consumptions',
                    'period' => 'lifetime',
                    'values' => [['value' => 4]]
                ],
            ]
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedResponseData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $insightsData = $facebook->getPagePostInsightsMetricData(
            self::FB_PAGE_ID,
            self::FB_POST_ID,
            ['post_impressions', 'post_fan_reach', 'post_consumptions']
        );


        $this->assertEquals($insightsData['post_impressions'], 5);
        $this->assertEquals($insightsData['post_fan_reach'], 12);
        $this->assertEquals($insightsData['post_consumptions'], 4);
    }

    public function testGetPagePostsShouldReturnEmptyOnEmptyBody()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getBody')
            ->once()
            ->andReturn('')
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('get')->once()->andThrow($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $posts = $facebook->getPagePosts(
            self::FB_PAGE_ID,
            strtotime("yesterday"),
            strtotime("now"),
            10
        );

        $this->assertEquals($posts, []);
    }

    public function testGetPagePostsShouldReturnCorrectData()
    {
        $response = [
            'data' => [
                [
                    'created_time' => strtotime('2017-04-20T17:50:27+0000'),
                    'id' => '511222705738444_744511765742869'
                ],
                [
                    'created_time' => strtotime('2017-04-19T18:23:52+0000'),
                    'id' => '511222705738444_744029602457752'
                ],
                [
                    'created_time' => strtotime('2017-04-19T18:20:58+0000'),
                    'id' => '511222705738444_744027942457918'
                ]
            ]
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getBody')
            ->once()
            ->andReturn(json_encode($response))
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('get')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $posts = $facebook->getPagePosts(
            self::FB_PAGE_ID,
            strtotime("yesterday"),
            strtotime("now"),
            10
        );

        $this->assertEquals(count($posts), 3);
        $this->assertEquals($posts, $response['data']);
    }

    public function testGetPagePostsShouldUseRightSinceAndUntilArgs()
    {
        $response = [
            'data' => [
                [
                    'created_time' => strtotime('2017-04-20T17:50:27+0000'),
                    'id' => '511222705738444_744511765742869'
                ],
            ]
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getBody')
            ->once()
            ->andReturn(json_encode($response))
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');

        $since  = "1493826552";
        $until = "1496418552";
        $params = [
            "limit" => 100,
            "until" => $until,
            "since" => $since,
        ];
        $expectedGetParams = ["/2222222/posts?since={$since}&until={$until}&limit=100&fields=id,created_time,updated_time,attachments,message"];
        $facebookMock->shouldReceive('get')->withArgs($expectedGetParams)->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);
        $facebook->getPagePosts(self::FB_PAGE_ID, $since, $until, 100);
    }

    public function testGetUserMediasShouldReturnCorrectData()
    {
        $graphEdge = new GraphEdge(new FacebookRequest(), [
            new GraphNode([
                'id' => '511222705738444_744511765742869',
                'timestamp' => '2018-04-03T03:38:30+0000',
            ]),
            new GraphNode([
                'id' => '511222705738444_744029602457752',
                'timestamp' => '2018-04-02T06:38:30+0000',
            ]),
            new GraphNode([
                'id' => '511222705738444_744029602457752',
                'timestamp' => '2018-03-03T03:38:30+0000',
            ]),
            new GraphNode([
                'id' => '511222705738444_744029602457752',
                'timestamp' => '2018-01-03T03:38:30+0000',
            ]),
        ]);

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getGraphEdge')
            ->once()
            ->andReturn($graphEdge)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('get')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $posts = $facebook->getUserMedias(
            self::IG_USER_ID,
            strtotime("2018-04-02T00:00:30+0000"),
            strtotime("now")
        );
        // only two posts fit within the given date range
        $this->assertEquals(count($posts), 2);
        $this->assertEquals($posts[0], "511222705738444_744511765742869");
        $this->assertEquals($posts[1], "511222705738444_744029602457752");
    }

    public function testGetUserMediasShouldReturnEmptyOnEmptyBody()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getBody')
            ->once()
            ->andReturn(json_encode([]))
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('get')->once()->andThrow($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $posts = $facebook->getPagePosts(
            self::IG_USER_ID,
            strtotime("yesterday"),
            strtotime("now"),
            10
        );

        $this->assertEquals($posts, []);
    }

    public function testGetUserMediasShouldUseRightSinceAndUntilArgs()
    {
        $graphEdge = new GraphEdge(
            new FacebookRequest(),
            [
                new GraphNode([
                    'id' => '511222705738444_744511765742869',
                    'timestamp' => '2018-04-02T00:00:30+0000'
                ]),
            ]
        );

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getGraphEdge')
            ->once()
            ->andReturn($graphEdge)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');

        $since  = "1493826552";
        $until = "1496418552";
        $expectedGetParams = ["/55556666/media?fields=timestamp"];
        $facebookMock->shouldReceive('get')->withArgs($expectedGetParams)->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);
        $facebook->getUserMedias(self::IG_USER_ID, $since, $until);
    }

    public function testGetBatchMediaBasicData()
    {
        $facebook = new Facebook();

        $decodedGraphResponseData1 = [
            'caption' => 'Test Caption 1',
            'media_type' => 'IMAGE',
        ];

        $decodedGraphResponseData2 = [
            'caption' => 'Test Caption 2',
            'media_type' => 'VIDEO',
        ];

        $responseMock1 = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedGraphResponseData1)
            ->getMock();
        $responseMock2 = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedGraphResponseData2)
            ->getMock();

        $getIteratorMock = new ArrayIterator([
            "11111_22222" => $responseMock1,
            "33333_444444" => $responseMock2
        ]);

        $responseBatchMock = m::mock('\Facebook\FacebookBatchResponse')
            ->shouldReceive('getIterator')
            ->once()
            ->andReturn($getIteratorMock)
            ->getMock();

        $requestMock1 = m::mock('\Facebook\FacebookRequest');
        $requestMock2 = m::mock('\Facebook\FacebookRequest');

        $mediaIds = ['11111_22222', '33333_444444'];
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebook->setFacebookLibrary($facebookMock);


        $facebookMock->shouldReceive('request')->twice()->andReturn($requestMock1, $requestMock2);

        $facebookMock->shouldReceive('sendBatchRequest')->once()->andReturn($responseBatchMock);

        $graphData = $facebook->getBatchMediaBasicData($mediaIds, ['caption', 'media_type']);

        $this->assertEquals($graphData['11111_22222']['caption'], 'Test Caption 1');
        $this->assertEquals($graphData['11111_22222']['media_type'], 'IMAGE');


        $this->assertEquals($graphData['33333_444444']['caption'], 'Test Caption 2');
        $this->assertEquals($graphData['33333_444444']['media_type'], 'VIDEO');
    }

    public function testGetInstagramGraphNodeMetadataShouldReturnEmptyIfError()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('isError')
            ->once()
            ->andReturn(true)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $insightsData = $facebook->getInstagramGraphNodeMetadata(
            self::FB_PAGE_ID,
            [
                'followers_count',
                'follows_count',
            ]
        );
        $this->assertEquals($insightsData, []);
    }

    public function testSubscribeToWebhookSubscribesAPageIdToOurWebhook()
    {
        $facebook = new Facebook();
        $pageId = self::FB_PAGE_ID;
        $facebookMock = m::mock('\Facebook\Facebook');

        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn(['success' => true])
            ->getMock();

        $facebookMock
            ->shouldReceive('sendRequest')
            ->once()
            ->with('POST', "/${pageId}/subscribed_apps", ["subscribed_fields" => 'email' ])
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->subscribeToWebhook($pageId);
        $this->assertEquals($response, ['success' => true]);
    }

    public function testUnsubscribeFromWebhookUnsubscribesAPageIdToOurWebhook()
    {
        $facebook = new Facebook();
        $pageId = self::FB_PAGE_ID;
        $facebookMock = m::mock('\Facebook\Facebook');

        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn(['success' => true])
            ->getMock();

        $facebookMock
            ->shouldReceive('sendRequest')
            ->once()
            ->with('DELETE', "/${pageId}/subscribed_apps", [])
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->unsubscribeFromWebhook($pageId);
        $this->assertEquals($response, ['success' => true]);
    }

    public function testGetInstagramGraphNodeMetadataShouldReturnEmptyIfNoResponse()
    {
        $facebook = new Facebook();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn(null);
        $facebook->setFacebookLibrary($facebookMock);

        $insightsData = $facebook->getInstagramGraphNodeMetadata(
            self::FB_PAGE_ID,
            [
                'followers_count',
                'follows_count',
            ]
        );
        $this->assertEquals($insightsData, []);
    }

    public function testGetInstagramGraphNodeMetadataShouldReturnTheMetrics()
    {
        $facebook = new Facebook();
        $facebookMock = m::mock('\Facebook\Facebook');
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('isError')
            ->once()
            ->andReturn(false)
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn([
                'followers_count' => 42,
                'follows_count' => 12,
                'id' => self::FB_PAGE_ID
            ])
            ->getMock();
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $insightsData = $facebook->getInstagramGraphNodeMetadata(
            self::FB_PAGE_ID,
            [
                'followers_count',
                'follows_count',
            ]
        );
        $this->assertEquals($insightsData, [
            'followers_count' => 42,
            'follows_count' => 12,
            'id' => self::FB_PAGE_ID
        ]);
    }

    public function testBrakeIntervalInLegalBatches()
    {
        $facebook = new Facebook();

        // 30 days
        $until = strtotime('now');
        $since = strtotime('- 30 days');

        $intervals = $facebook->getIntervalsForPeriod($since, $until);
        $this->assertIsArray($intervals);
        $this->assertEquals([
            [
                'since' => $since,
                'until' => $until,
            ],
        ], $intervals);

        // 93 days
        $until = strtotime('today');
        $since = strtotime('- 93 days');

        $intervals = $facebook->getIntervalsForPeriod($since, $until);
        $this->assertIsArray($intervals);
        $this->assertEquals([
            [
                'since' => $since,
                'until' => strtotime("+30 days", $since),
            ],
            [
                'since' => strtotime("+30 days", $since),
                'until' => strtotime("+60 days", $since),
            ],
            [
                'since' => strtotime("+60 days", $since),
                'until' => strtotime("+90 days", $since),
            ],
            [
                'since' => strtotime("+90 days", $since),
                'until' => $until,
            ],
        ], $intervals);
    }

    public function testGetPageAccessTokenShouldReturnNullIfError()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('isError')
            ->once()
            ->andReturn(true)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $accessToken = $facebook->getPageAccessToken(self::FB_PAGE_ID);
        $this->assertNull($accessToken);
    }


    public function testGetPageAccessTokenShouldReturnNullIfNoResponse()
    {
        $facebook = new Facebook();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn(null);
        $facebook->setFacebookLibrary($facebookMock);

        $accessToken = $facebook->getPageAccessToken(self::FB_PAGE_ID);
        $this->assertNull($accessToken);
    }

    public function testGetPageAccessTokenShouldReturnThePageAccessToken()
    {
        $facebook = new Facebook();
        $facebookMock = m::mock('\Facebook\Facebook');
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('isError')
            ->once()
            ->andReturn(false)
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn([
                'access_token' => 'test-access-token-1',
            ])
            ->getMock();
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $accessToken = $facebook->getPageAccessToken(self::FB_PAGE_ID);
        $this->assertEquals($accessToken, 'test-access-token-1');
    }

    public function testGetMeReturnsPageInformation()
    {
        $me = [
            'id' => '2345',
            'name' => 'Test Page',
        ];
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($me)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock
            ->shouldReceive('sendRequest')
            ->once()
            ->with('GET', '/me', [])
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getMe();

        $this->assertEquals('2345', $response['id']);
    }

    public function testGetInstagramUserStories()
    {
        $userId = "123456789";
        $data = [
            'timestamp' => '123456',
            'caption' => 'Test Caption',
        ];

        $fieldsArray = ['caption', 'timestamp'];
        $fields = join(",", $fieldsArray);

        $facebook = new Facebook();

        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('isError')
            ->once()
            ->andReturn(false)
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn(['data' => 'valid_data'])
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock
            ->shouldReceive('sendRequest')
            ->once()
            ->with('GET', "/${userId}/stories", ["fields" => $fields])
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getInstagramUserStories($userId, $fieldsArray);

        $this->assertEquals($response, 'valid_data');
    }

    public function testGetInstagramUserStoriesReturnsNullIfError()
    {
        $userId = "123456789";
        $data = [
            'timestamp' => '123456',
            'caption' => 'Test Caption',
        ];

        $fieldsArray = ['caption', 'timestamp'];
        $fields = join(",", $fieldsArray);

        $facebook = new Facebook();

        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('isError')
            ->once()
            ->andReturn(true)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock
            ->shouldReceive('sendRequest')
            ->once()
            ->with('GET', "/${userId}/stories", ["fields" => $fields])
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getInstagramUserStories($userId, $fieldsArray);

        $this->assertEquals($response, null);
    }

    public function testGetInstagramStoryInsights()
    {
        $mediaId = "123456789";
        $decodedInsightsResponseData = [
            'data' => [
                [
                    'name' => 'reach',
                    'period' => 'lifetime',
                    'values' => [
                        [
                            'value' => 123,
                        ],
                    ],
                ],
                [
                    'name' => 'taps_forward',
                    'period' => 'lifetime',
                    'values' => [
                        [
                            'value' => 12,
                        ],
                    ],
                ],
                [
                    'name' => 'impressions',
                    'period' => 'lifetime',
                    'values' => [
                        [
                            'value' => 200,
                        ],
                    ],
                ],
            ]
        ];

        $metricsArray = ['reach', 'impressions', 'taps_forward'];
        $metrics = join(",", $metricsArray);

        $facebook = new Facebook();

        $responseMock = m::mock('\Facebook\FacebookResponse');
        $responseMock->shouldReceive('isError')->once()->andReturn(false);
        $responseMock->shouldReceive('getDecodedBody')->once()->andReturn($decodedInsightsResponseData);

        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')
            ->once()
            ->with('GET', '/' . $mediaId . '/insights', ["metric" => $metrics])
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getInstagramStoryInsights($mediaId, $metricsArray);

        $this->assertEquals($response, [
            'reach' => 123,
            'taps_forward' => 12,
            'impressions' => 200
        ]);
    }

    public function testGetInstagramStoryInsightsNullIfError()
    {
        $mediaId = "123456789";

        $metricsArray = ['reach', 'impressions', 'taps_forward'];
        $metrics = join(",", $metricsArray);

        $facebook = new Facebook();

        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('isError')
            ->once()
            ->andReturn(true)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock
            ->shouldReceive('sendRequest')
            ->once()
            ->with('GET', '/' . $mediaId . '/insights', ["metric" => $metrics])
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getInstagramStoryInsights($mediaId, $metricsArray);

        $this->assertEquals($response, null);
    }

    public function testGetInstagramStoryNavigationInsightsNullIfError()
    {
        $mediaId = '123456789';

        $params = [
            'metric' => 'navigation',
            'breakdown' => 'story_navigation_action_type',
        ];

        $facebook = new Facebook();

        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('isError')
            ->once()
            ->andReturn(true)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock
            ->shouldReceive('sendRequest')
            ->once()
            ->with('GET', '/' . $mediaId . '/insights', $params)
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getInstagramStoryNavigationInsights($mediaId);

        $this->assertEquals($response, null);
    }

    public function testGetInstagramStoryNavigationInsightsReturnsData()
    {
        $mediaId = '123456789';

        $params = [
            'metric' => 'navigation',
            'breakdown' => 'story_navigation_action_type',
        ];

        $facebook = new Facebook();

        $decodedNavigationData = [
            'data' => [
                0 => [
                    'name' => 'navigation',
                    'period' => 'lifetime',
                    'total_value' => [
                        'breakdowns' => [
                            0 => [
                                'dimension_keys' => ['story_navigation_action_type'],
                                'results' => [
                                    0 => [
                                        'dimension_values' => ['swipe_forward'],
                                        'value' => 2
                                    ],
                                    1 => [
                                        'dimension_values' => ['tap_exit'],
                                        'value' => 20
                                    ],
                                    2 => [
                                        'dimension_values' => ['tap_back'],
                                        'value' => 4
                                    ],
                                    3 => [
                                        'dimension_values' => ['tap_forward'],
                                        'value' => 75
                                    ],
                                ]
                            ]
                        ],
                    ],
                ],
            ]
        ];

        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('isError')
            ->andReturn(false)
            ->shouldReceive('getDecodedBody')
            ->andReturn($decodedNavigationData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock
            ->shouldReceive('sendRequest')
            ->once()
            ->with('GET', '/' . $mediaId . '/insights', $params)
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getInstagramStoryNavigationInsights($mediaId);

        $this->assertEquals($response['results'][0]['dimension_values'][0], 'swipe_forward');
        $this->assertEquals($response['results'][0]['value'], 2);
    }

    public function testGetInstagramStoryNavigationInsightsEmptyResponse()
    {
        $mediaId = '123456789';
        $params = [
            'metric' => 'navigation',
            'breakdown' => 'story_navigation_action_type',
        ];

        $facebook = new Facebook();
        $decodedNavigationData = [
            'data' => []
        ];

        $responseMock = m::mock('\Facebook\FacebookResponse')
        ->shouldReceive('isError')
        ->andReturn(false)
            ->shouldReceive('getDecodedBody')
            ->andReturn($decodedNavigationData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock
            ->shouldReceive('sendRequest')
            ->once()
            ->with('GET', '/' . $mediaId . '/insights', $params)
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getInstagramStoryNavigationInsights($mediaId);

        $this->assertEquals([], $response);
    }

    public function testGetAccountsWorksAsExpected()
    {
        $me = [
            [
                'instagram_business_account' => [
                    'id' => '123455678'
                ],
                'id' => '2345',
                'access_token' => 'Test Page',
            ]
        ];
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($me)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock
            ->shouldReceive('sendRequest')
            ->once()
            ->with('GET', '/me/accounts', ["fields" => 'instagram_business_account,access_token'])
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getAccounts();

        $this->assertEquals('2345', $response[0]['id']);
        $this->assertEquals('123455678', $response[0]['instagram_business_account']['id']);
    }

    public function testGetAccountsWorksWithoutPagination()
    {
        $me = [
            'data' => [
                [
                    'id' => 'act_123456789',
                    'account_id' => '123456789'
                ]
            ],
            'paging' => [
                'before' => 'abc123456',
                'after' => 'abc123456'
            ]
        ];
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($me)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock
            ->shouldReceive('sendRequest')
            ->once()
            ->with('GET', '/me/adaccounts', ['fields' => 'account_id,currency,promote_pages{id,instagram_business_account}'])
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getAdAccounts();

        $this->assertEquals('act_123456789', $response['data'][0]['id']);
    }

    public function testGetAccountsWorksWithPagination()
    {
        $me = [
            'data' => [
                [
                    'id' => 'act_123456999',
                    'account_id' => '123456999'
                ]
            ],
            'paging' => [
                'before' => 'abc123456',
                'after' => 'abc123456'
            ]
        ];
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($me)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock
            ->shouldReceive('sendRequest')
            ->once()
            ->with('GET', '/me/adaccounts', [
                'fields' => 'account_id,currency,promote_pages{id,instagram_business_account}',
                'after' => 'mnb9876'
            ])
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getAdAccounts('mnb9876');

        $this->assertEquals('act_123456999', $response['data'][0]['id']);
    }

    public function testGetTokenScopes()
    {
        $inputToken = 'test_token';
        $scopes = [
            'data' => [
                'scopes' => [
                    'email',
                    'public_profile'
                ]
            ]
        ];
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($scopes)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock
            ->shouldReceive('sendRequest')
            ->once()
            ->with('GET', '/debug_token', ['fields' => 'scopes', 'input_token' => 'test_token'])
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getTokenScopes($inputToken);

        $this->assertEquals(['email', 'public_profile'], $response['data']['scopes']);
    }

    public function testGetInstagramStoryInsightsWithMetricType()
    {
        $mediaId = "123456789";
        $decodedInsightsResponseData = [
            'data' => [
                [
                    'name' => 'reach',
                    'period' => 'lifetime',
                    'values' => [
                        [
                            'value' => 123,
                        ],
                    ],
                ],
                [
                    'name' => 'views',
                    'period' => 'lifetime',
                    'values' => [
                        [
                            'value' => 500,
                        ],
                    ],
                ],
            ]
        ];

        $metricsArray = ['reach', 'views'];
        $metrics = join(",", $metricsArray);

        $facebook = new Facebook();

        $responseMock = m::mock('\Facebook\FacebookResponse');
        $responseMock->shouldReceive('isError')->once()->andReturn(false);
        $responseMock->shouldReceive('getDecodedBody')->once()->andReturn($decodedInsightsResponseData);

        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')
            ->once()
            ->with('GET', '/' . $mediaId . '/insights', ["metric" => $metrics, "metric_type" => "total_values"])
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getInstagramStoryInsights($mediaId, $metricsArray, "total_values");

        $this->assertEquals($response, [
            'reach' => 123,
            'views' => 500
        ]);
    }

    public function testGetPageInsightsForTotalValueMetrics()
    {
        $decodedResponseData = [
            'data' => [
                [
                    'name' => 'page_impressions',
                    'period' => 'day',
                    'total_value' => [
                        'value' => 500
                    ]
                ],
                [
                    'name' => 'page_engaged_users',
                    'period' => 'day',
                    'total_value' => [
                        'value' => 100
                    ]
                ]
            ]
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedResponseData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');

        $metrics = ['page_impressions', 'page_engaged_users'];
        $period = 'day';
        $since = '1493826552';
        $until = '1496418552';
        $params = [
            'metric' => join(',', $metrics),
            'metric_type' => 'total_value',
            'period' => $period,
            'since' => $since,
            'until' => $until,
        ];

        $facebookMock->shouldReceive('sendRequest')
            ->once()
            ->with('GET', '/' . self::FB_PAGE_ID . '/insights', $params)
            ->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getPageInsightsForTotalValueMetrics(
            self::FB_PAGE_ID,
            $metrics,
            $period,
            $since,
            $until
        );

        $this->assertEquals(500, $response['page_impressions']);
        $this->assertEquals(100, $response['page_engaged_users']);
    }

    public function testGetPageInsightsForTotalValueMetricsEmptyResponse()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn(['data' => []])
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getPageInsightsForTotalValueMetrics(
            self::FB_PAGE_ID,
            ['page_impressions'],
            'day',
            '1493826552',
            '1496418552'
        );

        $this->assertEquals([], $response);
    }

    public function testGetPageInsightsForTotalValueMetricsNullResponse()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn(null)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $response = $facebook->getPageInsightsForTotalValueMetrics(
            self::FB_PAGE_ID,
            ['page_impressions'],
            'day',
            '1493826552',
            '1496418552'
        );

        $this->assertEquals([], $response);
    }

    public function testGetPageInsightsBatchTotalValueMetrics()
    {
        $decodedResponseData = [
            'data' => [
                [
                    'name' => 'page_impressions',
                    'period' => 'day',
                    'total_value' => [
                        'value' => 500
                    ]
                ],
                [
                    'name' => 'page_engaged_users',
                    'period' => 'day',
                    'total_value' => [
                        'value' => 100
                    ]
                ]
            ]
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->times(102)
            ->andReturn($decodedResponseData)
            ->getMock();

        $responses = [];
        for ($i = 0; $i < 51; $i++) {
            $responses['1493826552_' . $i] = $responseMock;
        }

        $getIteratorMock = new ArrayIterator($responses);
        $responseBatchMock = m::mock('\Facebook\FacebookBatchResponse')
            ->shouldReceive('getIterator')
            ->times(2)
            ->andReturn($getIteratorMock)
            ->getMock();

        $requestMock = m::mock('\Facebook\FacebookRequest');
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebook->setFacebookLibrary($facebookMock);

        $metrics = ['page_impressions', 'page_engaged_users'];
        $period = 'day';
        $days = [];
        for ($i = 0; $i < 51; $i++) {
            $days[] = [
                'since' => '1493826552_' . $i,
                'until' => '1493912952_' . $i
            ];
        }

        $facebookMock->shouldReceive('request')->times(51)->andReturn($requestMock);
        $facebookMock->shouldReceive('sendBatchRequest')->times(2)->andReturn($responseBatchMock);

        $response = $facebook->getPageInsightsBatchTotalValueMetrics(
            self::FB_PAGE_ID,
            $metrics,
            $period,
            $days
        );

        $this->assertCount(51, $response);
        foreach ($response as $timestamp => $data) {
            $this->assertEquals(500, $data['page_impressions']);
            $this->assertEquals(100, $data['page_engaged_users']);
        }
    }

    public function testGetPageInsightsBatchTotalValueMetricsEmptyResponse()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn(['data' => []])
            ->getMock();

        $getIteratorMock = new ArrayIterator([
            '1493826552' => $responseMock
        ]);

        $responseBatchMock = m::mock('\Facebook\FacebookBatchResponse')
            ->shouldReceive('getIterator')
            ->once()
            ->andReturn($getIteratorMock)
            ->getMock();

        $requestMock = m::mock('\Facebook\FacebookRequest');
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebook->setFacebookLibrary($facebookMock);

        $metrics = ['page_impressions'];
        $period = 'day';
        $days = [
            [
                'since' => '1493826552',
                'until' => '1493912952'
            ]
        ];

        $facebookMock->shouldReceive('request')->once()->andReturn($requestMock);
        $facebookMock->shouldReceive('sendBatchRequest')->once()->andReturn($responseBatchMock);

        $response = $facebook->getPageInsightsBatchTotalValueMetrics(
            self::FB_PAGE_ID,
            $metrics,
            $period,
            $days
        );

        $this->assertEquals([
            '1493826552' => []
        ], $response);
    }

    public function testGetPageInsightsBatchTotalValueMetricsNullResponse()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn(null)
            ->getMock();

        $getIteratorMock = new ArrayIterator([
            '1493826552' => $responseMock
        ]);

        $responseBatchMock = m::mock('\Facebook\FacebookBatchResponse')
            ->shouldReceive('getIterator')
            ->once()
            ->andReturn($getIteratorMock)
            ->getMock();

        $requestMock = m::mock('\Facebook\FacebookRequest');
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebook->setFacebookLibrary($facebookMock);

        $metrics = ['page_impressions'];
        $period = 'day';
        $days = [
            [
                'since' => '1493826552',
                'until' => '1493912952'
            ]
        ];

        $facebookMock->shouldReceive('request')->once()->andReturn($requestMock);
        $facebookMock->shouldReceive('sendBatchRequest')->once()->andReturn($responseBatchMock);

        $response = $facebook->getPageInsightsBatchTotalValueMetrics(
            self::FB_PAGE_ID,
            $metrics,
            $period,
            $days
        );

        $this->assertEquals([], $response);
    }

    public function testGetPageInsightsBatchTotalValueMetricsBatchSizeLimit()
    {
        $decodedResponseData = [
            'data' => [
                [
                    'name' => 'page_impressions',
                    'period' => 'day',
                    'total_value' => [
                        'value' => 500
                    ]
                ],
                [
                    'name' => 'page_engaged_users',
                    'period' => 'day',
                    'total_value' => [
                        'value' => 100
                    ]
                ]
            ]
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->times(102)
            ->andReturn($decodedResponseData)
            ->getMock();

        $responses = [];
        for ($i = 0; $i < 51; $i++) {
            $responses['1493826552_' . $i] = $responseMock;
        }

        $getIteratorMock = new ArrayIterator($responses);
        $responseBatchMock = m::mock('\Facebook\FacebookBatchResponse')
            ->shouldReceive('getIterator')
            ->times(2)
            ->andReturn($getIteratorMock)
            ->getMock();

        $requestMock = m::mock('\Facebook\FacebookRequest');
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebook->setFacebookLibrary($facebookMock);

        $metrics = ['page_impressions', 'page_engaged_users'];
        $period = 'day';
        $days = [];
        for ($i = 0; $i < 51; $i++) {
            $days[] = [
                'since' => '1493826552_' . $i,
                'until' => '1493912952_' . $i
            ];
        }

        $facebookMock->shouldReceive('request')->times(51)->andReturn($requestMock);
        $facebookMock->shouldReceive('sendBatchRequest')->times(2)->andReturn($responseBatchMock);

        $response = $facebook->getPageInsightsBatchTotalValueMetrics(
            self::FB_PAGE_ID,
            $metrics,
            $period,
            $days
        );

        $this->assertCount(51, $response);
        foreach ($response as $timestamp => $data) {
            $this->assertEquals(500, $data['page_impressions']);
            $this->assertEquals(100, $data['page_engaged_users']);
        }
    }
}
