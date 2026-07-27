<?php

namespace App\Services\Import;

/**
 * A file-level import failure whose message is written FOR THE USER.
 *
 * Anything else that escapes the importer is a bug and should reach the
 * handler as itself; these are the failures a person can act on — wrong file,
 * missing columns, nothing recognisable inside.
 */
class ImportException extends \RuntimeException {}
