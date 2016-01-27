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

use DTForce\ResMan\Configuration;
use DTForce\ResMan\Exception\MissingKeyInVersionException;
use DTForce\ResMan\Exception\UndefinedKeysFoundException;
use Nette\Neon\Neon;
use PhpParser;
use PhpParser\BuilderFactory;
use PhpParser\Node\Arg;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\PrettyPrinter;


final class ValuesGenerator
{

	/**
	 * @var Configuration
	 */
	private $configuration;


	public function __construct(Configuration $configuration)
	{
		$this->configuration = $configuration;
	}


	/**
	 * @param string $path
	 */
	public function generateFromNeonFile($path)
	{
		$actualDir = dirname($path);
		$definition = Neon::decode(file_get_contents($path));

		assert(isset($definition['class']));
		assert(isset($definition['defaultVersion']));

		$addFolder = null;
		$addNamespace = null;
		if (isset($definition['addNamespace'])) {
			$addNamespace = $definition['addNamespace'];
			$addFolder = implode(DIRECTORY_SEPARATOR, explode('\\', $addNamespace));
		}

		$versionKeyPrefix = isset($definition['versionKeyPrefix']) ? $definition['versionKeyPrefix'] : 'VERSION_';

		$type = $this->getType($definition);
		$defaultVersion = $definition['defaultVersion'];
		list($versions, $keys) = $this->processVersions($definition['versions'], $type, $actualDir, $defaultVersion);

		if ($addFolder !== null) {
			$output = implode(DIRECTORY_SEPARATOR, [
				$this->configuration->getDir(),	$this->configuration->getOutputFolder(), $addFolder, $definition['class'] . '.php']
			);
		} else {
			$output = implode(DIRECTORY_SEPARATOR, [
				$this->configuration->getDir(), $this->configuration->getOutputFolder(), $definition['class'] . '.php'
			]);
		}

		$namespace = $addNamespace === null ?
			$this->configuration->getNamespace() : $this->configuration->getNamespace() . '\\' . $addNamespace;

		$constants = Helper::createIntConstants(array_flip($keys));
		$constants = array_merge($constants, $this->addVersionKeysConstants($versions, $versionKeyPrefix));

		$node = $this->createClassFromData(
			$definition['class'],
			$namespace,
			$constants,
			$defaultVersion,
			Helper::createArray($versions),
			Helper::createArray(array_keys($versions))
		);

		$prettyPrinter = new PrettyPrinter\Standard();
		@mkdir(dirname($output), 0777, true);
		file_put_contents($output, $prettyPrinter->prettyPrintFile([$node]));
	}


	/**
	 * @param string $className
	 * @param string $namespace
	 * @param Const_[] $constants
	 * @param string $defaultVersion
	 * @param Array_ $values
	 *
	 * @return PhpParser\Node
	 */
	private function createClassFromData($className, $namespace, $constants, $defaultVersion, Array_ $values, Array_ $allowedVersions)
	{
		$factory = new BuilderFactory();
		return $factory->namespace($namespace)
			->addStmt(
				$factory->class($className)
					->addStmt(new ClassConst($constants))
					->addStmt(
						$factory->property('values')
							->makePrivate()
							->makeStatic()
							->setDefault($values)
					)
					->addStmt(
						$factory->property('allowedVersions')
							->makePrivate()
							->makeStatic()
							->setDefault($allowedVersions)
					)
					->addStmt(
						$factory->method('getValue')
							->makePublic()
							->makeStatic()
							->addParam(
								$factory->param('key')
							)
							->addParam(
								$factory->param('version')
							)
							->addStmts([
								new Return_(
									new ArrayDimFetch(
										new ArrayDimFetch(
											new StaticPropertyFetch(new Name($className), 'values'),
											new Variable('version')
										),
										new Variable('key')
									)
								)
							])
					)
					->addStmt(
						$factory->method('getDefaultVersion')
							->makePublic()
							->makeStatic()
							->addStmts([
								new Return_(new String_($defaultVersion))
							])
					)
					->addStmt(
						$factory->method('isVersionAllowed')
							->makePublic()
							->makeStatic()
							->addParam(
								$factory->param('version')
							)
							->addStmts([
								new Return_(new FuncCall(new Name("in_array"), [new Arg(new Variable("version")), new Arg(new StaticPropertyFetch(new Name($className), 'allowedVersions'))]))
							])
					)
			)->getNode();
	}


	/**
	 * @param $definition
	 *
	 * @return mixed
	 */
	private function getType($definition)
	{
		assert(isset($definition['type']));
		assert(in_array($definition['type'], ['in-place', 'csv']));
		return $definition['type'];
	}


	/**
	 * @param array $versions
	 * @param string $type
	 * @param string $actualDir
	 * @param string $defaultVersion
	 *
	 * @return array
	 */
	private function processVersions($versions, $type, $actualDir, $defaultVersion)
	{
		if ($type === 'csv') {
			$result = [];
			foreach ($versions as $name => $version) {
				$result[$name] = Helper::readCsvValues($actualDir . DIRECTORY_SEPARATOR . $version);
			}
		} else {
			$result = $versions;
		}
		$keys = array_keys($result[$defaultVersion]);
		$flippedKeys = array_flip($keys);

		foreach ($result as $name => $version) {
			$result[$name] = $this->processVersion($name, $version, $flippedKeys);
		}

		return [$result, $keys];
	}


	/**
	 * @param $name
	 * @param $version
	 * @param array $flippedKeys
	 *
	 * @return array
	 * @throws MissingKeyInVersionException
	 * @throws UndefinedKeysFoundException
	 */
	private function processVersion($name, $version, array $flippedKeys)
	{
		$newVersion = [];
		foreach ($flippedKeys as $key => $newKey) {
			if (!isset($version[$key])) {
				throw new MissingKeyInVersionException($name, $key);
			}
			$newVersion[$newKey] = $version[$key];
			unset($version[$key]);
		}

		if (count($version)) {
			throw new UndefinedKeysFoundException(array_keys($version), $name);
		}
		return $newVersion;
	}


	private function addVersionKeysConstants($versions, $versionKeyPrefix)
	{
		$constants = [];
		foreach ($versions as $versionName => $version) {
			$constants[$versionKeyPrefix . strtoupper($versionName)] = $versionName;
		}
		return Helper::createStringConstants($constants);
	}

}
