<?php

namespace Doingfb\RedPacket\Api\Controller;

use Doingfb\RedPacket\Api\Serializer\RedPacketSerializer;
use Doingfb\RedPacket\Support\RedPacketRepository;
use Flarum\Api\Controller\AbstractShowController;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ShowRedPacketController extends AbstractShowController
{
    public $serializer = RedPacketSerializer::class;
    public $include = ['user'];

    public function __construct(private RedPacketRepository $repository)
    {
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        return $this->repository->findOrFail((int) Arr::get($request->getQueryParams(), 'id'));
    }
}
