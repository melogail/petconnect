<?php

namespace App\Nova;

use Laravel\Nova\Resource as NovaResource;

/**
 * The base every resource in this application extends.
 *
 * It is deliberately empty. `nova:install` scaffolds it with four query
 * overrides — `indexQuery`, `scoutQuery`, `detailQuery`, `relatableQuery` —
 * two of which returned `$query` unchanged and two of which called
 * `parent::` and returned the result. All four were byte-for-byte the parent's
 * own behaviour, so they carried no decision and only made `grep` for "which
 * resources constrain their index query" answer wrongly: App\Nova\Category
 * genuinely overrides `indexQuery` to aggregate its two count columns, and it
 * was indistinguishable from four inherited no-ops.
 *
 * Keep the class. It is the extension point for anything that really does
 * apply to every resource, and every resource already names it. Add a method
 * here only when it changes behaviour; a scaffold stub that delegates straight
 * back to Nova belongs in Nova.
 */
abstract class Resource extends NovaResource
{
    //
}
