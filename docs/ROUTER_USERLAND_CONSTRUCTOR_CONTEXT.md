# User-module constructor context defect

Tracking: `CMSEC-2026-4830-I`

## Problem

The router previously instantiated a user-module controller before calling
`setModuleContext()`. PHP invokes the controller constructor during
instantiation, so constructor-time calls such as `$this->model('feed_model')`
ran while the controller still identified itself as Core. The base controller
then searched `/app/models` rather than the selected module directory.

This made valid self-contained modules fail only when initialization occurred
in their constructors, and encouraged addon developers to place dependencies
back into `/app` as a workaround.

## Expected behavior

For a controller resolved from `/user/modules/{slug}`:

1. Allocate the controller without running its constructor.
2. Assign and validate the selected module context.
3. Invoke a public, zero-required-argument constructor, when present.
4. Continue explicit route authorization and dispatch.

Core controller construction is unchanged. User-module constructors requiring
arguments remain invalid HTTP controllers and resolve as not found.

## Regression case

A module controller whose constructor calls
`$this->model('{slug}_model')` must load that model exclusively from its own
`/user/modules/{slug}/models` directory. A same-named Core model must not be
loaded, and absence of a module-local model must fail rather than falling back
to Core.
