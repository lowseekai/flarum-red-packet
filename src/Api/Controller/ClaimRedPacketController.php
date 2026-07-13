<?php

namespace Doingfb\RedPacket\Api\Controller;

use Doingfb\RedPacket\Api\Serializer\RedPacketSerializer;
use Doingfb\RedPacket\Support\RedPacketRepository;
use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ClaimRedPacketController extends AbstractCreateController
{
    public $serializer = RedPacketSerializer::class;
    public $include = ['user'];

    public function __construct(private RedPacketRepository $repository)
    {
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        return $this->repository->claim(
            RequestUtil::getActor($request),
            (int) Arr::get($request->getQueryParams(), 'id')
        );
    }
}
