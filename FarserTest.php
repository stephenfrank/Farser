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

		$farser->parse();

		$this->assertEquals('bazbazls', $farser->parse());
	}

	public function testLoopingExample()
	{
		$farser = new Farser();
		$farser->setRaw('{$A loop="3"}asdf{foo}{/$A}');
		
		$farser->addCallback('A', function (& $scope)
		{
			// $loopNum = $scope['tagParms']['loop'];
			$loopNum = 3;

			$scope['vars']['foo'] = 'baz';

			$replaceWith = $scope['replaceWith'][0];
			
			$replaceWith['vars']['foo'] = 'baz';

			$scope['replaceWith'][] = $replaceWith;
			$scope['replaceWith'][] = $replaceWith;

			// for ($i=1; $i <= $loopNum-1; $i++) { 
			// 	$scope['replaceWith'][] = $replaceWith;
			// }
		});

		$this->assertEquals('asdfbazasdfbazasdfbaz', $farser->parse());
	}

	public function testLoopingNestedExample()
	{
		$farser = new Farser();
		$farser->setRaw('{$A loop="3"}{foo}{$B loop="2"}{foo}{/$B}{/$A}');
		
		$farser->addCallback('A', function (& $scope)
		{
			$scope['vars']['foo'] = 'A';
			$loopNum = $scope['tagParms']['loop'];

			for ($i = 0; $i < $loopNum; $i++) { 
				$iterations[] = $scope['replaceWith'][0];
			}

			$scope['replaceWith'] = $iterations;

		});

		$farser->addCallback('B', function (& $scope)
		{
			$scope['vars']['foo'] = 'B';
			$loopNum = $scope['tagParms']['loop'];

			for ($i = 0; $i < $loopNum; $i++) { 
				$iterations[] = $scope['replaceWith'][0];
			}

			$scope['replaceWith'] = $iterations;

		});

		$farser->parse();

		$this->assertEquals('ABBABBABB', $farser->parse());
	}

	public function testLoopingNestedVarsExample()
	{
		$farser = new Farser();
		$farser->setRaw('{$A loop="3"}{foo}{$B loop="2"}{foo}{/$B}{/$A}');
		
		$farser->addCallback('A', function (& $scope)
		{
			$scope['vars']['foo'] = 'A';
			$loopNum = $scope['tagParms']['loop'];

			$replaceWith = $scope['replaceWith'][0];

			foreach (array('X', 'Y', 'Z') as $char) {
				$replaceWith['vars']['foo'] = $char;
				$iterations[] = $replaceWith;
			}

			$scope['replaceWith'] = $iterations;
		});

		$farser->addCallback('B', function (& $scope)
		{
			$loopNum = $scope['tagParms']['loop'];

			$iterations = array();
			
			$replaceWith = $scope['replaceWith'][0];
			$replaceWith['vars']['foo'] = 'B';

			for ($i = 0; $i < $loopNum; $i++) { 
				$iterations[] = $replaceWith;
			}

			$scope['replaceWith'] = $iterations;
		});

		$farser->parse();

		$this->assertEquals('XBBYBBZBB', $farser->parse());
	}

	public function testCallbackCache()
	{
		$farser = new Farser();
		$farser->setRaw('{$A}{foo}{/$A}{$A}{foo}{/$A}{$A}{foo}{/$A}');
		$count = 0;

		$farser->addCallback('A', function (& $scope) use (& $count)
		{
			$count += 1;
			$scope['vars']['foo'] = 'baz';
		});
		$out = $farser->parse();

		// echo $farser->dumpLog();
		// exit;

		$this->assertEquals('bazbazbaz', $out);
		$this->assertEquals(1, $count);
	}

	public function testPassingVariablesAsArguments()
	{
		$farser = new Farser();
		$farser->setRaw('{$A foo="3"}{foo}{$B loop="{foo}"}b{/$B}{/$A}');

		// $farser->addCallback('A', function (& $scope) use (& $count)
		// {
		// 	$count += 1;
		// 	$scope['vars']['foo'] = 'baz';
		// });

		$farser->addCallback('B', function (& $scope)
		{
			$loopNum = $scope['tagParms']['loop'];

			for ($i = 0; $i < $loopNum; $i++) { 
				$iterations[] = $scope['replaceWith'][0];
			}

			$scope['replaceWith'] = $iterations;
		});

		$out = $farser->parse();

		// echo $farser->dumpLog();
		// exit;

		$this->assertEquals('3bbb', $out);
	}

	public function testSetGlobalVars()
	{
		$farser = new Farser();
		$farser->setRaw('{$A}{foo}{$B}{foo}{/$B}{/$A}');
		$farser->setGlobalVar('foo', 'baz');

		$this->assertEquals('bazbaz', $farser->parse());	
	}


}