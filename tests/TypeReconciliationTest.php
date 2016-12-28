<?php
namespace Psalm\Tests;

use PhpParser\ParserFactory;
use PHPUnit_Framework_TestCase;
use Psalm\Checker\FileChecker;
use Psalm\Checker\TypeChecker;
use Psalm\Clause;
use Psalm\Config;
use Psalm\Context;
use Psalm\Type;

class TypeReconciliationTest extends PHPUnit_Framework_TestCase
{
    /** @var \PhpParser\Parser */
    protected static $parser;

    public static function setUpBeforeClass()
    {
        self::$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

        $config = new TestConfig();
    }

    public function setUp()
    {
        FileChecker::clearCache();
    }

    public function testNotNull()
    {
        $this->assertEquals(
            'MyObject',
            (string) TypeChecker::reconcileTypes('!null', Type::parseString('MyObject'))
        );

        $this->assertEquals(
            'MyObject',
            (string) TypeChecker::reconcileTypes('!null', Type::parseString('MyObject|null'))
        );

        $this->assertEquals(
            'MyObject|false',
            (string) TypeChecker::reconcileTypes('!null', Type::parseString('MyObject|false'))
        );

        $this->assertEquals(
            'mixed',
            (string) TypeChecker::reconcileTypes('!null', Type::parseString('mixed'))
        );
    }

    public function testNotEmpty()
    {
        $this->assertEquals(
            'MyObject',
            (string) TypeChecker::reconcileTypes('!empty', Type::parseString('MyObject'))
        );

        $this->assertEquals(
            'MyObject',
            (string) TypeChecker::reconcileTypes('!empty', Type::parseString('MyObject|null'))
        );

        $this->assertEquals(
            'MyObject',
            (string) TypeChecker::reconcileTypes('!empty', Type::parseString('MyObject|false'))
        );

        $this->assertEquals(
            'mixed',
            (string) TypeChecker::reconcileTypes('!empty', Type::parseString('mixed'))
        );

        // @todo in the future this should also work
        /*
        $this->assertEquals(
            'MyObject|true',
            (string) TypeChecker::reconcileTypes('!empty', Type::parseString('MyObject|bool'))
        );
         */
    }

    public function testNull()
    {
        $this->assertEquals(
            'null',
            (string) TypeChecker::reconcileTypes('null', Type::parseString('MyObject|null'))
        );

        $this->assertEquals(
            'null',
            (string) TypeChecker::reconcileTypes('null', Type::parseString('mixed'))
        );
    }

    public function testEmpty()
    {
        $this->assertEquals(
            'null',
            (string) TypeChecker::reconcileTypes('empty', Type::parseString('MyObject'))
        );
        $this->assertEquals(
            'false',
            (string) TypeChecker::reconcileTypes('empty', Type::parseString('MyObject|false'))
        );

        $this->assertEquals(
            'false',
            (string) TypeChecker::reconcileTypes('empty', Type::parseString('MyObject|bool'))
        );

        $this->assertEquals(
            'mixed',
            (string) TypeChecker::reconcileTypes('empty', Type::parseString('mixed'))
        );

        /** @var Type\Union */
        $reconciled = TypeChecker::reconcileTypes('empty', Type::parseString('bool'));
        $this->assertEquals('false', (string) $reconciled);
        $this->assertInstanceOf('Psalm\Type\Atomic', $reconciled->types['false']);
    }

    public function testNotMyObject()
    {
        $this->assertEquals(
            'bool',
            (string) TypeChecker::reconcileTypes('!MyObject', Type::parseString('MyObject|bool'))
        );

        $this->assertEquals(
            'null',
            (string) TypeChecker::reconcileTypes('!MyObject', Type::parseString('MyObject|null'))
        );

        $this->assertEquals(
            'MyObjectB',
            (string) TypeChecker::reconcileTypes('!MyObjectA', Type::parseString('MyObjectA|MyObjectB'))
        );
    }

    public function testMyObject()
    {
        $this->assertEquals(
            'MyObject',
            (string) TypeChecker::reconcileTypes('MyObject', Type::parseString('MyObject|bool'))
        );

        $this->assertEquals(
            'MyObjectA',
            (string) TypeChecker::reconcileTypes('MyObjectA', Type::parseString('MyObjectA|MyObjectB'))
        );
    }

