<?php


namespace Larastart\DataStructures;


class Buffer
{
    private $maxSize;
    private $cb;

    private $buffer = [];
    private $bufferSize = 0;

    public function __construct($maxSize, callable $cb)
    {
        $this->maxSize = $maxSize;
        $this->cb = $cb;
    }

    public function add($el)
    {
        $this->buffer[] = $el;
        if ($this->bufferSize++ > $this->maxSize) {
            $this->finalize();
        }
    }

    public function finalize()
    {
        call_user_func($this->cb, $this->buffer);
        $this->buffer = [];
        $this->bufferSize = 0;
    }

    public function __destruct()
    {
        if ($this->bufferSize > 0) {
            \Log::warning("Buffer size {$this->bufferSize} > 0 in destructor..");
        }
    }

}