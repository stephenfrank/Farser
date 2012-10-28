<?php

class Farser
{

    private $raw = '';
    private $log = array();

    private $callbacks = array();

    private $callbackCache = array();
    private $parserCache = array();
    private $global = array();

    /**
     * Ye old'e constructor
     * @param [type] $path [description]
     */
    public function __construct($path = null)
    {
        if ($path) {
            $this->getTemplate($path);
        }

        // Set up the global context
        $this->global = array(
            'tagName' => 'global',
            'tagVars' => array(),
            'inheritedVars' => array(),
            'replaceWith' => array(
                array(
                    'content' => '',
                    'vars' => array(),
                    'innerScopes' => array()
                )
            )
        );
    }

    public function getGlobal()
    {
        return $this->global;
    }

    public function setGlobalVar($key, $val)
    {
        $this->global['inheritedVars'][$key] = $val;
    }

    /**
     * Add a callback
     *
     * Callbacks can modify the variables within
     * scope and process the tag content
     * 
     * @param string  $tagName  Tag name of call
     * @param Closure $callback Callback Closure
     */
    public function addCallback($tagName, Closure $callback)
    {
        $this->callbacks[$tagName] = $callback;
    }

    /**
     * Get Callback
     * @param  string $key Callback key
     * @return Closure
     */
    public function getCallback($key)
    {
        return $this->callbacks[$key];
    }

    /**
     * Check if the callback exists
     * @param  string $key Callback key
     * @return boolean
     */
    public function callBackExists($key)
    {
        return isset($this->callbacks[$key]);
    }

    /**
     * Add a line to the log
     * @param  [type] $str [description]
     * @return [type]      [description]
     */
    public function log($str)
    {
        $this->log[] = $str;
    }

    /**
     * Dump the log
     * @return void
     */
    public function dumpLog()
    {
        print_r(implode("\n", $this->log));
    }

    /**
     * Set Raw
     * Sets the raw template content
     * @param string $raw
     */
    public function setRaw($raw)
    {
        $this->raw = $raw;
    }

    /**
     * Get and set template from file
     * @param  string $path Path to file
     * @return void
     */
    public function getTemplate($path)
    {
        $this->raw = file_get_contents($path);
    }

    /**
     * Parse template
     * 
     * @return string Parsed template content
     */
    public function parse()
    {
        $lines = explode("\n", $this->raw);

        $this->global['fullTag'] = $this->raw;
        
        $this->global['replaceWith'][0]['content'] = $this->raw;
        
        $this->global['replaceWith'][0]['innerScopes'] = $this->findInnerScopes(
            $this->raw,
            $this->global['inheritedVars']
        );

        $this->global['tagContentParsed'] = $this->replaceVars($this->global);

        // $this->dumpLog();

        return $this->global['tagContentParsed'];
    }

    /**
     * Replace vars
     * Recursively replaces variable stubs
     * with their values. Works from 
     * the innermost tag outwards
     * @param  array      $scope Scope array
     * @return string        Parsed content
     */
    protected function replaceVars(& $scope)
    {
        // Caching attempts to retrieve results
        // from previous parsing
        $cacheKey = md5(serialize($scope));

        // if (isset($this->parserCache[$cacheKey])) {
        //     $scope['tagContentParsed'] = $this->parserCache[$cacheKey];
        //     return $scope['tagContentParsed'];
        // }

        $this->log('Replace scope: '.$scope['tagName']);

        $output = '';

        $tagName = $scope['tagName'];
        $fullTag = $scope['fullTag'];

        foreach ($scope['replaceWith'] as & $replacement) {
            // $replacement['content'];
            // $replacement['vars'];
            // $replacement['innerScopes'];

            $replacementContent = $replacement['content'];

            $this->log("Replace $tagName innerScopes: ");
            foreach ($replacement['innerScopes'] as & $innerScope) {
                

                $innerTagParsed = $this->replaceVars($innerScope);
                $innerTagName = $innerScope['tagName'];
                $innerTag = $innerScope['fullTag'];
                $innerFullTag = $innerScope['fullTag'];


                $this->log("Replace in $tagName innerTag $innerTagName:\n$replacementContent\n---\n$innerFullTag\n+++\n$innerTagParsed\n---");

                // $tagContentParsed = str_replace($innerFullTag, $innerTagParsed, $tagContentParsed);
                $replacementContent = preg_replace(
                    '/' . preg_quote($innerFullTag, '/') . '/',
                    $innerTagParsed,
                    $replacementContent,
                    1
                );
            }

            $varsInScope = array_merge($scope['inheritedVars'], $scope['tagVars'], $replacement['vars']);

            foreach ($varsInScope as $key => $var) {
                $this->log("Replace variable in $tagName: {".$key."} -> $var");
                $replacementContent = str_replace('{'.$key.'}', (string) $var, $replacementContent); 
            }

            $output .= $replacementContent;
        }

        $this->parserCache[$cacheKey] = $output;

        return $scope['tagContentParsed'] = $output;
    }

