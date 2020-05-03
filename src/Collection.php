<?php

namespace PluginEver\QueryBuilder;
defined( 'ABSPATH' ) || exit();

use ArrayAccess;
use IteratorAggregate;
use PluginEver\QueryBuilder\Interfaces\Arrayable;
use PluginEver\QueryBuilder\Interfaces\JSONable;
use PluginEver\QueryBuilder\Interfaces\Stringable;

class Collection implements ArrayAccess, Arrayable, IteratorAggregate, JSONable, Stringable {
	/**
	 * The items contained in the collection.
	 *
	 * @var array
	 */
	protected $items = array();

	/**
	 * Create a new collection.
	 *
	 * @param mixed $items
	 *
	 * @return void
	 */
	public function __construct( $items = array() ) {
		$items = is_null( $items ) ? [] : $this->getArrayableItems( $items );

		$this->items = (array) $items;
	}

	/**
	 * Create a new collection instance if the value isn't one already.
	 *
	 * @param mixed $items
	 *
	 * @return static
	 */
	public static function make( $items = null ) {
		return new static( $items );
	}

	/**
	 * Get all of the items in the collection.
	 *
	 * @return array
	 */
	public function all() {
		return $this->items;
	}


	/**
	 * Diff the collection with the given items.
	 *
	 * @param Arrayable|array $items
	 *
	 * @return static
	 */
	public function diff( $items ) {
		return new static( array_diff( $this->items, $this->getArrayableItems( $items ) ) );
	}

	/**
	 * Execute a callback over each item.
	 *
	 * @param callable $callback
	 *
	 * @return $this
	 */
	public function each( callable $callback ) {
		array_map( $callback, $this->items );

		return $this;
	}

	/**
	 * Run a filter over each of the items.
	 *
	 * @param callable $callback
	 *
	 * @return static
	 */
	public function filter( callable $callback ) {
		return new static( array_filter( $this->items, $callback ) );
	}

	/**
	 * Filter items by the given key value pair.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param bool $strict
	 *
	 * @return static
	 */
	public function where( $key, $value, $strict = true ) {
		return $this->filter( function ( $item ) use ( $key, $value, $strict ) {
			return $strict ? self::data_get( $item, $key ) === $value
				: self::data_get( $item, $key ) == $value;
		} );
	}

	/**
	 * Filter items by the given key value pair using loose comparison.
	 *
	 * @param string $key
	 * @param mixed $value
	 *
	 * @return static
	 */
	public function whereLoose( $key, $value ) {
		return $this->where( $key, $value, false );
	}

	/**
	 * Flip the items in the collection.
	 *
	 * @return static
	 */
	public function flip() {
		return new static( array_flip( $this->items ) );
	}

	/**
	 * Remove an item from the collection by key.
	 *
	 * @param mixed $key
	 *
	 * @return void
	 */
	public function forget( $key ) {
		$this->offsetUnset( $key );
	}

	/**
	 * Get an item from the collection by key.
	 *
	 * @param mixed $key
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		if ( $this->offsetExists( $key ) ) {
			return $this->items[ $key ];
		}

		return $default instanceof \Closure ? $default() : $default;
	}

	/**
	 * Determine if an item exists in the collection by key.
	 *
	 * @param mixed $key
	 *
	 * @return bool
	 */
	public function has( $key ) {
		return $this->offsetExists( $key );
	}

	/**
	 * Intersect the collection with the given items.
	 *
	 * @param Arrayable|array $items
	 *
	 * @return static
	 */
	public function intersect( $items ) {
		return new static( array_intersect( $this->items, $this->getArrayableItems( $items ) ) );
	}

	/**
	 * Determine if the collection is empty or not.
	 *
	 * @return bool
	 */
	public function isEmpty() {
		return empty( $this->items );
	}

	/**
	 * Determine if the given value is callable, but not a string.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	protected function useAsCallable( $value ) {
		return ! is_string( $value ) && is_callable( $value );
	}

	/**
	 * Get the keys of the collection items.
	 *
	 * @return static
	 */
	public function keys() {
		return new static( array_keys( $this->items ) );
	}

	/**
	 * Get the last item from the collection.
	 *
	 * @return mixed|null
	 */
	public function last() {
		return count( $this->items ) > 0 ? end( $this->items ) : null;
	}

	/**
	 * Run a map over each of the items.
	 *
	 * @param callable $callback
	 *
	 * @return static
	 */
	public function map( callable $callback ) {
		return new static( array_map( $callback, $this->items, array_keys( $this->items ) ) );
	}

	/**
	 * Merge the collection with the given items.
	 *
	 * @param Arrayable|array $items
	 *
	 * @return static
	 */
	public function merge( $items ) {
		return new static( array_merge( $this->items, $this->getArrayableItems( $items ) ) );
	}

