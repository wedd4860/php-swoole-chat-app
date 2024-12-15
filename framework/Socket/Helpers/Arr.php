<?php

namespace framework\Socket\Helpers;

use ArrayAccess;
use InvalidArgumentException;

class Arr
{
    /**
     * 주어진 값이 배열에 접근할 수 있는지 확인합니다.
     *
     * @param  mixed  $value
     * @return bool
     */
    public static function accessible($value)
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    /**
     * 배열 내에 키가 존재하지 않거나 null이면 주어진 key / value 쌍을 배열에 추가합니다.
     *
     * @param  array  $array
     * @param  string  $key
     * @param  mixed  $value
     * @return array
     */
    public static function add($array, $key, $value)
    {
        if (is_null(static::get($array, $key))) {
            static::set($array, $key, $value);
        }

        return $array;
    }

    /**
     * 배열들의 배열(여러 개의 배열)을 하나의 배열로 통합합니다.
     *
     * @param  iterable  $array
     * @return array
     */
    public static function collapse($array)
    {
        $results = [];

        foreach ($array as $values) {
            if (!is_array($values)) {
                continue;
            }

            $results[] = $values;
        }

        return array_merge([], ...$results);
    }

    /**
     * 주어진 배열을 교차 결합하여 가능한 모든 순열이 있는 데카르트 곱을 반환합니다.
     *
     * @param  iterable  ...$arrays
     * @return array
     */
    public static function crossJoin(...$arrays)
    {
        $results = [[]];

        foreach ($arrays as $index => $array) {
            $append = [];

            foreach ($results as $product) {
                foreach ($array as $item) {
                    $product[$index] = $item;

                    $append[] = $product;
                }
            }

            $results = $append;
        }

        return $results;
    }

    /**
     * 주어진 배열에서 키들을 담고 있는 배열과 값들을 담고 있는 배열, 총 2개의 배열을 반환합니다.
     *
     * @param  array  $array
     * @return array
     */
    public static function divide($array)
    {
        return [array_keys($array), array_values($array)];
    }

