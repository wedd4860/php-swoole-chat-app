<?php

namespace framework\Socket\Actions;

use framework\Socket\Actions\Abstractions\AbstractAction;

class ChannelDisconnectAction extends AbstractAction
{
    const ACTION_NAME = 'channel-disconnect';
    protected string $name = self::ACTION_NAME;

    public function validateData(array $data): void
    {
        return;
    }

    public function execute(array $data): mixed
    {
        return null;
    }
}