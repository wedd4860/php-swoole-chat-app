<?php

namespace framework\Socket\Helpers;

class Str
{
	/**
	 * The cache of snake-cased words.
	 *
	 * @var array
	 */
	protected static $snakeCache = [];

	/**
	 * The cache of camel-cased words.
	 *
	 * @var array
	 */
	protected static $camelCache = [];

	/**
	 * The cache of studly-cased words.
	 *
	 * @var array
	 */
	protected static $studlyCache = [];


	/**
	 * 주어진 값 이후의 모든 것을 반환합니다. 문자열 내에 값이 없으면 전체 문자열이 반환됩니다.
	 *
	 * @param  string  $subject
	 * @param  string  $search
	 * @return string
	 */
	public static function after($subject, $search)
	{
		return $search === '' ? $subject : array_reverse(explode($search, $subject, 2))[0];
	}

	/**
	 * 문자열에서 주어진 값이 마지막으로 나타난 후 모든 것을 반환합니다. 문자열 내에 값이 없으면 전체 문자열이 반환됩니다.
	 *
	 * @param  string  $subject
	 * @param  string  $search
	 * @return string
	 */
	public static function afterLast($subject, $search)
	{
		if ($search === '') {
			return $subject;
		}

		$position = strrpos($subject, (string) $search);

		if ($position === false) {
			return $subject;
		}

		return substr($subject, $position + strlen($search));
	}

	/**
	 * 문자열에서 주어진 값 이전의 모든 것을 반환합니다.
	 *
	 * @param  string  $subject
	 * @param  string  $search
	 * @return string
	 */
	public static function before($subject, $search)
	{
		return $search === '' ? $subject : explode($search, $subject)[0];
	}

	/**
	 * 문자열에서 주어진 값 이전의 모든 것을 반환합니다.
	 *
	 * @param  string  $subject
	 * @param  string  $search
	 * @return string
	 */
	public static function beforeLast($subject, $search)
	{
		if ($search === '') {
			return $subject;
		}

		$pos = mb_strrpos($subject, $search);

		if ($pos === false) {
			return $subject;
		}

		return static::substr($subject, 0, $pos);
	}

	/**
	 * 두 값 사이의 문자열 부분을 반환합니다.
	 *
	 * @param  string  $subject
	 * @param  string  $from
	 * @param  string  $to
	 * @return string
	 */
	public static function between($subject, $from, $to)
	{
		if ($from === '' || $to === '') {
			return $subject;
		}

		return static::beforeLast(static::after($subject, $from), $to);
	}

