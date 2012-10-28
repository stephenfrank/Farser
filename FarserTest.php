<?php

include "Farser.php";

class Farser_Test extends PHPUnit_Framework_TestCase {

	public function testSimple()
	{
		$lexer = new Farser();
		$lexer->setRaw('-- {$A}foo{/$A} --');
		$this->assertEquals('-- foo --', $lexer->parse());
	}

	public function testSimpleMultiline()
	{
		$lexer = new Farser();
		$lexer->setRaw("{\$A}\n\tfoo\n{/\$A}-");
		$this->assertEquals("\n\tfoo\n-", $lexer->parse());
	}

	public function testSimpleNested()
	{
		$lexer = new Farser();
		$lexer->setRaw('{$A}foo{$B}bar{/$B}{/$A}');
		$this->assertEquals('foobar', $lexer->parse());
	}

	public function testSimpleNestedVars()
	{
		$lexer = new Farser();
		$lexer->setRaw('{$A fiz="baz"}foo{$B}bar{fiz}{/$B}{/$A}');
		$this->assertEquals('foobarbaz', $lexer->parse());
	}

	public function testSimpleNestedVarsScoped()
	{
		$lexer = new Farser();
		$lexer->setRaw('{$A fiz="baz" dir="ls"}foo{dir}{$B dir="dir"}bar{fiz}{dir}{/$B}{/$A}');
		$this->assertEquals('foolsbarbazdir', $lexer->parse());
	}

	/**
	 * @expectedException NoClosingTagException
	 */
	public function testNoClosingTagException()
	{
		$lexer = new Farser();
		$lexer->setRaw('{$A}{foo}{$A}');
		$lexer->parse();
	}

	public function testSimpleCallback()
	{
		$lexer = new Farser();
		$lexer->setRaw('{$A}{foo}{/$A}');
		
		$lexer->addCallback('A', function (& $scope)
		{
			$scope['vars']['foo'] = 'baz';
		});

		$this->assertEquals('baz', $lexer->parse());
	}

	public function testSimpleNestedCallback()
	{
		$lexer = new Farser();
		$lexer->setRaw('{$A}{foo}{$B}{foo}{dir}{/$B}{/$A}');
		
		$lexer->addCallback('A', function (& $scope)
		{
			$scope['vars']['foo'] = 'baz';
		});

		$lexer->addCallback('B', function (& $scope)
		{
			$scope['vars']['dir'] = 'ls';
		});

		$this->assertEquals('bazbazls', $lexer->parse());
	}

}