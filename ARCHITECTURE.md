# Architecture: html5-php

## Purpose
A PHP HTML5 parser and serializer. Parses HTML5 documents into a standard PHP `DOMDocument` (or `DOMDocumentFragment`) and can serialize back to HTML5-compliant markup.

## Directory Structure
```
src/
  HTML5.php                       # Public facade — parse() and saveHTML()
  HTML5/
    Elements.php                  # HTML5 element metadata (void, raw-text, etc.)
    Entities.php                  # HTML5 named character reference table
    Instruction_Processor.php     # Interface for processing instructions (e.g. <?php ?>)
    Exception.php                 # Base exception
    Parser/
      Tokenizer.php               # HTML5 tokenizer (lexer) — produces token stream
      Scanner.php                 # Low-level character scanner
      Character_Reference.php     # Named/numeric entity decoding
      DOM_Tree_Builder.php        # Builds DOMDocument from the token stream (tree construction)
      Input_Stream.php            # Abstract character input
      File_Input_Stream.php       # File-backed input stream
      String_Input_Stream.php     # String-backed input stream
      UTF8Utils.php               # UTF-8 utilities
      Tree_Building_Rules.php     # HTML5 tree-building adoption agency algorithm
      Event_Handler.php           # Interface: receives parse events from the tokenizer
      Parse_Error.php             # Parse error record
    Serializer/
      Traverser.php               # Walks a DOMDocument and emits HTML5 text
      Output_Rules.php            # Per-node serialization rules
      Rules_Interface.php         # Contract for serialization rule sets
      HTML5Entities.php           # Entity table for serialization
bin/
  entities.php                    # Build script for the entity table
example.php                       # Runnable quick-start example
test/                             # PHPUnit test suite
```

## Key Design Decisions
- **Event-driven parsing** — the `Tokenizer` fires events at an `Event_Handler` (the `DOM_Tree_Builder`) rather than building the tree inline. This allows alternative consumers (e.g. SAX-style) to be swapped in.
- **Spec-compliant tree construction** — `Tree_Building_Rules` and `DOM_Tree_Builder` implement the HTML5 parsing spec's insertion modes and adoption agency algorithm.
- **Standard DOMDocument output** — the parser produces PHP's built-in `DOMDocument`, so callers can use familiar `DOMXPath`, `DOMElement`, etc. APIs.
- **Pluggable instruction processor** — processing instructions (e.g. `<?php ?>`) can be handled by a custom `Instruction_Processor` implementation.

## Extension Points
- Provide a custom `Instruction_Processor` to `HTML5::__construct()` to handle processing instructions.
- Provide a custom `Event_Handler` to the `Tokenizer` to consume parse events without building a DOM.
- Pass `DOMDocument::LIBXML_*` flags through the options array to influence DOM creation.

## Dependency Flow
```
HTML5::parse($input)
  ├─ Input_Stream (File or String)
  ├─ Scanner → Tokenizer
  │    └─ fires events → DOM_Tree_Builder
  │         ├─ Tree_Building_Rules (insertion modes)
  │         └─ Character_Reference (entity decoding)
  └─ returns DOMDocument

HTML5::saveHTML($dom)
  └─ Serializer\Traverser
       └─ Output_Rules → HTML5 string
```
