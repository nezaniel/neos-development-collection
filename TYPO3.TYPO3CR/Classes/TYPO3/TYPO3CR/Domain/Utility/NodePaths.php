<?php
namespace TYPO3\TYPO3CR\Domain\Utility;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3CR".               *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * Provides basic logic concerning node paths.
 */
abstract class NodePaths {

	/**
	 * Appends the given $nodePathSegment to the $nodePath
	 *
	 * @param string $nodePath Absolute node path
	 * @param string $nodePathSegment Usually a nodeName but could also be a relative node path.
	 * @return string
	 */
	static public function addNodePathSegment($nodePath, $nodePathSegment) {
		$nodePath = rtrim($nodePath, '/');
		if ($nodePathSegment !== '' || $nodePath === '') {
			$nodePath .= '/' . trim($nodePathSegment, '/');
		}

		return $nodePath;
	}

	/**
	 * Returns the given absolute node path appended with additional context information (such as the workspace name and dimensions).
	 *
	 * @param string $path absolute node path
	 * @param string $workspaceName
	 * @param array $dimensionValues
	 * @return string
	 */
	static public function generateContextPath($path, $workspaceName, array $dimensionValues = array()) {
		$contextPath = $path;
		$contextPath .= '@' . $workspaceName;

		if ($dimensionValues !== array()) {
			$contextPath .= ';';
			foreach ($dimensionValues as $dimensionName => $innerDimensionValues) {
				$contextPath .= $dimensionName . '=' . implode(',', $innerDimensionValues) . '&';
			}
			$contextPath = substr($contextPath, 0, -1);
		}

		return $contextPath;
	}

	/**
	 * Splits the given context path into relevant information, which results in an array with keys:
	 * "nodePath", "workspaceName", "dimensions"
	 *
	 * @param string $contextPath a context path including workspace and/or dimension information.
	 * @return array split information from the context path
	 * @see generateContextPath()
	 */
	static public function explodeContextPath($contextPath) {
		preg_match(NodeInterface::MATCH_PATTERN_CONTEXTPATH, $contextPath, $matches);
		if (!isset($matches['NodePath'])) {
			throw new \InvalidArgumentException('The given string was not a valid contextPath.', 1431281250);
		}

		$nodePath = $matches['NodePath'];
		$workspaceName = (isset($matches['WorkspaceName']) && $matches['WorkspaceName'] !== '' ? $matches['WorkspaceName'] : 'live');
		$dimensions = isset($matches['Dimensions']) ? static::parseDimensionValueStringToArray($matches['Dimensions']) : NULL;

		return array(
			'nodePath' => $nodePath,
			'workspaceName' => $workspaceName,
			'dimensions' => $dimensions
		);
	}

	/**
	 * @param string $dimensionValueString
	 * @return array
	 */
	static public function parseDimensionValueStringToArray($dimensionValueString) {
		parse_str($dimensionValueString, $dimensions);
		$dimensions = array_map(function ($commaSeparatedValues) { return explode(',', $commaSeparatedValues); }, $dimensions);

		return $dimensions;
	}

	/**
	 * Determine if the given node path is a context path.
	 *
	 * @param string $contextPath
	 * @return boolean
	 */
	static public function isContextPath($contextPath) {
		return (strpos($contextPath, '@') !== FALSE);
	}

	/**
	 * Get the name for a Node based on the given path.
	 *
	 * @param string $path
	 * @return string
	 */
	static public function getNodeNameFromPath($path) {
		return $path === '/' ? '' : substr($path, strrpos($path, '/') + 1);
	}

	/**
	 * Get the parent path of the given Node path.
	 *
	 * @param string $path
	 * @return string
	 */
	static public function getParentPath($path) {
		if ($path === '/') {
			$parentPath = '';
		} elseif (strrpos($path, '/') === 0) {
			$parentPath = '/';
		} else {
			$parentPath = substr($path, 0, strrpos($path, '/'));
		}

		return $parentPath;
	}

	/**
	 * Does $possibleSubPath begin with $path and so is a subpath or not.
	 *
	 * @param string $path
	 * @param string $possibleSubPath
	 * @return boolean
	 */
	static public function isSubPathOf($path, $possibleSubPath) {
		return (strpos($possibleSubPath, $path) === 0);
	}

	/**
	 * Returns the depth of the given Node path.
	 * The root node "/" has depth 0, for every segment 1 is added.
	 *
	 * @param string $path
	 * @return integer
	 */
	static public function getPathDepth($path) {
		return $path === '/' ? 0 : substr_count($path, '/');
	}

	/**
	 * Replaces relative path segments ("." or "..") in a given path
	 *
	 * @param string $path absolute node path with relative path elements ("." or "..").
	 * @return string
	 */
	static public function replaceRelativePathElements($path) {
		$pathSegments = explode('/', $path);
		$absolutePath = '';
		foreach ($pathSegments as $pathSegment) {
			switch ($pathSegment) {
				case '.':
					continue;
				break;
				case '..':
					$absolutePath = NodePaths::getParentPath($absolutePath);
				break;
				default:
					$absolutePath = NodePaths::addNodePathSegment($absolutePath, $pathSegment);
				break;
			}
		}

		return $absolutePath;
	}

	/**
	 * Get the relative path between the given $parentPath and the given $subPath.
	 * Example with "/foo" and "/foo/bar/baz" will return "bar/baz".
	 *
	 * @param string $parentPath
	 * @param string $subPath
	 * @return string
	 */
	static public function getRelativePathBetween($parentPath, $subPath) {
		if (self::isSubPathOf($parentPath, $subPath) === FALSE) {
			throw new \InvalidArgumentException('Given path "' . $parentPath . '" is not the beginning of "' . $subPath .'", cannot get a relative path between them.', 1430075362);
		}

		return trim(substr($subPath, strlen($parentPath)), '/');
	}

	/**
	 * Generates a simple random node name.
	 *
	 * @return string
	 */
	static public function generateRandomNodeName() {
		return uniqid('node-');
	}

	/**
	 * Normalizes the given node path to a reference path and returns an absolute path.
	 *
	 * You should usually use \TYPO3\TYPO3CR\Domain\Service\NodeService::normalizePath()  because functionality could be overloaded,
	 * this is here only for low level operations.
	 *
	 *
	 * @see \TYPO3\TYPO3CR\Domain\Service\NodeService::normalizePath()
	 * @param $path
	 * @param string $referencePath
	 * @return string
	 */
	static public function normalizePath($path, $referencePath = NULL) {
		if ($path === '.') {
			return $referencePath;
		}

		if (!is_string($path)) {
			throw new \InvalidArgumentException(sprintf('An invalid node path was specified: is of type %s but a string is expected.', gettype($path)), 1357832901);
		}

		if (strpos($path, '//') !== FALSE) {
			throw new \InvalidArgumentException('Paths must not contain two consecutive slashes.', 1291371910);
		}

		if ($path[0] === '/') {
			$absolutePath = $path;
		} else {
			$absolutePath = NodePaths::addNodePathSegment($referencePath, $path);
		}

		$normalizedPath = NodePaths::replaceRelativePathElements($absolutePath);
		return strtolower($normalizedPath);
	}
}