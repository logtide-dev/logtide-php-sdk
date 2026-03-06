<?php

declare(strict_types=1);

namespace LogTide\Exception;

class BufferFullException extends LogtideException
{
    public function __construct()
    {
        parent::__construct('Log buffer is full. Increase maxBufferSize or flush more frequently.');
    }
}
