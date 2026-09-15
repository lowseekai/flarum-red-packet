<?php

namespace Doingfb\RedPacket\Console;

use Doingfb\RedPacket\Support\RedPacketRepository;
use Flarum\Console\AbstractCommand;

class RefundExpiredRedPacketsCommand extends AbstractCommand
{
    public function __construct(private RedPacketRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('doingfb:red-packet:refund-expired')
            ->setDescription('Refund expired red packets.');
    }

    protected function fire(): int
    {
        $expired = $this->repository->refundExpired();
        $unpublished = $this->repository->refundStaleUnpublished();

        $this->info(sprintf('Refunded %d expired red packet(s), %d unpublished red packet(s).', $expired, $unpublished));

        return 0;
    }
}
