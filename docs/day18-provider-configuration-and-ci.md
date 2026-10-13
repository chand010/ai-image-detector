# Day 18 — Provider configuration and CI triage

Learning goals: understand provenance verification, test fail-closed configuration, and diagnose the first failing CI step.

External image processing remains disabled. The provider is not connected to the upload route. No real API request is part of the offline tests.

Official reading:
- https://developers.openai.com/api/docs/guides/content-provenance
- https://deepmind.google/models/synthid/

A missing supported watermark or provenance manifest is not proof that an image is human-created. API access may also be restricted by organization. Review privacy, access, and cost before enabling external processing.

CI triage found that the latest run failed in the source-level security checks because the test's expected string interpolated a PHP variable. Fix that assertion, then rerun CI. Do not claim tests pass until the next run confirms it.

SonarCloud remains pending.