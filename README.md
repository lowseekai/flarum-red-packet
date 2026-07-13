# DoingFB Red Packet

Flarum 红包扩展，使用 `antoinefr/flarum-ext-money` 的论坛货币作为红包金额。

第一版能力：

- 发平均红包，发出时先从发送者余额扣款。
- 帖子编辑器按钮插入红包标记。
- 帖子和聊天文本中渲染 `[redpacket id=123]` / `[[doingfb-red-packet:123]]` 红包卡片。
- 用户点击领取，防止重复领取和超领。
- 领取后给用户增加 money，并触发 money 更新事件。

红包短码：

```text
[redpacket id=123]
[[doingfb-red-packet:123]]
```

