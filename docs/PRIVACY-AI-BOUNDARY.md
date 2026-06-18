# Privacy and AI boundary gate

AI and translation content are trust-boundary features. Treat all source text, provider output, logs and exports as potentially sensitive.

## Defaults

- AI translation is disabled by default.
- AI output should remain review-first before publication.
- API keys should be supplied through server constants or the `wat_ai_api_key` filter.
- Database credential storage remains opt-in through `WAT_ENABLE_DB_AI_CREDENTIALS` or `wat_allow_db_ai_credentials`.

## Before enabling AI on a client site

Record:

- provider name and endpoint;
- model policy;
- processing region where known;
- retention terms;
- DPA/verwerkersovereenkomst status;
- client approval;
- whether personal data can appear in source strings;
- whether source strings may contain confidential content.

## Test failures

Test these on staging before enabling AI:

- missing API key;
- invalid API key;
- provider timeout;
- malformed provider response;
- rate/cost limit reached;
- low-privilege user tries to trigger AI;
- provider output contains unsafe markup or empty output.

Expected outcome: no automatic public publishing, no secret exposure, clear admin-visible error and no fatal error.
