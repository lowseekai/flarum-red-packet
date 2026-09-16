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
        $value = $this->settings->get('doingfb-red-packet.enabled', true);

        return $value !== false && $value !== null && $value !== '0' && $value !== 0;
    }

    public function minAmount(): int
    {
        return max(1, (int) $this->settings->get('doingfb-red-packet.min_amount', 1));
    }

    public function maxAmount(): int
    {
        return max($this->minAmount(), (int) $this->settings->get('doingfb-red-packet.max_amount', 1000));
    }

    public function maxCount(): int
    {
        return max(1, min(500, (int) $this->settings->get('doingfb-red-packet.max_count', 50)));
    }

    public function claimsDisplayCount(): int
    {
        return max(1, min(100, (int) $this->settings->get('doingfb-red-packet.claims_display_count', 10)));
    }

    public function expiresMinutes(): int
    {
        return max(1, min(10080, (int) $this->settings->get('doingfb-red-packet.expires_minutes', 1440)));
    }

    public function currencyName(): string
    {
        $name = trim((string) $this->settings->get('point-system.currency_name', '积分'));

        return $name !== '' ? $name : '积分';
    }

    public function pointSystemEnabled(): bool
    {
        $value = $this->settings->get('point-system.enabled', true);

        return $value !== false && $value !== null && $value !== '0' && $value !== 0;
    }
}
