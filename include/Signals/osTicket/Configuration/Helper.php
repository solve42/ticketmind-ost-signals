<?php

namespace TicketMind\Data\Signals\osTicket\Configuration;

class Helper {

    use HasConfigurationManagementTrait;

    const QUEUE_URL = 'queue_url';
    const API_KEY = 'api_key';
    const FORWARD_ENABLED = 'forward_enabled';
    const WITH_CONTENT = 'with_content';

    public static function getQueueUrl(): ?string {
        return static::getConfigAsTextboxField(static::QUEUE_URL)?->getClean();
    }

    public static function getApiKey(): ?string {
        return static::getConfigAsPasswordField(static::API_KEY)?->getClean();
    }

    public static function isForwardingEnabled(): bool {
        return (bool) static::getConfigAsBooleanField(static::FORWARD_ENABLED)?->getClean();
    }

    public static function includeContent(): bool {
        return (bool) static::getConfigAsBooleanField(static::WITH_CONTENT)?->getClean();
    }
}