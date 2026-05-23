# Audit A PetConnect Domain Flow

Use this command to review a PetConnect domain workflow for consistency across backend, frontend, and tests.

Target flow: `$ARGUMENTS` (examples: pet create wizard, reports, comments, messaging, profile media, reviews)

## Steps

1. Read `.cursor/skills/petconnect-domain-development/SKILL.md` and any relevant backend/frontend/test skills.
2. Trace the flow across:
   - routes
   - controller
   - Form Request
   - service/action/repository
   - model/policy/resource
   - Vue page/components/composables
   - tests/factories/seeders
3. Check for mismatches in request keys, enum values, route names, resource field names, media collection names, authorization, and tests.
4. Report findings by severity with file references.
5. If the user asked for fixes, implement the smallest coherent fix and run focused verification.

## Report

Lead with bugs or mismatches, then list recommended fixes and verification commands.
