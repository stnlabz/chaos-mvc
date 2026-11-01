# CDM — Diagnostic Service

ChAoS Foundation PHP-native administrator-only diagnostic module.

## Scope (initial development version)

- Administrative access via `/admin/cdm`, using the framework's `require_admin(7)` authorization and CSRF validation.
- Read-only inspection of installed modules.
- JSON metadata syntax diagnostics, conventional controller presence checks, and a preliminary index-parameter mismatch heuristic.
- Findings show module, route, code location, summary, evidence, recommendation, and confidence classification.
- No automatic repairs, database changes, or Core modifications.

## Limitations

The routing mismatch rule is a heuristic, not a proof of failure: parameter usage may belong to a different action. Installed Core contract verification and route-specific AST analysis remain pending. No findings does not mean a module is correct. The installed module path and metadata layout must be checked on a real ChAoS MVC deployment. This version is not qualified or certified.

## Next engineering gates

Confirm the installed framework's module discovery paths and metadata contracts, add fixture tests and negative tests, integrate Core source/route analysis, and run the full qualification bench before release.
