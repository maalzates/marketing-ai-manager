# LLM fixtures — provenance

Every file here is a provider response body replayed by `FakeLlmClient::replaying()`
through the real adapter. Line references are to
`spec/2026-08-23-initial-app-development/research/llm-providers.md`.

Do not edit a fixture to make a test pass. If a body is wrong, the research file is the
thing to correct first — these are copies, not sources.

| File | Source | Verified |
|---|---|---|
| `anthropic-text.json` | §1.4 "Full success response (test fixture)" | Yes — verbatim |
| `anthropic-tool-use.json` | §1.5 "Full tool-use response (test fixture)" | Yes — verbatim |
| `anthropic-401.json` | §1.10 "401 fixture" | Shape yes; the `message` string is UNVERIFIED per §1.11. Match on `error.type`, never the text. |
| `openai-text.json` | §2.4 "Full success response (test fixture)" | Yes — verbatim, except `model` set to `gpt-5.6-luna` so it matches a model priced in `config/services.php` |
| `openai-tool-calls.json` | §2.5 "Full tool-call response (test fixture)" | Partly — §2.11 records that the `tool_calls` block and `finish_reason` are documented verbatim but the envelope and `usage` numbers are composed |
| `openai-401.json` | §2.3 "401 fixture" | Observed response quoted in an openai-python issue, not an official docs example (§2.11) |
| `gemini-text.json` | §3.4 "Legacy generateContent" | Shape verbatim from the documented `generateContent` response |
| `gemini-400-invalid-key.json` | §3.3 "401 fixture" | Yes — verbatim. Note this provider returns **400**, not 401, for a bad key |
| `gemini-tool-call.unverified.json` | §3.5 "Legacy generateContent" | **No.** See below |
| `anthropic-cached.json` | §1.4 envelope, §1.4 "Cost accounting rule" | Shape verbatim; `cache_read_input_tokens` raised to 1500 so the additive arithmetic is observable. Every documented field kept |
| `openai-cached.json` | §2.4 envelope, §2.4 "Cost accounting rule" | Shape verbatim; `prompt_tokens` 1519 / `cached_tokens` 1500 — the same 19 uncached tokens as `anthropic-cached.json`, expressed the way this provider reports them |
| `openai-reasoning.json` | §2.4 envelope + `completion_tokens_details.reasoning_tokens` | Shape verbatim; `completion_tokens` 110 of which 100 reasoning, so the split between visible and hidden output is observable |
| `anthropic-structured-suggestion.json` | §1.4 envelope | Shape verbatim; the text block carries the JSON a field suggestion is expected to answer with |
| `anthropic-structured-missing-field.json` | §1.4 envelope | Shape verbatim; the text block omits a required field on purpose |
| `anthropic-500.json` | §1.9 error table (500 → `api_error`) + §1.10 error envelope | Shape verbatim; `message` composed — the string is not documented, so match on `error.type` |
| `openai-structured-suggestion.json` | §2.4 envelope | Shape verbatim; the message content carries the JSON a field suggestion is expected to answer with |
| `anthropic-chat-tool-read.json` | §1.5 tool-use envelope | Shape verbatim from `anthropic-tool-use.json`; the tool block names the chat module's own `get_experiments` read tool |
| `anthropic-chat-tool-propose-pause.json` | §1.5 tool-use envelope | Shape verbatim from `anthropic-tool-use.json`; the tool block names `propose_pause`, and carries no text block so the turn is a bare mutation request. `input.experiment_id` is a placeholder the test rewrites to the row it created |
| `anthropic-structured-comment-mining.json` | §1.4 envelope | Shape verbatim; the text block carries the `ideas` array comment mining is expected to answer with. `strategy_id` is deliberately an id no account owns, so the novelty of the link is observable |
| `anthropic-structured-no-ideas.json` | §1.4 envelope | Shape verbatim; the text block carries an empty `ideas` array — the model judging that no mined topic fits any strategy |

## `gemini-tool-call.unverified.json`

The research documents a full tool-call response body only for the **Interactions** API
(§3.5), which this module does not call — `GeminiClient` speaks legacy `generateContent`
so that all three adapters stay stateless. For the legacy surface the research states only
that the response carries function calls with `name` and `args`, without printing a body.

This fixture therefore assembles the documented `parts[].functionCall{name,args}` shape
into the legacy envelope from §3.4. The envelope and the token counts are composed, not
captured. Replace it with a captured body before trusting any assertion about Gemini tool
calling in production, and drop the `.unverified` suffix when you do.
