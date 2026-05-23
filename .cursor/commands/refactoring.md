# Refactor PetConnect Code Safely

Use this command for behavior-preserving refactors.

Target: `$ARGUMENTS`

If no target is provided, ask one concise clarifying question.

## Rules

- Preserve behavior unless the user explicitly asks for a behavior change.
- Characterize risky behavior with tests before refactoring.
- Keep one concern per pass.
- Match sibling file patterns before introducing abstractions.
- Do not add dependencies or top-level folders without approval.

## Workflow

1. Read `.cursor/rules/00-petconnect-project.mdc`, `.cursor/rules/10-laravel-backend.mdc`, `.cursor/rules/20-inertia-vue-frontend.mdc`, and `.cursor/rules/30-testing-and-project-commands.mdc` as relevant.
2. Read the target files and their tests.
3. Read applicable skills from `.cursor/skills/`.
4. Identify refactor category:
   - Fat controller to service/action.
   - Inline validation to Form Request.
   - Shared query to repository/scope.
   - Raw/duplicated Inertia props to resource.
   - Hardcoded frontend route to Wayfinder/Ziggy helper.
   - Repeated Vue logic to component/composable.
   - Tailwind/UI cleanup using existing primitives.
5. Add or update focused tests if behavior is not already covered.
6. Refactor in small steps.
7. Run focused verification.

## Verification Gate

- PHP: `vendor/bin/pint` + focused `php artisan test`.
- Route helper changes: regenerate Wayfinder.
- Frontend: `npm run format` + `npx vue-tsc --noEmit` or `npm run build`.

## Final Response

Summarize behavior preserved, files changed, tests/checks run, and intentional non-changes.
