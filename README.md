# Flarum Red Packet

Flarum 2.x red packet extension powered by `ramon/point-system`.

Features:

- Creates equal-split or lucky-split red packets using spendable points.
- Adds the entry only to the discussion composer, immediately after the lottery entry.
- Renders a red packet card from `[redpacket id=123]` markers.
- Prevents duplicate claims and refunds unclaimed points after expiration.
- Cancels and refunds unpublished packets when the marker is removed.

Marker format:

```text
[redpacket id=123]
```

The extension does not depend on `antoinefr/flarum-ext-money`.