	/**
	 * 주어진 문자열을 camelCase로 변환합니다.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public static function camel($value)
	{
		if (isset(static::$camelCache[$value])) {
			return static::$camelCache[$value];
		}

		return static::$camelCache[$value] = lcfirst(static::studly($value));
	}

	/**
	 * 주어진 문자열이 주어진 값을 포함하는지 확인합니다. (대소문자를 구분합니다.)
	 * 또한, 주어진 문자열에 값이 포함되어 있는지 확인하기 위해 배열을 전달할 수도 있습니다.
	 *
	 * @param  string  $haystack
	 * @param  string|string[]  $needles
	 * @return bool
	 */
	public static function contains($haystack, $needles)
	{
		foreach ((array) $needles as $needle) {
			if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 주어진 문자열에 모든 배열 값이 포함되어 있는지 확인합니다.
	 *
	 * @param  string  $haystack
	 * @param  string[]  $needles
	 * @return bool
	 */
	public static function containsAll($haystack, array $needles)
	{
		foreach ($needles as $needle) {
			if (!static::contains($haystack, $needle)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * 주어진 문자열이 주어진 값으로 끝나는지 확인합니다.
	 * 주어진 문자열이 주어진 값으로 끝나는지 확인하기 위해 배열을 전달할 수도 있습니다.
	 *
	 * @param  string  $haystack
	 * @param  string|string[]  $needles
	 * @return bool
	 */
	public static function endsWith($haystack, $needles)
	{
		foreach ((array) $needles as $needle) {
			if (substr($haystack, -strlen($needle)) === (string) $needle) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 문자열이 주어진 값으로 끝나지 않으면 해당 값을 추가합니다.
	 *
	 * @param  string  $value
	 * @param  string  $cap
	 * @return string
	 */
	public static function finish($value, $cap)
	{
		$quoted = preg_quote($cap, '/');

		return preg_replace('/(?:' . $quoted . ')+$/u', '', $value) . $cap;
	}

	/**
	 * 주어진 문자열을 kebab-case로 변환합니다.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public static function kebab($value)
	{
		return static::snake($value, '-');
	}

	/**
	 * 주어진 문자열을 지정된 길이를 반환합니다.
	 *
	 * @param  string  $value
	 * @param  string|null  $encoding
	 * @return int
	 */
	public static function length($value, $encoding = null)
	{
		if ($encoding) {
			return mb_strlen($value, $encoding);
		}

		return mb_strlen($value);
	}

	/**
	 * 주어진 문자열을 지정된 길이로 제한합니다.
	 * 끝에 추가 될 문자열을 변경하기 위해 세 번째 인수를 전달할 수도 있습니다.
	 *
	 * @param  string  $value
	 * @param  int  $limit
	 * @param  string  $end
	 * @return string
	 */
	public static function limit($value, $limit = 100, $end = '...')
	{
		if (mb_strwidth($value, 'UTF-8') <= $limit) {
			return $value;
		}

		return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
	}

	/**
	 * 주어진 문자열을 소문자로 변환합니다.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public static function lower($value)
	{
		return mb_strtolower($value, 'UTF-8');
	}

	/**
	 * 문자열의 단어 수를 제한합니다.
	 *
	 * @param  string  $value
	 * @param  int  $words
	 * @param  string  $end
	 * @return string
	 */
	public static function words($value, $words = 100, $end = '...')
	{
		preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

		if (!isset($matches[0]) || static::length($value) === static::length($matches[0])) {
			return $value;
		}

		return rtrim($matches[0]) . $end;
	}

	/**
	 * 첫번째 이미지 썸네일 추출
	 *
	 * @param  string  $html word
	 */
	public static function getImgSrc($htmlWord)
	{
		preg_match("/<img[^>]*src=[\"']?([^>\"']+)[\"']?[^>]*>/i", $htmlWord, $matches);
		$result = '';
		if ($matches[1]) {
			$result = $matches[1];
		} else if ($matches[2]) {
			$result = $matches[2];
		} else if ($matches[3]) {
			$result = $matches[3];
		}
		return $result;
	}

	/**
	 * Parse a Class[@]method style callback into class and method.
	 *
	 * @param  string  $callback
	 * @param  string|null  $default
	 * @return array<int, string|null>
	 */
	public static function parseCallback($callback, $default = null)
	{
		return static::contains($callback, '@') ? explode('@', $callback, 2) : [$callback, $default];
	}

	/**
	 * 지정된 길이의 문자열을 무작위로 생성합니다. 이 함수는 PHP의 random_bytes 함수를 사용합니다.
	 *
	 * @param  int  $length
	 * @return string
	 */
	public static function random($length = 16)
	{
		$string = '';

		while (($len = strlen($string)) < $length) {
			$size = $length - $len;

			$bytes = random_bytes($size);

			$string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
		}

		return $string;
	}

	/**
	 * 배열을 사용하여 문자열에서 주어진 값을 차례대로 바꿉니다.
	 *
	 * @param  string  $search
	 * @param  array<int|string, string>  $replace
	 * @param  string  $subject
	 * @return string
	 */
	public static function replaceArray($search, array $replace, $subject)
	{
		$segments = explode($search, $subject);

		$result = array_shift($segments);

		foreach ($segments as $segment) {
			$tmpReplace = '';
			$tmpReplace = array_shift($replace);
			$result .= ($tmpReplace ? $tmpReplace : $search) . $segment;
		}

		return $result;
	}

	/**
	 * 문자열에 배열의 값 중 하나라도 포함되어 있는지 검사합니다.
	 *
	 * @param string $string 검사할 문자열
	 * @param array $array 검사할 값이 포함된 배열
	 * @return bool 문자열에 배열의 값 중 하나라도 포함되어 있으면 true, 그렇지 않으면 false
	 */
	public static function searchArray($string, $array)
	{
		foreach ($array as $value) {
			if (strpos($string, $value) !== false) {
				// 값이 문자열 내에 존재하면 true를 반환합니다.
				return true;
			}
		}
		// 배열의 모든 값이 문자열 내에 존재하지 않으면 false를 반환합니다.
		return false;
	}

	/**
	 * 문자열에서 주어진 값이 발견된 첫 번째 부분을 대체합니다.
	 *
	 * @param  string  $search
	 * @param  string  $replace
	 * @param  string  $subject
	 * @return string
	 */
	public static function replaceFirst($search, $replace, $subject)
	{
		if ($search == '') {
			return $subject;
		}

		$position = strpos($subject, $search);

		if ($position !== false) {
			return substr_replace($subject, $replace, $position, strlen($search));
		}

		return $subject;
	}

	/**
	 * 문자열에서 주어진 값이 발견된 마지막 부분을 대체합니다.
	 *
	 * @param  string  $search
	 * @param  string  $replace
	 * @param  string  $subject
	 * @return string
	 */
	public static function replaceLast($search, $replace, $subject)
	{
		$position = strrpos($subject, $search);

		if ($position !== false) {
			return substr_replace($subject, $replace, $position, strlen($search));
		}

		return $subject;
	}

	/**
	 * 문자열이 주어진 값으로 시작하지 않으면 이를 문자열에 추가합니다.
	 *
	 * @param  string  $value
	 * @param  string  $prefix
	 * @return string
	 */
	public static function start($value, $prefix)
	{
		$quoted = preg_quote($prefix, '/');

		return $prefix . preg_replace('/^(?:' . $quoted . ')+/u', '', $value);
	}

	/**
	 * 주어진 문자열을 대문자로 변환합니다.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public static function upper($value)
	{
		return mb_strtoupper($value, 'UTF-8');
	}

	/**
	 * 주어진 문자열을 Title Case로 변환합니다.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public static function title($value)
	{
		return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
	}

	/**
	 * 주어진 문자열을 snake_case 형태로 변환합니다.
	 *
	 * @param  string  $value
	 * @param  string  $delimiter
	 * @return string
	 */
	public static function snake($value, $delimiter = '_')
	{
		$key = $value;

		if (isset(static::$snakeCache[$key][$delimiter])) {
			return static::$snakeCache[$key][$delimiter];
		}

		if (!ctype_lower($value)) {
			$value = preg_replace('/\s+/u', '', ucwords($value));

			$value = static::lower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
		}

		return static::$snakeCache[$key][$delimiter] = $value;
	}

	public static function PregMatch($string, $regexp, $index = 0)
	{
		preg_match($regexp, $string, $match);
		if (!$match) return null;
		if ($index == -1) return $match;
		else return $match[$index];
	}

	public static function PregMatchAll($string, $regexp, $index = 0)
	{
		preg_match_all($regexp, $string, $match);
		if (!$match) return null;
		if ($index == -1) return $match;
		else return $match[$index];
	}

	/**
	 * 문자열이 주어진 값으로 시작하는지를 확인합니다.
	 *
	 * @param  string  $haystack
	 * @param  string|string[]  $needles
	 * @return bool
	 */
	public static function startsWith($haystack, $needles)
	{
		foreach ((array) $needles as $needle) {
			if ((string) $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 주어진 문자열을 StudlyCase로 변환합니다.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public static function studly($value)
	{
		$key = $value;

		if (isset(static::$studlyCache[$key])) {
			return static::$studlyCache[$key];
		}

		$value = ucwords(str_replace(['-', '_'], ' ', $value));

		return static::$studlyCache[$key] = str_replace(' ', '', $value);
	}

	/**
	 * start 및 length 매개 변수로 지정된 문자열 부분을 리턴합니다.
	 *
	 * @param  string  $string
	 * @param  int  $start
	 * @param  int|null  $length
	 * @return string
	 */
	public static function substr($string, $start, $length = null)
	{
		return mb_substr($string, $start, $length, 'UTF-8');
	}

	/**
	 * Returns the number of substring occurrences.
	 *
	 * @param  string  $haystack
	 * @param  string  $needle
	 * @param  int  $offset
	 * @param  int|null  $length
	 * @return int
	 */
	public static function substrCount($haystack, $needle, $offset = 0, $length = null)
	{
		if (!is_null($length)) {
			return substr_count($haystack, $needle, $offset, $length);
		} else {
			return substr_count($haystack, $needle, $offset);
		}
	}

	/**
	 * 첫 문자를 대문자로하여 주어진 문자열을 반환합니다.
	 *
	 * @param  string  $string
	 * @return string
	 */
	public static function ucfirst($string)
	{
		return static::upper(static::substr($string, 0, 1)) . static::substr($string, 1);
	}

	public static function strToHex($string)
	{
		$hex = '';
		for ($i = 0; $i < mb_strlen($string); $i++) {
			$ord = mb_ord(mb_substr($string, $i, 1));
			$hexCode = dechex($ord);
			$hex .= (($i > 0) ? '|' : '') . $hexCode;
		}
		return strToUpper($hex);
	}
}
