<?php

class Farser {

    private $raw = '';
    private $log = array();

    private $callbacks = array();

    public function __construct($path = null)
    {
        if ($path) {
            $this->getTemplate($path);
        }
    }

    /**
     * Add a callback
     *
     * Callbacks can modify the variables within
     * scope and process the tag content
     * 
     * @param [type]  $tagName  [description]
     * @param Closure $callback [description]
     */
    public function addCallback($tagName, Closure $callback)
    {
        $this->callbacks[$tagName] = $callback;
    }

    public function getCallback($key)
    {
        return $this->callbacks[$key];
    }

    public function callBackExists($key)
    {
        return isset($this->callbacks[$key]);
    }

    public function log($str)
    {
        $this->log[] = $str;
    }

    public function dumpLog()
    {
        print_r(implode("\n", $this->log));
    }

    public function setRaw($raw)
    {
        $this->raw = $raw;
    }

    public function getTemplate($path)
    {
        $this->raw = file_get_contents($path);
    }

    public function parse()
    {
        $lines = explode("\n", $this->raw);

        // $globalScope = new Scope;
        $globalScope = array();
        $cursor = 0;

        $globalscope = array(
            'tagName' => 'global',
            'fullTag' => $this->raw,
            'tagContentParsed' => $this->raw,
            'vars' => array(),
            'innerScopes' => array()
        );

        $globalscope['innerScopes'] = $this->innerScope($this->raw);

        $globalscope['tagContentParsed'] = $this->varReplace($globalscope);

        // $this->dumpLog();

        return $globalscope['tagContentParsed'];
    }

    protected function varReplace(& $scope)
    {
        $this->log('Replace scope: '.$scope['tagName']);

        $tagName = $scope['tagName'];
        $fullTag = $scope['fullTag'];
        $tagContentParsed = $scope['tagContentParsed'];

        foreach ($scope['innerScopes'] as & $innerScope) {
            
            $innerTagParsed = $this->varReplace($innerScope);
            $innerTag = $innerScope['fullTag'];
            $innerFullTag = $innerScope['fullTag'];

            $this->log("Replace in $tagName:\n$tagContentParsed\n---\n$innerFullTag\n+++$innerTagParsed\n---");

            $tagContentParsed = str_replace($innerFullTag, $innerTagParsed, $tagContentParsed);

            
        }

        foreach ($scope['vars'] as $key => $var) {
            $tagContentParsed = str_replace('{'.$key.'}', $var, $tagContentParsed); 
        }

        // $this->log("Replace:\n===\n$tagContentParsed\n---\n$fullTag\n+++$tagContentParsed");

        // $tagContentParsed = str_replace($fullTag, $tagContentParsed, $tagContentParsed);

        return $scope['tagContentParsed'] = $tagContentParsed;
    }

    protected function innerScope($raw, $inheritedVars = array()) // , & $container
    {
        $cursor = 0;
        $container = array();
        $rawLen = strlen($raw);

        $this->log("Inspect raw scope [".$rawLen."]: \n---\n".$raw."\n---\n");

        while ($cursor < $rawLen) {
            // Move the cursor
            $this->log("Cursor: ".$cursor." [".substr($raw, $cursor, 1)."]");

            if ($cursor >= $rawLen) {
                break;
            }


            // Is the current position on a tag?
            if ($tag = $this->tagLookahead($raw, $cursor)) {

                extract($tag); // $tagName, $tagParmStr, $tagEnd, $tagStartLeft, $tagStartRight
                
                // Find the end tag position
                $endPos = $this->findClosing(substr($raw, $tagStartRight + 1), $tagName, $tagStartRight);

                extract($endPos); // $tagEndRight, $tagEndLeft

                // Extract out the tag content
                $tagContent = substr($raw, $tagStartRight, ($tagEndLeft - $tagStartRight));

                $vars = array_merge($inheritedVars, $tagParms);

                $fullTag = substr($raw, $tagStartLeft, $tagEndRight - $tagStartLeft);

                $newScope = $tag +
                array(
                    'vars' => $vars,
                    // 'content' => $tagContent,
                    'tagStart' => $tagStartLeft,
                    'tagEnd' => $tagEndRight,
                    'fullTag' => $fullTag,
                    'tagContentParsed' => $tagContent,
                    'innerScopes' => array()
                );

                // Callbacks act on the scope to modify it
                if ($this->callBackExists($tagName)) {
                    $callback = $this->getCallback($tagName);

                    $callback($newScope);
                }

                $this->log("Found tag scope: ".print_r($newScope, true));

                $newScope['innerScopes'] = $this->innerScope($tagContent, $newScope['vars']);

                $container[] = $newScope;

                $cursor = $tagEndRight;
            }

            $cursor += 1;
        }

        return $container;
    }

