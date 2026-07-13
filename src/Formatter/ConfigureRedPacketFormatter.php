<?php

namespace Doingfb\RedPacket\Formatter;

use s9e\TextFormatter\Configurator;

class ConfigureRedPacketFormatter
{
    public function __invoke(Configurator $config): void
    {
        $tagName = 'REDPACKET';

        $tag = $config->tags->add($tagName);
        $tag->attributes->add('id')->filterChain->append('#uint');
        $tag->template = '<span class="DoingfbRedPacketMount" data-red-packet-id="{@id}"></span>';

        $config->Preg->match('/\[redpacket\s+id=(?<id>\d+)\]/i', $tagName);
        $config->Preg->match('/\[\[doingfb-red-packet:(?<id>\d+)\]\]/i', $tagName);
    }
}
