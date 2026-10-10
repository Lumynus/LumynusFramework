<?php

namespace Lumynus\Framework;

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

class LumaJS
{

    /**
     * Mapping of LumaJS methods to corresponding data-luma- attributes.
     * @var array
     */
    private static array $methods = [

        /**
         * 
         * Usage example: 
         * Models are used to declare variables;
         * Others pass variables to evaluate whether condition is true or not.
         * 
         * @example:
         * <input @luma:model="test"> creates @var test
         * <div @luma:if="test">Content</div> Checks if @var test is true/exists and displays content;
         * 
         * @example:
         * <select @luma:model="opt"></select> Creates @var opt
         * <div @luma:if="opt == 'hi'">Show</div> Checks if @var opt equals 'hi' and displays content;
         * 
         * ///////////////////// OTHERS FOLLOW THE SAME PATTERN /////////////////////
         * 
         * @example:
         * <input @luma:model="post"> Creates @var post
         * <div @luma:show="post">Show</div> Checks if @var post exists or is truthy and displays content;
         * 
         */

        'model' => 'data-luma-model', // Used by inputs, selects, and textareas to bind values
        'event' => 'data-luma-click', // Used for events
        'if' => 'data-luma-if', // Used for conditionals
        'show' => 'data-luma-show', // Used to display elements
        'hide' => 'data-luma-hide', // Used to hide elements
        'text' => 'data-luma-text', // Used to insert text into elements
        'html' => 'data-luma-html', // Used to insert HTML into elements
        'class' => 'data-luma-class', // Used to toggle classes on elements
        'style' => 'data-luma-style', // Used to apply inline styles on elements
    ];

    /**
     * Compiles HTML context to replace custom LumaJS attributes
     * with corresponding data-luma- attributes.
     * @param string $context HTML context to compile.
     * @return string Compiled HTML context with data-luma- attributes.
     */
    public static function compile(string $context): string
    {

        if (strpos($context, '@luma:') !== false) {
            $context = self::compileLumaAttributes($context);
        }

        return $context;
    }

    /**
     * Compiles custom LumaJS attributes in HTML to data-luma- attributes.
     * @param string $html HTML string to compile.
     * @return string HTML with data-luma- attributes.
     */
    private static function compileLumaAttributes(string $html): string
    {
        return preg_replace_callback(
            '/@luma:([\w-]+)="([^"]+)"/',
            function ($matches) {
                $attr = $matches[1];  // ex: model, click, if, show...
                $val = htmlspecialchars($matches[2], ENT_QUOTES);

                if (array_key_exists($attr, self::$methods)) {
                    return "data-luma-{$attr}=\"{$val}\"";
                }
            },
            $html
        );
    }


    private function __js() {}
}
