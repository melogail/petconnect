# Verify PetConnect Backend

Use this command after PHP, route, request, model, policy, resource, service, repository, action, migration, or test changes.

Target/change: `$ARGUMENTS`

## Steps

1. Identify changed backend files and the narrowest relevant tests.
2. Run PHP formatting:

```bash
vendor/bin/pint
```

3. Run focused tests first, for example:

```bash
php artisan test --filter=MessagingTest
php artisan test tests/Feature/CommentStoreTest.php
```

4. If the change touches shared behavior, run:

```bash
php artisan test
```

5. If routes/controllers changed and frontend helpers may be stale, run:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

## Report

Return the exact commands run, pass/fail result, and any remaining risk.
