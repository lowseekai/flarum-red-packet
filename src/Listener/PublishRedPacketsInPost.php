<?php

namespace Doingfb\RedPacket\Listener;

use Doingfb\RedPacket\Support\RedPacketRepository;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;

class PublishRedPacketsInPost
{
    public function __construct(private RedPacketRepository $repository)
    {
    }

    public function handle($event): void
    {
        if (!$event instanceof Posted && !$event instanceof Revised) {
            return;
        }

        $post = $event->post;

        $this->repository->publishFromContent(
            (string) $post->content,
            (int) $post->user_id
        );
    }
}
