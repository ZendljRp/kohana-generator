<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Test case for Generator_Reflector.
 *
 * @group      generator
 * @group      generator.reflector
 *
 * @package    Generator
 * @category   Tests
 * @author     Zeebee
 * @copyright  (c) 2012 Zeebee
 * @license    BSD revised
 */
class Generator_ReflectorTest extends Unittest_TestCase
{
	/**
	 * Sources can be set via constructor or by setter method that also acts as
	 * a getter, and any stored values should always be reset.
	 */
	public function test_setting_source_and_type()
	{
		$refl = new TestReflector('TestInterface', Generator_Reflector::TYPE_INTERFACE);

		$this->assertSame('TestInterface', $refl->source());
		$this->assertTrue($refl->is_interface());
		$this->assertAttributeEmpty('_info', $refl);
		$this->assertFalse($refl->is_analyzed());

		$p = new ReflectionProperty('Generator_Reflector', '_info');
		$p->setAccessible(TRUE);
		$p->setValue($refl, array('foo'));
		$this->assertAttributeNotEmpty('_info', $refl);
		$this->assertTrue($refl->is_analyzed());

		$this->assertInstanceOf('Generator_Reflector', $refl->source('SomeSource'));
		$this->assertInstanceOf('Generator_Reflector', $refl->type(Generator_Reflector::TYPE_CLASS));
		$this->assertSame('SomeSource', $refl->source());
		$this->assertFalse($refl->is_interface());
		$this->assertTrue($refl->is_class());
		$this->assertAttributeEmpty('_info', $refl);
		$this->assertFalse($refl->is_analyzed());
	}

	/**
	 * The existence of sources should be checked differently depending on their
	 * types - class, interface, file, etc.
	 */
	public function test_source_exists()
	{
		$refl = new TestReflector('TestInterface', Generator_Reflector::TYPE_INTERFACE);

		$this->assertTrue($refl->exists());
		$refl->source('TestInterface')->type(Generator_Reflector::TYPE_CLASS);
		$this->assertFalse($refl->exists());

		$refl->source('TestReflector')->type(Generator_Reflector::TYPE_CLASS);
		$this->assertTrue($refl->exists());
		$refl->source('TestReflector')->type(Generator_Reflector::TYPE_INTERFACE);
		$this->assertFalse($refl->exists());
	}

	/**
	 * Sources should only be inspected once per run or each time that
	 * analyze() is called, and the method should also be chainable.
	 */
	public function test_analyze_runs_only_once()
	{
		$refl = new TestReflector('TestInterface');

		$this->assertFalse($refl->is_analyzed());

		$refl->get_methods();
		$this->assertTrue($refl->is_analyzed());
		$this->assertSame(1, $refl->analysis_count);

		$refl->get_methods();
		$this->assertTrue($refl->is_analyzed());
		$this->assertSame(1, $refl->analysis_count);

		$this->assertSame($refl, $refl->analyze());
		$this->assertTrue($refl->is_analyzed());
		$this->assertSame(2, $refl->analysis_count);
	}

	/**
	 * Most methods need a source to work with.
	 *
	 * @expectedException Generator_Exception
	 */
	public function test_missing_source_throws_exception()
	{
		$refl = new TestReflector;
		$refl->analyze();
	}

	/**
	 * The main source information should be stored locally as pre-parsed data,
	 * including doccomment, parent, interfaces, constants, properties, etc.
	 */
	public function test_getting_source_information()
	{
		$refl = new TestReflector('TestClass');

		$this->assertRegExp('/A test class/', $refl->get_doccomment());
		$this->assertSame('abstract', $refl->get_modifiers());
		$this->assertSame('TestParentClass', $refl->get_parent());
		$this->assertSame(array('TestInterfaceCountable'), $refl->get_interfaces());

		$constants = $refl->get_constants();
		$this->assertArrayHasKey('CONSTANT_ONE', $constants);
		$this->assertArrayHasKey('CONSTANT_TWO', $constants);

		$properties = $refl->get_properties();
		$this->assertArrayHasKey('prop_one', $properties);
		$this->assertArrayHasKey('prop_two', $properties);

		$this->assertSame(1, $refl->analysis_count);
	}

	/**
	 * We need to be able to check easily if the source is abstract.
	 */
	public function test_source_is_abstract()
	{
		$refl = new TestReflector('TestClass');
		$this->assertTrue($refl->is_abstract());

		$refl = new TestReflector('TestParentClass');
		$this->assertFalse($refl->is_abstract());
	}

	/**
	 * The class methods should be stored locally after initial analysis.
	 */
	public function test_getting_class_methods()
	{
		$refl = new TestReflector('TestClass');
		$methods = $refl->get_methods();
		$this->assertCount(3, $methods);

		$this->assertArrayHasKey('count', $methods);
		$this->assertSame('public', $methods['count']['modifiers']);
		$this->assertArrayHasKey('some_invoked_method', $methods);
		$this->assertSame('public', $methods['some_invoked_method']['modifiers']);
		$this->assertArrayHasKey('some_abstract_method', $methods);
		$this->assertTrue($methods['some_abstract_method']['abstract']);
		$this->assertSame('abstract public', $methods['some_abstract_method']['modifiers']);

		// We can optionally get only abstract methods
		$methods = $refl->get_methods(TRUE);
		$this->assertCount(1, $methods);
		$this->assertArrayHasKey('some_abstract_method', $methods);

		$this->assertSame(1, $refl->analysis_count);
	}

