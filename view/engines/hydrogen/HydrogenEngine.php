<?php
/*
 * Copyright (c) 2009 - 2012, Frosted Design
 * All rights reserved.
 */

namespace hydrogen\view\engines\hydrogen;

use hydrogen\config\Config;
use hydrogen\view\TemplateEngine;
use hydrogen\view\engines\hydrogen\Parser;
use hydrogen\view\engines\hydrogen\exceptions\NoSuchFilterException;
use hydrogen\view\engines\hydrogen\exceptions\NoSuchTagException;

/**
 * The Hydrogen Templating Engine is a robust template parser based on the
 * highly-acclaimed Django template language, with some key changes for
 * increased performance and more flexibility for template creators.  See
 * linked documentation for more information.
 *
 * @link http://www.webdevrefinery.com/forums/topic/6404-hydrogen-templates-for-front-end-developers
 * 		Frontend Development Documentation
 */
class HydrogenEngine implements TemplateEngine {
	
	protected static $filterClass = array();
	protected static $filterPath = array();
	protected static $filterNamespace = array(
		'\hydrogen\view\engines\hydrogen\filters\\'
		);
	protected static $tagClass = array();
	protected static $tagPath = array();
	protected static $tagNamespace = array(
		'\hydrogen\view\engines\hydrogen\tags\\'
		);

	/**
	 * Adds an external filter to the Hydrogen template engine.  Once added,
	 * the filter can be used in any Hydrogen template file for the duration
	 * of that request.
	 *
	 * @param string $filterName The name of the filter to add.  The name is
	 * 		what will be typed in the template files themselves.
	 * @param string $className The class name (with namespace) of this
	 * 		filter.  The filter must implement the interface
	 * 		{@link \hydrogen\view\engines\hydrogen\Filter}.
	 * @param string $path The path to the PHP to require_once before using
	 * 		the given class, either absolute or relative to the base_url
	 * 		defined in the hydrogen.autoconfig.php file.  This argument is
	 * 		OPTIONAL: if omitted, Hydrogen will assume the class will be
	 * 		automatically loaded when accessed.
	 */
	public static function addFilter($filterName, $className, $path=false) {
		$filterName = strtolower($filterName);
		static::$filterClass[$filterName] = static::formatNamespace($className,
			false);
		if ($path)
			static::$filterPath[$filterName] = Config::getAbsolutePath($path);
	}
	
	/**
	 * Adds an external tag to the Hydrogen template engine.  Once added,
	 * the tag can be used in any Hydrogen template file for the duration
	 * of that request.
	 *
	 * @param string $tagName The name of the tag to add.  The name is
	 * 		what will be typed in the template files themselves.
	 * @param string $className The class name (with namespace) of this
	 * 		tag.  The tag must extend the class
	 * 		{@link \hydrogen\view\engines\hydrogen\Tag}.
	 * @param string $path The path to the PHP to require_once before using
	 * 		the given class, either absolute or relative to the base_url
	 * 		defined in the hydrogen.autoconfig.php file.  This argument is
	 * 		OPTIONAL: if omitted, Hydrogen will assume the class will be
	 * 		automatically loaded when accessed.
	 */
	public static function addTag($tagName, $className, $path=false) {
		$tagName = strtolower($tagName);
		static::$tagClass[$tagName] = static::formatNamespace($className,
			false);
		if ($path)
			static::$tagPath[$tagName] = Config::getAbsolutePath($path);
	}
	
	/**
	 * Adds a namespace from which filters will be automatically loaded if
	 * they're not found in the default hydrogen namespace or any namespaces
	 * previously added using this function.  Only the namespace is to be
	 * provided; Hydrogen will then attempt to load any unknown filters by
	 * adding [filtername]Filter to the end of the namespace.
	 *
	 * For example, if the filter {{someVar|swedishchef}} is used in a
	 * template, and this function was called with the namespace
	 * '\myapp\filters', Hydrogen would attempt to load the class
	 * '\myapp\filters\SwedishchefFilter' if it hasn't already been defined
	 * with {@link addFilter()}.  Note the capitalization: for autoloaded
	 * filter names, only the first character of the filter name must be
	 * capitalized, followed by 'Filter' with a capital F.
	 *
	 * The autoloading of this class is up to the programmer, though the file
	 * hydrogen.inc.php can be used as a basis for good autoloading practices
	 * for your own class files.  If your code does not use namespaces, a
	 * backslash can be provided for the $namespace argument.
	 *
	 * @param string $namespace The namespace to which filter classes should
	 * 		be added to attempt to autoload them.
	 */
	public static function addFilterNamespace($namespace) {
		static::$filterNamespace[] = static::formatNamespace($namespace);
	}
	
	/**
	 * Adds a namespace from which tags will be automatically loaded if
	 * they're not found in the default hydrogen namespace or any namespaces
	 * previously added using this function.  Only the namespace is to be
	 * provided; Hydrogen will then attempt to load any unknown tags by
	 * adding [tagname]Tag to the end of the namespace.
	 *
	 * For example, if the tag {% piglatin %} is used in a
	 * template, and this function was called with the namespace
	 * '\myapp\tags', Hydrogen would attempt to load the class
	 * '\myapp\tags\PiglatinFilter' if it hasn't already been defined
	 * with {@link addTag()}.  Note the capitalization: for autoloaded
	 * tag names, only the first character of the tag name must be
	 * capitalized, followed by 'Tag' with a capital T.
	 *
	 * The autoloading of this class is up to the programmer, though the file
	 * hydrogen.inc.php can be used as a basis for good autoloading practices
	 * for your own class files.  If your code does not use namespaces, a
	 * backslash can be provided for the $namespace argument.
	 *
	 * @param string $namespace The namespace to which tag classes should
	 * 		be added to attempt to autoload them.
	 */
	public static function addTagNamespace($namespace) {
		static::$tagNamespace[] = static::formatNamespace($namespace);
	}
	