    /**
     * Tag Look Ahead
     *
     * Look ahead until a tag is found
     * This is likely to open a new scope
     * 
     * @param  [type] $raw [description]
     * @param  [type] $pos [description]
     * @return [type]      [description]
     */
    protected function tagLookahead($raw, $pos)
    {
        $substr = substr($raw, $pos, 2);

        if ($substr != '{$') {
            return false;
        }

        $tagStartLeft = $pos;
        $tagName = '';
        $tagParmStr = '';

        $pos += 2;

        while (
            substr($raw, $pos, 1) != ' '
            and substr($raw, $pos, 1) != '}'
        ) {
            $tagName .= substr($raw, $pos, 1);
            $pos += 1;
        }

        while (substr($raw, $pos, 1) != '}') {
            $tagParmStr .= substr($raw, $pos, 1);
            $pos += 1;
        }

        $pos += 1;

        $tagParms = $this->parmsToArray($tagParmStr);

        $tagStartRight = $pos;

        $openTag = substr($raw, $tagStartLeft, $tagStartRight - $tagStartLeft);

        $tag = compact('tagName', 'openTag', 'tagParmStr', 'tagParms', 'tagStartRight', 'tagStartLeft');

        return $tag;
    }

    protected function parmsToArray($parmStr)
    {
        preg_match_all('/(\w+?)="(.*?)"/', $parmStr, $matches);

        $tagParms = array();
        
        foreach ($matches[1] as $k => $parmKey) {
            $tagParms[$parmKey] = $matches[2][$k];
        }

        return $tagParms;
    }

    /**
     * Find Closing Tag
     *
     * Continues from the end of the opening
     * tag until the closing tag is found
     * 
     * @param  [type] $raw    [description]
     * @param  [type] $tag    [description]
     * @param  [type] $offset [description]
     * @throws NoClosingTagException
     * @return [type]         [description]
     */
    protected function findClosing($raw, $tag, $offset)
    {
        $closingMatch = '{/$'.$tag.'}';
        $openingMatch = '{$'.$tag;

        // $raw = substr($raw, $pos, 2);
        $pos = 0;
        $openDepth = 1;

        while ($openDepth !== 0 and $pos <= strlen($raw)) {

            if (substr($raw, $pos, strlen($closingMatch)) == $closingMatch) {
                $openDepth -= 1;
            }

            if (substr($raw, $pos, strlen($openingMatch)) == $openingMatch)
            {
                $openDepth += 1;    
            }
    
            $pos += 1;

        }

        if ($pos >= strlen($raw)) {
            // print_r($raw);
            
            throw new NoClosingTagException('Closing tag not found: '.$tag."\n".$this->posdump($pos, $raw, true));
        }

        $tagEndLeft = $pos + $offset;
        $tagEndRight = $pos + strlen($closingMatch) + $offset;

        return compact('tagEndLeft', 'tagEndRight');
    }

    public function posdump($pos, $str, $return = false)
    {
        $out = "\n==========\n";

        $firsthalf = substr($str, 0, $pos - 1);
        $char = substr($str, $pos, 1);
        $otherhalf = substr($str, $pos + 1);

        $out .= $firsthalf . "\033[32m".$char."\033[37m".$otherhalf;

        $out .= "\n==========\n";

        if ($return) {
            return $out;
        }

        print_r($out);
    }

}

class NoClosingTagException extends Exception {};

