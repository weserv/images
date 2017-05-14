<?php

namespace AndriesLouw\imagesweserv\Throttler;

use Cloudflare\Exception\AuthenticationException;
use Cloudflare\Exception\UnauthorizedException;
use Cloudflare\Zone\Firewall\AccessRules;
use DateTime;

class ThrottlingPolicy
{
    /**
     * Config
     *
     * @var array
     */
    protected $config;

    /**
     * Create a new ThrottlerPolicy instance.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
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
     * @return string
     */
    public function isCloudFlareEnabled(): string
    {
        return $this->config['cloudflare']['enabled'];
    }

    /**
     * Ban or challenge (such as a CAPTCHA) at CloudFlare
     *
     * @param string $ip
     *
     * @return string|bool CloudFlare rule id or false if the ban was unsuccessful
     */
    public function banAtCloudFlare(string $ip)
    {
        try {
            // Create a connection to the CloudFlare API
            $accessRule = new AccessRules($this->config['cloudflare']['email'],
                $this->config['cloudflare']['auth_key']);

            // Ban
            // We're removing the ban with a cronjob if the ban time has exceeded
            $response = $accessRule->create($this->config['cloudflare']['zone_id'], $this->config['cloudflare']['mode'],
                [
                    'target' => 'ip',
                    'value' => $ip
                ], 'Banned until ' . date(DateTime::ISO8601, time() + ($this->config['ban_time'] * 60)));

            if ($response->success) {
                $blockRuleId = $response->result->id;

                // Log it
                trigger_error("Blocked: $ip rule id: $blockRuleId", E_USER_WARNING);

                return $blockRuleId;
            }
        } catch (AuthenticationException $e) {
            trigger_error('AuthenticationException. Message: ' . $e->getMessage(), E_USER_WARNING);
        } catch (UnauthorizedException $e) {
            trigger_error('UnauthorizedException. Message: ' . $e->getMessage(), E_USER_WARNING);
        }
        return false;
    }

    /**
     * Unban a rule id by CloudFlare
     *
     * @param string $blockRuleId
     * @return bool indicating if the unban was successful
     */
    public function unbanAtCloudFlare(string $blockRuleId): bool
    {
        try {
            // Create a connection to the CloudFlare API
            $accessRule = new AccessRules($this->config['cloudflare']['email'],
                $this->config['cloudflare']['auth_key']);

            // Unban
            $response = $accessRule->delete_rule($this->config['cloudflare']['zone_id'], $blockRuleId);

            // Log it
            trigger_error("Removed rule id: $blockRuleId from CloudFlare", E_USER_WARNING);

            return $response->success;
        } catch (AuthenticationException $e) {
            trigger_error('AuthenticationException. Message: ' . $e->getMessage(), E_USER_WARNING);
        } catch (UnauthorizedException $e) {
            trigger_error('UnauthorizedException. Message: ' . $e->getMessage(), E_USER_WARNING);
        }
        return false;
    }
}