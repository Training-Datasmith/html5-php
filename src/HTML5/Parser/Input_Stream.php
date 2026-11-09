<?php

declare (strict_types=1);
namespace Masterminds\HTML5\Parser;

/**
 * Interface for stream readers.
 *
 * The parser only reads from streams. Various input sources can write
 * an adapater to this input stream.
 *
 * Currently provided input stream implementations include
 * File_Input_Stream and String_Input_Stream.
 *
 * @deprecated since 2.4, to remove in 3.0. Use a string in the scanner instead.
 */
interface Input_Stream extends \Iterator
{
    /**
     * Returns the current line that is being consumed.
     *
     * TODO: Move this to the scanner.
     */
    public function current_line();
    /**
     * Returns the current column of the current line that the tokenizer is at.
     *
     * Newlines are column 0. The first char after a newline is column 1.
     *
     * @TODO Move this to the scanner.
     *
     * @return int The column number.
     */
    public function column_offset();
    /**
     * Get all characters until EOF.
     *
     * This consumes characters until the EOF.
     */
    public function remaining_chars();
    /**
     * Read to a particular match (or until $max bytes are consumed).
     *
     * This operates on byte sequences, not characters.
     *
     * Matches as far as possible until we reach a certain set of bytes
     * and returns the matched substring.
     *
     * @see strcspn
     *
     * @param string $bytes Bytes to match.
     * @param int    $max   Maximum number of bytes to scan.
     *
     * @return mixed Index or false if no match is found. You should use strong
     *               equality when checking the result, since index could be 0.
     */
    public function chars_until($bytes, $max = null);
    /**
     * Returns the string so long as $bytes matches.
     *
     * Matches as far as possible with a certain set of bytes
     * and returns the matched substring.
     *
     * @see strspn
     *
     * @param string $bytes A mask of bytes to match. If ANY byte in this mask matches the
     *                      current char, the pointer advances and the char is part of the
     *                      substring.
     * @param int    $max   The max number of chars to read.
     */
    public function chars_while($bytes, $max = null);
    /**
     * Unconsume one character.
     *
     * @param int $howMany The number of characters to move the pointer back.
     */
    public function unconsume($how_many = 1);
    /**
     * Retrieve the next character without advancing the pointer.
     */
    public function peek();
}