<?php

include "Farser.php";

class Farser_Test extends PHPUnit_Framework_TestCase {

	public function testSimple()
	{
		$farser = new Farser();
		$farser->setRaw('-- {$A}foo{/$A} --');
		$this->assertEquals('-- foo --', $farser->parse());
	}

	public function testSimpleMultiline()
	{
		$farser = new Farser();
		$farser->setRaw("{\$A}\n\tfoo\n{/\$A}-");
		$this->assertEquals("\n\tfoo\n-", $farser->parse());
	}

	public function testSimpleNested()
	{
		$farser = new Farser();
		$farser->setRaw('{$A}foo{$B}bar{/$B}{/$A}');
		$this->assertEquals('foobar', $farser->parse());
	}

	public function testSimpleNestedVars()
	{
		$farser = new Farser();
		$farser->setRaw('{$A fiz="baz"}foo{$B}bar{fiz}{/$B}{/$A}');
		$this->assertEquals('foobarbaz', $farser->parse());
	}

	public function testSimpleNestedVarsScoped()
	{
		$farser = new Farser();
		$farser->setRaw('{$A fiz="baz" dir="ls"}foo{dir}{$B dir="dir"}bar{fiz}{dir}{/$B}{/$A}');
		$this->assertEquals('foolsbarbazdir', $farser->parse());
	}

	/**
	 * @expectedException NoClosingTagException
	 */
	public function testNoClosingTagException()
	{
		$farser = new Farser();
		$farser->setRaw('{$A}{foo}{$A}');
		$farser->parse();
	}

	public function testSimpleCallback()
	{
		$farser = new Farser();
		$farser->setRaw('{$A}{foo}{/$A}');
		
		$farser->addCallback('A', function (& $scope)
		{
			$scope['vars']['foo'] = 'baz';
		});

		$this->assertEquals('baz', $farser->parse());
	}

	public function testSimpleNestedCallback()
	{
		$farser = new Farser();
		$farser->setRaw('{$A}{foo}{$B}{foo}{dir}{/$B}{/$A}');
		
		$farser->addCallback('A', function (& $scope)
		{
			$scope['vars']['foo'] = 'baz';
		});

		$farser->addCallback('B', function (& $scope)
		{
			$scope['vars']['dir'] = 'ls';
		});

		$this->assertEquals('bazbazls', $farser->parse());
	}

}