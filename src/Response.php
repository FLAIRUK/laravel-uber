<?php

namespace FLAIRUK\Uber;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use IteratorAggregate;
use JsonSerializable;
use LogicException;
use Traversable;

/**
 * An Uber API response body.
 *
 * Uber's APIs don't share an envelope, so array access reads the whole body
 * ($response['status'], $response['pickup']['eta']) and get() takes dot paths.
 * Lists name their items key, e.g. "products", "history" or "data": iterate the
 * response, count() it or collect() it to work with those items, and walk
 * paginated lists with next() or lazy().
 *
 * @implements ArrayAccess<string, mixed>
 * @implements IteratorAggregate<array-key, mixed>
 * @implements Arrayable<string, mixed>
 */
class Response implements Arrayable, ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, list<string>>  $headers
     * @param  string|null  $itemsKey  where a list response keeps its items
     * @param  (Closure(Response): ?Response)|null  $fetchNext  fetches the page after the given one, or null on the last
     */
    public function __construct(
        protected array $body,
        protected int $status = 200,
        protected array $headers = [],
        protected ?string $itemsKey = null,
        protected ?Closure $fetchNext = null,
    ) {}

    /**
     * A value from the body, by dot path, e.g. get('pickup.eta').
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->body : Arr::get($this->body, $key, $default);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    /**
     * Requests left in the current rate-limit window, when Uber says.
     */
    public function rateLimitRemaining(): ?int
    {
        $remaining = $this->header('X-Rate-Limit-Remaining');

        return is_numeric($remaining) ? (int) $remaining : null;
    }

    /**
     * The items of a list response (all of the body for a JSON array).
     *
     * @return array<array-key, mixed>
     */
    public function items(): array
    {
        $items = $this->itemsKey === null ? $this->body : Arr::get($this->body, $this->itemsKey, []);

        return is_array($items) ? $items : [];
    }

    /**
     * @return Collection<array-key, mixed>
     */
    public function collect(?string $key = null): Collection
    {
        if ($key === null) {
            return new Collection($this->items());
        }

        $value = $this->get($key, []);

        return new Collection(is_array($value) ? $value : [$value]);
    }

    /**
     * Fetch the next page, or null on the last page.
     */
    public function next(): ?self
    {
        if ($this->fetchNext === null) {
            throw new LogicException('This response is not from a paginated endpoint.');
        }

        return ($this->fetchNext)($this);
    }

    /**
     * Every item from this page onwards, fetching further pages only as they are needed.
     *
     * @return LazyCollection<int, mixed>
     */
    public function lazy(): LazyCollection
    {
        return LazyCollection::make(function () {
            $page = $this;

            while ($page !== null) {
                foreach ($page->items() as $item) {
                    yield $item;
                }

                $page = $page->fetchNext === null ? null : $page->next();
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->body;
    }

    public function jsonSerialize(): array
    {
        return $this->body;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items());
    }

    public function count(): int
    {
        return count($this->items());
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->body[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->body[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Uber responses are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Uber responses are read-only.');
    }

    /**
     * A copy that knows where its items are and how to fetch the next page.
     *
     * @param  (Closure(Response): ?Response)|null  $fetchNext
     */
    public function paginate(?string $itemsKey, ?Closure $fetchNext = null): static
    {
        $clone = clone $this;
        $clone->itemsKey = $itemsKey;
        $clone->fetchNext = $fetchNext;

        return $clone;
    }
}
