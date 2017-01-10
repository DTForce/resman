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

use DirectoryIterator;
use dtforce\resman\src\Exception\BadFormatException;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassConst;


final class Helper
{
	const SEPARATOR = ",";

	/**
	 * @param array $data
	 *
	 * @return ClassConst[]
	 */
	public static function createStringConstants(array $data)
	{
		$consts = [];
		foreach ($data as $key => $value) {
			$consts[] = new ClassConst([new Const_($key, new String_($value))]);
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
				$items[] = new ArrayItem(new String_($value, ['kind' => String_::KIND_DOUBLE_QUOTED]), new String_($key));
			}

		}
		return new Array_($items);
	}


	/**
	 * @param string $file
	 *
	 * @return array
	 */
	public static function readCsvKeysValues($file)
	{
		return self::readFile($file, true);
	}


	/**
	 * @param string $file
	 *
	 * @return array
	 */
	public static function readCsvValues($file)
	{
		return self::readFile($file, false);
	}


	/**
	 * @param string $fileRow
	 * @param  bool $associative
	 *
	 * @return array|null
	 */
	public static function readFile($file, $associative)
	{
		$data = [];
		$handle = fopen($file, 'r');
		$lineCounter = 0;

		while($fileRow = fgets($handle)) {
			// line couting starts at 1
			++$lineCounter;
			$trimmed = trim($fileRow);
			$fileRow = rtrim($fileRow, "\n");
			if (mb_strlen($trimmed) === 0 || $trimmed[0] === '#') {
				continue;
			} else if ($associative) {
				$separatorIndex = mb_strpos($fileRow, self::SEPARATOR);
				if ($separatorIndex === false || $separatorIndex === 0) {
					throw new BadFormatException($file, $lineCounter);
				}
				$key = mb_substr($fileRow, 0, $separatorIndex);
				$value = mb_substr($fileRow, $separatorIndex + mb_strlen(self::SEPARATOR));
				$data[$key] = $value;
			} else {
				$data[] = $fileRow;
			}
		}

		fclose($handle);

		return $data;
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


	/**
	 * @param string $dirPath
	 * @param bool $skipItself
	 */
	public static function rmDirRec($dirPath, $skipItself = false)
	{
		if (is_dir($dirPath)) {
			$dirIter = new DirectoryIterator($dirPath);
			foreach ($dirIter as $file) {
				if ($file->isDot()) {
					continue;
				}

				if ($file->isDir()) {
					self::rmDirRec($file->getRealPath());
				} else {
					unlink($file->getRealPath());
				}
			}
			if ( ! $skipItself) {
				rmdir($dirPath);
			}
		}
	}


	public static function namesFromValues(array $values)
	{
		$map = [];
		foreach ($values as $value) {
			$key = self::formatName($value);
			$map[$key] = $value;
		}
		return $map;
	}


	public static function formatNames(array $values)
	{
		$map = [];
		foreach ($values as $key => $value) {
			$key = self::formatName($key);
			$map[$key] = $value;
		}
		return $map;
	}


	/**
	 * @param string $value
	 * @return string
	 */
	private static function formatName($value)
	{
		return strtoupper(str_replace('-', '_', $value));
	}

}
