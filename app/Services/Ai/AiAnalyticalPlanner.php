<?php

namespace App\Services\Ai;

use JsonException;

class AiAnalyticalPlanner
{
    public function __construct(
        private readonly LlmClientService $llm,
        private readonly AiSchemaCatalog $catalog,
        private readonly AiBusinessSemantics $semantics,
        private readonly AiAnalyticalPlanValidator $validator,
    ) {
    }

    public function plan(string $question): array
    {
        $system = $this->systemPrompt();
        $user = $this->planningPrompt($question);

        // Fast path: V4 Flash non-thinking mode. Low temperature improves
        // consistency, while the deterministic validator remains the actual
        // authority for schema, joins, fanout, literals, and safety.
        $raw = $this->llm->completeAdvanced(
            systemPrompt: $system,
            userPrompt: $user,
            jsonMode: true,
            options: [
                'thinking' => (bool) config('llm.planner_thinking', false),
                'temperature' => (float) config('llm.temperature', 0.2),
                'reasoning_effort' => (string) config('llm.reasoning_effort', 'high'),
                'max_tokens' => (int) config('llm.planner_max_tokens', 3500),
                'timeout' => (int) config('llm.planner_timeout', 10),
            ],
        );

        try {
            return $this->validator->validate($question, $this->decode($raw));
        } catch (AiPlanValidationException|JsonException $first) {
            // One bounded repair is allowed for a malformed/rejected plan.
            // It does not broaden permissions: the corrected plan still has
            // to pass the exact same deterministic validator.
            $repair = $this->llm->completeAdvanced(
                systemPrompt: $system,
                userPrompt: $user."\n\nThe previous JSON plan was rejected by the deterministic validator for this reason:\n".$first->getMessage()."\n\nPrevious JSON:\n".$raw."\n\nReturn one corrected JSON plan only.",
                jsonMode: true,
                options: [
                    'thinking' => (bool) config('llm.planner_repair_thinking', true),
                    'temperature' => (float) config('llm.temperature', 0.2),
                    'reasoning_effort' => (string) config('llm.reasoning_effort', 'high'),
                    'max_tokens' => (int) config('llm.planner_max_tokens', 3500),
                    'timeout' => (int) config('llm.planner_repair_timeout', 15),
                ],
            );

            return $this->validator->validate($question, $this->decode($repair));
        }
    }

    private function decode(string $raw): array
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw) ?? $raw;
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new JsonException('Planner did not return a JSON object.');
        }

        return $decoded;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are the planning component of a read-only pharmacy analytics agent.
Return strict JSON only. Never return SQL, prose, markdown, or code.
Do not restrict yourself to a fixed list of business intents. If the current safe schema contains enough evidence, build a plan for the question.
Every table, column, join, literal, aggregation, and calculation must be verifiable by the supplied schema and business semantics.
Never request or expose passwords, authentication tokens, protected customer contact data, or protected staff contact data.
Prefer the lowest-grain fact table needed for the requested measure and join dimensions through actual foreign keys.
Avoid fanout. If two independent facts are needed, use separate scalar queries plus deterministic calculations rather than joining unrelated fact branches.
Use completed sales/purchases and sellable-stock rules when those semantics apply.
Do not claim causality from correlation.
PROMPT;
    }

    private function planningPrompt(string $question): string
    {
        $schema = $this->catalog->promptJson();
        $semantics = $this->semantics->promptJson();

        return <<<PROMPT
CURRENT SAFE DATABASE SCHEMA JSON:
{$schema}

CURRENT BUSINESS SEMANTICS JSON:
{$semantics}

USER QUESTION:
{$question}

Return JSON with this shape:
{
  "answerable": true,
  "reason": "short explanation or empty string",
  "queries": [
    {
      "id": "snake_case_id",
      "from": "table_name",
      "joins": [
        {"table":"new_table","left":"table.column","right":"table.column","type":"inner"}
      ],
      "select": [
        {"kind":"column","column":"table.column","alias":"alias"},
        {"kind":"date_bucket","column":"table.date_column","unit":"day|week|month|year","alias":"period"},
        {"kind":"aggregate","function":"count|count_distinct|sum|avg|min|max","expression":{"type":"column","column":"table.column"},"alias":"alias"}
      ],
      "filters": [
        {"column":"table.column","operator":"=|!=|>|>=|<|<=|contains|in|between","value":"literal","source":"user|semantic"}
      ],
      "group_by": ["table.column"],
      "order_by": [{"field":"selected_alias","direction":"asc|desc"}],
      "limit": 50
    }
  ],
  "calculations": [
    {"alias":"net_profit","operator":"add|subtract|multiply|divide","left":{"query":"query_id","field":"scalar_alias"},"right":{"query":"query_id","field":"scalar_alias"}}
  ],
  "display_query": "query_id"
}

Expression nodes for numeric aggregate arithmetic may be nested:
{"type":"binary","operator":"+|-|*|/","left":EXPR,"right":EXPR}
where EXPR is a numeric column node, number node, or binary node.
Example gross-profit expression:
{"type":"binary","operator":"*","left":{"type":"column","column":"sale_items.quantity"},"right":{"type":"binary","operator":"-","left":{"type":"column","column":"sale_items.unit_price"},"right":{"type":"column","column":"sale_items.purchase_price_at_sale"}}}

For relative dates, use a filter value object such as:
{"kind":"relative_date","anchor":"today|start_of_day|end_of_day|start_of_month|end_of_month|start_of_year|end_of_year","offset_days":0,"offset_months":0}
Use two such values for BETWEEN when appropriate. For timestamp fields and whole-day/month ranges, prefer explicit start/end anchors.
For time trends, use kind="date_bucket" and group_by its alias.

Literal grounding rules:
- source="user": the literal must appear in the user's question exactly enough for deterministic verification.
- source="semantic": only for canonical business values supplied in the semantics, such as completed sale status.
- relative_date objects are allowed for natural phrases such as today, last 30 days, next 60 days.

If the database does not contain enough safe evidence, return:
{"answerable":false,"reason":"why the current data cannot support the answer","queries":[],"calculations":[],"display_query":null}
PROMPT;
    }
}