	/**
	 * Get and remove the last item from the collection.
	 *
	 * @return mixed|null
	 */
	public function pop() {
		return array_pop( $this->items );
	}

	/**
	 * Push an item onto the beginning of the collection.
	 *
	 * @param mixed $value
	 *
	 * @return void
	 */
	public function prepend( $value ) {
		array_unshift( $this->items, $value );
	}

	/**
	 * Push an item onto the end of the collection.
	 *
	 * @param mixed $value
	 *
	 * @return void
	 */
	public function push( $value ) {
		$this->offsetSet( null, $value );
	}

	/**
	 * Put an item in the collection by key.
	 *
	 * @param mixed $key
	 * @param mixed $value
	 *
	 * @return void
	 */
	public function put( $key, $value ) {
		$this->offsetSet( $key, $value );
	}

	/**
	 * Get one or more items randomly from the collection.
	 *
	 * @param int $amount
	 *
	 * @return mixed
	 */
	public function random( $amount = 1 ) {
		if ( $this->isEmpty() ) {
			return;
		}

		$keys = array_rand( $this->items, $amount );

		return is_array( $keys ) ? array_intersect_key( $this->items, array_flip( $keys ) ) : $this->items[ $keys ];
	}

	/**
	 * Reduce the collection to a single value.
	 *
	 * @param callable $callback
	 * @param mixed $initial
	 *
	 * @return mixed
	 */
	public function reduce( callable $callback, $initial = null ) {
		return array_reduce( $this->items, $callback, $initial );
	}

	/**
	 * Create a collection of all elements that do not pass a given truth test.
	 *
	 * @param callable|mixed $callback
	 *
	 * @return static
	 */
	public function reject( $callback ) {
		if ( $this->useAsCallable( $callback ) ) {
			return $this->filter( function ( $item ) use ( $callback ) {
				return ! $callback( $item );
			} );
		}

		return $this->filter( function ( $item ) use ( $callback ) {
			return $item != $callback;
		} );
	}

	/**
	 * Reverse items order.
	 *
	 * @return static
	 */
	public function reverse() {
		return new static( array_reverse( $this->items ) );
	}

	/**
	 * Search the collection for a given value and return the corresponding key if successful.
	 *
	 * @param mixed $value
	 * @param bool $strict
	 *
	 * @return mixed
	 */
	public function search( $value, $strict = false ) {
		if ( ! $this->useAsCallable( $value ) ) {
			return array_search( $value, $this->items, $strict );
		}

		foreach ( $this->items as $key => $item ) {
			if ( $value( $item, $key ) ) {
				return $key;
			}
		}

		return false;
	}

	/**
	 * Get and remove the first item from the collection.
	 *
	 * @return mixed|null
	 */
	public function shift() {
		return array_shift( $this->items );
	}

	/**
	 * Shuffle the items in the collection.
	 *
	 * @return $this
	 */
	public function shuffle() {
		shuffle( $this->items );

		return $this;
	}

	/**
	 * Slice the underlying collection array.
	 *
	 * @param int $offset
	 * @param int $length
	 * @param bool $preserveKeys
	 *
	 * @return static
	 */
	public function slice( $offset, $length = null, $preserveKeys = false ) {
		return new static( array_slice( $this->items, $offset, $length, $preserveKeys ) );
	}

	/**
	 * Chunk the underlying collection array.
	 *
	 * @param int $size
	 * @param bool $preserveKeys
	 *
	 * @return static
	 */
	public function chunk( $size, $preserveKeys = false ) {
		$chunks = [];

		foreach ( array_chunk( $this->items, $size, $preserveKeys ) as $chunk ) {
			$chunks[] = new static( $chunk );
		}

		return new static( $chunks );
	}

	/**
	 * Sort through each item with a callback.
	 *
	 * @param callable $callback
	 *
	 * @return $this
	 */
	public function sort( callable $callback ) {
		uasort( $this->items, $callback );

		return $this;
	}

	/**
	 * Splice portion of the underlying collection array.
	 *
	 * @param int $offset
	 * @param int $length
	 * @param mixed $replacement
	 *
	 * @return static
	 */
	public function splice( $offset, $length = 0, $replacement = [] ) {
		return new static( array_splice( $this->items, $offset, $length, $replacement ) );
	}

	/**
	 * Take the first or last {$limit} items.
	 *
	 * @param int $limit
	 *
	 * @return static
	 */
	public function take( $limit = null ) {
		if ( $limit < 0 ) {
			return $this->slice( $limit, abs( $limit ) );
		}

		return $this->slice( 0, $limit );
	}

	/**
	 * Transform each item in the collection using a callback.
	 *
	 * @param callable $callback
	 *
	 * @return $this
	 */
	public function transform( callable $callback ) {
		$this->items = array_map( $callback, $this->items );

		return $this;
	}

