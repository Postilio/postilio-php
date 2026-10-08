# Fixtures

Payloads the model tests read. Two kinds:

- **Copied verbatim from the platform's docs** (the Postilio repository, `docs/`), so a change there shows up as a
  difference here: `domain-created.json` (domains.md), `problem-details.json` and `error-message-too-large.json`
  (errors.md), `send-response.json`, `send-cc-bcc-request.json`, `email-bounced.json` and `send-request.json`
  (sending.md), `send-headers-request.json` (examples/send-headers.sh), `test-email-request.json`
  (examples/send-test.sh), `test-email-response.json` (test-emails.md), `usage.json` (usage.md), `webhook-bounced.json`
  and `create-webhook-endpoint-request.json` (webhooks.md), `create-domain-request.json` (domains.md),
  `create-suppression-request.json` and `remove-suppression-request.json` (suppressions.md).
- **Written from the OpenAPI schemas**, for answers the docs show no example of: `domain-verified.json` (its `dmarc`
  is the fragment in domains.md), `suppression-list.json`, `created-webhook-endpoint.json`,
  `webhook-endpoint-list.json`, `rotated-webhook-secret.json` and `webhook-delivery-list.json`.
