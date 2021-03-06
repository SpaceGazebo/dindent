<?php
namespace Gajus\Dindent;

/**
 * @link https://github.com/gajus/dintent for the canonical source repository
 * @license https://github.com/gajus/dintent/blob/master/LICENSE BSD 3-Clause
 */
class Indenter {
    private
        $log = array(),
        $options = array(
            'indentation_character' => '    '
        ),
        $inline_elements = array('b', 'big', 'i', 'small', 'tt', 'abbr', 'acronym', 'cite', 'code', 'dfn', 'em', 'kbd', 'strong', 'samp', 'var',
            //'a',
            'bdo', 'br', 'img', 'span', 'sub', 'sup'),
        $temporary_replacements_data = array(),
        $temporary_replacements_rules = array(
          // I found that using an underscore in the tagname could break stuff
          // also any uppercase letters
          'divphpbladedirective' => array(
              'regex' => '/^\s*\B(@\b\S\S.*)$/m', 'wrap' => 'div',
          ),
          'script' => array(
              'regex' => '/<script\b[^>]*>([\s\S]*?)<\/script>/mi', 'wrap' => 'div',
          ),
          ///*
          'style' => array(
              'regex' => '/<style\b[^>]*>([\s\S]*?)<\/style>/mi', 'wrap' => 'div',
          ),
          'divphplongtag' => array( // some of these need to be wrapped in divs, others do not
              'regex' => '/^\s*\B<\?php\b([\s\S]*?)\?>\s/mi', 'wrap' => 'div',
          ),
          'phplongtag' => array(
              'regex' => '/<\?php\b([\s\S]*?)\?>/mi',
          ),
          'divphpbladeescapebracketbracketbracket' => array(
              'regex' => '/^\s*\B\{\{\{([\s\S]*?)\}\}\}\s/mi', 'wrap' => 'div',
          ),
          'phpbladeescapebracketbracketbracket' => array(
              'regex' => '/\{\{\{([\s\S]*?)\}\}\}/mi',
          ),
          'divphpbladeescapebracketbracket' => array(
              'regex' => '/^\s*\B\{\{([\s\S]*?)\}\}\s/mi', 'wrap' => 'div',
          ),
          'phpbladeescapebracketbracket' => array(
              'regex' => '/\{\{([\s\S]*?)\}\}/mi',
          ),
          'divphpbladeescaperacketexclamationexclamation' => array(
              'regex' => '/^\s*\B\{\!\!([\s\S]*?)\!\!\}\s/mi', 'wrap' => 'div',
          ),
          'phpbladeescaperacketexclamationexclamation' => array(
              'regex' => '/\{\!\!([\s\S]*?)\!\!\}/mi',
          ),
          'divhtmlcomment' => array(
              'regex' => '/^\s*\B<\!\-\-([\s\S]*?)\-\->\s/mi', 'wrap' => 'div',
          ),
          'htmlcomment' => array(
              'regex' => '/<\!\-\-([\s\S]*?)\-\->/mi',
          ),
          //*/
        ),
        $temporary_replacements_inline = array();

    const ELEMENT_TYPE_BLOCK = 0;
    const ELEMENT_TYPE_INLINE = 1;

    const MATCH_INDENT_NO = 0;
    const MATCH_INDENT_DECREASE = 1;
    const MATCH_INDENT_INCREASE = 2;
    const MATCH_DISCARD = 3;

    /**
     * @param array $options
     */
    public function __construct (array $options = array()) {
        foreach ($options as $name => $value) {
            if (!array_key_exists($name, $this->options)) {
                throw new Exception\InvalidArgumentException('Unrecognized option.');
            }

            $this->options[$name] = $value;
        }
    }

    /**
     * @param string $element_name Element name, e.g. "b".
     * @param ELEMENT_TYPE_BLOCK|ELEMENT_TYPE_INLINE $type
     * @return null
     */
    public function setElementType ($element_name, $type) {
        if ($type === static::ELEMENT_TYPE_BLOCK) {
            $this->inline_elements = array_diff($this->inline_elements, array($element_name));
        } else if ($type === static::ELEMENT_TYPE_INLINE) {
            $this->inline_elements[] = $element_name;
        } else {
            throw new Exception\InvalidArgumentException('Unrecognized element type.');
        }

        $this->inline_elements = array_unique($this->inline_elements);
    }