    public function testArrayContains()
    {
        $this->assertTrue(
            TypeChecker::isContainedBy(
                Type::parseString('array<string>'),
                Type::parseString('array')
            )
        );

        $this->assertTrue(
            TypeChecker::isContainedBy(
                Type::parseString('array<Exception>'),
                Type::parseString('array')
            )
        );

        $this->assertTrue(
            TypeChecker::isContainedBy(
                Type::parseString('array<UnexpectedValueException>'),
                Type::parseString('array<Exception>')
            )
        );

        $this->assertFalse(
            TypeChecker::isContainedBy(
                Type::parseString('array<ValueException>'),
                Type::parseString('array<UnexpectedException>')
            )
        );
    }

    public function testNumeric()
    {
        $this->assertEquals(
            'string',
            (string) TypeChecker::reconcileTypes('numeric', Type::parseString('string'))
        );
    }

    public function testNegateFormula()
    {
        $formula = [
            new Clause(['$a' => ['!empty']])
        ];

        $negated_formula = TypeChecker::negateFormula($formula);

        $this->assertSame(1, count($negated_formula));
        $this->assertSame(['$a' => ['empty']], $negated_formula[0]->possibilities);

        $formula = [
            new Clause(['$a' => ['!empty'], '$b' => ['!empty']])
        ];

        $negated_formula = TypeChecker::negateFormula($formula);

        $this->assertSame(2, count($negated_formula));
        $this->assertSame(['$a' => ['empty']], $negated_formula[0]->possibilities);
        $this->assertSame(['$b' => ['empty']], $negated_formula[1]->possibilities);

        $formula = [
            new Clause(['$a' => ['!empty']]),
            new Clause(['$b' => ['!empty']]),
        ];

        $negated_formula = TypeChecker::negateFormula($formula);

        $this->assertSame(1, count($negated_formula));
        $this->assertSame(['$a' => ['empty'], '$b' => ['empty']], $negated_formula[0]->possibilities);

        $formula = [
            new Clause(['$a' => ['int', 'string'], '$b' => ['!empty']])
        ];

        $negated_formula = TypeChecker::negateFormula($formula);

        $this->assertSame(3, count($negated_formula));
        $this->assertSame(['$a' => ['!int']], $negated_formula[0]->possibilities);
        $this->assertSame(['$a' => ['!string']], $negated_formula[1]->possibilities);
        $this->assertSame(['$b' => ['empty']], $negated_formula[2]->possibilities);
    }

    public function testContainsClause()
    {
        $this->assertTrue(
            (new Clause(
                [
                    '$a' => ['!empty'],
                    '$b' => ['!empty']
                ]
            ))->contains(
                new Clause(
                    [
                        '$a' => ['!empty']
                    ]
                )
            )
        );

        $this->assertFalse(
            (new Clause(
                [
                    '$a' => ['!empty']
                ]
            ))->contains(
                new Clause(
                    [
                        '$a' => ['!empty'],
                        '$b' => ['!empty']
                    ]
                )
            )
        );
    }

    public function testSimplifyCNF()
    {
        $formula = [
            new Clause(['$a' => ['!empty']]),
            new Clause(['$a' => ['empty'], '$b' => ['empty']])
        ];

        $simplified_formula = TypeChecker::simplifyCNF($formula);

        $this->assertSame(2, count($simplified_formula));
        $this->assertSame(['$a' => ['!empty']], $simplified_formula[0]->possibilities);
        $this->assertSame(['$b' => ['empty']], $simplified_formula[1]->possibilities);
    }

