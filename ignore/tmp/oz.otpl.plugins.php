<?php

// ======================================================
// quote string with '\'' or '"'

	if (!function_exists('oz_quoted')) {
		function oz_quoted($str, $quote = "'")
		{
			return preg_quote($str);
		}
	}

// ======================================================
// return list like 'POST','GET', 'my \' item'

	if (!function_exists('oz_quoted_list')) {
		function oz_quoted_list(array $list, $quote = "'")
		{
			foreach ($list as $key => $value) {
				$list[$key] = oz_quoted($value, $quote);
			}

			return implode(' , ', $list);
		}
	}

// ======================================================
// indent

	if (!function_exists('oz_indent')) {
		function oz_indent($str, $indentCount = 1, $spaceCount = 0)
		{
			$indent = "\t";

			if ($spaceCount != 0) {
				$indent = str_repeat(' ', intval($spaceCount));
			}

			$indent = str_repeat($indent, $indentCount);

			return str_replace("\n", "\n" . $indent, $str);
		}
	}

// ======================================================
// uppercase

	if (!function_exists('oz_toupper')) {
		function oz_toupper($str, $type = 'all')
		{
			$out = '';

			switch ($type) {
				case 'first':
					$out = ucfirst($str);
					break;
				case 'firstword':
					$out = ucwords($str);
					break;
				default:
					$out = strtoupper($str);
			}

			return $out;
		}
	}

// ======================================================
// lowercase

	if (!function_exists('oz_tolower')) {
		function oz_tolower($str)
		{
			return strtolower($str);
		}
	}