    /**
     * 다차원 배열을 "점(.)"으로 배열 깊이를 표기하면서 단일 레벨의 배열로 만듭니다.
     *
     * @param  iterable  $array
     * @param  string  $prepend
     * @return array
     */
    public static function dot($array, $prepend = '')
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, static::dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }

    /**
     * 주어진 키 / 값 쌍을 배열에서 제거합니다.
     *
     * @param  array  $array
     * @param  array|string  $keys
     * @return array
     */
    public static function except($array, $keys)
    {
        static::forget($array, $keys);

        return $array;
    }

    /**
     * 주어진 키가 제공된 배열에 있는지 확인합니다.
     *
     * @param  \ArrayAccess|array  $array
     * @param  string|int  $key
     * @return bool
     */
    public static function exists($array, $key)
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }

        return array_key_exists($key, $array);
    }

    /**
     * 전달된 배열 중 주어진 조건을 만족하는 첫 번째 요소를 반환합니다.
     * 메소드에 세번째 파라미터로 기본값을 지정할 수 있습니다. 배열의 어떠한 값도 조건을 통과하지 못했을 때 이 값이 반환됩니다.
     *
     * @param  iterable  $array
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public static function first($array, callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            if (empty($array)) {
                return $default;
            }

            foreach ($array as $item) {
                return $item;
            }
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * 전달된 조건을 통과하는 아이템의 가장 마지막 요소를 반환합니다.
     *
     * @param  array  $array
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public static function last($array, callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return empty($array) ? $default : end($array);
        }

        return static::first(array_reverse($array, true), $callback, $default);
    }

    /**
     * 다차원 배열을 단일 레벨의 1차원 배열로 만듭니다.
     *
     * @param  iterable  $array
     * @param  int  $depth
     * @return array
     */
    public static function flatten($array, $depth = INF)
    {
        $result = [];

        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } else {
                $values = $depth === 1
                    ? array_values($item)
                    : static::flatten($item, $depth - 1);

                foreach ($values as $value) {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * "점(.)" 표기법을 사용하여 중첩 배열로부터 주어진 키 / 값 쌍을 제거합니다.
     *
     * @param  array  $array
     * @param  array|string  $keys
     * @return void
     */
    public static function forget(&$array, $keys)
    {
        $original = &$array;

        $keys = (array) $keys;

        if (count($keys) === 0) {
            return;
        }

        foreach ($keys as $key) {
            // if the exact key exists in the top-level, remove it
            if (static::exists($array, $key)) {
                unset($array[$key]);

                continue;
            }

            $parts = explode('.', $key);

            // clean up before each pass
            $array = &$original;

            while (count($parts) > 1) {
                $part = array_shift($parts);

                if (isset($array[$part]) && is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }

            unset($array[array_shift($parts)]);
        }
    }

    /**
     * "점(.)" 표기법으로 중첩 배열로부터 주어진 값을 찾습니다.
     * 특정 키를 찾지 못한 경우 반환되는 기본값을 지정할 수도 있습니다.
     *
     * @param  \ArrayAccess|array  $array
     * @param  string|int|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    private static function _get($array, $key, $default = null)
    {
        if (!static::accessible($array)) {
            return $default;
        }

        if (is_null($key)) {
            return $array;
        }

        if (static::exists($array, $key)) {
            return $array[$key];
        }

        if (strpos($key, '.') === false) {
            return isset($array[$key]) ? $array[$key] : $default;
        }

        foreach (explode('.', $key) as $segment) {
            if (static::accessible($array) && static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    public static function get($array, $key, $default = null, $emptry = true)
    {
        $value = static::_get($array, $key, $default);

        if (!$emptry && !$value) {
            $value = $default;
        }

        return $value;
    }

    /**
     * "점(.)" 표기를 이용하여 배열에 주어진 아이템 또는 아이템들이 존재하는지 확인합니다.
     *
     * @param  \ArrayAccess|array  $array
     * @param  string|array  $keys
     * @return bool
     */
    public static function has($array, $keys)
    {
        $keys = (array) $keys;

        if (!$array || $keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            $subKeyArray = $array;

            if (static::exists($array, $key)) {
                continue;
            }

            foreach (explode('.', $key) as $segment) {
                if (static::accessible($subKeyArray) && static::exists($subKeyArray, $segment)) {
                    $subKeyArray = $subKeyArray[$segment];
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * "점(.)" 표기를 이용하여 배열에 주어진 아이템이 배열이고 1개이상인지 확인합니다.
     *
     * @param  \ArrayAccess|array  $array
     * @param  string|null  $key
     * @return bool
     */
    public static function hasCount($array, $key = null)
    {
        if ($key) {
            $item = static::get($array, $key);
        } else {
            $item = $array;
        }

        if ($item && static::accessible($item) && count($item) > 0) {
            return true;
        }
        return false;
    }

    public static function count($array, $key = null)
    {
        if ($key) {
            $item = static::get($array, $key);
        } else {
            $item = $array;
        }

        if (!$item) {
            return -1;
        }

        if (!static::accessible($item)) {
            return -1;
        }

        return count($item);
    }

    /**
     * "점(.)" 표기를 이용하여 주어진 세트의 항목이 배열에 존재하는지 확인합니다.
     *
     * @param  \ArrayAccess|array  $array
     * @param  string|array  $keys
     * @return bool
     */
    public static function hasAny($array, $keys)
    {
        if (is_null($keys)) {
            return false;
        }

        $keys = (array) $keys;

        if (!$array) {
            return false;
        }

        if ($keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            if (static::has($array, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 주어진 배열이 연관 배열이면 true를 반환합니다.
     *
     * 배열은 0으로 시작하는 순차적 숫자 키가 없는 경우 "연관-associative"로 간주됩니다.
     *
     * @param  array  $array
     * @return bool
     */
    public static function isAssoc(array $array)
    {
        $keys = array_keys($array);

        return array_keys($keys) !== $keys;
    }

    /**
     * 특정한 키 / 값 쌍만을 배열로부터 반환합니다.
     *
     * @param  array  $array
     * @param  array|string  $keys
     * @return array
     */
    public static function only($array, $keys)
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    /**
     * Explode the "value" and "key" arguments passed to "pluck".
     *
     * @param  string|array  $value
     * @param  string|array|null  $key
     * @return array
     */
    protected static function explodePluckParameters($value, $key)
    {
        $value = is_string($value) ? explode('.', $value) : $value;

        $key = is_null($key) || is_array($key) ? $key : explode('.', $key);

        return [$value, $key];
    }

    /**
     * 배열의 시작 부분에 아이템을 추가할 것입니다.
     * 필요한 경우, 아이템의 키를 지정할 수도 있습니다.
     *
     * @param  array  $array
     * @param  mixed  $value
     * @param  mixed  $key
     * @return array
     */
    public static function prepend($array, $value, $key = null)
    {
        if (is_null($key)) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }

        return $array;
    }

    /**
     * 배열에서 주어진 키 / 값 쌍을 반환함과 동시에 제거합니다.
     *
     * @param  array  $array
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function pull(&$array, $key, $default = null)
    {
        $value = static::get($array, $key, $default);

        static::forget($array, $key);

        return $value;
    }

    /**
     * 배열에서 임의의 값을 반환합니다.
     * 두 번째 인자로 몇 개의 아이템을 반환할지 값을 지정할 수 있습니다. 이 인자를 지정하면, 하나의 아이템이 포함되더라도 배열이 반환됩니다.
     *
     * @param  array  $array
     * @param  int|null  $number
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public static function random($array, $number = null)
    {
        $requested = is_null($number) ? 1 : $number;

        $count = count($array);

        if ($requested > $count) {
            throw new InvalidArgumentException(
                "You requested {$requested} items, but there are only {$count} items available."
            );
        }

        if (is_null($number)) {
            return $array[array_rand($array)];
        }

        if ((int) $number === 0) {
            return [];
        }

        $keys = array_rand($array, $number);

        $results = [];

        foreach ((array) $keys as $key) {
            $results[] = $array[$key];
        }

        return $results;
    }

    /**
     * "점(.)" 표기법을 이용하여 중첩된 배열 내에 값을 설정합니다.
     * 메소드에 키가 제공되지 않으면 전체 배열이 교체됩니다.
     *
     * @param  array  $array
     * @param  string|null  $key
     * @param  mixed  $value
     * @return array
     */
    public static function set(&$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        foreach ($keys as $i => $key) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

    public static function setCallback(&$array, $key, callable $callback, $default = null)
    {
        $value = static::get($array, $key, $default);
        $value2 = $callback($value);
        static::set($array, $key, $value2);
    }

    public static function setEmptyInit(&$array, $key, $default)
    {
        $value = static::get($array, $key, $default, false);
        static::set($array, $key, $value);
    }

    /**
     * 배열의 항목을 무작위로 섞습니다.
     *
     * @param  array  $array
     * @param  int|null  $seed
     * @return array
     */
    public static function shuffle($array, $seed = null)
    {
        if (is_null($seed)) {
            shuffle($array);
        } else {
            mt_srand($seed);
            shuffle($array);
            mt_srand();
        }

        return $array;
    }

    /**
     * 배열을 쿼리 스트링으로 변환합니다.
     *
     * @param  array  $array
     * @return string
     */
    public static function query($array)
    {
        return http_build_query($array, 'null', '&', PHP_QUERY_RFC3986);
    }

    /**
     * 주어진 클로져를 사용하여 배열을 필터링합니다.
     *
     * @param  array  $array
     * @param  callable  $callback
     * @return array
     */
    public static function where($array, callable $callback)
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * 주어진 클로져를 사용하여 배열을 필터링합니다.
     *
     * @param  array  $array
     * @param  callable  $callback
     * @return array
     */
    public static function whereValue($array, callable $callback)
    {
        if (!static::hasCount($array)) {
            return [];
        }
        $array2 = [];
        foreach ($array as $key => $value) {
            $check = $callback($value, $key);
            if ($check) {
                $array2[] = $check;
            }
        }

        return $array2;
    }

    /**
     * 주어진 값을 배열로 만듭니다. 만약 함수에 전달된 값이 배열이라면 결과에는 변경사항이 없습니다.
     *
     * @param  mixed  $value
     * @return array
     */
    public static function wrap($value)
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    public static function inArray($key, $array, $cnt = 0)
    {
        if (!$key || !$array) {
            return false;
        }

        if (!static::accessible($array)) {
            $array = [$array];
        }

        if (static::accessible($key)) {
            $check = array_values(array_intersect($key, $array));
            if ($cnt > 0) {
                return count($check) == $cnt;
            } else {
                return !(count($check) == 0);
            }
        } else {
            return in_array($key, $array);
        }
    }

    public static function orderBy($array, $orderBy)
    {
        if (self::count($array) <= 0 || self::count($orderBy) <= 0) {
            return $array;
        }

        usort($array, function ($item1, $item2) use ($orderBy) {
            foreach ($orderBy as $key => $order) {
                $tmpItem1 = self::get($item1, $key);
                $tmpItem2 = self::get($item2, $key);
                $sort = 0;
                if ($tmpItem1 > $tmpItem2) {
                    $sort = 1;
                } else if ($tmpItem1 < $tmpItem2) {
                    $sort = -1;
                }
                if ($order == 'DESC') {
                    $sort *= -1;
                }
                if ($sort != -1) {
                    return $sort;
                }
            }

            return 0;
        });

        return $array;
    }
}