	/**
	 * Gets the full class name (with namespace) for the given filter.  The
	 * class will either be pre-loaded by this function, or is expected to be
	 * autoloaded when it is first accessed.  The returned class name can be
	 * used with no further error checking.
	 *
	 * @param string $filterName The filter for which to obtain the full
	 * 		namespace + class name.
	 * @param string $origin The template originating the request for this
	 * 		filter.  It is optional, but if provided, will be used to create
	 * 		a more helpful error message should the requested filter not be
	 * 		found.
	 * @return string the full namespace + class name, with leading backslash,
	 * 		for the provided filter name.
	 * @throws NoSuchFilterException when the requested filter was not found.
	 */
	public static function getFilterClass($filterName, $origin=false) {
		return static::getModuleClass($filterName, 'Filter',
			static::$filterClass, static::$filterPath,
			static::$filterNamespace, $origin);
	}
	
	/**
	 * Gets the full class name (with namespace) for the given tag.  The
	 * class will either be pre-loaded by this function, or is expected to be
	 * autoloaded when it is first accessed.  The returned class name can be
	 * used with no further error checking.
	 *
	 * @param string $tagName The tag for which to obtain the full
	 * 		namespace + class name.
	 * @param string $origin The template originating the request for this
	 * 		tag.  It is optional, but if provided, will be used to create
	 * 		a more helpful error message should the requested tag not be
	 * 		found.
	 * @return string the full namespace + class name, with leading backslash,
	 * 		for the provided tag name.
	 * @throws NoSuchTagException when the requested tag was not found.
	 */
	public static function getTagClass($tagName, $origin=false) {
		return static::getModuleClass($tagName, 'Tag',
			static::$tagClass, static::$tagPath,
			static::$tagNamespace, $origin);
	}
	
	/**
	 * Generates the raw PHP for the given template, using the given loader to
	 * load the template files.  Works as specified by
	 * {@link \hydrogen\view\TemplateEngine}.
	 *
	 * @param string $templateName The name of the template to load.
	 * @param \hydrogen\view\Loader $loader The loader with which to load
	 * 		template files.
	 * @return string the full, raw PHP that can be executed in a
	 * 		{@link \hydrogen\view\ViewSandbox}.
	 */
	public static function getPHP($templateName, $loader) {
		$parser = new Parser($templateName, $loader);
		$nodes = $parser->parse();
		return $nodes->render();
	}
	
	/**
	 * Adds a leading backslash to the provided namespace if it's missing one,
	 * and optionally adds as trailing backslash as well.
	 *
	 * @param string $namespace The namespace to start with.
	 * @param boolean $endSlash true to add a trailing slash to the namespace
	 * 		if it doesn't already have one, false otherwise.  This is an
	 * 		optional argument.  Defaults to true.
	 * @return string the formatted namespace.
	 */
	protected static function formatNamespace($namespace, $endSlash=true) {
		if ($namespace[0] !== '\\')
			$namespace = '\\' . $namespace;
		if ($endSlash && $namespace[strlen($namespace) - 1] !== '\\')
			$namespace .= '\\';
		return $namespace;
	}
	
	/**
	 * Gets the full class name for a certain module (tag or filter) using the
	 * provided arrays of data.  This is an abstraction for
	 * {@link getFilterClass} and {@link getTagClass}.
	 *
	 * @param string $modName The name of the filter or tag module for which
	 * 		to search.
	 * @param string $modType The type of module to be loaded.  Should be
	 * 		either 'Tag' or 'Filter', case sensitive.  There is no error
	 * 		checking for this value.
	 * @param array $modClasses An associative array of lowercase module
	 * 		names to full namespace + class names.
	 * @param array $modPaths An associative array of lowercase module names
	 * 		to absolute paths to the PHP file that contains them.
	 * @param array $modNamespaces An array of namespaces in which to search
	 * 		for $modName+$modType if it was not found in $modClasses.
	 * @param string $origin The template name requesting this module.  This
	 * 		value is optional, but helps to produce more helpful error messages
	 * 		should the module not be found.
	 * @throws NoSuchFilterException if the $modType is 'Filter' and the
	 * 		filter was not found.
	 * @throws NoSuchTagException if the $modType is 'Tag' and the tag was not
	 * 		found.
	 */
	protected static function getModuleClass($modName, $modType, &$modClasses,
			&$modPaths, &$modNamespaces, $origin=false) {
		$lowName = strtolower($modName);
		if (isset($modClasses[$lowName])) {
			if (isset($modPaths[$lowName]))
				require_once($modPaths[$lowName]);
			return $modClasses[$lowName];
		}
		$properName = ucfirst($lowName) . $modType;
		foreach ($modNamespaces as $namespace) {
			$class = $namespace . $properName;
			if (@class_exists($class)) {
				$modClasses[$lowName] = $class;
				return $class;
			}
		}
		$error = $modType . ' "' . $modName . '" does not exist' .
			($origin ? ' in template "' . $origin . '".' : '.');
		if ($modType === 'Filter')
			throw new NoSuchFilterException($error);
		else
			throw new NoSuchTagException($error);
	}

}

?>