	/**
	 * Interface methods should be marked as abstract but without the 'abstract'
	 * modifier (which is just annoying).
	 */
	public function test_getting_interface_methods()
	{
		$refl = new TestReflector('TestInterface', Generator_Reflector::TYPE_INTERFACE);

		$methods = $refl->get_methods();
		$this->assertCount(2, $methods);
		$this->assertArrayHasKey('method_one', $methods);
		$this->assertArrayHasKey('method_two', $methods);

		$this->assertTrue($methods['method_one']['abstract']);
		$this->assertSame('public', $methods['method_one']['modifiers']);
		$this->assertTrue($methods['method_two']['abstract']);
		$this->assertSame('public', $methods['method_two']['modifiers']);

		$refl = new TestReflector('TestInterfaceChild', Generator_Reflector::TYPE_INTERFACE);
		$methods = $refl->get_methods();
		$this->assertCount(4, $methods);

		$this->assertArrayHasKey('method_three', $methods);
		$this->assertTrue($methods['method_three']['abstract']);
		$this->assertSame('public', $methods['method_three']['modifiers']);
		$this->assertArrayHasKey('count', $methods);
		$this->assertTrue($methods['count']['abstract']);
		$this->assertSame('public', $methods['count']['modifiers']);

		// Interface methods should always be abstract
		$methods = $refl->get_methods(TRUE);
		$this->assertCount(4, $methods);
	}

	/**
	 * Interfaces can implement other interfaces via multiple inheritance, but
	 * we also need to be able to get just the interfaces that aren't inherited
	 * by others in the list, since trying to re-implement interfaces can
	 * cause problems.
	 */
	public function test_getting_inherited_interfaces()
	{
		$refl = new TestReflector('TestInterfaceChildTwo', Generator_Reflector::TYPE_INTERFACE);

		// Get all the interfaces
		$interfaces = $refl->get_interfaces(TRUE);
		$this->assertSame(array(
			'TestInterface', 'TestInterfaceSortable', 'TestInterfaceTraversable', 'TestInterfaceIterable'
		), $interfaces);

		// Get just the non-inherited interfaces
		$interfaces = $refl->get_interfaces();
		$this->assertSame(array('TestInterface', 'TestInterfaceSortable'), $interfaces);
	}

	/**
	 * We should be able to convert stored abstract methods to concrete methods
	 * for implementing in classes.
	 *
	 * @depends test_getting_class_methods
	 */
	public function test_make_abstract_method_concrete()
	{
		$class = new TestReflector('TestClass');

		$methods = $class->get_methods();
		$abstract = $methods['some_abstract_method'];
		$this->assertTrue($abstract['abstract']);
		$this->assertSame('abstract public', $abstract['modifiers']);

		$abstract = $class->make_method_concrete($abstract, 'some_abstract_method');
		$this->assertFalse($abstract['abstract']);
		$this->assertSame('public', $abstract['modifiers']);

		$methods = $class->get_methods();
		$abstract = $methods['some_abstract_method'];
		$this->assertFalse($abstract['abstract']);
		$this->assertSame('public', $abstract['modifiers']);
	}

	/**
	 * To support full method signatures etc., variables need to be exported as
	 * parsable string representations. Arrays need to be exported recursively
	 * and with optional indentation, objects should be ignored, capitalization
	 * should be normalized.
	 *
	 * @dataProvider  provider_exported_variables
	 * @param  mixed    $variable  The variable to export
	 * @param  string   $exported  The exported string
	 * @param  boolean  $indent    Should indentation be applied to arrays?
	 */
	public function test_export_variable($variable, $exported, $indent = FALSE)
	{
		$refl = new TestReflector('TestClass');

		$this->assertSame($exported, $refl->export_variable($variable, $indent));
	}

	/**
	 * Provides test data for test_export_variable.
	 */
	public function provider_exported_variables()
	{
		return array(
			array('foo', "'foo'"),
			array(1, '1'),
			array(1.1, '1.1'),
			array(TRUE, 'TRUE'),
			array(FALSE, 'FALSE'),
			array(NULL, 'NULL'),
			array(new stdClass, NULL),
			array(array('foo'), "array('foo')"),
			array(array('foo', 'bar', 1), "array('foo', 'bar', 1)"),
			array(array('foo'), 'array('.PHP_EOL."\t'foo',".PHP_EOL.")", TRUE),
			array(array('foo', 'bar'), 'array('.PHP_EOL."\t'foo',".PHP_EOL."\t'bar',".PHP_EOL.")", TRUE),
			array(array('foo' =>'bar', 'baz' => 1), "array('foo' => 'bar', 'baz' => 1)"),
			array(array('foo' => array('baz' => 1)), "array('foo' => array('baz' => 1))"),
			array(array('foo' => array('baz' => false)), "array('foo' => array('baz' => FALSE))"),
			array(array('foo' => array('baz', true)), "array('foo' => array('baz', TRUE))"),
		);
	}