    /**
     * @param string $input HTML input.
     * @return string Indented HTML.
     */
    public function indent ($input) {
        $this->log = array();

        $x = 1;

        $input = str_replace("<", ' <', $input);
        $input = str_replace("->", '_____AAAAA_B', $input);
        $input = str_replace("=>", '_____AAAAB_B', $input);
        $input = str_replace(">", '> ', $input);
        $input = str_replace("_____AAAAA_B", '->', $input);
        $input = str_replace("_____AAAAB_B", '=>', $input);

        foreach($this->temporary_replacements_rules as $tag_name => $rules)
        {
            $this->temporary_replacements_data[$tag_name] = array();
            // Dindent does not indent <script> body. Instead, it temporary removes it from the code, indents the input, and restores the script body.
            if (preg_match_all($rules['regex'], $input, $matches)) {
                $this->temporary_replacements_data[$tag_name] = $matches[0];
                
                foreach ($matches[0] as $i => $match) {
                  
                    //if ($x > 0) $x--; else return trim($input);
                    
                    if (isset($rules['wrap']))
                    {
                        $this->strReplaceOnlyFirst(//$input = str_replace(
                            $match,
                            '<'.$rules['wrap'].'>_'.$tag_name.'_' . ($i + 1) . '_'.$tag_name.'_</'.$rules['wrap'].'>',
                            $input
                        );
                    }
                    else
                    {
                        $this->strReplaceOnlyFirst(//$input = str_replace(
                            $match,
                            '_'.$tag_name.'_' . ($i + 1) . '_'.$tag_name.'_',
                            $input
                        );
                    }
                }
            }

        }
        
        //return trim($input);

        // Removing double whitespaces to make the source code easier to read.
        // With exception of <pre>/ CSS white-space changing the default behaviour, double whitespace is meaningless in HTML output.
        // This reason alone is sufficient not to use Dindent in production.
        $input = str_replace("\t", '    ', $input);
        $input = preg_replace('/\s{2,}/', ' ', $input);

        // Remove inline elements and replace them with text entities.
        if (preg_match_all('/<(' . implode('|', $this->inline_elements) . ')[^>]*>(?:[^<]*)<\/\1>/', $input, $matches)) {
            // if it's too long, it's not inline.
            
            var_dump($matches[0]);
            
            $matches[0] = array_filter($matches[0],function($match)
            {
                //return true;
                return mb_strlen($match) < 64;
            });
            
            
            
            $this->temporary_replacements_inline = $matches[0];
            foreach ($matches[0] as $i => $match) {
                $input = str_replace($match, 'ᐃᐃ' . ($i + 1) . 'ᐃ', $input);
            }
        }

        $subject = $input;

        $output = '';

        $next_line_indentation_level = 0;

        do {
            $indentation_level = $next_line_indentation_level;

            $patterns = array(
                // block tag
                '/^\B(@\b\S\S.*)/' => static::MATCH_INDENT_NO,
                // block tag
                '/^(<([a-z]+)(?:[^>]*)>(?:[^<]*)<\/(?:\2)>)/' => static::MATCH_INDENT_NO,
                // DOCTYPE
                '/^<!([^>]*)>/' => static::MATCH_INDENT_NO,
                // tag with implied closing
                '/^<(input|link|meta|base|br|img|hr)([^>]*)>/' => static::MATCH_INDENT_NO,
                // opening tag
                '/^<[^\/]([^>]*)>/' => static::MATCH_INDENT_INCREASE,
                // closing tag
                '/^<\/([^>]*)>/' => static::MATCH_INDENT_DECREASE,
                // self-closing tag
                '/^<(.+)\/>/' => static::MATCH_INDENT_DECREASE,
                // whitespace
                '/^(\s+)/' => static::MATCH_DISCARD,
                // text node
                '/([^<]+)/' => static::MATCH_INDENT_NO
            );
            $rules = array('NO', 'DECREASE', 'INCREASE', 'DISCARD');

            foreach ($patterns as $pattern => $rule) {
                if ($match = preg_match($pattern, $subject, $matches)) {
                    $this->log[] = array(
                        'rule' => $rules[$rule],
                        'pattern' => $pattern,
                        'subject' => $subject,
                        'match' => $matches[0]
                    );

                    $subject = mb_substr($subject, mb_strlen($matches[0]));

                    if ($rule === static::MATCH_DISCARD) {
                        break;
                    }

                    if ($rule === static::MATCH_INDENT_NO) {

                    } else if ($rule === static::MATCH_INDENT_DECREASE) {
                        $next_line_indentation_level--;
                        $indentation_level--;
                    } else {
                        $next_line_indentation_level++;
                    }

                    if ($indentation_level < 0) {
                        $indentation_level = 0;
                    }

                    $output .= str_repeat($this->options['indentation_character'], $indentation_level) . $matches[0] . "\n";

                    break;
                }
            }
        } while ($match);

        $interpreted_input = '';
        foreach ($this->log as $e) {
            $interpreted_input .= $e['match'];
        }

        if ($interpreted_input !== $input) {
            throw new Exception\RuntimeException('Did not reproduce the exact input.');
        }
        
        $output = preg_replace('/(<(\w+)[^>]*>)\s*(<\/\2>)/', '\\1\\3', $output);

        foreach ($this->temporary_replacements_inline as $i => $original) {
            $output = str_replace('ᐃᐃ' . ($i + 1) . 'ᐃ', $original, $output);
        }

        foreach(array_reverse($this->temporary_replacements_rules) as $tag_name => $rules)
        {
            foreach (array_reverse($this->temporary_replacements_data[$tag_name]) as $imc => $original)
            {
                $i = count($this->temporary_replacements_data[$tag_name]) - $imc;
                
                if (isset($rules['wrap']))
                {
                    $this->strReplaceOnlyFirst(//$input = str_replace(
                        '<'.$rules['wrap'].'>_'.$tag_name.'_' . ($i) . '_'.$tag_name.'_</'.$rules['wrap'].'>',
                        trim($original),
                        $output
                    );
                }
                else
                {
                    $this->strReplaceOnlyFirst(//$input = str_replace(
                        '_'.$tag_name.'_' . ($i) . '_'.$tag_name.'_',
                        trim($original),
                        $output
                    );
                }
            }
        }

        return trim($output);
    }
    
    protected function strReplaceOnlyFirst($needle,$replacement,&$haystack)
    {
        $pos = strpos($haystack,$needle);
        if ($pos !== false) {
            $haystack = substr_replace($haystack,$replacement,$pos,strlen($needle));
        }
    }

    /**
     * Debugging utility. Get log for the last indent operation.
     *
     * @return array
     */
    public function getLog () {
        return $this->log;
    }
}