    /**
     * @expectedException \Psalm\Exception\CodeException
     * @expectedExceptionMessage TypeDoesNotContainType
     */
    public function testMakeNonNullableNull()
    {
        $stmts = self::$parser->parse('<?php
        class A { }
        $a = new A();
        if ($a === null) {
        }
        ');

        $file_checker = new FileChecker('somefile.php', $stmts);
        $context = new Context('somefile.php');
        $file_checker->check(true, true, $context);
    }

    /**
     * @expectedException \Psalm\Exception\CodeException
     * @expectedExceptionMessage TypeDoesNotContainType
     */
    public function testMakeInstanceOfThingInElseif()
    {
        $stmts = self::$parser->parse('<?php
        class A { }
        class B { }
        class C { }
        $a = rand(0, 10) > 5 ? new A() : new B();
        if ($a instanceof A) {
        } elseif ($a instanceof C) {
        }
        ');

        $file_checker = new FileChecker('somefile.php', $stmts);
        $context = new Context('somefile.php');
        $file_checker->check(true, true, $context);
    }

    /**
     * @expectedException \Psalm\Exception\CodeException
     * @expectedExceptionMessage FailedTypeResolution
     */
    public function testFailedTypeResolution()
    {
        $stmts = self::$parser->parse('<?php
        class A { }
        $a = new A();
        if ($a instanceof A) {
        }
        ');

        $file_checker = new FileChecker('somefile.php', $stmts);
        $context = new Context('somefile.php');
        $file_checker->check(true, true, $context);
    }

    public function testNotInstanceOf()
    {
        $stmts = self::$parser->parse('<?php
        class A { }

        class B extends A { }

        $out = null;

        if ($a instanceof B) {
            // do something
        }
        else {
            $out = $a;
        }
        ');

        $file_checker = new FileChecker('somefile.php', $stmts);
        $context = new Context('somefile.php');
        $context->vars_in_scope['$a'] = Type::parseString('A');
        $file_checker->check(true, true, $context);
        $this->assertEquals('null|A', (string) $context->vars_in_scope['$out']);
    }

    public function testNotInstanceOfProperty()
    {
        $stmts = self::$parser->parse('<?php
        class B { }

        class C extends B { }

        class A {
            /** @var B */
            public $foo;
        }

        $out = null;

        if ($a->foo instanceof C) {
            // do something
        }
        else {
            $out = $a->foo;
        }
        ');

        $file_checker = new FileChecker('somefile.php', $stmts);
        $context = new Context('somefile.php');
        $context->vars_in_scope['$a'] = Type::parseString('A');
        $file_checker->check(true, true, $context);
        $this->assertEquals('null|B', (string) $context->vars_in_scope['$out']);
    }

    public function testNotInstanceOfPropertyElseif()
    {
        $stmts = self::$parser->parse('<?php
        class B { }

        class C extends B { }

        class A {
            /** @var string|B */
            public $foo;
        }

        $out = null;

        if (is_string($a->foo)) {

        }
        elseif ($a->foo instanceof C) {
            // do something
        }
        else {
            $out = $a->foo;
        }
        ');

        $file_checker = new FileChecker('somefile.php', $stmts);
        $context = new Context('somefile.php');
        $context->vars_in_scope['$a'] = Type::parseString('A');
        $file_checker->check(true, true, $context);
        $this->assertEquals('null|B', (string) $context->vars_in_scope['$out']);
    }

    public function testTypeArguments()
    {
        $stmts = self::$parser->parse('<?php
        $a = min(0, 1);
        $b = min([0, 1]);
        $c = min("a", "b");
        ');

        $file_checker = new FileChecker('somefile.php', $stmts);
        $context = new Context('somefile.php');
        $file_checker->check(true, true, $context);
        $this->assertEquals('int', (string) $context->vars_in_scope['$a']);
        $this->assertEquals('int', (string) $context->vars_in_scope['$b']);
        $this->assertEquals('string', (string) $context->vars_in_scope['$c']);
    }

    /**
     * @expectedException \Psalm\Exception\CodeException
     * @expectedExceptionMessage TypeDoesNotContainType
     */
    public function testTypeTransformation()
    {
        $this->markTestIncomplete('This currently fails');
        $stmts = self::$parser->parse('<?php
        $a = "5";

        if (is_numeric($a)) {
            if (is_int($a)) {
                echo $a;
            }
        }
        ');

        $file_checker = new FileChecker('somefile.php', $stmts);
        $context = new Context('somefile.php');
        $file_checker->check(true, true, $context);
    }
}