	/**
	 * The method signatures should be returned as a parsable string, with any
	 * array parameter values parsed recursively.
	 *
	 * @covers Generator_Reflector::get_method_signature
	 * @covers Generator_Reflector::get_method_param_signatures
	 * @covers Generator_Reflector::get_param_signature
	 * @depends test_export_variable
	 */
	public function test_get_method_signature()
	{
		$refl = new TestReflector('TestInterface', Generator_Reflector::TYPE_INTERFACE);

		$expected = 'public function method_one(SomeClass $class, $foo = \'foo\', '
			.'array $bar = array(\'bar1\', \'bar2\', \'bar3\' => FALSE, \'bar4\' => array(1, \'foo\' => \'bar\')), '
			.'$bool = FALSE)';
		$actual = $refl->get_method_signature('method_one');
		$this->assertSame($expected, $actual);

		$expected = 'public function & method_two(OtherClass $class, & $foo = NULL, array $bar = NULL, $empty = array())';
		$actual = $refl->get_method_signature('method_two');
		$this->assertSame($expected, $actual);

		$this->assertSame(1, $refl->analysis_count);
	}

	/**
	 * Constant declarations should be returned as parsable strings.
	 *
	 * @depends test_export_variable
	 */
	public function test_get_constant_declarations()
	{
		$refl = new TestReflector('TestClass');

		$this->assertSame("const CONSTANT_ONE = 'foo'", $refl->get_constant_declaration('CONSTANT_ONE'));
		$this->assertSame('const CONSTANT_TWO = 2', $refl->get_constant_declaration('CONSTANT_TWO'));

		$this->assertSame(1, $refl->analysis_count);
	}

	/**
	 * Property declarations should be returned as parsable strings.
	 *
	 * @depends test_export_variable
	 */
	public function test_get_property_declarations()
	{
		$refl = new TestReflector('TestClass');

		$this->assertSame('public $prop_one = \'foo\'', $refl->get_property_declaration('prop_one'));
		$this->assertSame('public $prop_two = 2', $refl->get_property_declaration('prop_two'));
		$this->assertSame('public $prop_three = array()', $refl->get_property_declaration('prop_three'));
		$this->assertSame('public $prop_four', $refl->get_property_declaration('prop_four'));
		$this->assertSame('protected $prop_five', $refl->get_property_declaration('prop_five'));
		$this->assertSame('public static $prop_six', $refl->get_property_declaration('prop_six'));

		$this->assertSame(1, $refl->analysis_count);

		// Objects in static properties should be ignored
		TestStaticClass::$object = new stdclass;
		$refl = new TestReflector('TestStaticClass');
		$this->assertSame('public static $object', $refl->get_property_declaration('object'));
	}

	/**
	 * Method invocations should be returned as parsable strings.
	 */
	public function test_get_method_invocation()
	{
		$refl = new TestReflector('TestClass');

		$this->assertSame('count()', $refl->get_method_invocation('count'));
		$this->assertSame('some_invoked_method($foo, $bar)',
			$refl->get_method_invocation('some_invoked_method'));

		$this->assertSame(1, $refl->analysis_count);
	}

} // End Generator_ReflectorTest

// Test interfaces

interface TestInterface
{
	/**
	 * Short description.
	 */
	public function method_one(SomeClass $class, $foo = 'foo',
		array $bar = array('bar1', 'bar2', 'bar3' => FALSE, 'bar4' => array(1, 'foo' => 'bar')),
		$bool = FALSE);

	public function &method_two(OtherClass $class, &$foo = NULL, array $bar = NULL, $empty = array());
}

interface TestInterfaceCountable
{
	public function count();
}

interface TestInterfaceChild extends TestInterface, TestInterfaceCountable
{
	public function method_three();
}

// For testing multiple inheritance

interface TestInterfaceIterable extends TestInterfaceTraversable
{
	public function iter();
}

interface TestInterfaceSortable extends TestInterfaceIterable
{
	public function sort();
}

interface TestInterfaceTraversable
{
	public function traverse();
}

interface TestInterfaceChildTwo extends TestInterface, TestInterfaceSortable
{
	public function method_three();
}

// Test classes

class TestReflector extends Generator_Reflector
{
	public $analysis_count = 0;

	public function analyze()
	{
		$this->analysis_count++;
		return parent::analyze();
	}
}

/**
 * A test class
 */
abstract class TestClass extends TestParentClass implements TestInterfaceCountable
{
	const CONSTANT_ONE = 'foo';
	const CONSTANT_TWO = 2;

	public $prop_one = 'foo';
	public $prop_two = 2;
	public $prop_three = array();
	public $prop_four;

	protected $prop_five;

	public static $prop_six;

	public function count()	{}
	public function some_invoked_method($foo = 1, array $bar = NULL) {}

	abstract public function some_abstract_method();
}

class TestParentClass {}

class TestStaticClass
{
	public static $object;
}
