Farser
======

An experiment in template parsing (in PHP) that's pretty simple minded 
and tries to implement nested scopes in a non-scary way.

And... look ma, no regex!

Turns

    {$A fiz="baz" dir="ls"}foo{dir}{$B dir="dir"}bar{fiz}{dir}{/$B}{/$A}

into

    foolsbarbazdir

also handles a simple callback mechanism

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

    echo $farser->parse();

    // output: bazbazls
