<?php

namespace Doingfb\RedPacket\Api\Controller;

use Doingfb\RedPacket\Support\RedPacketRepository;
use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class CancelRedPacketController extends AbstractDeleteController
{
    public function __construct(private RedPacketRepository $repository)
    {
    }

    protected function delete(ServerRequestInterface $request)
    {
        $this->repository->cancelUnpublished(
            RequestUtil::getActor($request),
            (int) Arr::get($request->getQueryParams(), 'id')
        );
    }
}
