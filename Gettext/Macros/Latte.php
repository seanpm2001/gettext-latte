<?php

namespace h4kuna\Gettext\Macros;

use Latte\Compiler;
use Latte\Macros\MacroSet;
use Latte\CompileException;
use Latte\MacroNode;
use Latte\PhpWriter;

/**
 * plural moznost zapisovat jednim parametrem
 * prepinani kontextu a nacteni slovniku
 * přidání helperů
 * 
 * @author Milan Matějček
 */
class Latte extends MacroSet {

    const GETTEXT = 'ettext';

    /** @var string */
    private $function;

    /** @var bool */
    private $plural;

    /** @var int */
    private $params;

    /** @var bool */
    private $oneParam = TRUE;

    /**
     * Name => count of arguments
     * 
     * @var array 
     */
    static private $functions = array('g' => 1, 'ng' => 3, 'dg' => 2, 'dng' => 4);

    /**
     * @param Compiler $compiler
     * @return self
     */
    public static function install(Compiler $compiler) {
        $me = new static($compiler);
        $me->addMacro('_', callback($me, 'unknown'));
        foreach (self::$functions as $prefix => $_n) {
            $me->addMacro($prefix . '_', callback($me, self::GETTEXT));
        }
        return $me;
    }

    public function unknown(MacroNode $node, PhpWriter $writer) {
        $node->args = $this->detectFunction($node->args);
        return $this->ettext($node, $writer);
    }

    public function ettext(MacroNode $node, PhpWriter $writer) {
        $this->setFunction($node->name);
        $args = self::stringToArgs($node->args);
        $argsGettext = $this->getGettextArgs($args);

        $out = $this->function . '(' . implode(', ', $argsGettext) . ')';
        $key = (int) (substr($this->function, 0, 1) == 'd');
        $diff = $this->foundReplace($args[$key]);
        if ($diff) {
            $out = 'sprintf(' . $out . ', ' . implode(', ', array_slice($args, $diff)) . ')';
        }
        $this->function = NULL;
        return $writer->write('echo %modify(' . $out . ')');
    }

    /**
     * 
     * @param array $args
     * @return array
     */
    private function getGettextArgs(array $args) {


        $argsGettext = array_slice($args, 0, $this->params);
        if ($this->plural) {
            $this->pluralData($argsGettext);
            // set another variable as plural
            foreach ($args as $param) {
                if (preg_match('/plural/i', $param)) {
                    $argsGettext[2] = $param;
                }
            }

            // absolute value
            if (preg_match('/abs/i', $argsGettext[2])) {
                $argsGettext[2] = 'abs(' . $argsGettext[2] . ')';
            }
        }
        return $argsGettext;
    }

    private function setFunction($prefix) {
        if (!$this->function) {
            $prefix = rtrim($prefix, '_');
            $this->function = $prefix . self::GETTEXT;
            $this->params = self::$functions[$prefix];
            $this->plural = strpos($prefix, 'n') !== FALSE;
            if ($this->plural && $this->oneParam) {
                --$this->params;
            }
        }
    }

    private function detectFunction($args) {
        if ($this->function !== NULL) {
            return $args;
        }
        if (preg_match('/(.*)(?:"|\')/U', $args, $find) && isset(self::$functions[$find[1] . 'g'])) {
            $this->setFunction($find[1] . 'g_');
            return preg_replace('/^' . $find[1] . '/', '', $args);
        }
        throw new CompileException('Wrong macro');
    }

    /**
     * @param string $s
     * @return string
     */
    static private function stringToArgs($s) {
        preg_match_all("/(?: ?)([^,]*\(.*?\)|[^,]*'[^']*'|[^,]*\"[^\"]*\"|.+?)(?: ?)(?:,|$)/", $s, $found);
        return $found[1];
    }

    /**
     * Prepare data to native function, only for plural
     * 
     * @param array $data
     */
    private function pluralData(array &$data) {
        if ($this->oneParam) {
            array_unshift($data, $data[0]);
            $n = $data[0];
            $data[0] = $data[1];
            $data[1] = $n;
        }
    }

    /**
     * Has term for replace?
     * 
     * @param string $str
     * @return int
     */
    private function foundReplace($str) {
        return -1 * substr_count($str, '%s');
    }

}
