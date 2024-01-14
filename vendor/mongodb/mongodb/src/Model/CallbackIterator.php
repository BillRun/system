<?php
/*
 * Copyright 2017-present MongoDB, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace MongoDB\Model;

use Closure;
use Iterator;
use IteratorIterator;
use ReturnTypeWillChange;
use Traversable;

/**
 * Iterator to apply a callback before returning an element
 *
 * @internal
 */
class CallbackIterator implements Iterator
{
    /** @var Closure */
    private $callback;

    /** @var Iterator */
    private $iterator;

    public function __construct(Traversable $traversable, Closure $callback)
    {
        $this->iterator = $traversable instanceof Iterator ? $traversable : new IteratorIterator($traversable);
        $this->callback = $callback;
    }

    /**
     * @see http://php.net/iterator.current
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        return ($this->callback)($this->iterator->current());
    }

    /**
     * @see http://php.net/iterator.key
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function key()
    {
        return $this->iterator->key();
    }

    /**
     * @see http://php.net/iterator.next
     * @return void
     */
    #[ReturnTypeWillChange]
    public function next()
    {
        $this->iterator->next();
    }

    /**
     * @see http://php.net/iterator.rewind
     * @return void
     */
    #[ReturnTypeWillChange]
    public function rewind()
    {
        $this->iterator->rewind();
    }

    /**
     * @see http://php.net/iterator.valid
     * @return boolean
     */
    #[ReturnTypeWillChange]
    public function valid()
    {
        return $this->iterator->valid();
    }
}
