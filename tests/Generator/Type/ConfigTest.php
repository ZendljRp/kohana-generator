<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Test case for Generator_Type_Config.
 *
 * @group      generator
 * @group      generator.types
 *
 * @package    Generator
 * @category   Tests
 * @author     Zeebee
 * @copyright  (c) 2012 Zeebee
 * @license    BSD revised
 */
class Generator_Type_ConfigTest extends Unittest_TestCase
{
	/**
	 * Tests that all type options are applied correctly.
	 */
	public function test_type_options()
	{
		$ds = DIRECTORY_SEPARATOR;
		$expected = array(
				'a' => array(
					'b' => array(
						'c' => 'some string',
						'd' => 2,
					),
				),
				'e' => 3,
				1 => 4,
				'0.2' => 5,
			);

		$type = new Generator_Type_Config();
		$this->assertSame('config', $type->folder());

		$type->value('a.b.c|some string');
		$type->value('a.b.d|2, e | 3, 1|4, 0.2|5');

		$type->render();
		$params = $type->params();
		$this->assertSame($expected, $params['values']);

		// With imported values
		$expected = array('defaults' => array('class' => array(
			'author' => 'Author', 'copyright' => '(c) 2012 Author', 'license' => 'License info'
		)));
		$type = new Generator_Type_Config();
		$type->import('testconfig/generator|defaults.class');

		$type->render();
		$params = $type->params();
		$this->assertSame($expected, $params['imports']);
		$this->assertSame($expected, $params['values']);

		// Stored values should override imported values
		$type = new Generator_Type_Config();
		$type->import('testconfig/generator|defaults.class');
		$type->value('defaults.class.author|Foobar');

		$type->render();
		$params = $type->params();
		$this->assertSame($expected, $params['imports']);
		$expected['defaults']['class']['author'] = 'Foobar';
		$this->assertSame($expected, $params['values']);
	}

} // End Generator_Type_ConfigTest
