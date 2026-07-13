<?php

namespace Doingfb\RedPacket\Api\Controller;

use Doingfb\RedPacket\Api\Serializer\RedPacketSerializer;
use Doingfb\RedPacket\Support\RedPacketRepository;
use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class CreateRedPacketController extends AbstractCreateController
{
    public $serializer = RedPacketSerializer::class;
    public $include = ['user'];

    public function __construct(private RedPacketRepository $repository)
    {
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $attributes = (array) Arr::get($request->getParsedBody(), 'data.attributes', []);

        return $this->repository->create(
            $actor,
            (float) Arr::get($attributes, 'totalAmount'),
            (int) Arr::get($attributes, 'totalCount'),
            (string) Arr::get($attributes, 'greeting', '')
        );
    }
}
