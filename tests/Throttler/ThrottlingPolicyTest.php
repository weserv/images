<?php

namespace AndriesLouw\imagesweserv\Test\Throttler;

use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use AndriesLouw\imagesweserv\Throttler\ThrottlingPolicy;
use Cloudflare\Exception\AuthenticationException;
use Cloudflare\Exception\UnauthorizedException;
use Cloudflare\Zone\Firewall\AccessRules;
use Mockery\MockInterface;

class ThrottlingPolicyTest extends ImagesweservTestCase
{
    /**
     * AccessRules instance.
     *
     * @var AccessRules|MockInterface
     */
    protected $accessRules;

    /**
     * Throttling policy config.
     *
     * @var array
     */
    protected $config;

    /**
     * Throttling policy.
     *
     * @var ThrottlingPolicy
     */
    protected $policy;

    public function setUp()
    {
        $this->accessRules = $this->getMockery(AccessRules::class);
        $this->config = [
            'ban_time' => 60, // If exceed, ban for 60 minutes
            'cloudflare' => [
                'enabled' => true, // Is CloudFlare enabled?
                'email' => 'user@example.com',
                'auth_key' => '',
                'zone_id' => '7c5dae5552338874e5053f2534d2767a',
                'mode' => 'block' // The action to apply if the IP get's banned
            ]
        ];
        $this->policy = new ThrottlingPolicy($this->accessRules, $this->config);
    }

    public function testGetBanTime()
    {
        $this->assertEquals($this->config['ban_time'], $this->policy->getBanTime());
    }

    public function testIsCloudFlareEnabled()
    {
        $this->assertEquals($this->config['cloudflare']['enabled'], $this->policy->isCloudFlareEnabled());
    }

    public function testBanAtCloudFlare()
    {
        $ipAddress = '127.0.0.1';

        $response = (object)[
            'success' => true,
            'errors' => [new \stdClass()],
            'messages' => [new \stdClass()],
            'result' => (object)[
                'id' => '92f17202ed8bd63d69a66b86a49a8f6b',
                'notes' => 'This rule is on because of an event that occurred on date X',
                'allowed_modes' => [
                    'whitelist',
                    'block',
                    'challenge'
                ],
                'mode' => 'challenge',
                'configuration' => (object)[
                    'target' => 'ip',
                    'value' => $ipAddress,
                ],
                'scope' => (object)[
                    'id' => '7c5dae5552338874e5053f2534d2767a',
                    'email' => 'user@example.com',
                    'type' => 'user'
                ],
                'created_on' => '2014-01-01T05:20:00.12345Z',
                'modified_on' => '2014-01-01T05:20:00.12345Z'
            ]
        ];

        $this->accessRules->shouldReceive('create')->with(
            $this->config['cloudflare']['zone_id'],
            $this->config['cloudflare']['mode'],
            [
                'target' => 'ip',
                'value' => $ipAddress
            ],
            \Mockery::any()
        )->andReturn($response);

        $blockRuleId = @$this->policy->banAtCloudFlare($ipAddress);
        $this->assertEquals($response->result->id, $blockRuleId);
    }

    public function testUnbanAtCloudFlare()
    {
        $blockRuleId = '92f17202ed8bd63d69a66b86a49a8f6b';

        $response = (object)[
            'success' => true,
            'errors' => [new \stdClass()],
            'messages' => [new \stdClass()],
            'result' => (object)[
                'id' => '92f17202ed8bd63d69a66b86a49a8f6b',
            ]
        ];

        $this->accessRules->shouldReceive('delete_rule')->with(
            $this->config['cloudflare']['zone_id'],
            $blockRuleId
        )->andReturn($response);

        $successful = @$this->policy->unbanAtCloudFlare($blockRuleId);
        $this->assertTrue($successful);
    }

    /**
     * @expectedException \PHPUnit\Framework\Error\Warning
     */
    public function testAuthenticationExceptionBan()
    {
        $ipAddress = '127.0.0.1';

        $this->accessRules->shouldReceive('create')->with(
            $this->config['cloudflare']['zone_id'],
            $this->config['cloudflare']['mode'],
            [
                'target' => 'ip',
                'value' => $ipAddress
            ],
            \Mockery::any()
        )->andThrow(new AuthenticationException());

        $this->policy->banAtCloudFlare($ipAddress);
    }

    /**
     * @expectedException \PHPUnit\Framework\Error\Warning
     */
    public function testAuthenticationExceptionUnban()
    {
        $blockRuleId = '92f17202ed8bd63d69a66b86a49a8f6b';

        $this->accessRules->shouldReceive('delete_rule')->with(
            $this->config['cloudflare']['zone_id'],
            $blockRuleId
        )->andThrow(new AuthenticationException());

        $this->policy->unbanAtCloudFlare($blockRuleId);
    }

    /**
     * @expectedException \PHPUnit\Framework\Error\Warning
     */
    public function testUnauthorizedExceptionBan()
    {
        $ipAddress = '127.0.0.1';

        $this->accessRules->shouldReceive('create')->with(
            $this->config['cloudflare']['zone_id'],
            $this->config['cloudflare']['mode'],
            [
                'target' => 'ip',
                'value' => $ipAddress
            ],
            \Mockery::any()
        )->andThrow(new UnauthorizedException());

        $this->policy->banAtCloudFlare($ipAddress);
    }

    /**
     * @expectedException \PHPUnit\Framework\Error\Warning
     */
    public function testUnauthorizedExceptionUnban()
    {
        $blockRuleId = '92f17202ed8bd63d69a66b86a49a8f6b';

        $this->accessRules->shouldReceive('delete_rule')->with(
            $this->config['cloudflare']['zone_id'],
            $blockRuleId
        )->andThrow(new UnauthorizedException());

        $this->policy->unbanAtCloudFlare($blockRuleId);
    }

    public function testReturnFalseOnExceptionBan()
    {
        $ipAddress = '127.0.0.1';

        $this->accessRules->shouldReceive('create')->with(
            $this->config['cloudflare']['zone_id'],
            $this->config['cloudflare']['mode'],
            [
                'target' => 'ip',
                'value' => $ipAddress
            ],
            \Mockery::any()
        )->andThrow(new AuthenticationException());

        $successful = @$this->policy->banAtCloudFlare($ipAddress);
        $this->assertFalse($successful);
    }

    public function testReturnFalseOnExceptionUnban()
    {
        $blockRuleId = '92f17202ed8bd63d69a66b86a49a8f6b';

        $this->accessRules->shouldReceive('delete_rule')->with(
            $this->config['cloudflare']['zone_id'],
            $blockRuleId
        )->andThrow(new AuthenticationException());

        $successful = @$this->policy->unbanAtCloudFlare($blockRuleId);
        $this->assertFalse($successful);
    }
}