	/**
	 * Return only unique items from the collection array.
	 *
	 * @return static
	 */
	public function unique() {
		return new static( array_unique( $this->items ) );
	}

	/**
	 * Reset the keys on the underlying array.
	 *
	 * @return static
	 */
	public function values() {
		return new static( array_values( $this->items ) );
	}

	/**
	 * Count the number of items in the collection.
	 *
	 * @return int
	 */
	public function count() {
		return count( $this->items );
	}

	/**
	 * Determine if an item exists at an offset.
	 *
	 * @param mixed $key
	 *
	 * @return bool
	 */
	public function offsetExists( $key ) {
		return array_key_exists( $key, $this->items );
	}

	/**
	 * Get an item at a given offset.
	 *
	 * @param mixed $key
	 *
	 * @return mixed
	 */
	public function offsetGet( $key ) {
		return $this->items[ $key ];
	}

	/**
	 * Set the item at a given offset.
	 *
	 * @param mixed $key
	 * @param mixed $value
	 *
	 * @return void
	 */
	public function offsetSet( $key, $value ) {
		if ( is_null( $key ) ) {
			$this->items[] = $value;
		} else {
			$this->items[ $key ] = $value;
		}
	}

	/**
	 * Unset the item at a given offset.
	 *
	 * @param string $key
	 *
	 * @return void
	 */
	public function offsetUnset( $key ) {
		unset( $this->items[ $key ] );
	}


	/**
	 * Get an iterator for the items.
	 *
	 * @return \ArrayIterator
	 */
	public function getIterator() {
		return new \ArrayIterator( $this->items );
	}

	/**
	 * Returns collection as pure array.
	 * Does depth array casting.
	 * @return array
	 * @since 1.0.2
	 *
	 */
	public function __toArray() {
		$output = [];
		$value  = null;
		foreach ( $this as $key => $value ) {
			$output[ $key ] = ! is_object( $value )
				? $value
				: ( method_exists( $value, '__toArray' )
					? $value->__toArray()
					: (array) $value
				);
		}

		return $output;
	}

	/**
	 * Returns collection as pure array.
	 * Does depth array casting.
	 * @return array
	 * @since 1.0.2
	 *
	 */
	public function toArray() {
		return $this->__toArray();
	}

	/**
	 * Returns collection as a string.
	 *
	 * @param string
	 *
	 * @since 1.0.2
	 *
	 */
	public function __toString() {
		return json_encode( $this->__toArray() );
	}

	/**
	 * Returns object as JSON string.
	 *
	 * @param int $options JSON encoding options. See @link.
	 * @param int $depth JSON encoding depth. See @link.
	 *
	 * @return string
	 * @link http://php.net/manual/en/function.json-encode.php
	 *
	 * @since 1.0.2
	 *
	 */
	public function __toJSON( $options = 0, $depth = 512 ) {
		return json_encode( $this->__toArray(), $options, $depth );
	}

	/**
	 * Returns object as JSON string.
	 *
	 * @param int $options JSON encoding options. See @link.
	 * @param int $depth JSON encoding depth. See @link.
	 *
	 * @return string
	 * @link http://php.net/manual/en/function.json-encode.php
	 *
	 * @since 1.0.2
	 *
	 */
	public function toJSON( $options = 0, $depth = 512 ) {
		return $this->__toJSON( $options, $depth );
	}

	/**
	 * @param $target
	 * @param $key
	 * @param null $default
	 *
	 * @return array
	 * @since
	 */
	public static function data_get( $target, $key, $default = null ) {
		if ( is_null( $key ) ) {
			return $target;
		}

		$key = is_array( $key ) ? $key : explode( '.', $key );

		foreach ( $key as $i => $segment ) {
			unset( $key[ $i ] );

			if ( is_null( $segment ) ) {
				return $target;
			}

			if ( $segment === '*' ) {
				if ( $target instanceof Collection ) {
					$target = $target->all();
				} elseif ( ! is_array( $target ) ) {
					return $default instanceof \Closure ? $default() : $default;
				}
				$result = [];

				foreach ( $target as $item ) {
					$result[] = self::data_get( $item, $key );
				}

				return in_array( '*', $key ) ? $result : $result;
			}

			if ( array_key_exists( $segment, $target ) ) {
				$target = $target[ $segment ];
			} elseif ( is_object( $target ) && isset( $target->{$segment} ) ) {
				$target = $target->{$segment};
			} else {
				return $default instanceof \Closure ? $default() : $default;
			}
		}

		return $target;
	}

	/**
	 * Results array of items from Collection or Arrayable.
	 *
	 * @param $items
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	protected function getArrayableItems( $items ) {
		if ( $items instanceof Collection ) {
			$items = $items->all();
		} elseif ( $items instanceof Arrayable ) {
			$items = $items->toArray();
		}

		return $items;
	}
}