    /**
     * Find Inner Scope
     * @param  string $raw           Raw template context
     * @param  array  $inheritedVars Inherited variables from outer scope
     * @return array                Container of found scopes
     */
    protected function findInnerScopes($raw, $inheritedVars = array()) // , & $container
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

                // $this->posdump($tagStartRight, $raw);
                // $this->posdump($tagEndLeft, $raw);

                // Extract out the tag content
                $tagContent = substr($raw, $tagStartRight + 1, ($tagEndLeft - $tagStartRight) - 1);

                $fullTag = substr($raw, $tagStartLeft, $tagEndRight - $tagStartLeft);

                extract($tag); // $tagName, $tagParmStr, $tagEnd, $tagStartLeft, $tagStartRight

                // Check for variables as arguments
                foreach ($tagVars as $k => $tagParm) {
                    foreach ($inheritedVars as $key => $var) {
                        $count = 0;
                        
                        $tagVars[$k] = str_replace('{'.$key.'}', (string) $var, $tagParm, $count);

                        if ($count > 0) {
                            $this->log('Variable as argument replaced {'.$key.'} -> '.$var);
                        }
                    }  
                }

                $newScope = array(
                    'tagName' => $tagName,
                    'tagParmStr' => $tagParmStr,
                    'content' => $tagContent,
                    'fullTag' => $fullTag,
                    'tagVars' => $tagVars,
                    'inheritedVars' => $inheritedVars,
                );


                // Caching attempts to retrieve results
                // from previous callback operations since
                // callbacks could be costly
                $cacheKey = md5(serialize($newScope));

                if ($this->callBackExists($tagName)) {
                    if (isset($this->callbackCache[$cacheKey])) {
                        $newScope = $this->callbackCache[$cacheKey];
                    } else {
                        // Callbacks act on the scope to modify it
                        $callback = $this->getCallback($tagName);

                        $callback($newScope);

                        $this->callbackCache[$cacheKey] = $newScope;
                    }
                }

                if (! isset($newScope['replaceWith'])) {
                    $newScope['replaceWith'][] = array(
                        'vars' => array(),
                        'content' => $tagContent,
                        'innerScopes' => array(),
                    );
                }

                $this->log("Found tag scope: ".print_r($newScope, true));

                foreach ($newScope['replaceWith'] as $k => $replacement) {

                    if (! isset($replacement['vars'])) {
                        $replacement['vars'] = array();
                    }
                    if (! isset($replacement['innerScopes'])) {
                        $replacement['innerScopes'] = array();
                    }

                    $inheritedInnerVars = array_merge($newScope['tagVars'], $newScope['inheritedVars'], $replacement['vars']);

                    $replacement['innerScopes'] = $this->findInnerScopes(
                        $replacement['content'],
                        $inheritedInnerVars
                    );

                    $newScope['replaceWith'][$k] = $replacement;
                }

                $container[] = $newScope;

                $cursor = $tagEndRight - 1;
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
        $tagVars = array();

        $pos += 2;

        while (
            substr($raw, $pos, 1) != ' '
            and substr($raw, $pos, 1) != '}'
        ) {
            $tagName .= substr($raw, $pos, 1);
            $pos += 1;
        }

        $openBraces = 1;

        // if (substr($raw, $pos, 1) == '}') {
        //     $this->posdump($pos, $raw);exit;
        // }

        if (substr($raw, $pos, 1) != '}') {
            while ($openBraces > 0) {
                $pos += 1;

                if (substr($raw, $pos, 1) == '}') {
                    $openBraces -= 1;
                }
                if (substr($raw, $pos, 1) == '{') {
                    $openBraces += 1;
                }

                $tagParmStr .= substr($raw, $pos, 1);
                
            }
        }

        if ($tagParmStr) {
            $tagVars = $this->parmsToArray($tagParmStr);

        }

        $tagStartRight = $pos;

        // $this->posdump($tagStartRight, $raw);exit;

        $openTag = substr($raw, $tagStartLeft, $tagStartRight - $tagStartLeft);

        $tag = compact('tagName', 'openTag', 'tagParmStr', 'tagVars', 'tagStartRight', 'tagStartLeft');

        return $tag;
    }

    protected function parmsToArray($parmStr)
    {
        preg_match_all('/(\w+?)="(.*?)"/', $parmStr, $matches);

        $tagVars = array();
        
        foreach ($matches[1] as $k => $parmKey) {
            $tagVars[$parmKey] = $matches[2][$k];
        }

        return $tagVars;
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

    /**
     * Position dump
     * Helper function - dumps the position of a char
     * @param  int  $pos
     * @param  string  $str
     * @param  boolean $return 
     * @return void
     */
    public function posdump($pos, $str, $return = false)
    {
        $out = "\n==========\n";

        $firsthalf = substr($str, 0, $pos);
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

