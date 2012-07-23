<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Generator Class type.
 *
 * @package    Generator
 * @category   Generator/Types
 * @author     Zeebee
 * @copyright  (c) 2012 Zeebee
 * @license    BSD revised
 */
class Generator_Generator_Type_Class extends Generator_Type
{
	protected $_template = 'generator/type/class';
	protected $_folder   = 'classes';

	protected $_defaults = array(
		'package'    => 'package',
		'category'   => 'category',
		'author'     => 'author',
		'copyright'  => 'copyright',
		'license'    => 'license',
		'class_type' => 'class',
	);

	/**
	 * Sets whether the class should be defined as abstract.
	 *
	 * @param   boolean  $abstract  Is the class abstract?
	 * @return  Generator_Type_Class  The current instance
	 */
	public function as_abstract($abstract = TRUE)
	{
		$this->_params['abstract'] = (bool) $abstract;
		return $this;
	}

	/**
	 * Sets any parent class that this class should extend.
	 *
	 * @param   string  $class  The parent class name
	 * @return  Generator_Type_Class  This instance
	 */
	public function extend($class)
	{
		$this->_params['extends'] = (string) $class;
		return $this;
	}

	/**
	 * Adds any interfaces that this class should be defined as implementing.
	 *
	 * The interfaces may be passed either as an array, comma-separated list
	 * of interface names, or as a single interface name.
	 *
	 * @param   string|array  $interfaces  The interface names to implement
	 * @return  Generator_Type_Class  This instance
	 */
	public function implement($interfaces)
	{
		$this->param_to_array($interfaces, 'implements');
		return $this;
	}

	/**
	 * Converts the interfaces list to a string, adds any method skeletons to be
	 * be implemented by the class and renders the template.
	 *
	 * @return  string  The rendered output
	 */
	public function render()
	{
		// Start the methods list
		$methods = isset($this->_params['methods']) ? $this->_params['methods'] : array();

		// Check any parent for abstract methods that need implementing
		if (empty($this->_params['abstract']) AND ! empty($this->_params['extends'])
			AND empty($this->_params['blank']))
		{
			$implemented = $this->_get_reflection_methods($this->_params['extends'],
				Generator_Reflector::TYPE_CLASS);

			// Merge any class and abstract methods to implement
			$methods = array_merge($methods, $implemented);
		}

		if ( ! empty($this->_params['implements']))
		{
			if (empty($this->_params['blank']))
			{
				// Get the interface methods
				$interfaces = $this->_get_reflection_methods($this->_params['implements'],
					Generator_Reflector::TYPE_INTERFACE);

				// Merge any class and interface methods
				$methods = array_merge($methods, $interfaces);
			}

			// Convert the interfaces list
			$this->_params['implements'] = implode(', ', (array) $this->_params['implements']);
		}

		// Group any methods by modifier
		$this->_params['methods'] = $this->_group_by_modifier($methods);

		return parent::render();
	}

	/**
	 * Returns reflection details of the source methods to be implemented,
	 * allowing the generation of basic skeleton methods.
	 *
	 * @param   string|array  $sources  The source names to inspect
	 * @param   string        $type     The inspected source type
	 * @param   boolean       $inherit  Should inherited methods be included?
	 * @param   Generator_Reflector  $reflector  The reflector object to use, if any
	 * @return  array   Details of the methods to be implemented
	 */
	protected function _get_reflection_methods($sources, $type, $inherit = FALSE,
		Generator_Reflector $reflector = NULL)
	{
		$refl = ($reflector !== NULL) ? $reflector : (new Generator_Reflector);
		$refl->type($type);

		// Are we implementing methods in a class?
		$implementing = ($refl !== $reflector);

		// Start the methods list
		$methods = array();

		foreach ( (array) $sources as $source)
		{
			// Only add skeleton methods for known sources
			if ($refl->source($source)->exists())
			{
				foreach ($refl->get_methods($implementing) as $method => $m)
				{
					// Check any inherited methods (including from interfaces)
					if (($m['class'] != $source) AND ($m['final'] OR $m['private']
						OR ( ! $implementing AND ! $inherit)))
					{
						// Skip uninheritable methods and any others that we're not
						// trying to implement if the inherit option isn't set.
						continue;
					}

					if ($m['abstract'] AND $implementing)
					{
						// Don't treat methods as abstract if we're implementing them
						$m = $refl->make_method_concrete($m, $method);
					}

					if (empty($m['doccomment']))
					{
						// Create a new doccomment if one doesn't exist
						$doc = View::factory('generator/type/doccomment')->set('tabs', 1);
						$tags = array();

						// Set the method description
						$prefix = $m['abstract'] ? 'Declaration' : 'Implementation';
						$doc->set('short_description',  "{$prefix} of {$m['class']}::{$method}");

						// Build the comment tags
						foreach ($m['params'] as $param => $p)
						{
							$tags[] = '@param   '.$p['type'].'  $'.$param;
						}
						$tags[] = '@return  void  **Needs editing**';

						// Include the rendered doccomment
						$m['doccomment'] = $doc->set('tags', $tags)->render();
					}

					// Include the full method signature
					$m['signature'] = $refl->get_method_signature($method);

					// Include the method body
					if ( ! $refl->is_abstract() AND ! $m['abstract'] AND $m['class'] != $source)
					{
						// Invoke the parent for inherited methods
						$m['body']  = isset($doc) ? '' : ('// Defined in '.$m['class'].PHP_EOL."\t\t");
						$m['body'] .= 'parent::'.$refl->get_method_invocation($method).';';
					}
					elseif ( ! $m['abstract'])
					{
						// Otherwise just add an in-line comment
						$m['body'] = isset($doc) ? '// Method implementation'
							: "// Implementation of {$m['class']}::{$method}";
					}

					// Add the method info
					$methods[$method] = $m;
					unset($doc);
				}
			}
		}

		return $methods;
	}

	/**
	 * Indexes a set of reflection values (e.g. methods, properties) by their
	 * modifier types for proper grouping in the template.
	 *
	 * @param   array  $source  The reflection values to group
	 * @return  array  The grouped list
	 */
	protected function _group_by_modifier(array $source)
	{
		$grouped = array();

		foreach ($source as $key => $value)
		{
			if (strpos($value['modifiers'], 'static') !== FALSE)
			{
				$grouped['static'][$key] = $value;
			}
			elseif (strpos($value['modifiers'], 'abstract') !== FALSE)
			{
				$grouped['abstract'][$key] = $value;
			}
			elseif (strpos($value['modifiers'], 'public') !== FALSE)
			{
				$grouped['public'][$key] = $value;
			}
			else
			{
				$grouped['other'][$key] = $value;
			}
		}

		return $grouped;
	}

} // End Generator_Generator_Type_Class
