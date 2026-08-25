# Contributing to Chaos MVC

## Purpose

Contributions to Chaos MVC should preserve the framework's established architecture, conventions, protected Core, and modular design.

Chaos MVC follows a simple principle:

**Protect the core. Grow outward.**

Contributions should improve the framework without unnecessarily expanding or destabilizing the Core.

## Development Expectations

Contributions should:

- Follow the existing Chaos MVC architecture.
- Preserve Model → View → Controller separation.
- Keep application logic out of views.
- Keep database operations inside models.
- Keep request and application flow inside controllers.
- Preserve existing routing behavior.
- Avoid unnecessary dependencies.
- Preserve existing interfaces unless a change is required.
- Keep changes focused on the issue or feature being addressed.
- Update affected documentation when behavior changes.
- Include enough evidence to demonstrate that changed behavior works as intended.

Do not refactor unrelated code as part of a focused contribution.

## Core Protection

Chaos MVC protects the framework components required for the system to operate.

Protected areas include:

```text
/app/core
/app/lib
```

Core modules are also protected.

A controller is considered Core when it declares:

```php
public static $is_core = true;
```

Models associated with protected Core controllers are likewise part of the protected system boundary.

Core components should not be modified as part of unrelated feature development.

Changes to Core should have a specific requirement and should remain as limited as practical.

## MVC Structure

Chaos MVC uses the traditional Model–View–Controller pattern.

### Controllers

Controllers are located in:

```text
/app/controllers
```

Controllers handle:

- Request flow
- Route actions
- Input handling
- Model interaction
- View selection
- Redirects
- Response preparation

Controllers should not contain direct database implementation when that behavior belongs in a model.

### Models

Models are located in:

```text
/app/models
```

Models handle:

- Database reads
- Database writes
- Data retrieval
- Data persistence
- Data-specific operations

Chaos MVC models use the database wrapper provided by the base model.

Common methods include:

```php
$this->query(...)
$this->fetch(...)
$this->fetchAll(...)
```

Do not introduce alternate database access patterns when the existing wrapper supports the required operation.

### Views

Views are located in:

```text
/app/views
```

Views handle presentation.

Views should not perform:

- Database operations
- Request processing
- Business logic
- Authentication logic
- Application state changes

A view should render the data supplied to it.

## Naming Conventions

Chaos MVC uses lowercase naming for framework components.

Examples:

```text
/app/controllers/posts.php
/app/controllers/accounts.php
/app/models/posts_model.php
/app/models/accounts_model.php
/app/views/posts/index.php
```

Preserve the existing naming convention when adding or modifying components.

Do not invent alternate model or controller names when an established component already exists.

## Modules

Chaos MVC functionality grows outward through modules.

Modules are implemented through the established controller, model, and view structure.

Core modules are identified by:

```php
public static $is_core = true;
```

Core modules cannot be altered or deleted through normal module administration.

Non-Core modules may provide optional application functionality without requiring changes to the protected Core.

A module should remain responsible for its own bounded functionality.

Do not move optional module behavior into `/app/core`.

## Dependencies

Chaos MVC is intentionally lightweight.

Do not introduce Composer, vendor stacks, frameworks, or new external dependencies unless the project explicitly requires and approves them.

Use existing Chaos MVC libraries and framework capabilities whenever practical.

## PHP Style

PHP contributions should follow PSR-12 formatting.

Code should use:

- Consistent indentation
- Standard brace placement
- Clear method structure
- Meaningful variable names
- Minimal unnecessary nesting
- Predictable control flow

Example:

```php
public function example($params = [])
{
    $id = is_array($params) ? ($params[0] ?? null) : $params;

    if (!$id) {
        return;
    }

    $model = $this->model('example_model');

    $data = [
        'item' => $model->get_by_id($id)
    ];

    $this->view('example/index', $data);
}
```

## DocBlocks

Classes and methods should include useful PHP DocBlocks.

Example:

```php
/**
 * Retrieve a product by ID.
 *
 * @param int $id Product ID.
 * @return array|false Product record or false when not found.
 */
public function get_product_by_id($id)
{
    return $this->fetch(
        "SELECT * FROM market_products WHERE id = ? LIMIT 1",
        [(int) $id]
    );
}
```

DocBlocks should describe actual behavior.

Do not add documentation merely to restate the method name.

## Database Changes

Database changes should be made only when required by the feature or correction being implemented.

When a schema change is required:

- Document the change.
- Update the applicable installation schema when necessary.
- Preserve compatibility with the current framework behavior.
- Avoid unrelated schema changes.

Do not alter database structure merely as part of code cleanup.

## Routing

Chaos MVC routing is Core infrastructure.

Contributions should work with the established routing behavior rather than modifying the router to accommodate a feature.

Before proposing a router change, verify that the required behavior cannot be implemented through the existing controller and method structure.

## Testing and Verification

Changed behavior should be tested before submission.

Verification should cover the affected path.

Examples include:

- Route resolves correctly.
- Controller method executes.
- Model returns or writes expected data.
- View receives the expected data.
- Redirect behavior is correct.
- Authentication and permissions behave as expected.
- File operations use the intended paths.
- Module behavior remains isolated from unrelated components.

Bug fixes should reproduce the original condition where practical and verify that the correction resolves it.

## Bug Reports

Useful bug reports should include:

- Affected Chaos MVC version
- Affected component
- Expected behavior
- Observed behavior
- Error message or log output when available
- Route or action involved
- Reproduction details
- Relevant environment information

A concise reproduction is more useful than speculation about the cause.

## Pull Requests

Pull requests should remain focused.

A pull request should:

- Address a defined issue, feature, or maintenance requirement.
- Avoid unrelated refactoring.
- Preserve existing conventions.
- Include documentation changes when required.
- Explain what changed.
- Explain how the change was verified.

Large unrelated changes should be separated into independent pull requests.

## AI-Assisted Contributions

Code created or modified with AI assistance must follow the established Chaos MVC and STN-LABZ AI annotation requirements.

AI-modified code should be identified using the required annotation format:

```c
/* [AI:MODEL_NAME | YYYY-MM-DD HH:MM:SS UTC] */

/* [End AI:MODEL_NAME] */
```

AI assistance does not change the coding, testing, documentation, or architectural requirements applied to the contribution.

## Documentation

Documentation should describe the current behavior of Chaos MVC.

When functionality changes, update the affected documentation in the same development cycle when practical.

Common project documentation includes:

```text
README.md
INSTALL.md
CONFIGURATION.md
ARCHITECTURE.md
CONTRIBUTING.md
SECURITY.md
CHANGELOG.md
```

Do not document planned behavior as though it already exists.

## Contribution Principle

Chaos MVC should remain understandable.

Prefer the smallest change that correctly solves the problem.

Preserve established behavior unless the requirement specifically changes it.

Keep optional capability outside the Core.

**Protect the core. Grow outward.**