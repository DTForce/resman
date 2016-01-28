<?php
/**
 * This file is part of ResMan library.
 *
 * Copyright (c) 2015 DTForce, s.r.o. (http://www.dtforce.com)
 *
 * For the full copyright and license information, please view
 * the file LICENSE that was distributed with this source code.
 */

namespace DTForce\ResMan\Generator;

use PhpParser\Node\Const_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;


final class Helper
{

	/**
	 * @param array $data
	 *
	 * @return Const_[]
	 */
	public static function createStringConstants(array $data)
	{
		$consts = [];
		foreach ($data as $key => $value) {
			$consts[] = new Const_($key, new String_($value));
		}
		return $consts;
	}


	/**
	 * @param array $data
	 *
	 * @return Const_[]
	 */
	public static function createIntConstants(array $data)
	{
		$consts = [];
		foreach ($data as $key => $value) {
			$consts[] = new Const_($key, new LNumber($value));
		}
		return $consts;
	}


	/**
	 * @param array $data
	 *
	 * @return Array_
	 */
	public static function createArray(array $data)
	{
		$items = [];
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$items[] = new ArrayItem(self::createArray($value), new String_($key));
			} else {
				$items[] = new ArrayItem(new String_($value), new String_($key));
			}

		}
		return new Array_($items);
	}


	/**
	 * @param string $file
	 *
	 * @return array
	 */
	public static function readCsvValues($file)
	{
		$csv = array_map('str_getcsv', file($file));
		$map = [];
		foreach ($csv as $row) {
			$map[$row[0]] = $row[1];
		}
		return $map;
	}


	/**
	 * Convert underscore to camelCase format.
	 *
	 * @param $property
	 *
	 * @return mixed
	 */
	public static function toCamelCase($property)
	{
		$func = create_function('$match', 'return strtoupper($match[1]);');

		return preg_replace_callback('/_([a-z])/', $func, $property);
	}


	/**
	 * Convert underscore to PascalCase format.
	 *
	 * @param $property
	 *
	 * @return mixed
	 */
	public static function toPascalCase($property)
	{
		return ucfirst(self::toCamelCase($property));
	}


	/**
	 * Convert camelCase to underscore format.
	 *
	 * @param $property
	 *
	 * @return mixed
	 */
	public static function toUnderscore($property)
	{
		$func = create_function('$match', 'return \'_\' . strtolower($match[1]);');

		return preg_replace_callback('/([A-Z])/', $func, $property);
	}

}
