# ADR-0010 — AI Provider, Vector and Tool Abstraction

- Status: `PROPOSED — V2 / NOT AUTHORITATIVE`
- Date: 2026-08-23
- Related: V1 production gate, D-008, Steps 36–47

## Context

V2 anticipates provider/model/vector/RAG/tool capabilities, but no use case, allowed data, provider, budget, evaluation threshold or tool allow-list is approved. V1 must not depend on AI.

## Proposed decision

After D-008, use provider-neutral LLM/embedding/vector adapters, versioned configuration/Prompt Registry and a Tool Registry whose policy/authorization executes outside the model. The model never has DB credentials/direct repositories. High-impact proposals require immutable human approval and normal domain actions. Exact providers/models/vector store remain configuration/contract decisions.

## Alternatives considered

| Alternative | Benefit | Reason not selected |
| --- | --- | --- |
| Direct provider SDK in agents/domains | Fast prototype | Lock-in, untraceable config and V1 dependency risk |
| Implement generic AI tables in V1 | Future readiness | Premature scope/schema and violates V1/V2 gate |

## Activation gate

This ADR remains proposed. It cannot authorize code, dependencies or schema until V1 launch gate and D-008 approval; a later review must accept/change/reject it with evaluation/security/cost evidence.
