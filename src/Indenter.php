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
        $inline_elements = array(
            //'b', 'big', 'i', 'small', 'tt', 'abbr', 'acronym', 'cite', 'code', 'dfn', 'em', 'kbd', 'strong', 'samp', 'var',
            //'a',
            //'bdo', 'br', 'img', 'span', 'sub', 'sup'
        ),
        $temporary_replacements_data = array(),
        $temporary_replacements_rules = array(
          // I found that using an underscore in the tagname could break stuff
          // also any uppercase letters
          // (@if(?=[^@<][A-Za-z]).*(?<![^@<][A-Za-z])) 
          // (@if(?![^@<][^A-Za-z]).*(?=[@<][A-Za-z]))
          // (@if.*?(?=(@[A-Za-z]|<[A-Za-z])))
          'divphpbladedirectiveif' => array(
              'regex' => '/(@if\b.*?(?=(@[A-Za-z]|<[A-Za-z])))/', 'replace' => '<div data-replace-key=":replace_key">',
          ),
          'divphpbladedirectiveunless' => array(
              'regex' => '/(@unless\b.*?(?=(@[A-Za-z]|<[A-Za-z])))/', 'replace' => '<div data-replace-key=":replace_key">',
          ),
          'divphpbladedirectiveforeach' => array(
              'regex' => '/(@foreach\b.*?(?=(@[A-Za-z]|<[A-Za-z])))/', 'replace' => '<div data-replace-key=":replace_key">',
          ),
          'divphpbladedirectivesection' => array(
              'regex' => '/(@section\b.*?(?=(@[A-Za-z]|<[A-Za-z])))/', 'replace' => '<div data-replace-key=":replace_key">',
          ),
          'divphpbladedirectiveyeild' => array(
              'regex' => '/(@yeild\b.*?(?=(@[A-Za-z]|<[A-Za-z])))/', 'wrap' => 'div',
          ),
          'divphpbladedirectiveyield' => array(
              'regex' => '/(@yield\b.*?(?=(@[A-Za-z]|<[A-Za-z])))/', 'wrap' => 'div',
          ),
          'divphpbladedirectiveelseif' => array(
              'regex' => '/(@elseif\b.*?(?=(@[A-Za-z]|<[A-Za-z])))/', 'replace' => '</div data-replace-key=":replace_key"><div data-replace-key=":replace_key">',
          ),
          'divphpbladedirectiveelse' => array(
              'regex' => '/(@else)/', 'replace' => '</div data-replace-key=":replace_key"><div data-replace-key=":replace_key">',
          ),
          'divphpbladedirectiveendif' => array(
              'regex' => '/(@endif\b)/', 'replace' => '</div data-replace-key=":replace_key">',
          ),
          'divphpbladedirectiveendunless' => array(
              'regex' => '/(@endunless\b)/', 'replace' => '</div data-replace-key=":replace_key">',
          ),
          'divphpbladedirectiveendforeach' => array(
              'regex' => '/(@endforeach\b)/', 'replace' => '</div data-replace-key=":replace_key">',
          ),
          'divphpbladedirectiveshow' => array(
              'regex' => '/(@show\b)/', 'replace' => '</div data-replace-key=":replace_key">',
          ),
          'divphpbladedirectivestop' => array(
              'regex' => '/(@stop\b)/', 'replace' => '</div data-replace-key=":replace_key">',
          ),
          
          //'divphpbladedirective' => array(
          //   'regex' => '/^\s*\B(@\b\S\S.*)$/m', 'wrap' => 'div',
          //),
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
    const MATCH_INDENT_BOTH = 4;

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
                    
                    $dataKey = '_'.$tag_name.'_' . ($i + 1) . '_'.$tag_name.'_';
                    
                    if (isset($rules['replace']))
                    {
                        continue;
                        $this->strReplaceOnlyFirst(//$input = str_replace(
                            $match,
                            str_replace(':replace_key',$dataKey,$rules['replace']),
                            $input
                        );
                    }
                    elseif (isset($rules['wrap']))
                    {
                        $this->strReplaceOnlyFirst(//$input = str_replace(
                            $match,
                            '<'.$rules['wrap'].'>'.$dataKey.'</'.$rules['wrap'].'>',
                            $input
                        );
                    }
                    else
                    {
                        $this->strReplaceOnlyFirst(//$input = str_replace(
                            $match,
                            $dataKey,
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

        $output = $this->setIndentsByPattern($subject,$matches);

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

        //$attempts = 0 ;
        //while($attempts--)
        {
        foreach(array_reverse($this->temporary_replacements_rules) as $tag_name => $rules)
        {
            foreach (array_reverse($this->temporary_replacements_data[$tag_name]) as $imc => $original)
            {
                $i = count($this->temporary_replacements_data[$tag_name]) - $imc;

                $dataKey = '_'.$tag_name.'_' . ($i) . '_'.$tag_name.'_';
                
                if (isset($rules['replace']))
                {
                    $replace_with = str_replace(':replace_key',$dataKey,$rules['replace']);
                    
                    if (strpos($rules['replace'],'><'))
                    {
                        $replace_with = explode('\s*',str_replace('><','>\s*<',$replace_with));
                        
                        $replace_with = array_map(function($string)
                        {
                            return preg_quote($string, '/');
                        },$replace_with);
                        
                        $replace_with = '/'.implode('\s*',$replace_with).'/m';
                        
                        $this->strRegexReplaceOnlyFirst(//$input = str_replace(
                            $replace_with,
                            trim($original),
                            $output
                        );
                    }
                    else
                    {

                        $this->strReplaceOnlyFirst(//$input = str_replace(
                            $replace_with,
                            trim($original),
                            $output
                        );
                    }
                    
                }
                elseif (isset($rules['wrap']))
                {
                    $this->strReplaceOnlyFirst(//$input = str_replace(
                        '<'.$rules['wrap'].'>'.$dataKey.'</'.$rules['wrap'].'>',
                        trim($original),
                        $output
                    );
                }
                else
                {
                    $this->strReplaceOnlyFirst(//$input = str_replace(
                        $dataKey,
                        trim($original),
                        $output
                    );
                }
            }
        }
        }

        return trim($output);
    }
    
    protected function setIndentsByPattern(&$subject,&$matches)
    {
        $output = '';
        
        $next_line_indentation_level = 0;

        do {
            $indentation_level = $next_line_indentation_level;

            $patterns = array(
                // @elseif
                '/^\s(@elseif\b)/' => static::MATCH_INDENT_BOTH,
                // @else
                '/^\s(@else\b)/' => static::MATCH_INDENT_BOTH,
                // @foreach
                '/^\s(@foreach\b)/' => static::MATCH_INDENT_INCREASE,
                // @if
                '/^\s(@if\b)/' => static::MATCH_INDENT_INCREASE,
                // @endforeach
                '/^\s(@endforeach\b)/' => static::MATCH_INDENT_DECREASE,
                // @endif
                '/^\s(@endif\b)/' => static::MATCH_INDENT_DECREASE,
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
            $rules = array('NO', 'DECREASE', 'INCREASE', 'DISCARD', 'BOTH');

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
                    } else if ($rule === static::MATCH_INDENT_INCREASE) {
                        $next_line_indentation_level++;
                    }

                    if ($indentation_level < 0) {
                        $indentation_level = 0;
                    }
                    
                    //echo str_repeat($this->options['indentation_character'], $indentation_level) . $matches[0] . "\n";

                    if (static::MATCH_INDENT_BOTH === $rule)
                    {
                      $output .= str_repeat($this->options['indentation_character'], max(0,$indentation_level - 1)) . $matches[0] . "\n";
                    }
                    else
                    {
                      $output .= str_repeat($this->options['indentation_character'], $indentation_level) . $matches[0] . "\n";
                    }

                    break;
                }
            }
        } while ($match);
        
        return $output;
    }
    
    protected function strRegexReplaceOnlyFirst($regex_needle,$replacement,&$haystack)
    {
        echo str_replace("\n",'\n','regex '.$regex_needle.' => '.$replacement),"\n";

        $haystack = preg_replace($regex_needle,$replacement,$haystack,1);
    }
    protected function strReplaceOnlyFirst($needle,$replacement,&$haystack)
    {
        echo str_replace("\n",'\n','string '.$needle.' => '.str_limit($replacement,160,'...')),"\n";
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
