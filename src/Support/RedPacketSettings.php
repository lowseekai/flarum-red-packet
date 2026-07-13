<?php

namespace Doingfb\RedPacket\Support;

use Flarum\Settings\SettingsRepositoryInterface;

class RedPacketSettings
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->get('doingfb-red-packet.enabled', true);
    }

    public function minAmount(): float
    {
        return max(0.01, (float) $this->settings->get('doingfb-red-packet.min_amount', 1));
    }

    public function maxAmount(): float
    {
        return max($this->minAmount(), (float) $this->settings->get('doingfb-red-packet.max_amount', 1000));
    }

    public function maxCount(): int
    {
        return max(1, min(500, (int) $this->settings->get('doingfb-red-packet.max_count', 50)));
    }

    public function expiresMinutes(): int
    {
        return max(1, min(10080, (int) $this->settings->get('doingfb-red-packet.expires_minutes', 1440)));
    }
}
