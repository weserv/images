<?php

namespace Weserv\Images\Throttler;

use Cloudflare\API\Adapter\ResponseException;
use Cloudflare\API\Endpoints\AccessRules;
use DateTimeInterface;
use GuzzleHttp\Exception\RequestException;

class ThrottlingPolicy
{
    /**
     * Config
     */
    protected array $config;

    /**
     * AccessRules instance.
     */
    protected AccessRules $accessRules;

    /**
     * Create a new ThrottlerPolicy instance.
     *
     * @param AccessRules $accessRules
     * @param array $config
     */
    public function __construct(AccessRules $accessRules, array $config)
    {
        $this->accessRules = $accessRules;
        $this->config = array_merge([
            'ban_time' => 60, // If exceed, ban for 60 minutes
            'cloudflare' => [
                'enabled' => false, // Is CloudFlare enabled?
                'email' => '',
                'auth_key' => '',
                'zone_id' => '',
                'mode' => 'block' // The action to apply if the IP get's banned
            ]
        ], $config);
    }

    /**
     * How long should the user be banned?
     *
     * @return int time in minutes
     */
    public function getBanTime(): int
    {
        return $this->config['ban_time'];
    }

    /**
     * Is the CloudFlare provider enabled?
     *
     * @return bool
     */
    public function isCloudFlareEnabled(): bool
    {
        return $this->config['cloudflare']['enabled'];
    }

    /**
     * Ban or challenge (such as a CAPTCHA) at CloudFlare
     *
     * @param string $ipAddress
     *
     * @return bool indicating if the ban was successful
     */
    public function banAtCloudFlare(string $ipAddress): bool
    {
        try {
            $config = new \Cloudflare\API\Configurations\AccessRules();
            $config->setIP($ipAddress);

            // Ban
            // We're removing the ban with a cronjob if the ban time has exceeded
            $success = $this->accessRules->createRule(
                $this->config['cloudflare']['zone_id'],
                $this->config['cloudflare']['mode'],
                $config,
                'Banned until ' . date(DateTimeInterface::ATOM, time() + ($this->config['ban_time'] * 60))
            );

            // Log it
            trigger_error("Blocked: $ipAddress at CloudFlare. Success: $success", E_USER_WARNING);

            return $success;
        } catch (ResponseException $e) {
            trigger_error('ResponseException. Message: ' . $e->getMessage(), E_USER_WARNING);
        } catch (RequestException $e) {
            trigger_error('RequestException. Message: ' . $e->getMessage(), E_USER_WARNING);
        }

        return false;
    }

    /**
     * Unban a rule id from CloudFlare
     *
     * @param string $blockRuleId
     *
     * @return bool indicating if the unban was successful
     */
    public function unbanAtCloudFlare(string $blockRuleId): bool
    {
        try {
            // Unban
            $success = $this->accessRules->deleteRule($this->config['cloudflare']['zone_id'], $blockRuleId);

            // Log it
            trigger_error("Removed rule id: $blockRuleId from CloudFlare. Success: $success", E_USER_WARNING);

            return $success;
        } catch (ResponseException $e) {
            trigger_error('ResponseException. Message: ' . $e->getMessage(), E_USER_WARNING);
        } catch (RequestException $e) {
            trigger_error('RequestException. Message: ' . $e->getMessage(), E_USER_WARNING);
        }

        return false;
    }
}
