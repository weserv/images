<?php

namespace Weserv\Images\Test\Throttler;

use Weserv\Images\Test\ImagesWeservTestCase;
use Weserv\Images\Throttler\ThrottlingPolicy;
use Cloudflare\API\Adapter\ResponseException;
use Cloudflare\API\Endpoints\AccessRules;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Mockery\MockInterface;

class ThrottlingPolicyTest extends ImagesWeservTestCase
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

        $this->accessRules->shouldReceive('createRule')->with(
            $this->config['cloudflare']['zone_id'],
            $this->config['cloudflare']['mode'],
            \Mockery::any(),
            \Mockery::any()
        )->andReturn(true);

        $successful = @$this->policy->banAtCloudFlare($ipAddress);
        $this->assertTrue($successful);
    }

    public function testUnbanAtCloudFlare()
    {
        $blockRuleId = '92f17202ed8bd63d69a66b86a49a8f6b';

        $this->accessRules->shouldReceive('deleteRule')->with(
            $this->config['cloudflare']['zone_id'],
            $blockRuleId
        )->andReturn(true);

        $successful = @$this->policy->unbanAtCloudFlare($blockRuleId);
        $this->assertTrue($successful);
    }

    /**
     * @expectedException \PHPUnit\Framework\Error\Warning
     */
    public function testResponseExceptionBan()
    {
        $ipAddress = '127.0.0.1';

        $this->accessRules->shouldReceive('createRule')->with(
            $this->config['cloudflare']['zone_id'],
            $this->config['cloudflare']['mode'],
            \Mockery::any(),
            \Mockery::any()
        )->andThrow(new ResponseException());

        $this->policy->banAtCloudFlare($ipAddress);
    }

    /**
     * @expectedException \PHPUnit\Framework\Error\Warning
     */
    public function testResponseExceptionUnban()
    {
        $blockRuleId = '92f17202ed8bd63d69a66b86a49a8f6b';

        $this->accessRules->shouldReceive('deleteRule')->with(
            $this->config['cloudflare']['zone_id'],
            $blockRuleId
        )->andThrow(new ResponseException());

        $this->policy->unbanAtCloudFlare($blockRuleId);
    }

    /**
     * @expectedException \PHPUnit\Framework\Error\Warning
     */
    public function testUnauthorizedExceptionBan()
    {
        $ipAddress = '127.0.0.1';
        $zoneID = $this->config['cloudflare']['zone_id'];

        $errorMsg = 'Client error: `POST https://api.cloudflare.com/client/v4/zones/' .
            $zoneID . '/firewall/access_rules/rules` resulted in a `403 Forbidden` response';

        $this->accessRules->shouldReceive('createRule')->with(
            $this->config['cloudflare']['zone_id'],
            $this->config['cloudflare']['mode'],
            \Mockery::any(),
            \Mockery::any()
        )->andThrow(new RequestException($errorMsg, new Request('POST', 'zones/' .
            $zoneID . '/firewall/access_rules/rules')));

        $this->policy->banAtCloudFlare($ipAddress);
    }

    /**
     * @expectedException \PHPUnit\Framework\Error\Warning
     */
    public function testUnauthorizedExceptionUnban()
    {
        $blockRuleId = '92f17202ed8bd63d69a66b86a49a8f6b';
        $zoneID = $this->config['cloudflare']['zone_id'];

        $errorMsg = 'Client error: `DELETE https://api.cloudflare.com/client/v4/zones/' .
            $zoneID . '/firewall/access_rules/rules/' . $blockRuleId . '` resulted in a `403 Forbidden` response';

        $this->accessRules->shouldReceive('deleteRule')->with(
            $this->config['cloudflare']['zone_id'],
            $blockRuleId
        )->andThrow(new RequestException($errorMsg, new Request('DELETE', 'zones/' .
            $zoneID . '/firewall/access_rules/rules' . $blockRuleId)));

        $this->policy->unbanAtCloudFlare($blockRuleId);
    }

    public function testReturnFalseOnExceptionBan()
    {
        $ipAddress = '127.0.0.1';
        $zoneID = $this->config['cloudflare']['zone_id'];

        $errorMsg = 'Client error: `POST https://api.cloudflare.com/client/v4/zones/' .
            $zoneID . '/firewall/access_rules/rules` resulted in a `403 Forbidden` response';

        $this->accessRules->shouldReceive('createRule')->with(
            $this->config['cloudflare']['zone_id'],
            $this->config['cloudflare']['mode'],
            \Mockery::any(),
            \Mockery::any()
        )->andThrow(new RequestException($errorMsg, new Request('POST', 'zones/' .
            $zoneID . '/firewall/access_rules/rules')));

        $successful = @$this->policy->banAtCloudFlare($ipAddress);
        $this->assertFalse($successful);
    }

    public function testReturnFalseOnExceptionUnban()
    {
        $blockRuleId = '92f17202ed8bd63d69a66b86a49a8f6b';
        $zoneID = $this->config['cloudflare']['zone_id'];

        $errorMsg = 'Client error: `DELETE https://api.cloudflare.com/client/v4/zones/' .
            $zoneID . '/firewall/access_rules/rules/' . $blockRuleId . '` resulted in a `403 Forbidden` response';

        $this->accessRules->shouldReceive('deleteRule')->with(
            $this->config['cloudflare']['zone_id'],
            $blockRuleId
        )->andThrow(new RequestException($errorMsg, new Request('DELETE', 'zones/' .
            $zoneID . '/firewall/access_rules/rules' . $blockRuleId)));

        $successful = @$this->policy->unbanAtCloudFlare($blockRuleId);
        $this->assertFalse($successful);
    }
}
